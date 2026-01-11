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
    public $showNewDepartmentModal = false;
    public $showDocumentsModal = false;
    
    // Salary management
    public $showSalaryModal = false;
    public $selectedEmployeeForSalary = null;
    public $newSalary = '';
    
    public $selectedUserForRoleChange = null;
    public $newRole = 'employee';
    public $newDepartment = '';
    public $newDepartmentName = '';
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
    $this->employees = DB::table('users as u')
        ->select(
            'u.user_id',
            'u.full_name',
            'u.username',
            'u.email',
            'u.role',
            'e.employee_id',
            'e.job_title',
            'e.hire_date',
            'e.salary',
            'e.status as emp_status',
            'e.department_id',
            'd.department_name'
        )
        ->join('employees as e', 'u.user_id', '=', 'e.user_id') // INNER JOIN
        ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
        ->where('e.status', 'active') // ✅ ONLY ACTIVE
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
            // Update user role to employee
            DB::table('users')
                ->where('user_id', $application->user_id)
                ->update(['role' => 'employee']);

            // Check if employee record already exists
            $existingEmployee = DB::table('employees')
                ->where('user_id', $application->user_id)
                ->first();
            
            if (!$existingEmployee) {
                // Create employee record for new hire with status 'active'
                DB::table('employees')->insert([
                    'user_id' => $application->user_id,
                    'job_title' => $application->position_applied,
                    'hire_date' => date('Y-m-d'),
                    'salary' => 0.00,
                    'status' => 'active', // Explicitly set status to 'active'
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Update existing employee record for re-hire
                DB::table('employees')
                    ->where('user_id', $application->user_id)
                    ->update([
                        'job_title' => $application->position_applied,
                        'hire_date' => date('Y-m-d'),
                        'status' => 'active', // Ensure status is set to 'active'
                        'updated_at' => now()
                    ]);
            }
            
            // Also update application notes to reflect hiring
            $currentNotes = $application->notes ?? '';
            $newNotes = $currentNotes . "\n\n--- HIRED ---\n";
            $newNotes .= "Hired as: " . $application->position_applied . "\n";
            $newNotes .= "Hire date: " . date('Y-m-d') . "\n";
            $newNotes .= "Employee record " . ($existingEmployee ? 'updated' : 'created');
            
            DB::table('job_applications')
                ->where('application_id', $applicationId)
                ->update(['notes' => $newNotes]);
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

    // SALARY MANAGEMENT METHODS
    public function openSalaryModal($employeeId)
    {
        $this->selectedEmployeeForSalary = DB::table('employees as e')
            ->select('e.*', 'u.full_name', 'u.email', 'd.department_name')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.employee_id', $employeeId)
            ->first();
        
        $this->newSalary = $this->selectedEmployeeForSalary->salary ?? 0;
        
        $this->showSalaryModal = true;
    }

    public function updateSalary()
    {
        $this->validate([
            'newSalary' => 'required|numeric|min:0|max:9999999.99'
        ]);

        $oldSalary = $this->selectedEmployeeForSalary->salary ?? 0;
        
        DB::table('employees')
            ->where('employee_id', $this->selectedEmployeeForSalary->employee_id)
            ->update([
                'salary' => $this->newSalary,
                'updated_at' => now()
            ]);

        // Log salary change
        DB::table('audit_logs')->insert([
            'action' => 'update',
            'table_name' => 'employees',
            'record_id' => $this->selectedEmployeeForSalary->employee_id,
            'old_values' => json_encode(['salary' => $oldSalary]),
            'new_values' => json_encode(['salary' => $this->newSalary]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showSalaryModal = false;
        $this->loadData();
        session()->flash('success', 'Salary updated successfully!');
    }

    public function openNewDepartmentModal()
    {
        $this->newDepartmentName = '';
        $this->showNewDepartmentModal = true;
    }

    public function createNewDepartment()
    {
        $this->validate([
            'newDepartmentName' => 'required|string|min:3|max:100|unique:departments,department_name'
        ]);

        DB::table('departments')->insert([
            'department_name' => $this->newDepartmentName,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Log the action
        DB::table('audit_logs')->insert([
            'action' => 'create',
            'table_name' => 'departments',
            'record_id' => DB::getPdo()->lastInsertId(),
            'old_values' => json_encode([]),
            'new_values' => json_encode(['department_name' => $this->newDepartmentName]),
            'user_id' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->showNewDepartmentModal = false;
        $this->newDepartmentName = '';
        $this->loadData();
        session()->flash('success', 'Department created successfully!');
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

        // Only create employee record when changing TO employee role
        if ($oldRole !== 'employee' && $this->newRole === 'employee') {
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

        // Log the change
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

    public function deleteDepartment($departmentId)
    {
        if (confirm('Are you sure you want to delete this department? This will remove the department assignment from all employees.')) {
            // First, remove department from all employees
            DB::table('employees')
                ->where('department_id', $departmentId)
                ->update(['department_id' => null]);
            
            // Then delete the department
            DB::table('departments')->where('department_id', $departmentId)->delete();
            
            $this->loadData();
            session()->flash('success', 'Department deleted successfully!');
        }
    }

    public function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-hr-900">Human Resources Management</h1>
            <p class="text-gray-600 mt-1">Manage applications, employees, salaries and departments</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                {{ $stats['total'] ?? 0 }} Total Applications
            </span>
            <button wire:click="openNewDepartmentModal" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>New Department
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

    <!-- Department List Section -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Departments</h2>
                <p class="text-sm text-gray-600">Manage company departments</p>
            </div>
            <div class="text-sm text-gray-600">
                {{ count($departments) }} Departments
            </div>
        </div>
        
        @if(count($departments) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-6">
                @foreach($departments as $department)
                    @php
                        // Count employees in this department
                        $employeeCount = DB::table('employees')
                            ->where('department_id', $department->department_id)
                            ->count();
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-medium text-gray-900">{{ $department->department_name }}</h3>
                            <button wire:click="deleteDepartment('{{ $department->department_id }}')" 
                                    onclick="return confirm('Delete {{ $department->department_name }} department?')"
                                    class="text-red-400 hover:text-red-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <i class="fas fa-users mr-2"></i>
                            <span>{{ $employeeCount }} {{ Str::plural('employee', $employeeCount) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8">
                <i class="fas fa-building text-4xl text-gray-300 mb-3"></i>
                <p class="text-lg text-gray-500">No departments created yet</p>
                <p class="text-sm text-gray-400 mt-1">Create your first department using the button above.</p>
            </div>
        @endif
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

    <!-- Current Employees Section with Salary -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Active Employees (All Roles)</h2>
                <p class="text-sm text-gray-600">Manage employees across all roles and departments</p>
            </div>
            <div class="text-sm text-gray-600">
                {{ count($employees) }} Active Employees
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role Information</th>
                        <th>Hire Date</th>
                        <th>Current Role</th>
                        <th>Salary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($employees) > 0)
                        @php
                            // Group employees by role for better organization
                            $groupedEmployees = $employees->groupBy('role');
                        @endphp
                        
                        @foreach($groupedEmployees as $role => $employeesByRole)
                            <!-- Role Header -->
                            <tr class="bg-gray-50">
                                <td colspan="6" class="px-4 py-3">
                                    <div class="flex items-center">
                                        @php
                                            $roleColors = [
                                                'admin' => 'text-red-700 bg-red-50',
                                                'manager' => 'text-orange-700 bg-orange-50',
                                                'hr' => 'text-purple-700 bg-purple-50',
                                                'employee' => 'text-green-700 bg-green-50',
                                                'customer' => 'text-blue-700 bg-blue-50',
                                                'supplier' => 'text-indigo-700 bg-indigo-50',
                                            ];
                                        @endphp
                                        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $roleColors[$role] ?? 'bg-gray-100 text-gray-800' }}">
                                            <i class="fas fa-user-shield mr-2"></i>
                                            {{ ucfirst($role) }}s ({{ count($employeesByRole) }})
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            
                            @foreach($employeesByRole as $employee)
                                @php
                                    $roleColors = [
                                        'admin' => 'bg-red-100 text-red-800',
                                        'manager' => 'bg-orange-100 text-orange-800',
                                        'hr' => 'bg-purple-100 text-purple-800',
                                        'employee' => 'bg-green-100 text-green-800',
                                        'customer' => 'bg-blue-100 text-blue-800',
                                        'supplier' => 'bg-indigo-100 text-indigo-800',
                                    ];
                                    
                                    $isSystemRole = in_array($employee->role, ['admin', 'manager', 'hr']);
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
                                        @if($isSystemRole)
                                            <!-- System Role Information -->
                                            <div class="flex flex-col space-y-1">
                                                @if($employee->role === 'admin')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-shield-alt mr-1"></i>Full System Access
                                                    </span>
                                                @elseif($employee->role === 'manager')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                        <i class="fas fa-tasks mr-1"></i>Management Access
                                                    </span>
                                                @elseif($employee->role === 'hr')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <i class="fas fa-users-cog mr-1"></i>HR System Access
                                                    </span>
                                                @endif
                                                <div class="text-xs text-gray-500">
                                                    @if($employee->department_name)
                                                        <div class="flex items-center mt-1">
                                                            <i class="fas fa-building mr-1"></i>
                                                            {{ $employee->department_name }}
                                                        </div>
                                                    @endif
                                                    @if($employee->job_title)
                                                        <div class="flex items-center mt-1">
                                                            <i class="fas fa-briefcase mr-1"></i>
                                                            {{ $employee->job_title }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <!-- Regular Employee Information -->
                                            <div class="flex flex-col space-y-1">
                                                @if($employee->job_title)
                                                    <span class="font-medium text-sm text-gray-900">
                                                        {{ $employee->job_title }}
                                                    </span>
                                                @endif
                                                @if($employee->department_name)
                                                    <div class="flex items-center">
                                                        <i class="fas fa-building text-gray-400 text-xs mr-1"></i>
                                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $employee->department_name }}
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400 text-xs">No Department</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ date('M d, Y', strtotime($employee->hire_date ?? now())) }}</td>
                                    <td>
                                        <div class="flex items-center">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $roleColors[$employee->role] ?? 'bg-gray-100 text-gray-800' }}">
                                                <i class="fas fa-user-tag mr-1"></i>
                                                {{ ucfirst($employee->role) }}
                                            </span>
                                            @if($employee->role !== 'employee')
                                                <span class="ml-2 text-xs text-gray-500">
                                                    (System Staff)
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="font-medium text-gray-900">
                                                ₱{{ number_format($employee->salary ?? 0, 2) }}
                                            </span>
                                            @if($employee->salary == 0)
                                                <span class="text-xs text-red-500">Not set</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            @if($employee->employee_id)
                                                <button wire:click="openSalaryModal('{{ $employee->employee_id }}')" 
                                                        class="px-3 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100">
                                                    <i class="fas fa-money-bill-wave mr-1"></i>Salary
                                                </button>
                                                
                                                @if($employee->role === 'employee' && $employee->employee_id)
                                                    <button wire:click="openDepartmentModal('{{ $employee->employee_id }}')" 
                                                            class="px-3 py-1 text-xs bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">
                                                        <i class="fas fa-building mr-1"></i>Department
                                                    </button>
                                                @endif
                                            @endif
                                            <button wire:click="openRoleChangeModal('{{ $employee->user_id }}')" 
                                                    class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
                                                <i class="fas fa-user-cog mr-1"></i>Role
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        
                        <!-- Summary Statistics -->
                        <tr class="bg-gray-50">
                            <td colspan="6" class="px-4 py-3">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="font-medium text-gray-700">
                                        <i class="fas fa-chart-pie mr-2"></i>Role Distribution
                                    </div>
                                    <div class="flex items-center gap-4">
                                        @foreach($groupedEmployees as $role => $employeesByRole)
                                            @php
                                                $roleColors = [
                                                    'admin' => 'text-red-600',
                                                    'manager' => 'text-orange-600',
                                                    'hr' => 'text-purple-600',
                                                    'employee' => 'text-green-600',
                                                    'customer' => 'text-blue-600',
                                                    'supplier' => 'text-indigo-600',
                                                ];
                                            @endphp
                                            <div class="flex items-center">
                                                <span class="w-3 h-3 rounded-full {{ str_replace('text', 'bg', $roleColors[$role] ?? 'bg-gray-500') }} mr-1"></span>
                                                <span class="{{ $roleColors[$role] ?? 'text-gray-600' }} font-medium">
                                                    {{ ucfirst($role) }}: {{ count($employeesByRole) }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-lg">No active employees found</p>
                                    <p class="text-sm mt-1">Hire applicants to add employees.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Salary Management Modal -->
    @if($showSalaryModal && $selectedEmployeeForSalary)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showSalaryModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Update Employee Salary</h3>
                            <p class="text-sm text-gray-500">{{ $selectedEmployeeForSalary->full_name ?? 'N/A' }}</p>
                        </div>
                        <button wire:click="$set('showSalaryModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Current Information -->
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-500">Position:</span>
                                    <p class="font-medium">{{ $selectedEmployeeForSalary->job_title ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Department:</span>
                                    <p class="font-medium">{{ $selectedEmployeeForSalary->department_name ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-gray-500">Current Salary:</span>
                                    <p class="font-medium text-lg text-green-600">
                                        ₱{{ number_format($selectedEmployeeForSalary->salary ?? 0, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Salary Input -->
                        <div>
                            <label class="form-label">New Monthly Salary</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">₱</span>
                                </div>
                                <input type="number" 
                                       wire:model="newSalary" 
                                       class="form-input pl-10"
                                       step="0.01"
                                       min="0"
                                       max="9999999.99"
                                       placeholder="0.00">
                            </div>
                            @error('newSalary') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Salary Information -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-700">
                                    <p><strong>Note:</strong></p>
                                    <ul class="mt-1 space-y-1">
                                        <li>• Salary is in Philippine Peso (₱)</li>
                                        <li>• This is the monthly gross salary</li>
                                        <li>• Deductions will be calculated separately</li>
                                        <li>• Changes take effect immediately</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="updateSalary" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-save mr-2"></i>
                        Update Salary
                    </button>
                    <button wire:click="$set('showSalaryModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- New Department Modal -->
    @if($showNewDepartmentModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 wire:click="$set('showNewDepartmentModal', false)"></div>
            
            <!-- Modal content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Create New Department</h3>
                            <p class="text-sm text-gray-500">Add a new department to the system</p>
                        </div>
                        <button wire:click="$set('showNewDepartmentModal', false)" 
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Department Name</label>
                            <input type="text" 
                                   wire:model="newDepartmentName" 
                                   class="form-input"
                                   placeholder="Enter department name (e.g., Marketing, Engineering, HR)">
                            @error('newDepartmentName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Information -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="flex">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-700">
                                    <p><strong>Note:</strong></p>
                                    <ul class="mt-1 space-y-1">
                                        <li>• Departments help organize employees by function</li>
                                        <li>• You can assign employees to departments later</li>
                                        <li>• Department names must be unique</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="createNewDepartment" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-plus mr-2"></i>
                        Create Department
                    </button>
                    <button wire:click="$set('showNewDepartmentModal', false)" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6">
                    <button wire:click="$set('showApplicationModal', false)" 
                            class="btn-secondary">
                        Close
                    </button>
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
                                       ($selectedUserForRoleChange->role === 'hr' ? 'bg-purple-100 text-purple-800' : 
                                       ($selectedUserForRoleChange->role === 'employee' ? 'bg-green-100 text-green-800' : 
                                       ($selectedUserForRoleChange->role === 'customer' ? 'bg-blue-100 text-blue-800' : 
                                       'bg-indigo-100 text-indigo-800')))) }}">
                                    {{ ucfirst($selectedUserForRoleChange->role ?? 'employee') }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label">New Role</label>
                            <select wire:model="newRole" class="form-input">
                                <option value="admin">Admin (Full System Access)</option>
                                <option value="manager">Manager (Department Management)</option>
                                <option value="hr">HR (Human Resources)</option>
                                <option value="employee">Employee (Regular Staff)</option>
                                <option value="customer">Customer (External Client)</option>
                                <option value="supplier">Supplier (Vendor/Supplier)</option>
                            </select>
                            
                            <!-- Role descriptions -->
                            <div class="mt-2 text-sm text-gray-600 space-y-1">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-red-600">Admin:</span>
                                    <span class="ml-1">Full system access and management</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-orange-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-orange-600">Manager:</span>
                                    <span class="ml-1">Team and project management</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-purple-600">HR:</span>
                                    <span class="ml-1">Human resources and recruitment</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    <span class="font-medium text-green-600">Employee:</span>
                                    <span class="ml-1">Regular staff with assigned tasks</span>
                                </div>
                            </div>
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
                @this.set('showNewDepartmentModal', false);
                @this.set('showDepartmentModal', false);
                @this.set('showRoleChangeModal', false);
                @this.set('showSalaryModal', false);
            }
        });
    </script>
</div>