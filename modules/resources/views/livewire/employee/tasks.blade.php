<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public $tasks = [];
    public $employeeId;
    public $user;
    public $userRole;
    public $debugInfo = '';

    public function mount()
    {
        $this->user = Auth::user();
        $this->userRole = $this->user->role; // 'employee' or 'manager'
        
        // Debug info
        $this->debugInfo = "User: " . ($this->user->username ?? 'Unknown') . " | Role: " . $this->userRole;
        
        // Get employee ID from employees table based on user_id
        $employee = DB::table('employees')
            ->where('user_id', $this->user->user_id)
            ->first();
            
        $this->employeeId = $employee->employee_id ?? null;
        $this->debugInfo .= " | Employee ID: " . ($this->employeeId ?? 'Not found');

        if (!$this->employeeId) {
            $this->tasks = [];
            return;
        }

        $this->loadTasks();
    }

    private function loadTasks()
{
    $allTasks = collect();

    // Check if user is a PROJECT MANAGER (manages any projects) - REGARDLESS OF ROLE
    $managedProjectIds = DB::table('projects')
        ->where('project_manager_id', $this->employeeId)
        ->pluck('project_id');

    // Always load tasks for review if user manages projects
    if ($managedProjectIds->isNotEmpty()) {
        // Get phase IDs for the managed projects
        $managedPhaseIds = DB::table('project_phases')
            ->whereIn('project_id', $managedProjectIds)
            ->pluck('phase_id');

        if ($managedPhaseIds->isNotEmpty()) {
            $managerTasks = DB::table('tasks')
                ->whereIn('phase_id', $managedPhaseIds)
                ->where('status', 'submitted') // Only show submitted tasks for review
                ->get()
                ->filter(function ($task) {
                    // Check if current employee is NOT in the comma-separated assigned_to list
                    // (Project managers shouldn't review tasks they're assigned to)
                    $assignedToArray = explode(',', $task->assigned_to);
                    $assignedToArray = array_map('trim', $assignedToArray);
                    return !in_array((string)$this->employeeId, $assignedToArray);
                })
                ->map(function ($task) {
                    $task->role_type = 'manager_review';
                    return $task;
                });

            $allTasks = $allTasks->merge($managerTasks);
        }
    }

    // Always load tasks assigned to this employee (for everyone)
    $assignedTasks = DB::table('tasks')
        ->get()
        ->filter(function ($task) {
            // Check if current employee is in the comma-separated assigned_to list
            $assignedToArray = explode(',', $task->assigned_to);
            $assignedToArray = array_map('trim', $assignedToArray);
            return in_array((string)$this->employeeId, $assignedToArray);
        })
        ->map(function ($task) {
            $task->role_type = 'assigned';
            return $task;
        });

    $allTasks = $allTasks->merge($assignedTasks);

    // Get project names for all tasks
    $this->tasks = $this->addProjectInfoToTasks($allTasks->sortBy('status')->values());
    

    foreach ($this->tasks as $task) {
        \Log::info("Task ID: {$task->task_id}, Name: {$task->task_name}, Role Type: {$task->role_type}, Status: {$task->status}");
    }
    \Log::info("================================");
}

    private function addProjectInfoToTasks($tasks)
    {
        // Get all unique phase IDs from tasks
        $phaseIds = $tasks->pluck('phase_id')->filter()->unique();
        
        if ($phaseIds->isEmpty()) {
            return $tasks;
        }

        // Get phases with their projects
        $phases = DB::table('project_phases')
            ->join('projects', 'project_phases.project_id', '=', 'projects.project_id')
            ->whereIn('project_phases.phase_id', $phaseIds)
            ->select('project_phases.phase_id', 'projects.project_name', 'projects.project_id', 'projects.project_manager_id')
            ->get()
            ->keyBy('phase_id');

        // Add project info to each task
        return $tasks->map(function ($task) use ($phases) {
            if (isset($phases[$task->phase_id])) {
                $task->project_name = $phases[$task->phase_id]->project_name;
                $task->project_id = $phases[$task->phase_id]->project_id;
                $task->project_manager_id = $phases[$task->phase_id]->project_manager_id;
                
                // Check if current user is the manager of this project
                $task->is_project_manager = ($task->project_manager_id == $this->employeeId);
            } else {
                $task->project_name = 'Unknown Project';
                $task->project_id = null;
                $task->project_manager_id = null;
                $task->is_project_manager = false;
            }
            
            // For manager review tasks, get assignee info
            if ($task->role_type === 'manager_review' && $task->assigned_to) {
                // Get first assignee for display
                $assignedToArray = explode(',', $task->assigned_to);
                $firstAssigneeId = trim($assignedToArray[0] ?? '');
                
                $assignee = DB::table('employees')
                    ->join('users', 'employees.user_id', '=', 'users.user_id')
                    ->where('employees.employee_id', $firstAssigneeId)
                    ->select('users.full_name')
                    ->first();
                
                $task->assigned_to_name = $assignee->full_name ?? 'Unknown';
                
                // Also show total assignee count
                $task->assignee_count = count($assignedToArray);
            }
            
            return $task;
        });
    }

    public function startTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) {
            return "Task not found!";
        }

        // Check if user is assigned to this task (comma-separated list)
        $assignedToArray = explode(',', $task->assigned_to);
        $assignedToArray = array_map('trim', $assignedToArray);
        
        if (!in_array((string)$this->employeeId, $assignedToArray)) {
            return "You are not assigned to this task!";
        }

        $allowedStartStatuses = ['not_started','pending','assigned'];
        $currentStatus = strtolower(trim($task->status));

        if (!in_array($currentStatus, $allowedStartStatuses)) {
            return "Cannot start task. Current status: {$task->status}";
        }

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'in_progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        return "Task started successfully!";
    }

    public function submitTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) {
            return "Task not found!";
        }

        // Check if user is assigned to this task (comma-separated list)
        $assignedToArray = explode(',', $task->assigned_to);
        $assignedToArray = array_map('trim', $assignedToArray);
        
        if (!in_array((string)$this->employeeId, $assignedToArray)) {
            return "You are not assigned to this task!";
        }

        // Only allow submission if task is in progress
        if ($task->status !== 'in_progress') {
            return "Task must be in progress to submit!";
        }

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'submitted',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        return "Task submitted for review!";
    }

    public function approveTask($taskId)
{
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) {
        return "Task not found!";
    }

    // Verify user is the project manager for this task's project
    $isProjectManager = DB::table('project_phases')
        ->join('projects', 'project_phases.project_id', '=', 'projects.project_id')
        ->where('project_phases.phase_id', $task->phase_id)
        ->where('projects.project_manager_id', $this->employeeId)
        ->exists();

    if (!$isProjectManager) {
        return "You are not the project manager for this task!";
    }

    // Check if task is in submitted status
    if ($task->status !== 'submitted') {
        return "Task must be submitted for review!";
    }

    DB::table('tasks')->where('task_id', $taskId)->update([
        'status' => 'completed',
        'updated_at' => now(),
    ]);

    $this->loadTasks();
    return "Task approved!";
}

public function rejectTask($taskId)
{
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) {
        return "Task not found!";
    }

    // Verify user is the project manager for this task's project
    $isProjectManager = DB::table('project_phases')
        ->join('projects', 'project_phases.project_id', '=', 'projects.project_id')
        ->where('project_phases.phase_id', $task->phase_id)
        ->where('projects.project_manager_id', $this->employeeId)
        ->exists();

    if (!$isProjectManager) {
        return "You are not the project manager for this task!";
    }

    // Check if task is in submitted status
    if ($task->status !== 'submitted') {
        return "Task must be submitted for review!";
    }

    DB::table('tasks')->where('task_id', $taskId)->update([
        'status' => 'in_progress',
        'progress_percentage' => 50,
        'updated_at' => now(),
    ]);

    $this->loadTasks();
    return "Task returned for revision.";
}

    public function completeTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) {
            return "Task not found!";
        }

        // Check if user is assigned to this task (comma-separated list)
        $assignedToArray = explode(',', $task->assigned_to);
        $assignedToArray = array_map('trim', $assignedToArray);
        
        if (!in_array((string)$this->employeeId, $assignedToArray)) {
            return "You are not assigned to this task!";
        }

        // Only allow completion if task is in progress
        if ($task->status !== 'in_progress') {
            return "Task must be in progress to complete!";
        }

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        return "Task marked as complete!";
    }
};
?>

<style>
.task-card {
    transition: all 0.3s ease;
    border-left: 4px solid;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

.task-card.manager_review {
    border-left-color: #8b5cf6;
}

.task-card.assigned {
    border-left-color: #3b82f6;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-not_started {
    background-color: #f3f4f6;
    color: #6b7280;
}

.status-pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-assigned {
    background-color: #dbeafe;
    color: #1e40af;
}

.status-in_progress {
    background-color: #93c5fd;
    color: #1e3a8a;
}

.status-submitted {
    background-color: #fde68a;
    color: #92400e;
}

.status-completed {
    background-color: #d1fae5;
    color: #065f46;
}

.progress-bar {
    height: 0.5rem;
    border-radius: 9999px;
    overflow: hidden;
    background-color: #e5e7eb;
}

.progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.5s ease;
}

.assignee-badge {
    background-color: #f3f4f6;
    color: #4b5563;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}
</style>

<div class="min-h-screen bg-gradient-to-b from-gray-50 to-blue-50 p-4 sm:p-6">

    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-4">
                <a href="{{ route('employee.dashboard') }}" class="flex items-center space-x-2 text-blue-700 hover:text-blue-800 transition-colors duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span class="font-semibold">Back to Dashboard</span>
                </a>
                <div class="h-6 w-px bg-gray-300 hidden sm:block"></div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">My Tasks</h1>
            </div>
            
            <!-- Task Stats -->
            <div class="flex space-x-3">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3 text-center min-w-[100px]">
                    <div class="text-xl sm:text-2xl font-bold text-blue-600">{{ count($tasks) }}</div>
                    <div class="text-xs sm:text-sm text-gray-600">Total Tasks</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3 text-center min-w-[100px]">
                    <div class="text-xl sm:text-2xl font-bold text-green-600">
                        {{ collect($tasks)->where('status', 'completed')->count() }}
                    </div>
                    <div class="text-xs sm:text-sm text-gray-600">Completed</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3 text-center min-w-[100px]">
                    <div class="text-xl sm:text-2xl font-bold text-purple-600">
                        {{ collect($tasks)->where('role_type', 'manager_review')->count() }}
                    </div>
                    <div class="text-xs sm:text-sm text-gray-600">To Review</div>
                </div>
            </div>
        </div>
        
        <!-- User Info -->
        <div class="mt-4 flex flex-wrap items-center gap-4">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                    <span class="text-blue-600 font-semibold">{{ substr($user->username ?? 'U', 0, 1) }}</span>
                </div>
                <div>
                    <p class="font-medium text-gray-800">{{ $user->full_name ?? 'User' }}</p>
                    <p class="text-sm text-gray-600 capitalize">{{ $userRole ?? 'employee' }}</p>
                </div>
            </div>
            
            @if($userRole === 'manager')
            <div class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                Manager Access
            </div>
            @endif
            
 
        </div>
    </div>

    <!-- Task Filters -->
    <div class="mb-6 flex flex-wrap gap-2">
        <button onclick="filterTasks('all')" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
            All Tasks
        </button>
        <button onclick="filterTasks('assigned')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition">
            My Assigned Tasks
        </button>
        @if($userRole === 'manager')
        <button onclick="filterTasks('manager_review')" class="px-4 py-2 bg-purple-200 text-purple-700 rounded-lg font-medium hover:bg-purple-300 transition">
            Tasks to Review
        </button>
        @endif
        <button onclick="filterTasks('in_progress')" class="px-4 py-2 bg-blue-200 text-blue-700 rounded-lg font-medium hover:bg-blue-300 transition">
            In Progress
        </button>
        <button onclick="filterTasks('submitted')" class="px-4 py-2 bg-yellow-200 text-yellow-700 rounded-lg font-medium hover:bg-yellow-300 transition">
            Submitted
        </button>
        <button onclick="filterTasks('completed')" class="px-4 py-2 bg-green-200 text-green-700 rounded-lg font-medium hover:bg-green-300 transition">
            Completed
        </button>
    </div>

    <!-- Task Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="taskContainer">
        @forelse ($tasks as $task)
            <div wire:key="task-{{ $task->task_id }}-{{ $task->status }}" 
                 class="task-card {{ $task->role_type === 'manager_review' ? 'manager_review' : 'assigned' }} bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                 data-task-type="{{ $task->role_type }}"
                 data-task-status="{{ strtolower($task->status) }}">
                
                <!-- Task Header -->
                <div class="p-4 border-b border-gray-100">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-gray-800 text-lg truncate">{{ $task->task_name }}</h3>
                        <div class="flex flex-col items-end space-y-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                {{ $task->role_type === 'manager_review' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ $task->role_type === 'manager_review' ? 'Review' : 'My Task' }}
                            </span>
                            @if(isset($task->assignee_count) && $task->assignee_count > 1)
                                <span class="assignee-badge">
                                    {{ $task->assignee_count }} assignees
                                </span>
                            @endif
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm line-clamp-2">{{ $task->description }}</p>
                    @if(isset($task->project_name))
                        <div class="mt-2 flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>{{ $task->project_name }}</span>
                        </div>
                    @endif
                </div>

                <!-- Task Details -->
                <div class="p-4 space-y-4">
                    <!-- Timeline -->
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ \Carbon\Carbon::parse($task->start_date)->format('M d') }}</span>
                        <span class="mx-2">→</span>
                        <span>{{ \Carbon\Carbon::parse($task->end_date)->format('M d, Y') }}</span>
                    </div>

                    <!-- Status & Progress -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Status</span>
                            <span class="status-badge status-{{ strtolower(str_replace(' ', '_', $task->status)) }}">
                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                            </span>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div>
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Progress</span>
                                <span>{{ $task->progress_percentage ?? 0 }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill {{ $task->role_type === 'manager_review' ? 'bg-purple-600' : 'bg-blue-600' }}" 
                                     style="width: {{ $task->progress_percentage ?? 0 }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 border-t border-gray-100">
                        @if ($task->role_type === 'manager_review')
                            <!-- Manager Review Actions - Only for submitted tasks -->
                            @if(strtolower($task->status) === 'submitted')
                                <div class="mb-3">
                                    <p class="text-sm text-gray-600 mb-1">
                                        <span class="font-semibold">Submitted by:</span> {{ $task->assigned_to_name ?? 'Employee' }}
                                    </p>
                                    @if(isset($task->assignee_count) && $task->assignee_count > 1)
                                        <p class="text-xs text-gray-500">
                                            + {{ $task->assignee_count - 1 }} more assignee(s)
                                        </p>
                                    @endif
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" 
                                            onclick="approveTaskJS({{ $task->task_id }})" 
                                            class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span>Approve</span>
                                    </button>
                                    <button type="button" 
                                            onclick="rejectTaskJS({{ $task->task_id }})" 
                                            class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <span>Reject</span>
                                    </button>
                                </div>
                            @else
                                <div class="text-center text-sm text-gray-500 py-2">
                                    Awaiting submission from employee
                                </div>
                            @endif
                        @elseif ($task->role_type === 'assigned')
                            <!-- Employee Actions for Assigned Tasks -->
                            @switch(strtolower($task->status))
                                @case('not_started')
                                @case('pending')
                                @case('assigned')
                                    <button type="button" 
                                            onclick="startTaskJS({{ $task->task_id }})" 
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                                        Start Task
                                    </button>
                                    @break

                                @case('in_progress')
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" 
                                                onclick="submitTaskJS({{ $task->task_id }})" 
                                                class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                                            Submit for Review
                                        </button>
                                            <!-- <button type="button" 
                                                    onclick="completeTaskJS({{ $task->task_id }})" 
                                                    class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                                                Mark Complete
                                            </button> -->
                                    </div>
                                    @break

                                @case('submitted')
                                    <div class="text-center">
                                        <div class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Awaiting Manager Review
                                        </div>
                                    </div>
                                    @break
                                    
                                @case('completed')
                                    <div class="text-center">
                                        <div class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Completed ✓
                                        </div>
                                    </div>
                                    @break
                            @endswitch
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="col-span-full">
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        @if(!$employeeId)
                            No employee record found. Please contact administrator.
                        @else
                            @if($userRole === 'manager')
                                You don't have any tasks to review at the moment. Tasks will appear here when employees submit them for review.
                            @else
                                You don't have any tasks assigned to you at the moment. Tasks will appear here when they are assigned.
                            @endif
                        @endif
                    </p>

\
                </div>
            </div>
        @endforelse
    </div>
</div>
<script>
// Task filtering functionality
function filterTasks(filterType) {
    const taskCards = document.querySelectorAll('#taskContainer .task-card');
    
    taskCards.forEach(card => {
        switch(filterType) {
            case 'all':
                card.style.display = 'block';
                break;
            case 'assigned':
                if (card.dataset.taskType === 'assigned') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                break;
            case 'manager_review':
                if (card.dataset.taskType === 'manager_review') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                break;
            case 'in_progress':
                if (card.dataset.taskStatus === 'in_progress') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                break;
            case 'submitted':
                if (card.dataset.taskStatus === 'submitted') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                break;
            case 'completed':
                if (card.dataset.taskStatus === 'completed') {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
                break;
            default:
                card.style.display = 'block';
        }
    });
    
    // Update active filter button styles
    document.querySelectorAll('button').forEach(btn => {
        if (btn.textContent.toLowerCase().includes(filterType) || 
            (filterType === 'all' && btn.textContent.toLowerCase().includes('all'))) {
            btn.classList.remove('bg-gray-200', 'text-gray-700', 'bg-blue-200', 'bg-purple-200', 'bg-yellow-200', 'bg-green-200');
            btn.classList.add('bg-blue-600', 'text-white');
        } else {
            if (btn.textContent.toLowerCase().includes('review')) {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-purple-200', 'text-purple-700');
            } else if (btn.textContent.toLowerCase().includes('submitted')) {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-yellow-200', 'text-yellow-700');
            } else if (btn.textContent.toLowerCase().includes('completed')) {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-green-200', 'text-green-700');
            } else if (btn.textContent.toLowerCase().includes('progress')) {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-blue-200', 'text-blue-700');
            } else {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            }
        }
    });
}

// JavaScript functions to call Livewire methods
function startTaskJS(taskId) {
    if (confirm('Start working on this task?')) {
        @this.startTask(taskId).then((message) => {
            alert(message);
            // Reload the page after success
            setTimeout(() => {
                location.reload();
            }, 500);
        }).catch((err) => {
            alert("An error occurred!");
            console.error(err);
        });
    }
}

function submitTaskJS(taskId) {
    if (confirm('Submit this task for manager review?')) {
        @this.submitTask(taskId).then((message) => {
            alert(message);
            // Reload the page after success
            setTimeout(() => {
                location.reload();
            }, 500);
        }).catch((err) => {
            alert("An error occurred!");
            console.error(err);
        });
    }
}

function completeTaskJS(taskId) {
    if (confirm('Mark this task as complete?')) {
        @this.completeTask(taskId).then((message) => {
            alert(message);
            // Reload the page after success
            setTimeout(() => {
                location.reload();
            }, 500);
        }).catch((err) => {
            alert("An error occurred!");
            console.error(err);
        });
    }
}

function approveTaskJS(taskId) {
    if (confirm('Approve this task?')) {
        @this.approveTask(taskId).then((message) => {
            alert(message);
            // Reload the page after success
            setTimeout(() => {
                location.reload();
            }, 500);
        }).catch((err) => {
            alert("An error occurred!");
            console.error(err);
        });
    }
}

function rejectTaskJS(taskId) {
    if (confirm('Reject this task and return to employee?')) {
        @this.rejectTask(taskId).then((message) => {
            alert(message);
            // Reload the page after success
            setTimeout(() => {
                location.reload();
            }, 500);
        }).catch((err) => {
            alert("An error occurred!");
            console.error(err);
        });
    }
}
</script>