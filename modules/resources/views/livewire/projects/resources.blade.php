<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.project')] class extends Component
{
    // Projects, Phases & Tasks
    public array $projects = [];
    public array $tasks = [];
    public array $projectTasks = [];
    public array $phases = [];
    public bool $showBudgetModal = false;
    public bool $showAddBudgetModal = false;
    public bool $showAddResourceModal = false;
    public bool $showAssignMemberModal = false;
    public ?int $currentTaskId = null;
    public $showViewResourcesModal = false;
    public $resources = [];
    public $showEditAllocationModal = false;
    public $editingAllocation = [];
    public $showEditResourceModal = false;
    public $editingResource;
    public $totalCost = 0;
    public $allocatedQuantity = 0;
    public $selectedResourceId = null;
    public $resourceUnitCost = 0;
    public $remainingBudget = 0;
    public array $editingBudget = [];
    public ?int $selectedTaskId = null;
    public ?int $selectedProjectFilter = null; // null means show all projects

    public ?int $task_assigned_to = null;
    public ?int $editTaskAssignedTo = null;

    public ?int $newBudgetProjectId = null;
    public ?int $newBudgetPhaseId = null;
    public float $newEstimatedCost = 0;

    public string $newResourceName = '';
    public string $newResourceType = 'Food';
    public float $newResourceUnitCost = 0;
    public float $newResourceQuantity = 0;

    // Employees & Resources
    public array $employees = [];
    
    public array $budgets = [];
    public ?int $currentProjectId = null;
    public array $projectMembers = [];

    // Allocation Modal
    public bool $showAllocationModal = false;
    public ?int $selectedEmployeeId = null;
    public float $allocationCost = 0;
    public string $allocationType = 'Employee';
    public ?string $errorMessage = null;

    public function mount()
    {
        $this->loadProjects();       // loads $this->projects as array
        $this->loadEmployees();
        $this->loadResources();
        $this->loadTasksForAllProjects();

        // Loop through projects to get their members
        foreach ($this->projects as $p) {
            // Get project members from employees table
            $memberIds = json_decode($p->team_members ?? '[]', true) ?? [];
            
            $this->projectMembers[$p->project_id] = DB::table('employees')
                ->join('users', 'employees.user_id', '=', 'users.user_id')
                ->whereIn('employees.employee_id', $memberIds)
                ->select('employees.employee_id', 'users.full_name')
                ->get()
                ->toArray();
        }
    }

    // Open Add Budget Modal
    public function openAddBudgetModal($taskId)
    {
        $this->selectedTaskId = $taskId;
        $this->editingBudget = [
            'budget_id' => null,  // No budget yet
            'task_id' => $taskId,
            'estimated_cost' => 0,
            'actual_cost' => 0,
            'variance' => 0,
        ];
        $this->showBudgetModal = true;
    }

    // Open Edit Budget Modal
    public function openEditBudgetModal($budgetId)
    {
        $budget = DB::table('budgets')->where('budget_id', $budgetId)->first();

        if (!$budget) {
            $this->addError('editingBudget.estimated_cost', 'Budget not found.');
            return;
        }

        $this->editingBudget = (array) $budget; // budget_id will exist here
        $this->showBudgetModal = true;
    }

    public function addBudget()
    {
        // 1️⃣ Fetch task with phase to get project_id
        $task = DB::table('tasks as t')
            ->join('project_phases as ph', 't.phase_id', '=', 'ph.phase_id')
            ->select('t.*', 'ph.project_id')
            ->where('t.task_id', $this->editingBudget['task_id'])
            ->first();

        if (!$task) {
            $this->addError('editingBudget.estimated_cost', 'Cannot find project for this task.');
            return;
        }

        // 2️⃣ Insert into budgets table
        $budgetId = DB::table('budgets')->insertGetId([
            'task_id' => $task->task_id,
            'phase_id' => $task->phase_id ?? 0,
            'project_id' => $task->project_id,
            'estimated_cost' => $this->editingBudget['estimated_cost'] ?? 0,
            'actual_cost' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3️⃣ Insert journal entry
        $referenceNo = 'BUDGET-' . $budgetId;

        DB::table('journal_entries')->insert([
            'entry_date' => now()->toDateString(),
            'journal_number' => $referenceNo,
            'description' => 'Budget allocated for Task ID ' . $task->task_id,
            'created_by' => Auth::id(),
            'status' => 'posted',
            'total_debit' => $this->editingBudget['estimated_cost'] ?? 0,
            'total_credit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4️⃣ Get journal entry ID for foreign key reference
        $journalId = DB::table('journal_entries')
            ->where('journal_number', $referenceNo)
            ->value('journal_id');

        // 5️⃣ Insert into budget_approvals
        DB::table('budget_approvals')->insert([
            'budget_id' => $budgetId,
            'requested_by' => Auth::id(),
            'status' => 'pending',
            'remarks' => 'Initial budget allocation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6️⃣ UPDATE PROJECT STATUS TO 'on_hold'
        DB::table('projects')->where('project_id', $task->project_id)->update([
            'status' => 'on_hold',
            'updated_at' => now(),
        ]);

        // 7️⃣ Reset modal and redirect to projects.projects
        $this->showBudgetModal = false;
        $this->editingBudget = [];
        
        // Flash success message
        session()->flash('success', 'Budget added successfully! Project is now on hold until finance approval.');
        
        // Redirect to projects.projects route
        return redirect()->route('projects.projects');
    }

    public function saveBudget()
    {
        $this->validate([
            'editingBudget.estimated_cost' => 'required|numeric|min:0',
        ]);

        // 1️⃣ Correct variance formula: estimated - actual
        $estimated = $this->editingBudget['estimated_cost'];
        $actual = $this->editingBudget['actual_cost'] ?? 0;
        $variance = $estimated - $actual;

        // 2️⃣ Get project ID from the budget
        $budget = DB::table('budgets')->where('budget_id', $this->editingBudget['budget_id'])->first();
        
        // 3️⃣ Update budgets table
        DB::table('budgets')
            ->where('budget_id', $this->editingBudget['budget_id'])
            ->update([
                'estimated_cost' => $estimated,
                'variance' => $variance,
                'updated_at' => now(),
            ]);

        // 4️⃣ Update journal entry
        DB::table('journal_entries')
            ->where('journal_number', 'BUDGET-' . $this->editingBudget['budget_id'])
            ->update([
                'description' => 'Updated budget for Task ID ' . $this->editingBudget['task_id'],
                'total_debit' => $estimated,
                'updated_at' => now(),
            ]);

        // 5️⃣ Create new budget approval request
        DB::table('budget_approvals')->insert([
            'budget_id' => $this->editingBudget['budget_id'],
            'requested_by' => Auth::id(),
            'status' => 'pending',
            'remarks' => 'Budget modification requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6️⃣ UPDATE PROJECT STATUS TO 'on_hold'
        if ($budget && $budget->project_id) {
            DB::table('projects')->where('project_id', $budget->project_id)->update([
                'status' => 'on_hold',
                'updated_at' => now(),
            ]);
        }

        // 7️⃣ Reset modal and redirect to projects.projects
        $this->showBudgetModal = false;
        $this->editingBudget = [];
        
        // Flash success message
        session()->flash('success', 'Budget update requested! Project is now on hold until finance approval.');
        
        // Redirect to projects.projects route
        return redirect()->route('projects.projects');
    }

    private function getProjectIdByTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return null;

        $phase = DB::table('project_phases')->where('id', $task->phase_id)->first();
        return $phase ? $phase->project_id : null;
    }

    private function getPhaseIdByTask($taskId)
    {
        return DB::table('tasks')->where('task_id', $taskId)->value('phase_id');
    }

    public function computeEditTotalCost()
    {
        // 1️⃣ Check required fields
        if (!isset($this->editingAllocation['quantity'], $this->editingAllocation['resource_id'], $this->editingAllocation['task_id'])) {
            $this->editingAllocation['total_cost'] = 0;
            $this->editingAllocation['remaining_budget'] = 0;
            return;
        }

        // 2️⃣ Fetch resource
        $resource = DB::table('resources')
            ->where('resource_id', $this->editingAllocation['resource_id'])
            ->first();

        if (!$resource) {
            $this->editingAllocation['total_cost'] = 0;
            $this->editingAllocation['remaining_budget'] = 0;
            return;
        }

        // 3️⃣ Calculate total cost
        $this->editingAllocation['total_cost'] = $this->editingAllocation['quantity'] * $resource->unit_cost;

        // 4️⃣ Fetch task → phase → project
        $task = DB::table('tasks')->where('task_id', $this->editingAllocation['task_id'])->first();
        if (!$task) {
            $this->editingAllocation['remaining_budget'] = 0;
            return;
        }

        $phase = DB::table('project_phases')->where('id', $task->phase_id)->first();
        if (!$phase) {
            $this->editingAllocation['remaining_budget'] = 0;
            return;
        }

        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
        if (!$project) {
            $this->editingAllocation['remaining_budget'] = 0;
            return;
        }

        // 5️⃣ Calculate current spent budget
        $currentSpent = DB::table('resource_allocations as ra')
            ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
            ->join('project_phases as pp', 't.phase_id', '=', 'pp.id')
            ->where('pp.project_id', $project->project_id)
            ->sum('ra.cost');

        // 6️⃣ Subtract current allocation cost so we don't double-count
        $originalQty = $this->editingAllocation['original_quantity'] ?? 0;
        $currentSpent -= $originalQty * $resource->unit_cost;

        // 7️⃣ Set remaining budget
        $this->editingAllocation['remaining_budget'] = $project->budget_total - $currentSpent;
    }

    public function submitBudgetAndPauseProject($taskId, $projectId, $budget)
    {
        // 1️⃣ Get the budget for this task
        $taskBudget = DB::table('budgets')
            ->where('task_id', $taskId)
            ->first();

        if (!$taskBudget) {
            session()->flash('error', 'No budget found for this task.');
            return;
        }

        // 2️⃣ Create budget approval request
        DB::table('budget_approvals')->insert([
            'budget_id' => $taskBudget->budget_id,
            'requested_by' => Auth::id(),
            'status' => 'pending',
            'remarks' => "Budget increase requested: ₱" . number_format($budget, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3️⃣ Change project status to 'on_hold'
        DB::table('projects')->where('project_id', $projectId)->update([
            'status' => 'on_hold',
            'updated_at' => now(),
        ]);

        session()->flash('success', 'Budget request submitted. Project is now on hold pending approval.');
        
        // Redirect to projects.projects route
        return redirect()->route('projects.projects');
    }

    public function updatedAllocatedQuantity($value)
    {
        $this->computeTotalCost();
    }

    public function updatedSelectedResourceId($value)
    {
        if ($value) {
            $resource = collect($this->resources)->firstWhere('resource_id', $value);
            $this->resourceUnitCost = $resource ? $resource->unit_cost : 0;
        } else {
            $this->resourceUnitCost = 0;
        }

        $this->computeTotalCost();
    }

    public function computeTotalCost()
    {
        if ($this->selectedResourceId && is_numeric($this->allocatedQuantity)) {
            $unitCost = DB::table('resources')
                ->where('resource_id', $this->selectedResourceId)
                ->value('unit_cost');

            $this->totalCost = $unitCost * $this->allocatedQuantity;
        } else {
            $this->totalCost = 0;
        }
    }

    public function closeEditResourceModal()
    {
        $this->showEditResourceModal = false;
        $this->editingResource = null;
    }

    public function updateResource()
    {
        $validated = $this->validate([
            'editingResource.resource_name' => 'required|string|max:255',
            'editingResource.type' => 'required|string',
            'editingResource.unit_cost' => 'required|numeric|min:0',
            'editingResource.available_quantity' => 'required|numeric|min:0',
            'editingResource.status' => 'required|string',
        ]);

        DB::table('resources')
            ->where('resource_id', $this->editingResource['resource_id'])
            ->update($this->editingResource);

        $this->showEditResourceModal = false;
        $this->resources = DB::table('resources')->get();
    }

    // Delete resource
    public function deleteResource($resourceId)
    {
        DB::table('resources')->where('resource_id', $resourceId)->delete();
        $this->resources = DB::table('resources')->get();
    }

    public function openEditAllocationModal($allocationId)
    {
        $allocation = DB::table('resource_allocations as ra')
            ->join('resources as r', 'ra.resource_id', '=', 'r.resource_id')
            ->select('ra.*', 'r.resource_name', 'r.unit_cost', 'r.type')
            ->where('ra.allocation_id', $allocationId)
            ->first();

        if (!$allocation) {
            $this->addError('editingAllocation', 'Allocation not found.');
            return;
        }

        $taskBudget = DB::table('budgets')
            ->where('task_id', $allocation->task_id)
            ->first();

        // Compute total allocations for this task excluding the current allocation
        $allocatedSumExcludingCurrent = DB::table('resource_allocations')
            ->where('task_id', $allocation->task_id)
            ->where('allocation_id', '!=', $allocationId)
            ->sum('cost');

        $this->editingAllocation = [
            'allocation_id' => $allocation->allocation_id,
            'resource_id' => $allocation->resource_id,
            'resource_name' => $allocation->resource_name,
            'unit_cost' => $allocation->unit_cost,
            'type' => $allocation->type,
            'task_id' => $allocation->task_id,
            'quantity' => $allocation->quantity,
            'total_cost' => $allocation->quantity * $allocation->unit_cost,
            'remaining_budget' => $taskBudget 
                ? max(0, $taskBudget->estimated_cost - $allocatedSumExcludingCurrent) 
                : 0,
        ];

        $this->showEditAllocationModal = true;
    }

    public function updateAllocation()
    {
        // 1️⃣ Fetch allocation
        $allocation = DB::table('resource_allocations')
            ->where('allocation_id', $this->editingAllocation['allocation_id'])
            ->first();

        if (!$allocation) {
            $this->addError('editingAllocation.quantity', "Allocation not found.");
            return;
        }

        // 2️⃣ Fetch resource
        $resource = DB::table('resources')->where('resource_id', $allocation->resource_id)->first();
        if (!$resource) {
            $this->addError('editingAllocation.quantity', "Resource not found.");
            return;
        }

        // 3️⃣ Fetch task → phase → project
        $task = DB::table('tasks')->where('task_id', $allocation->task_id)->first();
        if (!$task) {
            $this->addError('editingAllocation.quantity', "Task not found.");
            return;
        }

        $phase = DB::table('project_phases')->where('id', $task->phase_id)->first();
        if (!$phase) {
            $this->addError('editingAllocation.quantity', "Phase not found.");
            return;
        }

        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
        if (!$project) {
            $this->addError('editingAllocation.quantity', "Project not found.");
            return;
        }

        // 4️⃣ Calculate differences
        $oldQty = $allocation->quantity;
        $newQty = $this->editingAllocation['quantity'];
        $diffQty = $newQty - $oldQty;
        $diffCost = $diffQty * $resource->unit_cost;

        // 5️⃣ Check stock availability
        if ($diffQty > $resource->available_quantity) {
            $this->addError('editingAllocation.quantity', "Insufficient stock. Available: {$resource->available_quantity}");
            return;
        }

        // 6️⃣ Calculate remaining budget
        $currentSpent = DB::table('resource_allocations as ra')
            ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
            ->join('project_phases as pp', 't.phase_id', '=', 'pp.id')
            ->where('pp.project_id', $project->project_id)
            ->sum('ra.cost');

        // Subtract the current allocation's cost so we don't double-count
        $currentSpent -= $allocation->cost;

        $remainingBudget = $project->budget_total - $currentSpent;

        if (($newQty * $resource->unit_cost) > $remainingBudget) {
            $this->addError('editingAllocation.quantity', 
                "Cannot update. Remaining project budget: ₱ " . number_format($remainingBudget, 2)
            );
            return;
        }

        // 7️⃣ Update allocation
        DB::table('resource_allocations')
            ->where('allocation_id', $allocation->allocation_id)
            ->update([
                'quantity' => $newQty,
                'cost' => $newQty * $resource->unit_cost,
                'updated_at' => now(),
            ]);

        // 8️⃣ Update resource availability
        DB::table('resources')
            ->where('resource_id', $resource->resource_id)
            ->update([
                'available_quantity' => $resource->available_quantity - $diffQty,
            ]);

        // 9️⃣ Reset modal & refresh
        $this->showEditAllocationModal = false;
        $this->editingAllocation = [];
        $this->resetValidation();
        $this->loadTasksForAllProjects();

        session()->flash('success', 'Allocation updated successfully!');
    }

    public function deleteAllocation($allocationId)
    {
        // 1️⃣ Fetch the allocation
        $allocation = DB::table('resource_allocations')
            ->where('allocation_id', $allocationId)
            ->first();

        if (!$allocation) return;

        // 2️⃣ Restore resource availability
        DB::table('resources')
            ->where('resource_id', $allocation->resource_id)
            ->increment('available_quantity', $allocation->quantity);

        // 3️⃣ Delete allocation
        DB::table('resource_allocations')
            ->where('allocation_id', $allocationId)
            ->delete();

        // 4️⃣ Recalculate task actual cost & variance
        $task = DB::table('tasks')->where('task_id', $allocation->task_id)->first();
        if (!$task) return;

        $phase = DB::table('project_phases')->where('id', $task->phase_id)->first();
        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();

        $taskActualCost = DB::table('resource_allocations')
            ->where('task_id', $task->task_id)
            ->sum('cost');

        $taskBudget = DB::table('budgets')->where([
            'project_id' => $project->project_id,
            'phase_id' => $phase->id,
            'task_id' => $task->task_id,
        ])->first();

        $estimated = $taskBudget->estimated_cost ?? 0;
        $variance = $estimated - $taskActualCost;

        DB::table('budgets')->updateOrInsert(
            [
                'project_id' => $project->project_id,
                'phase_id' => $phase->id,
                'task_id' => $task->task_id,
            ],
            [
                'actual_cost' => $taskActualCost,
                'variance' => $variance,
                'updated_at' => now(),
            ]
        );

        // 5️⃣ Reload tasks/resources
        $this->loadTasksForAllProjects();

        session()->flash('success', 'Allocation deleted and budget updated successfully!');
    }

    public function getFilteredProjectsProperty()
    {
        $filter = (int) $this->selectedProjectFilter;
        if ($filter > 0) {
            return collect($this->projects)
                ->where('project_id', $filter)
                ->all();
        }
        return $this->projects;
    }

    public function openAssignMemberModal(int $taskId, int $projectId)
    {
        $this->currentTaskId = $taskId;
        $this->currentProjectId = $projectId;
        $this->selectedEmployeeId = null;

        // Get the project
        $project = collect($this->projects)->first(fn($p) => $p->project_id == $projectId);

        // Get member IDs from team_members JSON
        $memberIds = json_decode($project->team_members ?? '[]', true) ?? [];

        // Fetch members from employees table
        $this->projectMembers[$projectId] = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->whereIn('employees.employee_id', $memberIds)
            ->select('employees.employee_id', 'users.full_name')
            ->get()
            ->toArray();

        $this->showAssignMemberModal = true;
    }

    public function assignMember()
    {
        if ($this->currentTaskId && $this->selectedEmployeeId) {
            $task = DB::table('tasks')->where('task_id', $this->currentTaskId)->first();

            if ($task) {
                // Get current assigned IDs as array (they should be numbers)
                $assignedIds = $task->assigned_to ? explode(',', $task->assigned_to) : [];
                
                // Convert all values to integers
                $assignedIds = array_map('intval', $assignedIds);

                if (!in_array((int)$this->selectedEmployeeId, $assignedIds)) {
                    $assignedIds[] = (int)$this->selectedEmployeeId;

                    DB::table('tasks')
                        ->where('task_id', $this->currentTaskId)
                        ->update(['assigned_to' => implode(',', $assignedIds)]);
                }
            }

            $this->showAssignMemberModal = false;
            $this->loadTasksForAllProjects();
            $this->selectedEmployeeId = null;
            $this->currentTaskId = null;
        }
    }

    public function getActualCostForPhase($phaseId)
    {
        return DB::table('resource_allocations as ra')
            ->join('tasks as t', 't.task_id', '=', 'ra.task_id')
            ->where('t.phase_id', $phaseId)
            ->sum('ra.cost');
    }

    public function openAllocationModal($taskId)
    {
        $this->selectedTaskId = $taskId;

        // Fetch task
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        // Fetch phase
        $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
        if (!$phase) return;

        // Fetch project
        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
        if (!$project) return;

        // Compute remaining budget
        $taskBudget = DB::table('budgets')
            ->where('task_id', $task->task_id)
            ->first();

        // Compute total allocated for this task
        $allocatedSumForTask = DB::table('resource_allocations')
            ->where('task_id', $task->task_id)
            ->sum('cost');

        // Remaining budget is task-level estimated minus already allocated
        $this->remainingBudget = $taskBudget 
            ? max(0, $taskBudget->estimated_cost - $allocatedSumForTask) 
            : 0;

        // Reset fields
        $this->selectedResourceId = null;
        $this->allocatedQuantity = 0;
        $this->totalCost = 0;
        $this->showAllocationModal = true;
    }

    public function closeAllocationModal()
    {
        $this->showAllocationModal = false;
        $this->selectedTaskId = null;
        $this->selectedResourceId = null;
        $this->allocatedQuantity = 0;
    }

    public function saveAllocation()
    {
        // 1️⃣ Fetch resource
        $resource = DB::table('resources')
            ->where('resource_id', $this->selectedResourceId)
            ->first();

        if (!$resource) {
            $this->addError('allocatedQuantity', 'Resource not found.');
            return;
        }

        // 2️⃣ Fetch task
        $task = DB::table('tasks')->where('task_id', $this->selectedTaskId)->first();
        if (!$task) {
            $this->addError('allocatedQuantity', 'Task not found.');
            return;
        }

        // 3️⃣ Fetch phase & project
        $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();

        // 4️⃣ Compute total cost for this allocation
        $totalCost = $this->allocatedQuantity * $resource->unit_cost;

        // 5️⃣ Check stock
        if ($this->allocatedQuantity > $resource->available_quantity) {
            $this->addError('allocatedQuantity', "Insufficient stock. Available: {$resource->available_quantity}");
            return;
        }

        // 6️⃣ Check remaining project budget
        $currentSpent = DB::table('resource_allocations as ra')
            ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
            ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
            ->where('pp.project_id', $project->project_id)
            ->sum('ra.cost');

        $remainingBudget = $project->budget_total - $currentSpent;

        if ($totalCost > $remainingBudget) {
            $this->addError('allocatedQuantity', 
                "Cannot allocate. Remaining project budget: ₱ " . number_format($remainingBudget, 2)
            );
            return;
        }

        // 7️⃣ Check if allocation already exists for this task + resource
        $existingAllocation = DB::table('resource_allocations')
            ->where('task_id', $task->task_id)
            ->where('resource_id', $resource->resource_id)
            ->first();

        if ($existingAllocation) {
            // Update existing allocation quantity & cost
            DB::table('resource_allocations')
                ->where('allocation_id', $existingAllocation->allocation_id)
                ->update([
                    'quantity' => $existingAllocation->quantity + $this->allocatedQuantity,
                    'cost' => $existingAllocation->cost + $totalCost,
                    'allocation_date' => now(),
                ]);
        } else {
            // Insert new allocation
            DB::table('resource_allocations')->insert([
                'task_id' => $task->task_id,
                'resource_id' => $resource->resource_id,
                'quantity' => $this->allocatedQuantity,
                'allocation_date' => now(),
                'cost' => $totalCost,
                'resource_type' => $resource->resource_type,
                'resource_name' => $resource->resource_name,
            ]);
        }

        // 8️⃣ Update resource availability
        DB::table('resources')
            ->where('resource_id', $resource->resource_id)
            ->decrement('available_quantity', $this->allocatedQuantity);

        // 9️⃣ Update task budget actual cost & variance
        $taskActualCost = DB::table('resource_allocations')
            ->where('task_id', $task->task_id)
            ->sum('cost');

        $taskBudget = DB::table('budgets')->where([
            'project_id' => $project->project_id,
            'phase_id' => $phase->phase_id,
            'task_id' => $task->task_id,
        ])->first();

        $estimated = $taskBudget->estimated_cost ?? 0;
        $variance = $estimated - $taskActualCost;

        DB::table('budgets')->updateOrInsert(
            [
                'project_id' => $project->project_id,
                'phase_id' => $phase->id,
                'task_id' => $task->task_id,
            ],
            [
                'actual_cost' => $taskActualCost,
                'variance' => $variance,
                'updated_at' => now(),
            ]
        );

        // 🔟 Reset modal & fields
        $this->loadResources();
        $this->selectedTaskId = null;
        $this->selectedResourceId = null;
        $this->allocatedQuantity = 0;
        $this->totalCost = 0;
        $this->remainingBudget = 0;
        $this->showAllocationModal = false;

        session()->flash('success', 'Allocation saved successfully!');
    }

    public function updatedNewBudgetProjectId($projectId)
    {
        if ($projectId) {
            $this->phases = DB::table('project_phases')
                ->where('project_id', $projectId)
                ->select('id', 'phase_name')
                ->get()
                ->toArray();
        } else {
            $this->phases = [];
        }

        $this->newBudgetPhaseId = null;
    }

    public function loadProjects()
    {
        $this->projects = DB::table('projects')->orderBy('start_date')->get()->toArray();
    }

    public function loadTasks($projectId)
{
    $this->tasks = DB::table('tasks as t')
        ->join('project_phases as p', 't.phase_id', '=', 'p.phase_id')
        ->where('p.project_id', $projectId)
        ->select('t.*', 'p.phase_name')
        ->orderBy('t.task_id')
        ->get()
        ->toArray();
}


   public function loadTasksForAllProjects()
{
    $this->projectTasks = [];

    foreach ($this->projects as $p) {
        $tasks = DB::table('tasks as t')
            ->join('project_phases as ph', 't.phase_id', '=', 'ph.phase_id') // ✅ FIX
            ->where('ph.project_id', $p->project_id)
            ->select('t.*', 'ph.phase_name')
            ->orderBy('t.task_id')
            ->get()
            ->toArray();

        $this->projectTasks[$p->project_id] = $tasks;
    }
}


    public function loadEmployees()
    {
        $this->employees = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->select('employees.employee_id', 'users.full_name')
            ->get()
            ->toArray();
    }

    public function loadResources()
    {
        $this->resources = DB::table('resources')->get()->toArray();
    }
};
?>
<div>
    <!-- Resources Section -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">

    <!-- Back Button -->
    <div class="task-header">
        <a href="{{ route('projects.projects') }}" class="back-link">← Back to Projects</a>
    </div>

    <!-- View Resources Button -->
    <a href="{{ route('projects.viewresources') }}"
       style="
           background-color: #22c55e;
           color: #fff;
           border: none;
           border-radius: 8px;
           padding: 10px 20px;
           font-size: 1rem;
           font-weight: 600;
           cursor: pointer;
           text-decoration: none;
           transition: background 0.2s ease, transform 0.1s ease;
           display: inline-block;
       "
       onmouseover="this.style.backgroundColor='#16a34a'; this.style.transform='translateY(-2px)'"
       onmouseout="this.style.backgroundColor='#22c55e'; this.style.transform='none'">
       👁 View Resources
    </a>

</div>




<!-- Edit Allocation Modal -->
@if($showEditAllocationModal)
<div class="resources-modal" style="display:flex;">
    <div class="resources-modal-box">
        <!-- Header -->
        <div class="resources-modal-header">
            <h3>Edit Resource Allocation</h3>
            <button class="resources-close-btn" wire:click="$set('showEditAllocationModal', false)">&times;</button>
        </div>

        <!-- Body -->
        <div class="resources-form-grid">
            <!-- Resource (readonly) -->
            <label>
                Resource
                <input type="text" readonly value="{{ $editingAllocation['resource_name'] ?? '' }}">
            </label>

            <!-- Quantity -->
            <label>
                Quantity
                <input type="number" min="0" step="0.01"
                       wire:model.debounce.200ms="editingAllocation.quantity"
                       wire:keyup="computeEditTotalCost"
                       class="border rounded-md px-3 py-2 w-full" />
            </label>

            <!-- Total Cost -->
            <label>
                Total Cost
                <input type="text" readonly value="₱ {{ number_format($editingAllocation['total_cost'] ?? 0, 2) }}">
            </label>

            <!-- Remaining Budget -->
            <label>
                Remaining Budget
                <input type="text" readonly value="₱ {{ number_format($editingAllocation['remaining_budget'] ?? 0, 2) }}">
            </label>

            <!-- Error Messages -->
            @if(isset($editingAllocation['total_cost']) && $editingAllocation['total_cost'] > ($editingAllocation['remaining_budget'] ?? 0))
                <p class="text-red-600 text-sm mt-1">
                    Error: Allocation exceeds remaining budget!
                </p>
            @endif

            @error('editingAllocation.quantity')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Footer -->
        <div class="resources-modal-actions">
            <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                    wire:click="updateAllocation">
                Save
            </button>
            <button class="resources-btn-gray"
                    wire:click="$set('showEditAllocationModal', false)">
                Cancel
            </button>
        </div>
    </div>
</div>
@endif


    <!-- Projects Table -->
<div class="phase-table-container">
    <div style="background-color:#2e7d32;color:white;text-align:left;
                       padding:10px 12px;font-size:1rem;">
        Projects & Tasks
    </div>
    <table class="phase-table">
        <thead>
            <tr>
                <th>Project</th>
                <th>Phase</th>
                <th>Task</th>
                <th>Assigned Employee</th>
                <th>Resources</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($this->filteredProjects as $p)
            @php $tasks = $projectTasks[$p->project_id] ?? []; @endphp
            @if(count($tasks) > 0)
                @foreach($tasks as $t)
                    <tr>
                        <td>{{ $p->project_name }}</td>
                        <td>{{ $t->phase_name }}</td>
                        <td>{{ $t->task_name }}</td>
                        <td>
                            @php
                                $assignedIds = explode(',', $t->assigned_to ?? '');
                                $assignedEmployees = collect($employees)
                                    ->whereIn('employee_id', $assignedIds)
                                    ->pluck('full_name')
                                    ->toArray();
                            @endphp
                            @if(count($assignedEmployees) > 0)
                                {{ implode(', ', $assignedEmployees) }}
                            @else
                                Unassigned
                            @endif
                        </td>
                        <td>
                            @php
                                $allocs = DB::table('resource_allocations')
                                    ->join('resources', 'resources.resource_id', '=', 'resource_allocations.resource_id')
                                    ->where('resource_allocations.task_id', $t->task_id)
                                    ->select(
                                        'resource_allocations.allocation_id',
                                        'resources.resource_name',
                                        'resources.type',
                                        'resource_allocations.quantity'
                                    )
                                    ->get();
                            @endphp

                            @foreach($allocs as $a)
                                @php
                                    $unit = match($a->resource_type) {
                                        'Food' => 'kg',
                                        'Equipment' => 'pcs',
                                        default => 'pcs',
                                    };
                                @endphp
                                <div class="phase-btn-row">
                                    <span>{{ $a->resource_name }} {{ rtrim(rtrim(number_format($a->quantity, 2), '0'), '.') }} {{ $unit }}</span>
                                    <span class="phase-btn-actions">
                                        <button class="phase-btn phase-btn-yellow"
                                                wire:click="openEditAllocationModal({{ $a->allocation_id }})">Edit</button>
                                        <button class="phase-btn phase-btn-red"
                                                onclick="if(confirm('Delete this allocation?')) @this.call('deleteAllocation', {{ $a->allocation_id }})">Delete</button>
                                    </span>
                                </div>
                            @endforeach
                            @if(count($allocs) === 0)
                                <span class="phase-no-data">No resources allocated</span>
                            @endif
                        </td>
<td class="phase-btn-actions-cell">
    <button class="phase-btn phase-btn-green"
            wire:click="openAllocationModal({{ $t->task_id }})">Allocate</button>
    <button class="phase-btn phase-btn-yellow"
            wire:click="openAssignMemberModal({{ $t->task_id }}, {{ $p->project_id }})">Assign</button>

    @php
        $taskBudget = DB::table('budgets')->where('task_id', $t->task_id)->first();
    @endphp

    @if($taskBudget)
        <button class="phase-btn phase-btn-green" 
                onclick="document.getElementById('budgetModal{{ $t->task_id }}').classList.remove('hidden')">
            Request Budget Change
        </button>

        <!-- Budget Change Modal -->
        <div id="budgetModal{{ $t->task_id }}" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-96 relative">
                <!-- Close button -->
                <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 font-bold text-xl"
                        onclick="document.getElementById('budgetModal{{ $t->task_id }}').classList.add('hidden')">&times;</button>

                <h2 class="text-lg font-bold mb-4">Request Budget Change</h2>
                <input type="text" id="budgetInput{{ $t->task_id }}" placeholder="Enter new budget" class="w-full border rounded p-2 mb-6">

                <div class="flex justify-center gap-4">
                    <button class="phase-btn phase-btn-green px-8 py-3 text-lg font-semibold"
                            onclick="submitBudget({{ $t->task_id }}, {{ $p->project_id }})">
                        Submit
                    </button>
                </div>

                <p class="text-sm text-gray-500 mt-4 text-center">*Put Desired Budget.*</p>
            </div>
        </div>

        <!-- Waiting for Approval Modal -->
        <div id="waitingModal{{ $t->task_id }}" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center z-50 pointer-events-auto">
            <div class="bg-white rounded-lg p-8 w-80 text-center relative">
                <!-- Close button -->
                <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 font-bold text-xl"
                        onclick="document.getElementById('waitingModal{{ $t->task_id }}').classList.add('hidden')">&times;</button>

                <h2 class="text-lg font-bold mb-4">Waiting for Approval</h2>
                <p class="text-gray-600">Your budget request has been submitted. Please wait for manager approval.</p>
            </div>
        </div>

        <script>
            function submitBudget(taskId, projectId) {
                const budgetValue = document.getElementById(`budgetInput${taskId}`).value;

                // Hide budget modal, show waiting modal
                document.getElementById(`budgetModal${taskId}`).classList.add('hidden');
                document.getElementById(`waitingModal${taskId}`).classList.remove('hidden');

                // Call Livewire to save budget and pause project
                @this.call('submitBudgetAndPauseProject', taskId, projectId, budgetValue);
            }
        </script>
    @else
        <button class="phase-btn phase-btn-green"
                wire:click="openAddBudgetModal({{ $t->task_id }})">Add Budget</button>
    @endif
</td>

                    </tr>
                @endforeach
            @else
                <tr>
                    <td>{{ $p->project_name }}</td>
                    <td colspan="5" class="phase-no-data">No tasks yet</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
</div>




<!-- Allocate Resource Modal -->
<div class="modal" style="display: {{ $showAllocationModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">Allocate Resource</h3>
            <button class="btn btn-warning" wire:click="closeAllocationModal">× Close</button>
        </div>

        <div class="modal-body space-y-4">
            <!-- Resource -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Resource</label>
                <select wire:model="selectedResourceId" wire:change="computeTotalCost" class="w-full border rounded-md px-3 py-2">
                    <option value="">-- Select Resource --</option>
                    @foreach($resources as $r)
                        <option value="{{ $r->resource_id }}">
                            {{ $r->resource_name }} (Available: {{ $r->available_quantity }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Quantity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" wire:model="allocatedQuantity" wire:keyup="computeTotalCost" min="0" step="1"
                       class="w-full border rounded-md px-3 py-2" />
            </div>

            <!-- Total Cost & Remaining Budget -->
            <div class="grid grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost</label>
                    <input type="text" readonly value="₱ {{ number_format($totalCost, 2) }}"
                           class="w-full border bg-gray-50 rounded-md px-3 py-2 text-gray-600" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remaining Budget</label>
                    <input type="text" readonly value="₱ {{ number_format($remainingBudget, 2) }}"
                           class="w-full border bg-gray-50 rounded-md px-3 py-2 text-gray-600" />
                </div>
            </div>
            @error('allocatedQuantity')
    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
@enderror


            <!-- Buttons -->
            <div class="text-right mt-4">
                <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                        wire:click="saveAllocation">
                    Allocate
                </button>
                <button class="btn btn-gray"
                        wire:click="closeAllocationModal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@if($showBudgetModal)
<div class="resources-modal">
    <div class="resources-modal-box">
        <div class="resources-modal-header">
            <h3>{{ $editingBudget['budget_id'] ? 'Edit Budget' : 'Add Budget' }}</h3>
            <button class="resources-close-btn" wire:click="$set('showBudgetModal', false)">&times;</button>
        </div>

        <div class="resources-form-grid">
            <label>
                Budget 
                <input type="number" min="0" step="0.01" wire:model.defer="editingBudget.estimated_cost">
            </label>

            @error('editingBudget.estimated_cost')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="resources-modal-actions">
            <button class="resources-btn-gray" wire:click="$set('showBudgetModal', false)">Cancel</button>
            <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                    wire:click="{{ $editingBudget['budget_id'] ? 'saveBudget' : 'addBudget' }}">
                {{ $editingBudget['budget_id'] ? 'Update' : 'Add' }}
            </button>
        </div>
    </div>
</div>
@endif




<div class="modal" style="display: {{ $showAssignMemberModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Assign Member</h3>
            <button class="btn btn-warning" wire:click="$set('showAssignMemberModal', false)">Close</button>
        </div>
        <div class="modal-body">
            @php
$assignedIds = collect($projectTasks[$currentProjectId] ?? [])
    ->pluck('assigned_to')
    ->filter() // remove nulls
    ->all();
@endphp

<select wire:model="selectedEmployeeId">
    <option value="">-- Select Member --</option>
    @foreach($projectMembers[$currentProjectId] ?? [] as $member)
        @if(!in_array($member->employee_id, $assignedIds))
            <option value="{{ $member->employee_id }}">{{ $member->full_name }}</option>
        @endif
    @endforeach
</select>



        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" wire:click="assignMember">Assign</button>
        </div>
    </div>
</div>
</div>