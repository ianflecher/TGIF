<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.project')] class extends Component
{
    public array $projects = [];
    public array $managers = [];
    public bool $showRemarksModal = false;
    public string $remarksText = '';
    public int $remarksBudgetId;

    public bool $showProjectModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public bool $showEditBudgetModal = false;
    
    public ?int $projectToDelete = null;
    public array $editProject = [];
    public array $currentBudget = [];
    public float $newEstimatedCost = 0.0;

    // Project fields
    public string $project_name = '';
    public string $description = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $status = 'on_hold'; // Default to on_hold
    public float $budget_total = 0.0;
    public float $actual_cost = 0.0;
    public ?int $project_manager_id = null;
    public ?int $client_id = null;

    // Accounting fields
    public $accountingEnabled = false;

    public function mount()
    {
        $this->loadProjects();
        $this->loadManagers();
        
        // Check if accounting tables exist
        $this->accountingEnabled = DB::getSchemaBuilder()->hasTable('chart_of_accounts') &&
                                  DB::getSchemaBuilder()->hasTable('journal_entries') &&
                                  DB::getSchemaBuilder()->hasTable('journal_details');
        
        // Ensure accounting accounts exist
        if ($this->accountingEnabled) {
            $this->ensureAccountsExist();
        }
    }

    /***************
     * Load Data
     ***************/
    public function loadProjects()
    {
        // Get all projects with manager and client info
        $projects = DB::table('projects')
            ->leftJoin('employees', 'projects.project_manager_id', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('customers', 'projects.client_id', '=', 'customers.customer_id')
            ->select(
                'projects.*',
                'users.full_name as manager_name',
                'customers.first_name as client_first_name',
                'customers.last_name as client_last_name'
            )
            ->orderBy('projects.start_date', 'desc')
            ->get();

        // Get budget approvals (using the new structure)
        $budgetApprovals = DB::table('budget_approvals')
            ->join('budgets', 'budget_approvals.budget_id', '=', 'budgets.budget_id')
            ->select(
                'budgets.project_id',
                'budgets.budget_id',
                'budget_approvals.remarks',
                'budget_approvals.approval_id',
                'budget_approvals.status as approval_status'
            )
            ->whereNotNull('budget_approvals.remarks')
            ->where('budget_approvals.remarks', '!=', '')
            ->get();

        // Group by project_id
        $budgetRemarksByProject = $budgetApprovals->groupBy('project_id')
            ->map(function ($group) {
                return $group->first();
            });

        // Combine the data
        $this->projects = $projects->map(function ($project) use ($budgetRemarksByProject) {
            $projectData = (array) $project;
            $budgetInfo = $budgetRemarksByProject->get($project->project_id);
            
            $projectData['budget_id'] = $budgetInfo->budget_id ?? null;
            $projectData['remarks'] = $budgetInfo->remarks ?? null;
            $projectData['approval_id'] = $budgetInfo->approval_id ?? null;
            $projectData['approval_status'] = $budgetInfo->approval_status ?? null;
            
            // Combine client name
            $projectData['client_name'] = $project->client_first_name && $project->client_last_name 
                ? $project->client_first_name . ' ' . $project->client_last_name 
                : null;
            
            return (object) $projectData;
        })->toArray();
    }

    public function openRemarks($budgetId, $remarks)
    {
        $this->remarksText = $remarks;
        $this->remarksBudgetId = $budgetId;
        $this->showRemarksModal = true;
    }

    public function closeRemarks()
    {
        $this->showRemarksModal = false;
        $this->remarksText = '';
    }

    public function openEditBudget($budgetId)
    {
        $budget = DB::table('budgets')
            ->where('budget_id', $budgetId)
            ->first();
        
        if ($budget) {
            $this->currentBudget = (array) $budget;
            $this->newEstimatedCost = $budget->estimated_cost;
            $this->showEditBudgetModal = true;
        }
    }

    public function closeEditBudgetModal()
    {
        $this->showEditBudgetModal = false;
        $this->currentBudget = [];
        $this->newEstimatedCost = 0.0;
    }

    public function updateBudget()
    {
        if (!isset($this->currentBudget['budget_id'])) return;

        try {
            DB::beginTransaction();

            $oldEstimatedCost = $this->currentBudget['estimated_cost'] ?? 0;
            $difference = $this->newEstimatedCost - $oldEstimatedCost;
            $projectId = $this->currentBudget['project_id'] ?? 0;

            // Get project details
            $project = DB::table('projects')
                ->where('project_id', $projectId)
                ->first();

            if (!$project) {
                throw new \Exception('Project not found');
            }

            // Update the budget with new estimated cost
            DB::table('budgets')
                ->where('budget_id', $this->currentBudget['budget_id'])
                ->update([
                    'estimated_cost' => $this->newEstimatedCost,
                    'variance' => $this->newEstimatedCost - ($this->currentBudget['actual_cost'] ?? 0),
                    'updated_at' => now(),
                ]);

            // Reset the approval status to 'pending' for finance to review again
            DB::table('budget_approvals')
                ->where('budget_id', $this->currentBudget['budget_id'])
                ->update([
                    'status' => 'pending',
                    'remarks' => null,
                    'reviewed_by' => null,
                    'approved_at' => null,
                    'updated_at' => now(),
                ]);

            // Update project budget total
            DB::table('projects')
                ->where('project_id', $projectId)
                ->update([
                    'budget_total' => DB::raw('budget_total + ' . $difference),
                    'updated_at' => now(),
                ]);

            // ================================================
            // CREATE ACCOUNTING JOURNAL ENTRIES
            // ================================================
            if ($this->accountingEnabled && $difference != 0) {
                $this->createBudgetAdjustmentJournalEntries($project, $difference, $oldEstimatedCost);
            }
            // ================================================

            DB::commit();

            $this->closeEditBudgetModal();
            $this->loadProjects();

            session()->flash('success', 'Budget updated successfully. Accounting entries have been created.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Budget update error: ' . $e->getMessage());
            session()->flash('error', 'Failed to update budget: ' . $e->getMessage());
        }
    }

    /***************
     * Accounting Methods
     ***************/
    private function createBudgetAdjustmentJournalEntries($project, $difference, $oldAmount)
    {
        // Generate journal number
        $journalNumber = 'JE' . date('Ymd') . rand(1000, 9999);
        $userId = Auth::id();

        // Get or create necessary accounts
        $accounts = [
            'budget_reserve' => $this->getOrCreateAccountId('Budget Reserve', 'equity', '3200'),
            'projects_in_progress' => $this->getOrCreateAccountId('Projects In Progress', 'asset', '1300'),
        ];

        // Create journal entry
        $journalId = DB::table('journal_entries')->insertGetId([
            'entry_date' => now()->format('Y-m-d'),
            'journal_number' => $journalNumber,
            'description' => 'Budget adjustment for project: ' . $project->project_name . 
                           ' (Old: ₱' . number_format($oldAmount, 2) . 
                           ', New: ₱' . number_format($this->newEstimatedCost, 2) . ')',
            'created_by' => $userId,
            'reference_type' => 'budget_adjustment',
            'reference_id' => $this->currentBudget['budget_id'],
            'status' => 'posted',
            'total_debit' => abs($difference),
            'total_credit' => abs($difference),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($difference > 0) {
            // Budget increase
            DB::table('journal_details')->insert([
                'journal_id' => $journalId,
                'account_id' => $accounts['projects_in_progress'],
                'debit' => $difference,
                'credit' => 0.00,
                'description' => 'Budget increase for project: ' . $project->project_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_details')->insert([
                'journal_id' => $journalId,
                'account_id' => $accounts['budget_reserve'],
                'debit' => 0.00,
                'credit' => $difference,
                'description' => 'Budget allocation from reserve for project: ' . $project->project_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Budget decrease
            $decreaseAmount = abs($difference);
            
            DB::table('journal_details')->insert([
                'journal_id' => $journalId,
                'account_id' => $accounts['budget_reserve'],
                'debit' => $decreaseAmount,
                'credit' => 0.00,
                'description' => 'Budget reduction returned to reserve for project: ' . $project->project_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_details')->insert([
                'journal_id' => $journalId,
                'account_id' => $accounts['projects_in_progress'],
                'debit' => 0.00,
                'credit' => $decreaseAmount,
                'description' => 'Budget reduction for project: ' . $project->project_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        \Log::info('Budget adjustment journal entry created with ID: ' . $journalId . 
                  ' for project: ' . $project->project_name . 
                  ' (Difference: ' . $difference . ')');
    }

    // Helper function to get or create account ID
    private function getOrCreateAccountId($accountName, $accountType, $accountCode = null, $normalBalance = null)
    {
        // Set default normal balance based on account type
        if (!$normalBalance) {
            $normalBalance = $this->getDefaultNormalBalance($accountType);
        }
        
        // Try to get existing account
        $account = DB::table('chart_of_accounts')
            ->where(function($query) use ($accountName, $accountCode) {
                $query->where('account_name', $accountName);
                if ($accountCode) {
                    $query->orWhere('account_code', $accountCode);
                }
            })
            ->where('account_type', $accountType)
            ->where('is_active', 1)
            ->first();
        
        if ($account) {
            return $account->account_id;
        }
        
        // If account doesn't exist, create it
        // Generate account code if not provided
        if (!$accountCode) {
            $accountCode = $this->generateAccountCode($accountType);
        }
        
        $accountId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => $accountCode,
            'account_name' => $accountName,
            'account_type' => $accountType,
            'normal_balance' => $normalBalance,
            'parent_account_id' => null,
            'description' => 'Auto-created for project management system',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return $accountId;
    }
    
    // Helper to get default normal balance based on account type
    private function getDefaultNormalBalance($accountType)
    {
        switch ($accountType) {
            case 'asset':
            case 'expense':
                return 'debit';
            case 'liability':
            case 'equity':
            case 'revenue':
                return 'credit';
            default:
                return 'debit';
        }
    }
    
    // Helper to generate account code based on account type
    private function generateAccountCode($accountType)
    {
        // Get the highest account code for this type
        $prefixMap = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
        ];
        
        $prefix = $prefixMap[$accountType] ?? '9';
        
        // Find the highest existing account code with this prefix
        $highestCode = DB::table('chart_of_accounts')
            ->where('account_code', 'like', $prefix . '%')
            ->orderBy('account_code', 'desc')
            ->value('account_code');
        
        if ($highestCode) {
            $lastNumber = intval(substr($highestCode, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            return $prefix . $newNumber;
        }
        
        return $prefix . '0001';
    }
    
    // Ensure required accounts exist
    private function ensureAccountsExist()
    {
        $requiredAccounts = [
            ['Projects In Progress', 'asset', '1300'],
            ['Budget Reserve', 'equity', '3200'],
            ['Accounts Receivable', 'asset', '1100'],
            ['Sales Revenue', 'revenue', '4100'],
            ['Cost of Goods Sold', 'expense', '5100'],
            ['Inventory', 'asset', '1200'],
        ];
        
        foreach ($requiredAccounts as $account) {
            $this->getOrCreateAccountId($account[0], $account[1], $account[2]);
        }
    }

    public function loadManagers()
    {
        $this->managers = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->where('users.role', '!=', 'admin')
            ->orderBy('users.full_name')
            ->select('employees.employee_id', 'users.full_name')
            ->get()
            ->toArray();
    }

    /***************
     * Create Project
     ***************/
    public function openProjectModal() { 
        $this->resetProjectFields(); 
        $this->showProjectModal = true; 
    }
    
    public function closeProjectModal() { 
        $this->showProjectModal = false; 
    }

    public function saveProject()
    {
        if (!$this->project_name || !$this->start_date || !$this->end_date) return;

        try {
            DB::beginTransaction();

            // Insert project with status always 'on_hold' initially
            $projectId = DB::table('projects')->insertGetId([
                'project_name'       => $this->project_name,
                'description'        => $this->description,
                'start_date'         => $this->start_date,
                'end_date'           => $this->end_date,
                'status'             => 'on_hold', // Always on_hold initially
                'budget_total'       => $this->budget_total,
                'actual_cost'        => $this->actual_cost,
                'project_manager_id' => $this->project_manager_id,
                'client_id'          => $this->client_id,
                'objectives'         => null,
                'team_members'       => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Create a main budget record for the project
            $budgetId = DB::table('budgets')->insertGetId([
                'project_id'     => $projectId,
                'phase_id'       => 0,
                'task_id'        => null,
                'estimated_cost' => $this->budget_total,
                'actual_cost'    => 0.00,
                'variance'       => 0.00,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Insert into budget_approvals table
            DB::table('budget_approvals')->insert([
                'budget_id'    => $budgetId,
                'requested_by' => auth()->id(),
                'status'       => 'pending',
                'remarks'      => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // ================================================
            // CREATE ACCOUNTING JOURNAL ENTRIES FOR NEW PROJECT
            // ================================================
            if ($this->accountingEnabled && $this->budget_total > 0) {
                $this->createProjectJournalEntries($projectId, $this->project_name, $this->budget_total);
            }
            // ================================================

            DB::commit();

            $this->closeProjectModal();
            $this->loadProjects();

            session()->flash('success', 'Project created successfully. Accounting entries have been created.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project creation error: ' . $e->getMessage());
            session()->flash('error', 'Failed to create project: ' . $e->getMessage());
        }
    }

    private function createProjectJournalEntries($projectId, $projectName, $budgetAmount)
    {
        // Generate journal number
        $journalNumber = 'JE' . date('Ymd') . rand(1000, 9999);
        $userId = Auth::id();

        // Get or create necessary accounts
        $accounts = [
            'projects_in_progress' => $this->getOrCreateAccountId('Projects In Progress', 'asset', '1300'),
            'budget_reserve' => $this->getOrCreateAccountId('Budget Reserve', 'equity', '3200'),
        ];

        // Create journal entry
        $journalId = DB::table('journal_entries')->insertGetId([
            'entry_date' => now()->format('Y-m-d'),
            'journal_number' => $journalNumber,
            'description' => 'Budget allocation for new project: ' . $projectName,
            'created_by' => $userId,
            'reference_type' => 'project_creation',
            'reference_id' => $projectId,
            'status' => 'posted',
            'total_debit' => $budgetAmount,
            'total_credit' => $budgetAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create journal details
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $accounts['projects_in_progress'],
            'debit' => $budgetAmount,
            'credit' => 0.00,
            'description' => 'Initial budget allocation for project: ' . $projectName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $accounts['budget_reserve'],
            'debit' => 0.00,
            'credit' => $budgetAmount,
            'description' => 'Budget allocation from reserve for project: ' . $projectName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Log::info('Project creation journal entry created with ID: ' . $journalId . 
                  ' for project: ' . $projectName . 
                  ' (Amount: ' . $budgetAmount . ')');
    }

    public function resetProjectFields()
    {
        $this->project_name = '';
        $this->description = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->status = 'on_hold'; // Reset to on_hold
        $this->budget_total = 0.0;
        $this->actual_cost = 0.0;
        $this->project_manager_id = null;
        $this->client_id = null;
    }

    /***************
     * Edit Project
     ***************/
    public function openEditModal($projectId)
    {
        $project = DB::table('projects')->where('project_id', $projectId)->first();
        if ($project) {
            // Only allow editing if project is NOT on_hold
            if ($project->status === 'on_hold') {
                session()->flash('error', 'Project is on hold. Cannot edit until budget is approved.');
                return;
            }
            
            $this->editProject = (array) $project;
            $this->showEditModal = true;
        }
    }

    public function closeEditModal() { 
        $this->showEditModal = false; 
        $this->editProject = []; 
    }

    public function updateProject()
    {
        if (!isset($this->editProject['project_id'])) return;

        try {
            DB::beginTransaction();

            // Get current project data
            $oldProject = DB::table('projects')
                ->where('project_id', $this->editProject['project_id'])
                ->first();

            $oldBudget = $oldProject->budget_total ?? 0;
            $newBudget = $this->editProject['budget_total'] ?? 0;
            $budgetDifference = $newBudget - $oldBudget;

            // Update project
            DB::table('projects')
                ->where('project_id', $this->editProject['project_id'])
                ->update([
                    'project_name'       => $this->editProject['project_name'] ?? '',
                    'description'        => $this->editProject['description'] ?? '',
                    'budget_total'       => $newBudget,
                    'actual_cost'        => $this->editProject['actual_cost'] ?? 0,
                    'project_manager_id' => $this->editProject['project_manager_id'] ?? null,
                    'client_id'          => $this->editProject['client_id'] ?? null,
                    'start_date'         => $this->editProject['start_date'] ?? null,
                    'end_date'           => $this->editProject['end_date'] ?? null,
                    'status'             => $this->editProject['status'] ?? 'on_hold',
                    'updated_at'         => now(),
                ]);

            // Update main budget record
            DB::table('budgets')
                ->where('project_id', $this->editProject['project_id'])
                ->where('phase_id', 0)
                ->update([
                    'estimated_cost' => $newBudget,
                    'variance' => $newBudget - ($this->editProject['actual_cost'] ?? 0),
                    'updated_at' => now(),
                ]);

            // ================================================
            // CREATE ACCOUNTING JOURNAL ENTRIES FOR BUDGET UPDATE
            // ================================================
            if ($this->accountingEnabled && $budgetDifference != 0) {
                $this->createBudgetAdjustmentJournalEntries(
                    $oldProject, 
                    $budgetDifference, 
                    $oldBudget
                );
            }
            // ================================================

            DB::commit();

            $this->closeEditModal();
            $this->loadProjects();

            session()->flash('success', 'Project updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Project update error: ' . $e->getMessage());
            session()->flash('error', 'Failed to update project: ' . $e->getMessage());
        }
    }

    /***************
     * Delete Project
     ***************/
    public function confirmDelete($projectId)
    {
        $this->projectToDelete = $projectId;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->projectToDelete = null;
    }

    public function deleteProject()
    {
        if ($this->projectToDelete) {
            try {
                DB::beginTransaction();

                // Get project details for accounting
                $project = DB::table('projects')
                    ->where('project_id', $this->projectToDelete)
                    ->first();

                $budgetIds = DB::table('budgets')
                    ->where('project_id', $this->projectToDelete)
                    ->pluck('budget_id')
                    ->toArray();

                if (!empty($budgetIds)) {
                    DB::table('budget_approvals')->whereIn('budget_id', $budgetIds)->delete();
                }

                DB::table('budgets')->where('project_id', $this->projectToDelete)->delete();
                DB::table('tasks')->where('project_id', $this->projectToDelete)->delete();
                DB::table('projects')->where('project_id', $this->projectToDelete)->delete();

                // ================================================
                // CREATE ACCOUNTING JOURNAL ENTRIES FOR PROJECT DELETION
                // ================================================
                if ($this->accountingEnabled && $project && $project->budget_total > 0) {
                    $this->createProjectDeletionJournalEntries($project);
                }
                // ================================================

                DB::commit();

                $this->closeDeleteModal();
                $this->loadProjects();

                session()->flash('success', 'Project deleted successfully. Accounting entries have been created.');

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Project deletion error: ' . $e->getMessage());
                session()->flash('error', 'Failed to delete project: ' . $e->getMessage());
            }
        }
    }

    private function createProjectDeletionJournalEntries($project)
    {
        // Generate journal number
        $journalNumber = 'JE' . date('Ymd') . rand(1000, 9999);
        $userId = Auth::id();
        $budgetAmount = $project->budget_total ?? 0;

        // Get or create necessary accounts
        $accounts = [
            'budget_reserve' => $this->getOrCreateAccountId('Budget Reserve', 'equity', '3200'),
            'projects_in_progress' => $this->getOrCreateAccountId('Projects In Progress', 'asset', '1300'),
        ];

        // Create journal entry
        $journalId = DB::table('journal_entries')->insertGetId([
            'entry_date' => now()->format('Y-m-d'),
            'journal_number' => $journalNumber,
            'description' => 'Project cancellation: ' . $project->project_name,
            'created_by' => $userId,
            'reference_type' => 'project_deletion',
            'reference_id' => $project->project_id,
            'status' => 'posted',
            'total_debit' => $budgetAmount,
            'total_credit' => $budgetAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create journal details (reverse the initial entry)
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $accounts['budget_reserve'],
            'debit' => $budgetAmount,
            'credit' => 0.00,
            'description' => 'Budget returned to reserve for cancelled project: ' . $project->project_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $accounts['projects_in_progress'],
            'debit' => 0.00,
            'credit' => $budgetAmount,
            'description' => 'Budget reversal for cancelled project: ' . $project->project_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Log::info('Project deletion journal entry created with ID: ' . $journalId . 
                  ' for project: ' . $project->project_name . 
                  ' (Amount: ' . $budgetAmount . ')');
    }
};
?>
<div>
<div>
<div class="phase-container" style="padding: 2rem; background: #fffef6; min-height: 100vh;">
    <!-- Top Buttons Container -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Add Project Button -->
        <button type="button" wire:click="openProjectModal" class="phase-btn phase-btn-green" style="background: #22c55e; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
            + Add Project
        </button>

        <!-- Resource Allocation Button -->
        <a href="{{ route('projects.resources') }}" class="phase-btn phase-btn-green" style="background: #16a34a; color: white; border: none; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500;">
            Allocate Resources
        </a>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #7f1d1d; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Projects Table Container -->
    <div class="phase-table-container" style="overflow-x: auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <table class="phase-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <!-- Green header bar -->
                <tr>
                    <th colspan="12" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; text-align: left; padding: 1rem; font-size: 1.1rem; font-weight: 600;">
                        Projects Overview
                    </th>
                </tr>

                <!-- Column headers -->
                <tr style="background-color: #f0fdf4; text-align:left;">
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Name</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Description</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Start</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">End</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Status</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Budget</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Manager</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Member</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Phases</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600;">Gantt</th>
                    <th style="padding: 12px; border-bottom: 2px solid #d1fae5; color: #14532d; font-weight: 600; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $p)
                    @php
                        $isPaused = $p->status === 'on_hold';
                        $statusColor = match($p->status) {
                            'planning' => 'bg-blue-100 text-blue-800',
                            'in_progress' => 'bg-green-100 text-green-800',
                            'on_hold' => 'bg-yellow-100 text-yellow-800',
                            'completed' => 'bg-gray-100 text-gray-800',
                            'cancelled' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800'
                        };
                    @endphp
                    <tr style="border-bottom: 1px solid #e5e7eb; {{ $isPaused ? 'background-color: #fef3c7;' : '' }}">
                        <td style="padding: 12px; color: #1f2937; font-weight: 500;">{{ $p->project_name }}</td>
                        <td style="padding: 12px; color: #6b7280;">{{ Str::limit($p->description, 50) }}</td>
                        <td style="padding: 12px; color: #4b5563;">{{ $p->start_date }}</td>
                        <td style="padding: 12px; color: #4b5563;">{{ $p->end_date }}</td>
                        <td style="padding: 12px;">
                            <span class="status-badge" style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; {{ $statusColor }}">
                                {{ ucfirst(str_replace('_', ' ', $p->status)) }}
                            </span>
                        </td>
                        <td style="padding: 12px; font-weight: 600; color: #15803d;">
                            ₱{{ number_format($p->budget_total, 2) }}
                        </td>

                        <td style="padding: 12px; color: #374151;">{{ $p->manager_name ?? 'Unknown' }}</td>

                        @if($isPaused)
                            <!-- For On Hold projects - show View Remarks in the centered section -->
                            <td colspan="4" style="text-align:center; padding: 12px;">
                                @if ($p->remarks)
                                    <button wire:click="openRemarks({{ $p->budget_id }}, '{{ addslashes($p->remarks) }}')"
                                            class="phase-btn phase-btn-yellow" style="background: #facc15; color: #1f2937; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        View Remarks
                                    </button>
                                @else
                                    <span style="color: #6b7280; font-style: italic;">Budget pending approval</span>
                                @endif
                            </td>
                        @else
                            <!-- Active projects - normal view -->
                            <!-- Member -->
                            <td style="padding: 12px;">
                                <a href="{{ route('projects.members', ['project_id' => $p->project_id]) }}" 
                                   class="phase-btn phase-btn-green" style="background: #22c55e; color: white; border: none; padding: 6px 16px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 500;">
                                    View
                                </a>
                            </td>

                            <!-- Phases -->
                            <td style="padding: 12px;">
                                <a href="{{ route('projects.phase', ['project_id' => $p->project_id]) }}" 
                                   class="phase-btn phase-btn-green" style="background: #22c55e; color: white; border: none; padding: 6px 16px; border-radius: 6px; text-decoration: none; display: inline-block; font-weight: 500;">
                                    View
                                </a>
                            </td>

                            <!-- Gantt -->
                            <td style="padding: 12px;">
                                <button onclick="loadGantt({{ $p->project_id }})" 
                                        class="phase-btn phase-btn-green" style="background: #22c55e; color: white; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                    View
                                </button>
                            </td>

                            <!-- Actions -->
                            <td style="padding: 12px; text-align:center;">
                                <div style="display:flex; justify-content:center; align-items:center; gap: 0.5rem;">
                                    <button wire:click="openEditModal({{ $p->project_id }})"
                                            class="phase-btn phase-btn-yellow" style="background: #facc15; color: #1f2937; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Edit
                                    </button>
                                    <button wire:click="confirmDelete({{ $p->project_id }})"
                                            class="phase-btn phase-btn-red" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(count($projects) === 0)
            <div style="text-align: center; padding: 3rem; color: #6b7280; font-style: italic; background: white;">
                No projects found. Create your first project by clicking the "Add Project" button.
            </div>
        @endif
    </div>

    <!-- Budget Remarks Modal -->
    @if ($showRemarksModal)
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="text-lg font-semibold">Budget Details</h3>
                    <button wire:click="closeRemarks" class="text-white font-bold text-xl" style="background: none; border: none; cursor: pointer;">&times;</button>
                </div>

                <div class="modal-body" style="padding: 1.5rem;">
                    <!-- Current Remarks -->
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Finance Remarks:</h4>
                        <p style="padding: 1rem; background: #f8fafc; border-radius: 8px; color: #4b5563; min-height: 80px;">{{ $remarksText ?: 'No remarks provided' }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div style="margin-top: 1rem; display: flex; gap: 10px;">
                        <button wire:click="openEditBudget({{ $remarksBudgetId }})" 
                                class="phase-btn phase-btn-green" style="background: #22c55e; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                            Edit Budget
                        </button>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 1rem; background: #f8fafc; display: flex; justify-content: flex-end;">
                    <button wire:click="closeRemarks" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Close</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Budget Modal -->
    @if ($showEditBudgetModal)
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="text-lg font-semibold">Edit Budget</h3>
                    <button wire:click="closeEditBudgetModal" class="text-white font-bold text-xl" style="background: none; border: none; cursor: pointer;">&times;</button>
                </div>

                <div class="modal-body" style="padding: 1.5rem;">
                    <div style="display: grid; gap: 1rem;">
                        <!-- Current Budget Info -->
                        <div style="background: #f0fdf4; padding: 1rem; border-radius: 8px; border: 1px solid #d1fae5;">
                            <h4 style="font-weight: 600; color: #14532d; margin-bottom: 0.5rem;">Current Budget Information:</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem;">
                                <div style="color: #4b5563;">Estimated Cost:</div>
                                <div style="font-weight: 600; color: #15803d;">₱{{ number_format($currentBudget['estimated_cost'] ?? 0, 2) }}</div>
                                <div style="color: #4b5563;">Actual Cost:</div>
                                <div style="font-weight: 600; color: #15803d;">₱{{ number_format($currentBudget['actual_cost'] ?? 0, 2) }}</div>
                                <div style="color: #4b5563;">Variance:</div>
                                <div style="font-weight: 600; {{ ($currentBudget['variance'] ?? 0) < 0 ? 'color: #dc2626' : 'color: #16a34a' }}">
                                    ₱{{ number_format($currentBudget['variance'] ?? 0, 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">New Estimated Cost</span>
                            <input type="number" 
                                   wire:model="newEstimatedCost" 
                                   min="0" 
                                   step="0.01" 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem; font-size: 1rem; transition: border-color 0.2s ease;"
                                   onfocus="this.style.borderColor='#22c55e'; this.style.outline='none';"
                                   onblur="this.style.borderColor='#d1d5db';">
                        </label>

                        <!-- Accounting Info -->
                        @if($accountingEnabled)
                        <div style="background: #dbeafe; border: 1px solid #60a5fa; border-radius: 8px; padding: 1rem;">
                            <h5 style="font-weight: 600; color: #1e40af; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-book" style="font-size: 0.875rem;"></i>
                                Accounting Impact
                            </h5>
                            <p style="font-size: 0.875rem; color: #1e40af; margin: 0;">
                                Changing the budget will create accounting journal entries to track the adjustment:
                                <br>
                                <strong>Budget increase:</strong> Debit Projects In Progress, Credit Budget Reserve
                                <br>
                                <strong>Budget decrease:</strong> Debit Budget Reserve, Credit Projects In Progress
                            </p>
                        </div>
                        @endif

                        <!-- Warning Message -->
                        <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 1rem;">
                            <p style="font-size: 0.875rem; color: #92400e;">
                                <strong style="display: block; margin-bottom: 0.25rem;">Note:</strong> 
                                Changing the budget will:
                                <br>1. Reset the approval status to "Pending" for finance review
                                <br>2. Create accounting journal entries
                                <br>3. Update the project's total budget
                            </p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 1rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button wire:click="closeEditBudgetModal" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancel</button>
                    <button wire:click="updateBudget" class="btn btn-primary" style="background: #22c55e; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Update Budget
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Project Modal -->
    @if ($showEditModal)
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="text-lg font-semibold">Edit Project</h3>
                    <button wire:click="closeEditModal" class="text-white font-bold text-xl" style="background: none; border: none; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div style="display: grid; gap: 1rem;">
                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Project Name</span>
                            <input type="text" wire:model="editProject.project_name" 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                        </label>

                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Description</span>
                            <textarea wire:model="editProject.description" 
                                      style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem; min-height: 80px;"></textarea>
                        </label>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Budget</span>
                                <input type="number" wire:model="editProject.budget_total" min="0" step="0.01"
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>

                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Actual Cost</span>
                                <input type="number" wire:model="editProject.actual_cost" min="0" step="0.01"
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>
                        </div>

                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Project Manager</span>
                            <select wire:model="editProject.project_manager_id" 
                                    style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                                <option value="">-- Select Manager --</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->employee_id }}">{{ $manager->full_name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Start Date</span>
                                <input type="date" wire:model="editProject.start_date" 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>

                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">End Date</span>
                                <input type="date" wire:model="editProject.end_date" 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>
                        </div>

                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Status</span>
                            <select wire:model="editProject.status" 
                                    style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                                <option value="planning">Planning</option>
                                <option value="in_progress">In Progress</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </label>

                        <!-- Accounting Warning -->
                        @if($accountingEnabled)
                        <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 0.75rem;">
                            <p style="font-size: 0.875rem; color: #92400e; margin: 0;">
                                <strong>Note:</strong> Changing the budget will create accounting journal entries.
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1rem; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button wire:click="closeEditModal" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancel</button>
                    <button wire:click="updateProject" class="btn btn-primary" style="background: #22c55e; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Save Changes</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($showDeleteModal)
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="text-lg font-semibold">Confirm Delete</h3>
                    <button wire:click="closeDeleteModal" class="text-white font-bold text-xl" style="background: none; border: none; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 1.5rem; text-align: center;">
                    <p style="color: #4b5563; margin-bottom: 1rem;">Are you sure you want to delete this project? This action cannot be undone.</p>
                    
                    @if($accountingEnabled)
                    <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem;">
                        <p style="font-size: 0.875rem; color: #92400e; margin: 0;">
                            <strong>Accounting Impact:</strong> This will create journal entries to reverse the budget allocation.
                        </p>
                    </div>
                    @endif
                </div>
                <div class="modal-footer" style="padding: 1rem; background: #f8fafc; display: flex; justify-content: center; gap: 1rem;">
                    <button wire:click="closeDeleteModal" class="btn btn-secondary" style="background: #6b7280; color: white; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancel</button>
                    <button wire:click="deleteProject" class="btn btn-danger" style="background: #ef4444; color: white; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; font-weight: 500;">Delete</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Project Modal -->
    @if ($showProjectModal)
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="text-lg font-semibold">New Project</h3>
                    <button wire:click="closeProjectModal" class="text-white font-bold text-xl" style="background: none; border: none; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <form wire:submit.prevent="saveProject" style="display: grid; gap: 1rem;">
                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Project Name *</span>
                            <input type="text" wire:model="project_name" required 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                        </label>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Start Date *</span>
                                <input type="date" wire:model="start_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>

                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">End Date *</span>
                                <input type="date" wire:model="end_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>
                        </div>

                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Description</span>
                            <input type="text" wire:model="description" 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                        </label>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Budget</span>
                                <input type="number" wire:model="budget_total" min="0" step="0.01" 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </label>

                            <!-- <label style="display: block;">
                                <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Status</span>
                                <select wire:model="status" 
                                        style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                                    <option value="planning">Planning</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="on_hold" selected>On Hold</option>
                                </select>
                            </label> -->
                        </div>

                        <label style="display: block;">
                            <span style="font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem;">Project Manager *</span>
                            <select wire:model="project_manager_id" required 
                                    style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                                <option value="">-- Select Manager --</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->employee_id }}">{{ $manager->full_name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <!-- Accounting Info -->
                        @if($accountingEnabled)
                        <div style="background: #dbeafe; border: 1px solid #60a5fa; border-radius: 8px; padding: 0.75rem;">
                            <p style="font-size: 0.875rem; color: #1e40af; margin: 0;">
                                <strong>Accounting Note:</strong> Creating this project will generate accounting journal entries to allocate the budget.
                            </p>
                        </div>
                        @endif

                        <button type="submit" 
                                style="background: #22c55e; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; margin-top: 1rem;">
                            Save Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Gantt Chart Container -->
    <div id="gantt_here" style="width:100%; height:500px; margin-top: 2rem; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"></div>
</div>
</div>

<!-- Include DHTMLX Gantt -->
<link rel="stylesheet" href="{{ asset('css/dhtmlxgantt.css') }}">
<script src="{{ asset('js/dhtmlxgantt.js') }}"></script>
<script>
function loadGantt(projectId) {
    fetch(`/gantt-tasks/${projectId}`)
        .then(res => res.json())
        .then(data => {
            const ganttData = {
                data: data.map(item => ({
                    ...item,
                    duration: item.start_date && item.end_date ?
                        Math.ceil((new Date(item.end_date) - new Date(item.start_date)) / (1000*60*60*24)) + 1
                        : 0
                }))
            };

            gantt.init("gantt_here");
            gantt.parse(ganttData);
        })
        .catch(err => console.error("Error fetching Gantt data:", err));
}
</script>