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

    public function mount()
    {
        $this->user = Auth::user();
        
        // Get employee ID from users table (if you have employee_id column)
        // Or get from employees table based on user_id
        $employee = DB::table('employees')
            ->where('user_id', $this->user->id ?? $this->user->user_id)
            ->first();
            
        $this->employeeId = $employee->employee_id ?? null;

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
            $managerTasks = DB::table('project_tasks')
                ->whereIn('project_id', $managerProjectIds)
                ->where('status', 'submitted')
                ->select('*')
                ->get()
                ->map(function ($task) {
                    $task->role_type = 'Manager';
                    return $task;
                });

            $allTasks = $allTasks->merge($managerTasks);
        }

        // 2. Employee tasks: tasks assigned to this employee
        $employeeTasks = DB::table('project_tasks')
            ->where('assigned_to', $this->employeeId)
            ->select('*')
            ->get()
            ->map(function ($task) {
                $task->role_type = 'Employee';
                return $task;
            });

        $allTasks = $allTasks->merge($employeeTasks);

        // Also get HR tasks if applicable
        $hrTasks = DB::table('project_tasks')
            ->where('assigned_to', $this->employeeId)
            ->orWhere(function($query) use ($managerProjectIds) {
                if ($managerProjectIds->isNotEmpty()) {
                    $query->whereIn('project_id', $managerProjectIds)
                          ->where('status', 'submitted');
                }
            })
            ->get();

        $this->tasks = $allTasks->sortBy('status')->values();
    }

    public function startTask($taskId)
    {
        try {
            $task = DB::table('project_tasks')->where('task_id', $taskId)->first();

            if (!$task) {
                $this->dispatch('alert', type: 'error', message: 'Task not found!');
                return;
            }

            if ($task->assigned_to != $this->employeeId) {
                $this->dispatch('alert', type: 'error', message: 'You are not assigned to this task!');
                return;
            }

            $allowedStartStatuses = ['not_started', 'pending', 'assigned'];

            if (!in_array($task->status, $allowedStartStatuses)) {
                $this->dispatch('alert', type: 'error', message: "Cannot start task. Current status: {$task->status}");
                return;
            }

            DB::table('project_tasks')->where('task_id', $taskId)->update([
                'status' => 'in_progress',
                'progress' => 50,
                'updated_at' => now(),
            ]);

            $this->loadTasks();
            $this->dispatch('alert', type: 'success', message: 'Task started successfully!');

        } catch (\Exception $e) {
            $this->dispatch('alert', type: 'error', message: 'An error occurred while starting the task.');
        }
    }

    public function submitTask($taskId)
    {
        $task = DB::table('project_tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        DB::table('project_tasks')->where('task_id', $taskId)->update([
            'status' => 'submitted',
            'progress' => 100,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'success', message: 'Task submitted for review!');
    }

    public function approveTask($taskId)
    {
        $task = DB::table('project_tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        DB::table('project_tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'success', message: 'Task approved!');
    }

    public function rejectTask($taskId)
    {
        DB::table('project_tasks')->where('task_id', $taskId)->update([
            'status' => 'in_progress',
            'progress' => 50,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        $this->dispatch('alert', type: 'info', message: 'Task returned for revision.');
    }

    public function completeTask($taskId)
    {
        DB::table('project_tasks')->where('task_id', $taskId)->update([
            'status' => 'completed',
            'progress' => 100,
            'updated_at' => now(),
        ]);

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

.loading-button {
    position: relative;
}

.loading-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
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
                                <span>{{ $task->progress ?? 0 }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill {{ $task->role_type === 'Manager' ? 'bg-purple-600' : 'bg-blue-600' }}" 
                                     style="width: {{ $task->progress ?? 0 }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 border-t border-gray-100">
                        @if ($task->role_type === 'Manager')
                            <!-- Manager Actions -->
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="approveTask({{ $task->task_id }})" 
                                        class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Approve</span>
                                </button>
                                <button onclick="rejectTask({{ $task->task_id }})" 
                                        class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>Reject</span>
                                </button>
                            </div>
                        @else
                            <!-- Employee Actions -->
                            @switch($task->status)
                                @case('not_started')
                                @case('pending')
                                    <button onclick="startTask({{ $task->task_id }}, this)" 
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-2 loading-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Start Task</span>
                                    </button>
                                    @break

                                @case('in_progress')
                                    <div class="grid grid-cols-2 gap-2">
                                        <button onclick="submitTask({{ $task->task_id }}, this)" 
                                                class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-2 loading-button">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span>Submit</span>
                                        </button>
                                        <button onclick="completeTask({{ $task->task_id }}, this)" 
                                                class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-2 loading-button">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            <span>Complete</span>
                                        </button>
                                    </div>
                                    @break

                                @case('submitted')
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
                                        <div class="flex items-center justify-center space-x-2 text-yellow-800">
                                            <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-semibold">Awaiting Approval</span>
                                        </div>
                                    </div>
                                    @break

                                @case('completed')
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                                        <div class="flex items-center justify-center space-x-2 text-green-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-semibold">Task Completed</span>
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
                    <p class="text-gray-500 max-w-md mx-auto">You don't have any tasks assigned to you at the moment. Tasks will appear here when they are assigned.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Loading State -->
    <div wire:loading class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600">Loading tasks...</p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function startTask(taskId, button) {
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div><span>Starting...</span>';
                button.disabled = true;
            }
            
            @this.call('startTask', taskId)
                .then(result => {
                    // Button will reset when component re-renders
                })
                .catch(error => {
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                });
        }

        function submitTask(taskId, button) {
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div><span>Submitting...</span>';
                button.disabled = true;
            }
            
            @this.call('submitTask', taskId)
                .then(result => {
                    // Button will reset when component re-renders
                })
                .catch(error => {
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                });
        }

        function approveTask(taskId) {
            if (confirm('Are you sure you want to approve this task?')) {
                @this.call('approveTask', taskId);
            }
        }

        function rejectTask(taskId) {
            if (confirm('Are you sure you want to reject this task?')) {
                @this.call('rejectTask', taskId);
            }
        }

        function completeTask(taskId, button) {
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div><span>Completing...</span>';
                button.disabled = true;
            }
            
            @this.call('completeTask', taskId)
                .then(result => {
                    // Button will reset when component re-renders
                })
                .catch(error => {
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                });
        }

        function filterTasks(type) {
            const cards = document.querySelectorAll('.task-card');
            cards.forEach(card => {
                if (type === 'all') {
                    card.style.display = 'block';
                } else if (type === 'employee') {
                    card.style.display = card.classList.contains('employee') ? 'block' : 'none';
                } else if (type === 'manager') {
                    card.style.display = card.classList.contains('manager') ? 'block' : 'none';
                }
            });
        }
    </script>
</div>