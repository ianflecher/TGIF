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
    public $debugInfo = '';

    public function mount()
    {
        $this->user = Auth::user();
        
        // Debug info
        $this->debugInfo = "User: " . ($this->user->username ?? 'Unknown');
        
        // In your database, the users table has 'user_id' not 'id'
        $userId = $this->user->user_id; // This is the correct column name
        
        // Get employee ID from employees table based on user_id
        $employee = DB::table('employees')
            ->where('user_id', $userId)
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

        // 1. Manager tasks: tasks submitted to this employee for projects they manage
        $managerProjectIds = DB::table('projects')
            ->where('project_manager_id', $this->employeeId)
            ->pluck('project_id');

        if ($managerProjectIds->isNotEmpty()) {
            // Get phase IDs for the manager's projects
            $managerPhaseIds = DB::table('project_phases')
                ->whereIn('project_id', $managerProjectIds)
                ->pluck('phase_id');

            if ($managerPhaseIds->isNotEmpty()) {
                $managerTasks = DB::table('tasks')
                    ->whereIn('phase_id', $managerPhaseIds)
                    ->where('status', 'submitted') // Only show submitted tasks for managers
                    ->get()
                    ->map(function ($task) {
                        $task->role_type = 'Manager';
                        return $task;
                    });

                $allTasks = $allTasks->merge($managerTasks);
            }
        }

        // 2. Employee tasks: tasks assigned to this employee
        $employeeTasks = DB::table('tasks')
            ->where('assigned_to', $this->employeeId)
            ->get()
            ->map(function ($task) {
                $task->role_type = 'Employee';
                return $task;
            });

        $allTasks = $allTasks->merge($employeeTasks);

        // Get project names for all tasks
        $this->tasks = $this->addProjectInfoToTasks($allTasks->sortBy('status')->values());
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
            ->select('project_phases.phase_id', 'projects.project_name', 'projects.project_id')
            ->get()
            ->keyBy('phase_id');

        // Add project info to each task
        return $tasks->map(function ($task) use ($phases) {
            if (isset($phases[$task->phase_id])) {
                $task->project_name = $phases[$task->phase_id]->project_name;
                $task->project_id = $phases[$task->phase_id]->project_id;
            } else {
                $task->project_name = 'Unknown Project';
                $task->project_id = null;
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

    if ($task->assigned_to != $this->employeeId) {
        return "You are not assigned to this task!";
    }

    $allowedStartStatuses = ['not_started','pending','assigned'];

if (!in_array(strtolower($task->status), $allowedStartStatuses)) {
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
        \Log::info('submitTask called', ['taskId' => $taskId]);
        
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        // Check if user is assigned to this task
        if ($task->assigned_to != $this->employeeId) {
            $this->dispatch('alert', type: 'error', message: 'You are not assigned to this task!');
            return;
        }

        $updated = DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'submitted',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        \Log::info('Task submitted', ['rows_affected' => $updated]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'success', message: 'Task submitted for review!');
    }

    public function approveTask($taskId)
    {
        \Log::info('approveTask called', ['taskId' => $taskId]);
        
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        // Verify user is manager for this task's project
        $isManager = DB::table('project_phases')
            ->join('projects', 'project_phases.project_id', '=', 'projects.project_id')
            ->where('project_phases.phase_id', $task->phase_id)
            ->where('projects.project_manager_id', $this->employeeId)
            ->exists();

        if (!$isManager) {
            $this->dispatch('alert', type: 'error', message: 'You are not authorized to approve this task!');
            return;
        }

        $updated = DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        \Log::info('Task approved', ['rows_affected' => $updated]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'success', message: 'Task approved!');
    }

    public function rejectTask($taskId)
    {
        \Log::info('rejectTask called', ['taskId' => $taskId]);
        
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        // Verify user is manager for this task's project
        $isManager = DB::table('project_phases')
            ->join('projects', 'project_phases.project_id', '=', 'projects.project_id')
            ->where('project_phases.phase_id', $task->phase_id)
            ->where('projects.project_manager_id', $this->employeeId)
            ->exists();

        if (!$isManager) {
            $this->dispatch('alert', type: 'error', message: 'You are not authorized to reject this task!');
            return;
        }

        $updated = DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'in_progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);

        \Log::info('Task rejected', ['rows_affected' => $updated]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'info', message: 'Task returned for revision.');
    }

    public function completeTask($taskId)
    {
        \Log::info('completeTask called', ['taskId' => $taskId]);
        
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        // Check if user is assigned to this task
        if ($task->assigned_to != $this->employeeId) {
            $this->dispatch('alert', type: 'error', message: 'You are not assigned to this task!');
            return;
        }

        $updated = DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        \Log::info('Task completed', ['rows_affected' => $updated]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'success', message: 'Task marked as complete!');
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

.task-card.manager {
    border-left-color: #8b5cf6;
}

.task-card.employee {
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

.status-in_progress {
    background-color: #dbeafe;
    color: #1e40af;
}

.status-submitted {
    background-color: #fef3c7;
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
            </div>
        </div>
    </div>

    <!-- Task Filters -->
    <div class="mb-6 flex flex-wrap gap-2">
        <button onclick="filterTasks('all')" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">
            All Tasks
        </button>
        <button onclick="filterTasks('employee')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition">
            My Tasks
        </button>
        <button onclick="filterTasks('manager')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition">
            Review Tasks
        </button>
    </div>


    <!-- Task Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($tasks as $task)
            <div wire:key="task-{{ $task->task_id }}-{{ $task->status }}" 
                 class="task-card {{ $task->role_type === 'Manager' ? 'manager' : 'employee' }} bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                
                <!-- Task Header -->
                <div class="p-4 border-b border-gray-100">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-gray-800 text-lg truncate">{{ $task->task_name }}</h3>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $task->role_type === 'Manager' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $task->role_type }}
                        </span>
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
                                <div class="progress-fill {{ $task->role_type === 'Manager' ? 'bg-purple-600' : 'bg-blue-600' }}" 
                                     style="width: {{ $task->progress_percentage ?? 0 }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 border-t border-gray-100">
                        @if ($task->role_type === 'Manager')
                            <!-- Manager Actions - Show only for submitted tasks -->
                            @if($task->status === 'submitted')
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" 
                                            wire:click="approveTask({{ $task->task_id }})" 
                                            class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span>Approve</span>
                                    </button>
                                    <button type="button" 
                                            wire:click="rejectTask({{ $task->task_id }})" 
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
                        @else
                            <!-- Employee Actions -->
                            @switch(strtolower($task->status))
    @case('not_started')
    @case('pending')
    @case('assigned')
        <button type="button" wire:click="startTask({{ $task->task_id }})"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
            Start Task
        </button>
        @break

    @case('in_progress')
        <div class="grid grid-cols-2 gap-2">
            <button type="button" onclick="submitTaskJS({{ $task->task_id }})"
                    class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                Submit
            </button>
            <button type="button" wire:click="completeTask({{ $task->task_id }})"
                    class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                Complete
            </button>
        </div>
        @break

    @case('submitted')
        <div class="text-center text-sm text-gray-500 py-2 font-medium">
            Awaiting confirmation
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
                            You don't have any tasks assigned to you at the moment. Tasks will appear here when they are assigned.
                        @endif
                    </p>
                </div>
            </div>
        @endforelse
    </div>
</div>

<script>
function startTaskJS(taskId) {
    // Call the Livewire component method
    @this.startTask(taskId).then((message) => {
        alert(message); // simple JS alert
        console.log("Start Task Message:", message);
    }).catch((err) => {
        alert("An error occurred!");
        console.error(err);
    });
}
function submitTaskJS(taskId) {
    // Call the Livewire submitTask method
    @this.submitTask(taskId).then((message) => {
        alert(message || 'Task submitted!'); // show alert
        console.log("Submit Task Message:", message);
    }).catch((err) => {
        alert("An error occurred while submitting the task!");
        console.error(err);
    });
}

</script>
