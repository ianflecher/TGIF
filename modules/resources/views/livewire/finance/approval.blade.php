<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layouts.finance')] class extends Component
{
    use WithPagination;
    
    // Search and filter properties
    public $search = '';
    public $statusFilter = '';
    public $perPage = 10;
    
    // Approval properties
    public $showApprovalModal = false;
    public $showRejectionModal = false;
    public $selectedApproval = null;
    public $selectedApprovalDetails = null;
    public $rejectionRemarks = '';
    
    // Statistics
    public $stats = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'total' => 0
    ];
    
    public function mount()
    {
        $this->calculateStats();
    }
    
    public function calculateStats()
    {
        $this->stats['pending'] = DB::table('budget_approvals')
            ->where('status', 'Pending')
            ->count();
            
        $this->stats['approved'] = DB::table('budget_approvals')
            ->where('status', 'Approved')
            ->count();
            
        $this->stats['rejected'] = DB::table('budget_approvals')
            ->where('status', 'Rejected')
            ->count();
            
        $this->stats['total'] = DB::table('budget_approvals')->count();
    }
    
    public function getPendingApprovals()
    {
        return DB::table('budget_approvals as ba')
            ->select(
                'ba.approval_id',
                'ba.budget_id',
                'ba.status',
                'ba.remarks',
                'ba.created_at',
                'ba.approved_at',
                'ba.reviewed_by',
                'p.project_name',
                'p.project_id',
                'p.description',
                'p.budget_total',
                'p.status as project_status',
                'requester.full_name as requester_name',
                'requester.email as requester_email',
                'reviewer.full_name as reviewer_name'
            )
            ->leftJoin('budgets as b', 'ba.budget_id', '=', 'b.budget_id')
            ->leftJoin('projects as p', 'b.project_id', '=', 'p.project_id')
            ->leftJoin('users as requester', 'ba.requested_by', '=', 'requester.user_id')
            ->leftJoin('users as reviewer', 'ba.reviewed_by', '=', 'reviewer.user_id')
            ->where('ba.status', 'Pending')
            ->orderBy('ba.created_at', 'desc')
            ->get();
    }
    
    public function getAllApprovals()
    {
        $query = DB::table('budget_approvals as ba')
            ->select(
                'ba.approval_id',
                'ba.budget_id',
                'ba.status',
                'ba.remarks',
                'ba.created_at',
                'ba.approved_at',
                'ba.reviewed_by',
                'p.project_name',
                'p.project_id',
                'p.description',
                'p.budget_total',
                'p.status as project_status',
                'requester.full_name as requester_name',
                'requester.email as requester_email',
                'reviewer.full_name as reviewer_name'
            )
            ->leftJoin('budgets as b', 'ba.budget_id', '=', 'b.budget_id')
            ->leftJoin('projects as p', 'b.project_id', '=', 'p.project_id')
            ->leftJoin('users as requester', 'ba.requested_by', '=', 'requester.user_id')
            ->leftJoin('users as reviewer', 'ba.reviewed_by', '=', 'reviewer.user_id');
        
        // Apply status filter
        if ($this->statusFilter) {
            $query->where('ba.status', $this->statusFilter);
        }
        
        // Apply search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('p.project_name', 'like', '%' . $this->search . '%')
                  ->orWhere('requester.full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('requester.email', 'like', '%' . $this->search . '%')
                  ->orWhere('p.description', 'like', '%' . $this->search . '%');
            });
        }
        
        return $query->orderBy('ba.created_at', 'desc')
                    ->paginate($this->perPage);
    }
    
    public function getApprovalDetails($approvalId)
    {
        return DB::table('budget_approvals as ba')
            ->select(
                'ba.approval_id',
                'ba.budget_id',
                'ba.status',
                'ba.remarks',
                'ba.created_at',
                'ba.approved_at',
                'ba.reviewed_by',
                'p.project_name',
                'p.project_id',
                'p.description',
                'p.budget_total',
                'p.status as project_status',
                'requester.full_name as requester_name',
                'requester.email as requester_email',
                'reviewer.full_name as reviewer_name'
            )
            ->leftJoin('budgets as b', 'ba.budget_id', '=', 'b.budget_id')
            ->leftJoin('projects as p', 'b.project_id', '=', 'p.project_id')
            ->leftJoin('users as requester', 'ba.requested_by', '=', 'requester.user_id')
            ->leftJoin('users as reviewer', 'ba.reviewed_by', '=', 'reviewer.user_id')
            ->where('ba.approval_id', $approvalId)
            ->first();
    }
    
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'perPage'])) {
            $this->resetPage();
        }
    }
    
    public function showApproveModal($approvalId)
    {
        $this->selectedApproval = $approvalId;
        $this->selectedApprovalDetails = $this->getApprovalDetails($approvalId);
        $this->showApprovalModal = true;
    }
    
    public function showRejectModal($approvalId)
    {
        $this->selectedApproval = $approvalId;
        $this->selectedApprovalDetails = $this->getApprovalDetails($approvalId);
        $this->rejectionRemarks = '';
        $this->showRejectionModal = true;
    }
    
    public function approveBudget()
    {
        try {
            DB::beginTransaction();
            
            $currentUser = Auth::user();
            $now = now();
            
            // Update budget approval
            DB::table('budget_approvals')
                ->where('approval_id', $this->selectedApproval)
                ->update([
                    'status' => 'Approved',
                    'reviewed_by' => $currentUser->user_id,
                    'approved_at' => $now,
                    'remarks' => 'Budget approved by ' . $currentUser->full_name,
                    'updated_at' => $now
                ]);
            
            // Get budget_id from approval
            $approval = DB::table('budget_approvals')
                ->where('approval_id', $this->selectedApproval)
                ->first(['budget_id']);
            
            if ($approval) {
                // Get project_id from budget
                $budget = DB::table('budgets')
                    ->where('budget_id', $approval->budget_id)
                    ->first(['project_id']);
                
                if ($budget && $budget->project_id) {
                    // Update the project status
                    DB::table('projects')
                        ->where('project_id', $budget->project_id)
                        ->update([
                            'status' => 'in_progress',
                            'updated_at' => $now
                        ]);
                }
            }
            
            DB::commit();
            
            $this->showApprovalModal = false;
            $this->selectedApproval = null;
            $this->selectedApprovalDetails = null;
            
            session()->flash('success', 'Budget has been approved successfully.');
            $this->calculateStats();
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to approve budget: ' . $e->getMessage());
        }
    }
    
    public function rejectBudget()
{
    $this->validate([
        'rejectionRemarks' => 'required|min:10|max:500'
    ]);
    
    try {
        DB::beginTransaction();
        
        $currentUser = Auth::user();
        $now = now();
        
        // Update budget approval
        DB::table('budget_approvals')
            ->where('approval_id', $this->selectedApproval)
            ->update([
                'status' => 'Rejected',
                'reviewed_by' => $currentUser->user_id,
                'approved_at' => $now,
                'remarks' => $this->rejectionRemarks . ' - Rejected by ' . $currentUser->full_name,
                'updated_at' => $now
            ]);
        
        // Get budget_id from approval
        $approval = DB::table('budget_approvals')
            ->where('approval_id', $this->selectedApproval)
            ->first(['budget_id']);
        
        if ($approval) {
            // Get project_id from budget
            $budget = DB::table('budgets')
                ->where('budget_id', $approval->budget_id)
                ->first(['project_id']);
            
            if ($budget && $budget->project_id) {
                // Update the project status to 'on_hold' (not 'cancelled')
                DB::table('projects')
                    ->where('project_id', $budget->project_id)
                    ->update([
                        'status' => 'on_hold',
                        'updated_at' => $now
                    ]);
            }
        }
        
        DB::commit();
        
        $this->showRejectionModal = false;
        $this->selectedApproval = null;
        $this->selectedApprovalDetails = null;
        $this->rejectionRemarks = '';
        
        session()->flash('success', 'Budget has been rejected and project has been put on hold.');
        $this->calculateStats();
        
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('error', 'Failed to reject budget: ' . $e->getMessage());
    }
}
    
    public function exportApprovals()
    {
        $approvals = DB::table('budget_approvals as ba')
            ->select(
                'ba.approval_id',
                'ba.status',
                'ba.created_at',
                'ba.approved_at',
                'p.project_name',
                'p.budget_total',
                'requester.full_name as requester_name',
                'requester.email as requester_email',
                'reviewer.full_name as reviewer_name',
                'ba.remarks'
            )
            ->leftJoin('budgets as b', 'ba.budget_id', '=', 'b.budget_id')
            ->leftJoin('projects as p', 'b.project_id', '=', 'p.project_id')
            ->leftJoin('users as requester', 'ba.requested_by', '=', 'requester.user_id')
            ->leftJoin('users as reviewer', 'ba.reviewed_by', '=', 'reviewer.user_id')
            ->orderBy('ba.created_at', 'desc')
            ->get();
        
        // Store export data in session
        session()->put('export_data', $approvals);
        session()->flash('info', 'Export data prepared. ' . count($approvals) . ' records ready for download.');
    }
    
    #[On('approval-processed')]
    public function refreshData()
    {
        $this->calculateStats();
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="page-header">
        <h1>Budget Approvals</h1>
        <p>Review and approve or reject budget requests from departments</p>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats mb-6">
        <div class="quick-stat">
            <div class="quick-stat-icon icon-expense">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Pending Approval</div>
                <div class="text-2xl font-bold">{{ $stats['pending'] }}</div>
            </div>
        </div>
        
        <div class="quick-stat">
            <div class="quick-stat-icon icon-income">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Approved</div>
                <div class="text-2xl font-bold">{{ $stats['approved'] }}</div>
            </div>
        </div>
        
        <div class="quick-stat">
            <div class="quick-stat-icon icon-budget">
                <i class="fas fa-times-circle"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Rejected</div>
                <div class="text-2xl font-bold">{{ $stats['rejected'] }}</div>
            </div>
        </div>
        
        <div class="quick-stat">
            <div class="quick-stat-icon icon-profit">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Total Requests</div>
                <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
            </div>
        </div>
    </div>

    <!-- Pending Approvals Section -->
    @php
        $pendingApprovals = $this->getPendingApprovals();
    @endphp
    
    @if($pendingApprovals && $pendingApprovals->count() > 0)
    <div class="page-card mb-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Pending Approvals</h2>
            <span class="status-badge status-pending">
                <i class="fas fa-clock"></i>
                {{ $pendingApprovals->count() }} Pending
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Project Details</th>
                        <th>Requested By</th>
                        <th>Budget Amount</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingApprovals as $approval)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">
                                {{ $approval->project_name ?? 'N/A' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Project ID: {{ $approval->project_id ?? 'N/A' }}
                            </div>
                            @if($approval->description)
                            <div class="text-xs text-gray-400 mt-1">
                                {{ Str::limit($approval->description, 100) }}
                            </div>
                            @endif
                        </td>
                        <td>
                            <div class="font-medium text-gray-900">
                                {{ $approval->requester_name ?? 'N/A' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $approval->requester_email ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="table-amount">
                            <span class="font-bold">
                                ₱{{ number_format($approval->budget_total ?? 0, 2) }}
                            </span>
                        </td>
                        <td>
                            @if($approval->created_at)
                                {{ \Carbon\Carbon::parse($approval->created_at)->format('M d, Y') }}
                                <div class="text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($approval->created_at)->format('h:i A') }}
                                </div>
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <button wire:click="showApproveModal({{ $approval->approval_id }})" 
                                        class="btn btn-success text-sm">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button wire:click="showRejectModal({{ $approval->approval_id }})" 
                                        class="btn btn-secondary text-sm">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- All Approvals Section -->
    <div class="page-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">All Budget Approvals</h2>
            <div class="flex gap-4">
                <button wire:click="exportApprovals" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div>
                <input type="text" 
                       wire:model.live="search" 
                       placeholder="Search by project name, description, or requester..."
                       class="form-input">
            </div>
            <div>
                <select wire:model.live="statusFilter" class="form-input">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div>
                <select wire:model.live="perPage" class="form-input">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
        
        <!-- Approvals Table -->
        <div class="overflow-x-auto">
            @php
                $allApprovals = $this->getAllApprovals();
            @endphp
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Project Details</th>
                        <th>Requester</th>
                        <th>Budget Amount</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Decision Date</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allApprovals as $approval)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">
                                {{ $approval->project_name ?? 'N/A' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Project ID: {{ $approval->project_id ?? 'N/A' }}
                            </div>
                            @if($approval->description)
                            <div class="text-xs text-gray-400 mt-1">
                                {{ Str::limit($approval->description, 100) }}
                            </div>
                            @endif
                        </td>
                        <td>
                            <div class="font-medium text-gray-900">
                                {{ $approval->requester_name ?? 'N/A' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $approval->requester_email ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="table-amount">
                            <span class="font-bold">
                                ${{ number_format($approval->budget_total ?? 0, 2) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusClasses = [
                                    'Pending' => 'status-pending',
                                    'Approved' => 'status-paid',
                                    'Rejected' => 'status-overdue'
                                ];
                            @endphp
                            <span class="status-badge {{ $statusClasses[$approval->status] ?? 'status-pending' }}">
                                <i class="fas fa-{{ $approval->status == 'Approved' ? 'check' : ($approval->status == 'Rejected' ? 'times' : 'clock') }}"></i>
                                {{ $approval->status }}
                            </span>
                        </td>
                        <td>
                            @if($approval->reviewer_name)
                            <div class="font-medium text-gray-900">
                                {{ $approval->reviewer_name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                @if($approval->approved_at)
                                    {{ \Carbon\Carbon::parse($approval->approved_at)->format('M d, Y') }}
                                @else
                                    N/A
                                @endif
                            </div>
                            @else
                            <span class="text-gray-400">Not reviewed</span>
                            @endif
                        </td>
                        <td>
                            @if($approval->approved_at)
                                {{ \Carbon\Carbon::parse($approval->approved_at)->format('M d, Y') }}
                                <div class="text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($approval->approved_at)->format('h:i A') }}
                                </div>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="max-w-xs">
                            <div class="truncate" title="{{ $approval->remarks ?? 'No remarks' }}">
                                {{ Str::limit($approval->remarks ?? 'No remarks', 50) }}
                            </div>
                        </td>
                        <td>
                            <div class="flex gap-2">
                                @if($approval->status === 'Pending')
                                <button wire:click="showApproveModal({{ $approval->approval_id }})" 
                                        class="btn btn-success text-sm">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button wire:click="showRejectModal({{ $approval->approval_id }})" 
                                        class="btn btn-secondary text-sm">
                                    <i class="fas fa-times"></i>
                                </button>
                                @else
                                <button onclick="showApprovalDetails({{ $approval->approval_id }})" 
                                        class="btn btn-secondary text-sm"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <div>No budget approvals found.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            
            <!-- Pagination -->
            @if($allApprovals->hasPages())
            <div class="mt-6">
                {{ $allApprovals->links() }}
            </div>
            @endif
        </div>
    </div>

    <!-- Approval Modal -->
    @if($showApprovalModal && $selectedApprovalDetails)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Approve Budget</h3>
                    <button wire:click="$set('showApprovalModal', false)" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="font-medium text-blue-900">Project Details</div>
                        <div class="mt-2">
                            <div class="text-sm text-gray-600">Project: {{ $selectedApprovalDetails->project_name ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-600">ID: {{ $selectedApprovalDetails->project_id ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-600 mt-1">
                                Budget: <span class="font-bold">${{ number_format($selectedApprovalDetails->budget_total ?? 0, 2) }}</span>
                            </div>
                            @if($selectedApprovalDetails->description)
                            <div class="text-sm text-gray-600 mt-2">
                                Description: {{ Str::limit($selectedApprovalDetails->description, 100) }}
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="p-4 bg-green-50 rounded-lg">
                        <div class="font-medium text-green-900">Approval Summary</div>
                        <div class="mt-2 text-sm text-gray-600">
                            <p>By approving this budget, you are authorizing the allocated funds for the project.</p>
                            <p class="mt-2">This action cannot be undone.</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button wire:click="$set('showApprovalModal', false)" 
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button wire:click="approveBudget" 
                                class="btn btn-success">
                            <i class="fas fa-check mr-2"></i> Confirm Approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Rejection Modal -->
    @if($showRejectionModal && $selectedApprovalDetails)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reject Budget</h3>
                    <button wire:click="$set('showRejectionModal', false)" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="font-medium text-blue-900">Project Details</div>
                        <div class="mt-2">
                            <div class="text-sm text-gray-600">Project: {{ $selectedApprovalDetails->project_name ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-600">ID: {{ $selectedApprovalDetails->project_id ?? 'N/A' }}</div>
                            <div class="text-sm text-gray-600 mt-1">
                                Budget: <span class="font-bold">${{ number_format($selectedApprovalDetails->budget_total ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="form-label">Reason for Rejection *</label>
                        <textarea wire:model="rejectionRemarks" 
                                  class="form-input h-32"
                                  placeholder="Please provide a detailed reason for rejecting this budget..."
                                  required></textarea>
                        @error('rejectionRemarks')
                            <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                        @enderror
                        <div class="text-xs text-gray-500">
                            Minimum 10 characters required. This will be recorded as part of the approval history.
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button wire:click="$set('showRejectionModal', false)" 
                                class="btn btn-secondary">
                            Cancel
                        </button>
                        <button wire:click="rejectBudget" 
                                class="btn btn-primary bg-red-600 hover:bg-red-700">
                            <i class="fas fa-times mr-2"></i> Confirm Rejection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Details Modal Script -->
    <script>
        function showApprovalDetails(approvalId) {
            // Fetch approval details via AJAX or Livewire and show in a modal
            // For simplicity, we'll redirect to a details page or show a basic alert
            alert('Approval details for ID: ' + approvalId + '\n\nIn a real implementation, this would show detailed information about the approval.');
        }
        
        document.addEventListener('livewire:init', () => {
            // Auto-refresh every 30 seconds to check for new approvals
            setInterval(() => {
                Livewire.dispatch('approval-processed');
            }, 30000);
        });udg
    </script>
</div>