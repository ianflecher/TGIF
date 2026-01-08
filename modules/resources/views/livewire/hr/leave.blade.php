<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithPagination;
    
    public $employees = [];
    public $departments = [];
    public $filters = [
        'status' => null,
        'type' => null,
        'date_from' => null,
        'date_to' => null,
        'search' => null,
        'department' => null,
    ];
    public $stats = [];
    public $showLeaveModal = false;
    public $selectedLeave = null;
    public $leaveTypes = [
        'vacation' => 'Vacation Leave',
        'sick' => 'Sick Leave',
        'emergency' => 'Emergency Leave',
        'maternity' => 'Maternity Leave',
        'paternity' => 'Paternity Leave',
        'bereavement' => 'Bereavement Leave',
        'study' => 'Study Leave',
        'unpaid' => 'Unpaid Leave',
        'others' => 'Others'
    ];
    public $statusColors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-100 text-gray-800',
    ];

    public function mount()
    {
        $this->filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
        $this->filters['date_to'] = date('Y-m-d');
        $this->loadEmployees();
        $this->loadDepartments();
        $this->loadStats();
    }

    public function loadEmployees()
    {
        $this->employees = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('e.status', 'active')
            ->select('e.employee_id', 'u.full_name', 'u.email', 'e.job_title')
            ->orderBy('u.full_name')
            ->get();
    }

    public function loadDepartments()
    {
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->orderBy('department_name')
            ->get();
    }

    public function loadStats()
    {
        $stats = DB::table('leaves')
            ->select(
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count'),
                DB::raw('COUNT(CASE WHEN status = "approved" THEN 1 END) as approved_count'),
                DB::raw('COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected_count'),
                DB::raw('COUNT(CASE WHEN status = "cancelled" THEN 1 END) as cancelled_count'),
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN total_days ELSE 0 END) as total_approved_days')
            )
            ->first();

        $this->stats = [
            'pending' => $stats->pending_count ?? 0,
            'approved' => $stats->approved_count ?? 0,
            'rejected' => $stats->rejected_count ?? 0,
            'cancelled' => $stats->cancelled_count ?? 0,
            'total' => $stats->total_count ?? 0,
            'total_approved_days' => $stats->total_approved_days ?? 0
        ];
    }

    public function getLeavesProperty()
    {
        $query = DB::table('leaves as l')
            ->select(
                'l.leave_id',
                'l.employee_id',
                'l.leave_type',
                'l.start_date',
                'l.end_date',
                'l.total_days',
                'l.reason',
                'l.status',
                'l.attachment_path',
                'l.approved_by',
                'l.approved_at',
                'l.rejection_reason',
                'l.created_at',
                'l.updated_at',
                'e.user_id',
                'e.job_title',
                'e.department_id',
                'u.full_name',
                'u.email',
                'd.department_name',
                'approver.full_name as approver_name'
            )
            ->join('employees as e', 'l.employee_id', '=', 'e.employee_id')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->leftJoin('users as approver', 'l.approved_by', '=', 'approver.user_id')
            ->orderBy('l.created_at', 'desc');

        // Apply filters
        if ($this->filters['status']) {
            $query->where('l.status', $this->filters['status']);
        } else {
            // Show all except cancelled by default
            $query->where('l.status', '!=', 'cancelled');
        }

        if ($this->filters['type']) {
            $query->where('l.leave_type', $this->filters['type']);
        }

        if ($this->filters['date_from']) {
            $query->whereDate('l.start_date', '>=', $this->filters['date_from']);
        }

        if ($this->filters['date_to']) {
            $query->whereDate('l.start_date', '<=', $this->filters['date_to']);
        }

        if ($this->filters['search']) {
            $query->where(function($q) {
                $q->where('u.full_name', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.email', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('d.department_name', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        if ($this->filters['department']) {
            $query->where('e.department_id', $this->filters['department']);
        }

        return $query->paginate(10);
    }

    public function viewLeave($leaveId)
    {
        $this->selectedLeave = DB::table('leaves as l')
            ->select(
                'l.*',
                'e.user_id',
                'e.job_title',
                'e.department_id',
                'u.full_name',
                'u.email',
                'd.department_name',
                'approver.full_name as approver_name',
                'approver.email as approver_email'
            )
            ->join('employees as e', 'l.employee_id', '=', 'e.employee_id')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->leftJoin('users as approver', 'l.approved_by', '=', 'approver.user_id')
            ->where('l.leave_id', $leaveId)
            ->first();

        $this->showLeaveModal = true;
    }

    public function approveLeave($leaveId, $approvedBy = null)
    {
        DB::table('leaves')
            ->where('leave_id', $leaveId)
            ->update([
                'status' => 'approved',
                'approved_by' => $approvedBy ?? auth()->id() ?? 1,
                'approved_at' => now(),
                'updated_at' => now()
            ]);

        // Log the action
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'leaves',
            'record_id' => $leaveId,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode(['status' => 'approved']),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        if ($this->showLeaveModal) {
            $this->showLeaveModal = false;
            $this->selectedLeave = null;
        }

        $this->loadStats();
        session()->flash('success', 'Leave approved successfully!');
    }

    public function rejectLeave($leaveId, $rejectionReason = '')
    {
        // If called from modal, show modal for rejection reason
        if ($this->showLeaveModal && $this->selectedLeave) {
            $this->selectedLeave->rejection_reason = '';
            return; // Will be handled by modal form
        }

        // If direct rejection without modal
        DB::table('leaves')
            ->where('leave_id', $leaveId)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'updated_at' => now()
            ]);

        // Log the action
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'leaves',
            'record_id' => $leaveId,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => $rejectionReason]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->loadStats();
        session()->flash('success', 'Leave rejected successfully!');
    }

    public function saveRejection()
    {
        if (!$this->selectedLeave) return;

        $this->validate([
            'selectedLeave.rejection_reason' => 'required|string|min:5|max:500'
        ]);

        DB::table('leaves')
            ->where('leave_id', $this->selectedLeave->leave_id)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $this->selectedLeave->rejection_reason,
                'updated_at' => now()
            ]);

        // Log the action
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'leaves',
            'record_id' => $this->selectedLeave->leave_id,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => $this->selectedLeave->rejection_reason]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showLeaveModal = false;
        $this->selectedLeave = null;
        $this->loadStats();
        session()->flash('success', 'Leave rejected successfully!');
    }

    public function cancelLeave($leaveId)
    {
        DB::table('leaves')
            ->where('leave_id', $leaveId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now()
            ]);

        // Log the action
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'leaves',
            'record_id' => $leaveId,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode(['status' => 'cancelled']),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        if ($this->showLeaveModal) {
            $this->showLeaveModal = false;
            $this->selectedLeave = null;
        }

        $this->loadStats();
        session()->flash('success', 'Leave cancelled successfully!');
    }

    public function applyFilters()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->filters = [
            'status' => null,
            'type' => null,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'search' => null,
            'department' => null,
        ];
        $this->resetPage();
    }

    public function downloadAttachment($leaveId)
    {
        $leave = DB::table('leaves')->where('leave_id', $leaveId)->first();
        
        if ($leave && $leave->attachment_path) {
            // In a real application, you would serve the file for download
            session()->flash('info', 'Attachment download would start here.');
        } else {
            session()->flash('error', 'No attachment found for this leave request.');
        }
    }

    public function getUpcomingLeaves()
    {
        return DB::table('leaves as l')
            ->join('employees as e', 'l.employee_id', '=', 'e.employee_id')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->where('l.status', 'approved')
            ->whereDate('l.start_date', '>=', date('Y-m-d'))
            ->whereDate('l.start_date', '<=', date('Y-m-d', strtotime('+7 days')))
            ->orderBy('l.start_date')
            ->limit(5)
            ->get();
    }

    public function calculateLeaveDuration($startDate, $endDate)
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        return $interval->days + 1; // Inclusive of both start and end dates
    }

    public function formatDateRange($startDate, $endDate)
    {
        $start = date('M d', strtotime($startDate));
        $end = date('M d', strtotime($endDate));
        
        if (date('Y', strtotime($startDate)) !== date('Y', strtotime($endDate))) {
            $start .= ', ' . date('Y', strtotime($startDate));
            $end .= ', ' . date('Y', strtotime($endDate));
        }
        
        return $start . ' - ' . $end;
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-hr-900">Leave Management</h1>
            <p class="text-gray-600 mt-1">View and manage all employee leave requests</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                {{ $stats['total'] ?? 0 }} Total Leaves
            </span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['pending'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Pending</div>
            <div class="card-subtitle">Awaiting approval</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-green-100 text-green-600">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['approved'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Approved</div>
            <div class="card-subtitle">Leaves approved</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-red-100 text-red-600">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['rejected'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Rejected</div>
            <div class="card-subtitle">Leaves denied</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['total_approved_days'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Total Days</div>
            <div class="card-subtitle">Approved leave days</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="form-label">Leave Status</label>
                <select wire:model.live="filters.status" class="form-input">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Leave Type Filter -->
            <div>
                <label class="form-label">Leave Type</label>
                <select wire:model.live="filters.type" class="form-input">
                    <option value="">All Types</option>
                    @foreach($leaveTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range -->
            <div>
                <label class="form-label">Date From</label>
                <input type="date" 
                       wire:model.live="filters.date_from"
                       class="form-input">
            </div>

            <div>
                <label class="form-label">Date To</label>
                <input type="date" 
                       wire:model.live="filters.date_to"
                       class="form-input">
            </div>

            <!-- Department Filter -->
            <div>
                <label class="form-label">Department</label>
                <select wire:model.live="filters.department" class="form-input">
                    <option value="">All Departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Search -->
            <div>
                <label class="form-label">Search</label>
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="filters.search"
                           placeholder="Search by name, email..."
                           class="form-input pl-10">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
        </div>

        <!-- Filter Actions -->
        <div class="flex justify-end mt-4 space-x-3">
            <button wire:click="applyFilters" class="btn-primary">
                <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
            <button wire:click="resetFilters" class="btn-secondary">
                <i class="fas fa-redo mr-2"></i>Reset
            </button>
        </div>
    </div>

    <!-- Upcoming Approved Leaves -->
    @php
        $upcomingLeaves = $this->getUpcomingLeaves();
    @endphp
    @if($upcomingLeaves->count() > 0)
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Upcoming Leaves (Next 7 Days)</h2>
                <p class="text-sm text-gray-600">Employees on approved leave</p>
            </div>
            <div class="text-sm text-gray-600">
                {{ $upcomingLeaves->count() }} Upcoming Leaves
            </div>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                @foreach($upcomingLeaves as $leave)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-900">{{ $leave->full_name }}</h3>
                                <p class="text-sm text-gray-500">{{ $leave->job_title }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $this->leaveTypes[$leave->leave_type] ?? ucfirst($leave->leave_type) }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-gray-400"></i>
                                {{ date('M d', strtotime($leave->start_date)) }}
                                @if($leave->start_date != $leave->end_date)
                                    - {{ date('M d', strtotime($leave->end_date)) }}
                                @endif
                            </div>
                            <div class="flex items-center mt-1">
                                <i class="fas fa-clock mr-2 text-gray-400"></i>
                                {{ $leave->total_days }} {{ Str::plural('day', $leave->total_days) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Leaves Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">All Leave Requests</h2>
            <div class="text-sm text-gray-600">
                Showing {{ $this->leaves->firstItem() ?? 0 }}-{{ $this->leaves->lastItem() ?? 0 }} of {{ $this->leaves->total() }} leave requests
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Date Range</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if($this->leaves->count() > 0)
                        @foreach($this->leaves as $leave)
                            @php
                                $typeColors = [
                                    'vacation' => 'bg-blue-100 text-blue-800',
                                    'sick' => 'bg-purple-100 text-purple-800',
                                    'emergency' => 'bg-red-100 text-red-800',
                                    'maternity' => 'bg-pink-100 text-pink-800',
                                    'paternity' => 'bg-indigo-100 text-indigo-800',
                                    'bereavement' => 'bg-gray-100 text-gray-800',
                                    'study' => 'bg-yellow-100 text-yellow-800',
                                    'unpaid' => 'bg-orange-100 text-orange-800',
                                    'others' => 'bg-teal-100 text-teal-800',
                                ];
                                $typeColor = $typeColors[$leave->leave_type] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-hr-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $leave->full_name ?? 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ $leave->department_name ?? 'No Department' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $typeColor }}">
                                        {{ $leaveTypes[$leave->leave_type] ?? ucfirst($leave->leave_type) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium">
                                        {{ date('M d, Y', strtotime($leave->start_date)) }}
                                    </div>
                                    @if($leave->start_date != $leave->end_date)
                                        <div class="text-sm text-gray-500">
                                            to {{ date('M d, Y', strtotime($leave->end_date)) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock text-gray-400 mr-2"></i>
                                        <span class="font-medium">{{ $leave->total_days }}</span>
                                        <span class="text-sm text-gray-500 ml-1">
                                            {{ Str::plural('day', $leave->total_days) }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="max-w-xs truncate" title="{{ $leave->reason }}">
                                        {{ $leave->reason }}
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$leave->status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($leave->status) }}
                                        </span>
                                        @if($leave->approved_at && $leave->approver_name)
                                            <div class="text-xs text-gray-500 mt-1">
                                                By {{ $leave->approver_name }}
                                            </div>
                                        @endif
                                        @if($leave->rejection_reason)
                                            <div class="text-xs text-red-500 mt-1">
                                                {{ Str::limit($leave->rejection_reason, 30) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <button wire:click="viewLeave('{{ $leave->leave_id }}')" 
                                                class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        
                                        @if($leave->attachment_path)
                                            <button wire:click="downloadAttachment('{{ $leave->leave_id }}')" 
                                                    class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                                <i class="fas fa-paperclip mr-1"></i>Attachment
                                            </button>
                                        @endif
                                        
                                        @if($leave->status === 'pending')
                                            <button wire:click="approveLeave('{{ $leave->leave_id }}')" 
                                                    onclick="return confirm('Approve leave request for {{ $leave->full_name }}?')"
                                                    class="px-3 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100">
                                                <i class="fas fa-check mr-1"></i>Approve
                                            </button>
                                            
                                            <button wire:click="viewLeave('{{ $leave->leave_id }}')" 
                                                    class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100">
                                                <i class="fas fa-times mr-1"></i>Reject
                                            </button>
                                            
                                            <button wire:click="cancelLeave('{{ $leave->leave_id }}')" 
                                                    onclick="return confirm('Cancel this leave request?')"
                                                    class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                                <i class="fas fa-ban mr-1"></i>Cancel
                                            </button>
                                        @endif
                                        
                                        @if(in_array($leave->status, ['approved', 'rejected']) && $leave->status !== 'cancelled')
                                            <button wire:click="cancelLeave('{{ $leave->leave_id }}')" 
                                                    onclick="return confirm('Cancel this leave? This action cannot be undone.')"
                                                    class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                                <i class="fas fa-undo mr-1"></i>Cancel
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No leave requests found</p>
                                    <p class="text-sm mt-1">Try adjusting your filters or check back later.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($this->leaves->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $this->leaves->links() }}
            </div>
        @endif
    </div>

    <!-- Leave Details Modal -->
    @if($showLeaveModal && $selectedLeave)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showLeaveModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Leave Request Details</h3>
                            <p class="text-sm text-gray-500">ID: #{{ $selectedLeave->leave_id }}</p>
                        </div>
                        <button wire:click="$set('showLeaveModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Employee Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Employee Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Employee Name</label>
                                    <p class="font-medium">{{ $selectedLeave->full_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Email</label>
                                    <p class="font-medium">{{ $selectedLeave->email ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Job Title</label>
                                    <p class="font-medium">{{ $selectedLeave->job_title ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Department</label>
                                    <p class="font-medium">{{ $selectedLeave->department_name ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Leave Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Leave Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Leave Type</label>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $typeColors[$selectedLeave->leave_type] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $leaveTypes[$selectedLeave->leave_type] ?? ucfirst($selectedLeave->leave_type) }}
                                    </span>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Date Range</label>
                                    <p class="font-medium">
                                        {{ date('M d, Y', strtotime($selectedLeave->start_date)) }}
                                        @if($selectedLeave->start_date != $selectedLeave->end_date)
                                            to {{ date('M d, Y', strtotime($selectedLeave->end_date)) }}
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Duration</label>
                                    <p class="font-medium">
                                        {{ $selectedLeave->total_days }} {{ Str::plural('day', $selectedLeave->total_days) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Status</label>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$selectedLeave->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($selectedLeave->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="md:col-span-2">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Reason for Leave</h4>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-gray-700 whitespace-pre-wrap">{{ $selectedLeave->reason }}</p>
                            </div>
                        </div>

                        <!-- Approval Information -->
                        @if($selectedLeave->status === 'approved')
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Approval Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500">Approved By</label>
                                    <p class="font-medium">{{ $selectedLeave->approver_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Approved At</label>
                                    <p class="font-medium">{{ date('M d, Y h:i A', strtotime($selectedLeave->approved_at)) }}</p>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Rejection Information -->
                        @if($selectedLeave->status === 'rejected' && $selectedLeave->rejection_reason)
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Rejection Information</h4>
                            <div class="p-4 bg-red-50 rounded-lg">
                                <p class="text-red-700 whitespace-pre-wrap">{{ $selectedLeave->rejection_reason }}</p>
                            </div>
                        </div>
                        @endif

                        <!-- Attachment -->
                        @if($selectedLeave->attachment_path)
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Attachment</h4>
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-paperclip text-blue-500 mr-3"></i>
                                    <div>
                                        <p class="font-medium text-blue-700">File attached</p>
                                        <button wire:click="downloadAttachment('{{ $selectedLeave->leave_id }}')" 
                                                class="text-sm text-blue-600 hover:text-blue-800 mt-1">
                                            <i class="fas fa-download mr-1"></i>Download Attachment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Actions (if pending) -->
                        @if($selectedLeave->status === 'pending')
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Actions</h4>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button wire:click="approveLeave('{{ $selectedLeave->leave_id }}')" 
                                        class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-check mr-2"></i>
                                    Approve Leave
                                </button>
                                
                                <!-- Rejection Form -->
                                <div class="flex-1">
                                    <div x-data="{ showRejectForm: false }" class="w-full">
                                        <template x-if="!showRejectForm">
                                            <button @click="showRejectForm = true" 
                                                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject Leave
                                            </button>
                                        </template>
                                        
                                        <template x-if="showRejectForm">
                                            <div class="space-y-3">
                                                <textarea wire:model="selectedLeave.rejection_reason" 
                                                          rows="3"
                                                          class="form-input w-full"
                                                          placeholder="Enter reason for rejection..."></textarea>
                                                @error('selectedLeave.rejection_reason') 
                                                    <span class="text-red-500 text-xs">{{ $message }}</span> 
                                                @enderror
                                                
                                                <div class="flex gap-2">
                                                    <button wire:click="saveRejection" 
                                                            class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                                        <i class="fas fa-times mr-2"></i>
                                                        Submit Rejection
                                                    </button>
                                                    <button @click="showRejectForm = false" 
                                                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                
                                <button wire:click="cancelLeave('{{ $selectedLeave->leave_id }}')" 
                                        onclick="return confirm('Cancel this leave request?')"
                                        class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                    <i class="fas fa-ban mr-2"></i>
                                    Cancel Request
                                </button>
                            </div>
                        </div>
                        @endif

                        <!-- Additional Actions for non-pending leaves -->
                        @if(in_array($selectedLeave->status, ['approved', 'rejected']) && $selectedLeave->status !== 'cancelled')
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Additional Actions</h4>
                            <button wire:click="cancelLeave('{{ $selectedLeave->leave_id }}')" 
                                    onclick="return confirm('Cancel this leave? This action cannot be undone.')"
                                    class="inline-flex justify-center items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                <i class="fas fa-undo mr-2"></i>
                                Cancel Leave
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6">
                    <button wire:click="$set('showLeaveModal', false)" 
                            class="btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        .dashboard-card {
            @apply bg-white rounded-xl p-4 shadow-sm border border-gray-100;
        }
        
        .card-header {
            @apply flex items-center justify-between mb-3;
        }
        
        .card-icon {
            @apply w-10 h-10 rounded-lg flex items-center justify-center text-lg;
        }
        
        .card-stat {
            @apply text-2xl font-bold text-gray-900;
        }
        
        .card-title {
            @apply text-sm font-medium text-gray-900;
        }
        
        .card-subtitle {
            @apply text-xs text-gray-500 mt-1;
        }
        
        .data-table {
            @apply min-w-full divide-y divide-gray-200;
        }
        
        .data-table thead {
            @apply bg-gray-50;
        }
        
        .data-table th {
            @apply px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider;
        }
        
        .data-table tbody {
            @apply bg-white divide-y divide-gray-200;
        }
        
        .data-table td {
            @apply px-6 py-4 whitespace-nowrap;
        }
        
        .btn-primary {
            @apply inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150;
        }
        
        .btn-secondary {
            @apply inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150;
        }
        
        .form-input {
            @apply w-full border-gray-300 rounded-md shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50;
        }
        
        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-1;
        }
    </style>

    <script>
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                @this.set('showLeaveModal', false);
            }
        });
    </script>
</div>