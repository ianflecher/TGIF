<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    public $applications = [];
    public $employees = [];
    public $departments = [];
    public $users = [];
    public $filters = [
        'status' => null,
        'date_from' => null,
        'date_to' => null,
        'search' => null,
    ];
    public $stats = [];
    public $selectedApplication = null;
    public $selectedEmployee = null;
    public $showApplicationModal = false;
    public $showRoleChangeModal = false;
    public $showInterviewModal = false;
    public $showInterviewResultModal = false;
    public $showDepartmentModal = false;
    public $showDocumentsModal = false;
    public $selectedUserForRoleChange = null;
    public $newRole = 'employee';
    public $newDepartment = '';
    public $documents = [];
    
    // Interview scheduling
    public $interviewDate = '';
    public $interviewTime = '';
    public $interviewNotes = '';
    public $interviewerId = '';
    public $interviewType = 'in_person';
    
    // Interview results
    public $interviewFeedback = '';
    public $interviewResult = 'passed';

    public function mount()
    {
        $this->filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
        $this->filters['date_to'] = date('Y-m-d');
        $this->loadData();
    }

    public function loadData()
    {
        $this->loadApplications();
        $this->loadEmployees();
        $this->loadDepartments();
        $this->loadUsers();
        $this->loadStats();
    }

    public function loadApplications()
    {
        $query = DB::table('job_applications as ja')
            ->select(
                'ja.application_id',
                'ja.user_id',
                'ja.position_applied',
                'ja.years_experience',
                'ja.application_date',
                'ja.status',
                'ja.interview_date',
                'ja.interview_type',
                'ja.interviewer_id',
                'ja.interview_notes',
                'ja.interview_status',
                'ja.notes',
                'ja.resume_data',
                'ja.created_at',
                'ja.updated_at',
                'u.full_name',
                'u.username',
                'u.email',
                'u.role',
                'interviewer.full_name as interviewer_name',
                DB::raw('(SELECT COUNT(*) FROM job_applications ja2 WHERE ja2.user_id = ja.user_id) as total_applications')
            )
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->leftJoin('users as interviewer', 'ja.interviewer_id', '=', 'interviewer.user_id')
            ->orderBy('ja.interview_date', 'desc')
            ->orderBy('ja.application_date', 'desc');

        if ($this->filters['status']) {
            if ($this->filters['status'] === 'active') {
                $query->whereIn('ja.status', ['pending', 'reviewed', 'shortlisted', 'rejected']);
            } elseif ($this->filters['status'] === 'interview_scheduled') {
                $query->where('ja.status', 'reviewed')
                      ->whereNotNull('ja.interview_date');
            } else {
                $query->where('ja.status', $this->filters['status']);
            }
        } else {
            $query->whereIn('ja.status', ['pending', 'reviewed', 'shortlisted', 'rejected']);
        }

        if ($this->filters['date_from']) {
            $query->whereDate('ja.application_date', '>=', $this->filters['date_from']);
        }

        if ($this->filters['date_to']) {
            $query->whereDate('ja.application_date', '<=', $this->filters['date_to']);
        }

        if ($this->filters['search']) {
            $query->where(function($q) {
                $q->where('u.full_name', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.username', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('u.email', 'like', '%' . $this->filters['search'] . '%')
                  ->orWhere('ja.position_applied', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        $this->applications = $query->get();
    }

    public function loadEmployees()
    {
        $this->employees = DB::table('employees as e')
            ->select(
                'e.employee_id',
                'e.job_title',
                'e.hire_date',
                'e.status as emp_status',
                'e.department_id',
                'u.user_id',
                'u.full_name',
                'u.username',
                'u.email',
                'u.role',
                'd.department_name'
            )
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('u.role', 'employee')
            ->where('e.status', '!=', 'inactive')
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

    public function loadUsers()
    {
        $this->users = DB::table('users')
            ->select('user_id', 'full_name', 'username', 'email', 'role')
            ->whereIn('role', ['admin', 'manager', 'hr'])
            ->orderBy('full_name')
            ->get();
    }

    public function loadStats()
    {
        $stats = DB::table('job_applications')
            ->select(
                DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count'),
                DB::raw('COUNT(CASE WHEN status = "reviewed" AND interview_date IS NOT NULL THEN 1 END) as interview_scheduled_count'),
                DB::raw('COUNT(CASE WHEN status = "reviewed" AND interview_date IS NULL THEN 1 END) as reviewed_count'),
                DB::raw('COUNT(CASE WHEN status = "shortlisted" THEN 1 END) as shortlisted_count'),
                DB::raw('COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected_count'),
                DB::raw('COUNT(CASE WHEN status = "hired" THEN 1 END) as hired_count'),
                DB::raw('COUNT(*) as total_count')
            )
            ->first();

        $this->stats = [
            'pending' => $stats->pending_count ?? 0,
            'interview_scheduled' => $stats->interview_scheduled_count ?? 0,
            'reviewed' => $stats->reviewed_count ?? 0,
            'shortlisted' => $stats->shortlisted_count ?? 0,
            'rejected' => $stats->rejected_count ?? 0,
            'hired' => $stats->hired_count ?? 0,
            'total' => $stats->total_count ?? 0
        ];
    }

    public function viewApplication($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications as ja')
            ->select(
                'ja.*',
                'u.full_name',
                'u.username',
                'u.email',
                'u.role',
                'interviewer.full_name as interviewer_name'
            )
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->leftJoin('users as interviewer', 'ja.interviewer_id', '=', 'interviewer.user_id')
            ->where('ja.application_id', $applicationId)
            ->first();

        $this->showApplicationModal = true;
    }

    public function viewDocuments($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();
        
        $this->documents = DB::table('application_documents as ad')
            ->select('ad.*', 'u.full_name')
            ->leftJoin('users as u', 'ad.user_id', '=', 'u.user_id')
            ->where('ad.application_id', $applicationId)
            ->orderBy('ad.uploaded_at', 'desc')
            ->get();
        
        $this->showDocumentsModal = true;
    }

    public function updateApplicationStatus($applicationId, $status)
    {
        $updates = [
            'status' => $status,
            'updated_at' => now()
        ];

        if ($status !== 'reviewed') {
            $updates['interview_date'] = null;
            $updates['interview_notes'] = null;
            $updates['interviewer_id'] = null;
            $updates['interview_status'] = null;
        }

        DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->update($updates);

        if ($status === 'hired') {
            $application = DB::table('job_applications')
                ->where('application_id', $applicationId)
                ->first();

            if ($application) {
                DB::table('users')
                    ->where('user_id', $application->user_id)
                    ->update(['role' => 'employee']);

                $existingEmployee = DB::table('employees')
                    ->where('user_id', $application->user_id)
                    ->first();
                
                if (!$existingEmployee) {
                    DB::table('employees')->insert([
                        'user_id' => $application->user_id,
                        'job_title' => $application->position_applied,
                        'hire_date' => date('Y-m-d'),
                        'salary' => 0.00,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        if ($this->showApplicationModal) {
            $this->showApplicationModal = false;
            $this->selectedApplication = null;
        }
        
        $this->loadData();
        
        session()->flash('success', 'Application status updated successfully!');
    }

    public function markAsReviewed($applicationId)
    {
        DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status' => 'reviewed',
                'updated_at' => now()
            ]);

        $this->loadData();
        session()->flash('success', 'Application marked as reviewed. You can now schedule an interview.');
    }

    public function openInterviewModal($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();

        $this->interviewDate = date('Y-m-d', strtotime('+2 days'));
        $this->interviewTime = '10:00';
        $this->interviewNotes = '';
        $this->interviewerId = '';
        $this->interviewType = 'in_person';

        if ($this->selectedApplication->interview_date) {
            $interviewDateTime = \Carbon\Carbon::parse($this->selectedApplication->interview_date);
            $this->interviewDate = $interviewDateTime->format('Y-m-d');
            $this->interviewTime = $interviewDateTime->format('H:i');
            $this->interviewNotes = $this->selectedApplication->interview_notes ?? '';
            $this->interviewerId = $this->selectedApplication->interviewer_id ?? '';
            $this->interviewType = $this->selectedApplication->interview_type ?? 'in_person';
        }

        $this->showInterviewModal = true;
    }

    public function saveInterviewSchedule()
    {
        $this->validate([
            'interviewDate' => 'required|date',
            'interviewTime' => 'required',
            'interviewerId' => 'required',
            'interviewType' => 'required|in:phone,video,in_person,technical,hr',
        ]);

        $interviewDateTime = $this->interviewDate . ' ' . $this->interviewTime . ':00';

        DB::table('job_applications')
            ->where('application_id', $this->selectedApplication->application_id)
            ->update([
                'interview_date' => $interviewDateTime,
                'interview_notes' => $this->interviewNotes,
                'interviewer_id' => $this->interviewerId,
                'interview_type' => $this->interviewType,
                'interview_status' => 'scheduled',
                'updated_at' => now()
            ]);

        $this->showInterviewModal = false;
        $this->loadData();
        session()->flash('success', 'Interview scheduled successfully!');
    }

    public function cancelInterview($applicationId)
    {
        DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->update([
                'interview_date' => null,
                'interview_notes' => null,
                'interviewer_id' => null,
                'interview_type' => null,
                'interview_status' => null,
                'updated_at' => now()
            ]);

        $this->loadData();
        session()->flash('success', 'Interview cancelled successfully!');
    }

    public function markInterviewCompleted($applicationId)
    {
        $this->selectedApplication = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();
        
        $this->interviewFeedback = '';
        $this->interviewResult = 'passed';
        
        $this->showInterviewResultModal = true;
    }

    public function saveInterviewResult()
    {
        $this->validate([
            'interviewFeedback' => 'required|string|min:10',
            'interviewResult' => 'required|in:passed,failed'
        ]);
        
        $existingNotes = $this->selectedApplication->interview_notes ?? '';
        $combinedNotes = $existingNotes . "\n\n--- Interview Results ---\n";
        $combinedNotes .= "Result: " . ucfirst($this->interviewResult) . "\n";
        $combinedNotes .= "Feedback: " . $this->interviewFeedback . "\n";
        $combinedNotes .= "Completed on: " . date('Y-m-d H:i:s');
        
        DB::table('job_applications')
            ->where('application_id', $this->selectedApplication->application_id)
            ->update([
                'interview_status' => 'completed',
                'interview_notes' => $combinedNotes,
                'updated_at' => now()
            ]);
        
        if ($this->interviewResult === 'passed') {
            DB::table('job_applications')
                ->where('application_id', $this->selectedApplication->application_id)
                ->update(['status' => 'shortlisted']);
        } elseif ($this->interviewResult === 'failed') {
            DB::table('job_applications')
                ->where('application_id', $this->selectedApplication->application_id)
                ->update(['status' => 'rejected']);
        }
        
        $this->showInterviewResultModal = false;
        $this->loadData();
        session()->flash('success', 'Interview result saved!');
    }

    public function openDepartmentModal($employeeId)
    {
        $this->selectedEmployee = DB::table('employees as e')
            ->select('e.*', 'u.full_name', 'd.department_name')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employee_id', $employeeId)
            ->first();
        
        $this->newDepartment = $this->selectedEmployee->department_id ?? '';
        $this->showDepartmentModal = true;
    }

    public function updateDepartment()
    {
        $this->validate([
            'newDepartment' => 'required|exists:departments,department_id'
        ]);

        DB::table('employees')
            ->where('employee_id', $this->selectedEmployee->employee_id)
            ->update([
                'department_id' => $this->newDepartment,
                'updated_at' => now()
            ]);

        // Log the change
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'employees',
            'record_id' => $this->selectedEmployee->employee_id,
            'old_values' => json_encode(['department_id' => $this->selectedEmployee->department_id]),
            'new_values' => json_encode(['department_id' => $this->newDepartment]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showDepartmentModal = false;
        $this->loadData();
        session()->flash('success', 'Department updated successfully!');
    }

    public function openRoleChangeModal($userId)
    {
        $this->selectedUserForRoleChange = DB::table('users')
            ->where('user_id', $userId)
            ->first();

        $this->newRole = $this->selectedUserForRoleChange->role ?? 'employee';
        $this->showRoleChangeModal = true;
    }

    public function changeUserRole()
    {
        if (!$this->selectedUserForRoleChange) {
            return;
        }

        $userId = $this->selectedUserForRoleChange->user_id;
        $oldRole = $this->selectedUserForRoleChange->role ?? 'employee';
        
        DB::table('users')
            ->where('user_id', $userId)
            ->update([
                'role' => $this->newRole,
                'updated_at' => now()
            ]);

        if ($oldRole === 'employee' && $this->newRole !== 'employee') {
            DB::table('employees')->where('user_id', $userId)->delete();
        } elseif ($oldRole !== 'employee' && $this->newRole === 'employee') {
            $employee = DB::table('employees')->where('user_id', $userId)->first();
            if (!$employee) {
                DB::table('employees')->insert([
                    'user_id' => $userId,
                    'job_title' => 'New Employee',
                    'hire_date' => date('Y-m-d'),
                    'salary' => 0.00,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'users',
            'record_id' => $userId,
            'old_values' => json_encode(['role' => $oldRole]),
            'new_values' => json_encode(['role' => $this->newRole]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showRoleChangeModal = false;
        $this->loadData();
        session()->flash('success', 'User role changed successfully!');
    }

    public function deleteApplication($applicationId)
    {
        if (confirm('Are you sure you want to delete this application?')) {
            DB::table('job_applications')->where('application_id', $applicationId)->delete();
            $this->loadData();
            session()->flash('success', 'Application deleted successfully!');
        }
    }

    public function applyFilters()
    {
        $this->loadData();
    }

    public function resetFilters()
    {
        $this->filters = [
            'status' => null,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'search' => null,
        ];
        $this->loadData();
    }

    public function downloadResume($applicationId)
    {
        $application = DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->first();

        if ($application && $application->resume_data) {
            $resumeData = base64_decode($application->resume_data);
            session()->flash('info', 'Resume download would start here.');
        }
    }

    public function downloadDocument($documentId)
    {
        $document = DB::table('application_documents')
            ->where('id', $documentId)
            ->first();

        if ($document) {
            session()->flash('info', 'Document download would start here.');
        }
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-hr-900">Job Applications Management</h1>
            <p class="text-gray-600 mt-1">Review and manage job applications</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                {{ $stats['total'] ?? 0 }} Total Applications
            </span>
            <button class="btn-primary" onclick="openNewApplication()">
                <i class="fas fa-plus mr-2"></i>New Application
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
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
            <div class="card-subtitle">Awaiting review</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['reviewed'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Reviewed</div>
            <div class="card-subtitle">Under consideration</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-purple-100 text-purple-600">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['interview_scheduled'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Interview</div>
            <div class="card-subtitle">Scheduled</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-green-100 text-green-600">
                    <i class="fas fa-list"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['shortlisted'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Shortlisted</div>
            <div class="card-subtitle">Top candidates</div>
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
            <div class="card-subtitle">Not selected</div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon bg-indigo-100 text-indigo-600">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="text-right">
                    <div class="card-stat">{{ $stats['hired'] ?? 0 }}</div>
                </div>
            </div>
            <div class="card-title">Hired</div>
            <div class="card-subtitle">Successfully hired</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl p-4 mb-6 shadow-sm border border-gray-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="form-label">Application Status</label>
                <select wire:model.live="filters.status" class="form-input">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="interview_scheduled">Scheduled for Interview</option>
                    <option value="shortlisted">Shortlisted</option>
                    <option value="rejected">Rejected</option>
                    <option value="hired">Hired</option>
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

            <!-- Search -->
            <div>
                <label class="form-label">Search</label>
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="filters.search"
                           placeholder="Search by name, position..."
                           class="form-input pl-10">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
                <button wire:click="applyFilters" class="btn-primary w-full">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <button wire:click="resetFilters" class="btn-secondary w-full">
                    <i class="fas fa-redo mr-2"></i>Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Job Applications</h2>
            <div class="text-sm text-gray-600">
                Showing {{ count($applications) }} applications
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Position</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Interview Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($applications) > 0)
                        @foreach($applications as $application)
                            @php
                                // Determine display status
                                $displayStatus = $application->status ?? 'pending';
                                $statusLabel = ucfirst($application->status ?? 'pending');
                                $interviewCompleted = ($application->interview_status ?? '') === 'completed';
                                
                                if (($application->interview_date ?? false) && ($application->status ?? '') === 'reviewed') {
                                    if ($interviewCompleted) {
                                        $displayStatus = 'interview_completed';
                                        $statusLabel = 'Interview Completed';
                                    } else {
                                        $displayStatus = 'scheduled_interview';
                                        $statusLabel = 'Scheduled for Interview';
                                    }
                                }

                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'reviewed' => 'bg-blue-100 text-blue-800',
                                    'scheduled_interview' => 'bg-purple-100 text-purple-800',
                                    'interview_completed' => 'bg-indigo-100 text-indigo-800',
                                    'shortlisted' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'hired' => 'bg-teal-100 text-teal-800',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-hr-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $application->full_name ?? 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ $application->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-medium">{{ $application->position_applied ?? 'N/A' }}</td>
                                <td>{{ $application->years_experience ?? 0 }} years</td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$displayStatus] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabel }}
                                    </span>
                                    @if(($application->interview_date ?? false) && ($application->status ?? '') === 'reviewed')
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-user-tie mr-1"></i>
                                            {{ $application->interviewer_name ?? 'Interviewer not assigned' }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($application->interview_date ?? false)
                                        <div class="font-medium">
                                            {{ date('M d, Y', strtotime($application->interview_date)) }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ date('h:i A', strtotime($application->interview_date)) }}
                                        </div>
                                        @if($application->interview_type ?? false)
                                            <div class="text-xs text-gray-500">
                                                {{ ucfirst(str_replace('_', ' ', $application->interview_type)) }}
                                            </div>
                                        @endif
                                        @if($application->interview_status === 'completed')
                                            <div class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-check-circle mr-1"></i>Completed
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">Not scheduled</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <button wire:click="viewApplication('{{ $application->application_id }}')" 
                                                class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        
                                        <button wire:click="viewDocuments('{{ $application->application_id }}')" 
                                                class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                            <i class="fas fa-file-alt mr-1"></i>Documents
                                        </button>
                                        
                                        @if(($application->status ?? '') === 'pending')
                                            <button wire:click="markAsReviewed('{{ $application->application_id }}')" 
                                                    class="px-3 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100">
                                                <i class="fas fa-check mr-1"></i>Review
                                            </button>
                                        @endif

                                        @if(($application->status ?? '') === 'reviewed')
                                            @if($application->interview_date ?? false)
                                                @if($application->interview_status !== 'completed')
                                                    <button wire:click="markInterviewCompleted('{{ $application->application_id }}')" 
                                                            class="px-3 py-1 text-xs bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">
                                                        <i class="fas fa-clipboard-check mr-1"></i>Complete
                                                    </button>
                                                @endif
                                                <button wire:click="openInterviewModal('{{ $application->application_id }}')" 
                                                        class="px-3 py-1 text-xs bg-purple-50 text-purple-600 rounded hover:bg-purple-100">
                                                    <i class="fas fa-edit mr-1"></i>Reschedule
                                                </button>
                                                <button wire:click="cancelInterview('{{ $application->application_id }}')" 
                                                        onclick="return confirm('Are you sure you want to cancel this interview?')"
                                                        class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100">
                                                    <i class="fas fa-times mr-1"></i>Cancel
                                                </button>
                                            @else
                                                <button wire:click="openInterviewModal('{{ $application->application_id }}')" 
                                                        class="px-3 py-1 text-xs bg-purple-50 text-purple-600 rounded hover:bg-purple-100">
                                                    <i class="fas fa-calendar-alt mr-1"></i>Schedule
                                                </button>
                                            @endif
                                        @endif

                                        @if(in_array($application->status, ['shortlisted', 'reviewed']))
                                            @if($application->interview_status === 'completed' || $application->status === 'shortlisted')
                                                <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'hired')" 
                                                        class="px-3 py-1 text-xs bg-teal-50 text-teal-600 rounded hover:bg-teal-100"
                                                        onclick="return confirm('Hire {{ $application->full_name }} as {{ $application->position_applied }}?')">
                                                    <i class="fas fa-user-tie mr-1"></i>Hire
                                                </button>
                                                <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'rejected')" 
                                                        class="px-3 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100"
                                                        onclick="return confirm('Reject {{ $application->full_name }}?')">
                                                    <i class="fas fa-times mr-1"></i>Reject
                                                </button>
                                            @endif
                                        @endif

                                        @if($application->resume_data ?? false)
                                            <button wire:click="downloadResume('{{ $application->application_id }}')" 
                                                    class="px-3 py-1 text-xs bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
                                                <i class="fas fa-download mr-1"></i>Resume
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
                                    <i class="fas fa-file-alt text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No applications found</p>
                                    <p class="text-sm mt-1">Try adjusting your filters or check back later.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Current Employees Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Current Employees</h2>
                <p class="text-sm text-gray-600">Manage employee roles and information</p>
            </div>
            <div class="text-sm text-gray-600">
                {{ count($employees) }} Employees
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Job Title</th>
                        <th>Hire Date</th>
                        <th>Current Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($employees) > 0)
                        @foreach($employees as $employee)
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                    'terminated' => 'bg-red-100 text-red-800',
                                    'on_leave' => 'bg-blue-100 text-blue-800',
                                ];

                                $roleColors = [
                                    'admin' => 'bg-red-100 text-red-800',
                                    'manager' => 'bg-orange-100 text-orange-800',
                                    'employee' => 'bg-green-100 text-green-800',
                                    'customer' => 'bg-blue-100 text-blue-800',
                                    'supplier' => 'bg-purple-100 text-purple-800',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-hr-100 flex items-center justify-center mr-3">
                                            <i class="fas fa-user-tie text-hr-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $employee->full_name ?? 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ $employee->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center">
                                        @if($employee->department_name)
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $employee->department_name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">No Department</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="font-medium">{{ $employee->job_title ?? 'N/A' }}</td>
                                <td>{{ date('M d, Y', strtotime($employee->hire_date ?? now())) }}</td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $roleColors[$employee->role] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($employee->role) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$employee->emp_status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $employee->emp_status)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <button wire:click="openDepartmentModal('{{ $employee->employee_id }}')" 
                                                class="px-3 py-1 text-xs bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">
                                            <i class="fas fa-building mr-1"></i>Department
                                        </button>
                                        <button wire:click="openRoleChangeModal('{{ $employee->user_id }}')" 
                                                class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                            <i class="fas fa-user-cog mr-1"></i>Role
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No employees found</p>
                                    <p class="text-sm mt-1">Hire applicants to add employees.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Application Details Modal -->
    @if($showApplicationModal && $selectedApplication)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showApplicationModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Application Details</h3>
                            <p class="text-sm text-gray-500">#{{ $selectedApplication->application_id ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showApplicationModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Applicant Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Applicant Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Full Name</label>
                                    <p class="font-medium">{{ $selectedApplication->full_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Username</label>
                                    <p class="font-medium">{{ $selectedApplication->username ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Email</label>
                                    <p class="font-medium">{{ $selectedApplication->email ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Current Role</label>
                                    <p class="font-medium">{{ ucfirst($selectedApplication->role ?? 'N/A') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Application Information -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Application Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">Position Applied</label>
                                    <p class="font-medium">{{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Years of Experience</label>
                                    <p class="font-medium">{{ $selectedApplication->years_experience ?? 0 }} years</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Application Date</label>
                                    <p class="font-medium">{{ date('M d, Y', strtotime($selectedApplication->application_date ?? now())) }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Status</label>
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'reviewed' => 'bg-blue-100 text-blue-800',
                                            'shortlisted' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'hired' => 'bg-teal-100 text-teal-800',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$selectedApplication->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($selectedApplication->status ?? 'pending') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Interview Information (if exists) -->
                        @if($selectedApplication->interview_date ?? false)
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Interview Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500">Interview Date & Time</label>
                                    <p class="font-medium">{{ date('M d, Y h:i A', strtotime($selectedApplication->interview_date)) }}</p>
                                    @if($selectedApplication->interview_status === 'completed')
                                        <span class="text-xs text-green-600">
                                            <i class="fas fa-check-circle mr-1"></i>Completed
                                        </span>
                                    @endif
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Interviewer</label>
                                    <p class="font-medium">{{ $selectedApplication->interviewer_name ?? 'Not assigned' }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Interview Type</label>
                                    <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $selectedApplication->interview_type ?? '')) }}</p>
                                </div>
                                @if($selectedApplication->interview_notes ?? false)
                                <div class="md:col-span-3">
                                    <label class="text-xs text-gray-500">Interview Notes</label>
                                    <div class="p-3 bg-gray-50 rounded-lg mt-1">
                                        {{ $selectedApplication->interview_notes }}
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Notes -->
                        @if($selectedApplication->notes ?? false)
                        <div class="md:col-span-2 border-t pt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Application Notes</h4>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                {{ $selectedApplication->notes }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <div class="flex gap-2">
                        <button wire:click="$set('showApplicationModal', false)" 
                                class="btn-secondary">
                            Close
                        </button>
                        
                        @if(($selectedApplication->status ?? '') === 'pending')
                            <button wire:click="markAsReviewed('{{ $selectedApplication->application_id }}')" 
                                    class="btn-primary">
                                <i class="fas fa-check mr-2"></i>Mark as Reviewed
                            </button>
                        @endif
                        
                        @if(($selectedApplication->status ?? '') === 'reviewed' && $selectedApplication->interview_date && $selectedApplication->interview_status !== 'completed')
                            <button wire:click="markInterviewCompleted('{{ $selectedApplication->application_id }}')" 
                                    class="btn-indigo">
                                <i class="fas fa-clipboard-check mr-2"></i>Complete Interview
                            </button>
                        @endif
                        
                        @if(in_array($selectedApplication->status, ['shortlisted', 'reviewed']) && ($selectedApplication->interview_status === 'completed' || $selectedApplication->status === 'shortlisted'))
                            <button wire:click="updateApplicationStatus('{{ $selectedApplication->application_id }}', 'hired')" 
                                    class="btn-success"
                                    onclick="return confirm('Hire {{ $selectedApplication->full_name }} as {{ $selectedApplication->position_applied }}?')">
                                <i class="fas fa-user-tie mr-2"></i>Hire Applicant
                            </button>
                            <button wire:click="updateApplicationStatus('{{ $selectedApplication->application_id }}', 'rejected')" 
                                    class="btn-danger"
                                    onclick="return confirm('Reject {{ $selectedApplication->full_name }}?')">
                                <i class="fas fa-times mr-2"></i>Reject
                            </button>
                        @endif
                        
                        @if(($selectedApplication->status ?? '') === 'reviewed' && !$selectedApplication->interview_date)
                            <button wire:click="openInterviewModal('{{ $selectedApplication->application_id }}')" 
                                    class="btn-purple">
                                <i class="fas fa-calendar-alt mr-2"></i>Schedule Interview
                            </button>
                        @endif

                        <button wire:click="viewDocuments('{{ $selectedApplication->application_id }}')" 
                                class="btn-gray">
                            <i class="fas fa-file-alt mr-2"></i>View Documents
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Documents Modal -->
    @if($showDocumentsModal && $selectedApplication)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showDocumentsModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Application Documents</h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showDocumentsModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    @if(count($documents) > 0)
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($documents as $document)
                                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center">
                                                @php
                                                    $fileIcons = [
                                                        'pdf' => 'fas fa-file-pdf text-red-500',
                                                        'doc' => 'fas fa-file-word text-blue-500',
                                                        'docx' => 'fas fa-file-word text-blue-500',
                                                        'xls' => 'fas fa-file-excel text-green-500',
                                                        'xlsx' => 'fas fa-file-excel text-green-500',
                                                        'jpg' => 'fas fa-file-image text-purple-500',
                                                        'jpeg' => 'fas fa-file-image text-purple-500',
                                                        'png' => 'fas fa-file-image text-purple-500',
                                                    ];
                                                    $extension = pathinfo($document->filename, PATHINFO_EXTENSION);
                                                    $fileIcon = $fileIcons[strtolower($extension)] ?? 'fas fa-file text-gray-500';
                                                @endphp
                                                <i class="{{ $fileIcon }} text-2xl mr-3"></i>
                                                <div>
                                                    <div class="font-medium text-gray-900 truncate" title="{{ $document->filename }}">
                                                        {{ $document->filename }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $this->formatFileSize($document->filesize) }} • {{ $document->filetype }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-2">
                                            Uploaded: {{ date('M d, Y h:i A', strtotime($document->uploaded_at)) }}
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="downloadDocument('{{ $document->id }}')" 
                                                    class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 flex-1">
                                                <i class="fas fa-download mr-1"></i>Download
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-file-alt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-lg text-gray-500">No documents uploaded</p>
                            <p class="text-sm text-gray-400 mt-1">No documents have been uploaded for this application.</p>
                        </div>
                    @endif
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="$set('showDocumentsModal', false)" 
                            class="btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Interview Scheduling Modal -->
    @if($showInterviewModal && $selectedApplication)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showInterviewModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ $selectedApplication->interview_date ? 'Reschedule Interview' : 'Schedule Interview' }}
                            </h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showInterviewModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Interview Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Interview Date</label>
                                <input type="date" 
                                       wire:model="interviewDate" 
                                       min="{{ date('Y-m-d') }}"
                                       class="form-input">
                                @error('interviewDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="form-label">Interview Time</label>
                                <input type="time" 
                                       wire:model="interviewTime" 
                                       class="form-input">
                                @error('interviewTime') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <!-- Interviewer -->
                        <div>
                            <label class="form-label">Interviewer</label>
                            <select wire:model="interviewerId" class="form-input">
                                <option value="">Select Interviewer</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->user_id }}">
                                        {{ $user->full_name }} ({{ ucfirst($user->role) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('interviewerId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Interview Type -->
                        <div>
                            <label class="form-label">Interview Type</label>
                            <select wire:model="interviewType" class="form-input">
                                <option value="in_person">In Person</option>
                                <option value="video">Video Call</option>
                                <option value="phone">Phone Call</option>
                                <option value="technical">Technical Interview</option>
                                <option value="hr">HR Interview</option>
                            </select>
                        </div>
                        
                        <!-- Notes -->
                        <div>
                            <label class="form-label">Interview Notes (Optional)</label>
                            <textarea wire:model="interviewNotes" 
                                      rows="3"
                                      class="form-input"
                                      placeholder="Any special instructions or notes for the interview..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="saveInterviewSchedule" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-calendar-check mr-2"></i>
                        {{ $selectedApplication->interview_date ? 'Update Schedule' : 'Schedule Interview' }}
                    </button>
                    <button wire:click="$set('showInterviewModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Interview Result Modal -->
    @if($showInterviewResultModal && $selectedApplication)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showInterviewResultModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Interview Results</h3>
                            <p class="text-sm text-gray-500">{{ $selectedApplication->full_name ?? 'N/A' }} - {{ $selectedApplication->position_applied ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showInterviewResultModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Interview Result -->
                        <div>
                            <label class="form-label">Interview Result</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model="interviewResult" value="passed" class="form-radio text-green-600">
                                    <span class="ml-2 text-green-700">
                                        <i class="fas fa-check-circle mr-1"></i>Passed
                                    </span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model="interviewResult" value="failed" class="form-radio text-red-600">
                                    <span class="ml-2 text-red-700">
                                        <i class="fas fa-times-circle mr-1"></i>Failed
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Feedback -->
                        <div>
                            <label class="form-label">Interview Feedback</label>
                            <textarea wire:model="interviewFeedback" 
                                      rows="4"
                                      class="form-input"
                                      placeholder="Enter detailed feedback about the interview..."></textarea>
                            @error('interviewFeedback') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Information -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-700">
                                    <p><strong>Note:</strong></p>
                                    <ul class="mt-1 space-y-1">
                                        <li>• "Passed" will move applicant to Shortlisted</li>
                                        <li>• "Failed" will automatically reject the applicant</li>
                                        <li>• You can still hire or reject shortlisted applicants later</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="saveInterviewResult" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>
                        Save Results
                    </button>
                    <button wire:click="$set('showInterviewResultModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Department Change Modal -->
    @if($showDepartmentModal && $selectedEmployee)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showDepartmentModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Change Employee Department</h3>
                            <p class="text-sm text-gray-500">{{ $selectedEmployee->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showDepartmentModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Current Department</label>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                @if($selectedEmployee->department_name)
                                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        {{ $selectedEmployee->department_name }}
                                    </span>
                                @else
                                    <span class="text-gray-500">No department assigned</span>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">New Department</label>
                            <select wire:model="newDepartment" class="form-input">
                                <option value="">Select Department</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->department_id }}">
                                        {{ $department->department_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="updateDepartment" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Update Department
                    </button>
                    <button wire:click="$set('showDepartmentModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Role Change Modal -->
    @if($showRoleChangeModal && $selectedUserForRoleChange)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showRoleChangeModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Change User Role</h3>
                            <p class="text-sm text-gray-500">{{ $selectedUserForRoleChange->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showRoleChangeModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Current Role</label>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <span class="px-3 py-1 rounded-full text-sm font-medium 
                                    {{ $selectedUserForRoleChange->role === 'admin' ? 'bg-red-100 text-red-800' : 
                                       ($selectedUserForRoleChange->role === 'manager' ? 'bg-orange-100 text-orange-800' : 
                                       ($selectedUserForRoleChange->role === 'employee' ? 'bg-green-100 text-green-800' : 
                                       ($selectedUserForRoleChange->role === 'customer' ? 'bg-blue-100 text-blue-800' : 
                                       'bg-purple-100 text-purple-800'))) }}">
                                    {{ ucfirst($selectedUserForRoleChange->role ?? 'employee') }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">New Role</label>
                            <select wire:model="newRole" class="form-input">
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="employee">Employee</option>
                                <option value="customer">Customer</option>
                                <option value="supplier">Supplier</option>
                            </select>
                        </div>
                        
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="changeUserRole" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-hr-600 text-base font-medium text-white hover:bg-hr-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Change Role
                    </button>
                    <button wire:click="$set('showRoleChangeModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-hr-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        function openNewApplication() {
            alert('New application form would open here.');
        }
        
        function viewEmployeeDetails(employeeId) {
            alert('Employee details for ID: ' + employeeId);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                @this.set('showApplicationModal', false);
                @this.set('showDocumentsModal', false);
                @this.set('showInterviewModal', false);
                @this.set('showInterviewResultModal', false);
                @this.set('showDepartmentModal', false);
                @this.set('showRoleChangeModal', false);
            }
        });
    </script>
</div>