<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

new #[Layout('components.layouts.project')] class extends Component
{
    #[Url]
    public int $phase_id;
    
    public string $phase_name = '';
    public string $project_name = '';
    public array $tasks = [];

    // === Add/Edit Task Modal fields ===
    public bool $showTaskModal = false;
    public bool $isEditing = false;
    public int $editingTaskId = 0;
    public string $task_name = '';
    public string $task_start_date = '';
    public string $task_end_date = '';
    public string $task_description = '';
    public string $taskError = '';

    // === Delete Confirmation Modal ===
    public bool $showDeleteModal = false;
    public int $deleteTaskId = 0;
    public string $deleteTaskName = '';

    public function mount()
    {
        if (!$this->phase_id) {
            $this->phase_id = request()->query('phase_id') ?? session('current_phase_id') ?? 0;
        }
        
        if ($this->phase_id) {
            $this->loadPhaseData();
            $this->loadTasks();
        }
    }

    public function updatedPhaseId($value)
    {
        if ($value) {
            $this->loadPhaseData();
            $this->loadTasks();
        }
    }

    private function loadPhaseData()
    {
        $phase = DB::table('project_phases')->where('phase_id', $this->phase_id)->first();
        if ($phase) {
            $this->phase_name = $phase->phase_name;
            $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
            $this->project_name = $project->project_name ?? 'Unnamed Project';
        }
    }

    /**************************
     * ✅ LOAD TASKS
     **************************/
    public function loadTasks()
    {
        $this->tasks = DB::table('tasks')
            ->where('phase_id', $this->phase_id)
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($task) {
                // Since assigned_to is varchar, we need to handle it differently
                $assignedNames = [];
                
                if ($task->assigned_to) {
                    // Check if assigned_to contains comma-separated IDs
                    $assignedIds = explode(',', $task->assigned_to);
                    
                    // Filter out empty values and convert to integers if possible
                    $assignedIds = array_filter($assignedIds, function($id) {
                        return trim($id) !== '' && is_numeric(trim($id));
                    });
                    
                    if (count($assignedIds) > 0) {
                        // Try to get employee names from hr_employees if it exists
                        if (Schema::hasTable('hr_employees')) {
                            $employees = DB::table('hr_employees')
                                ->whereIn('employee_id', $assignedIds)
                                ->select('employee_id', 'full_name')
                                ->get()
                                ->keyBy('employee_id');
                            
                            foreach ($assignedIds as $id) {
                                if (isset($employees[(int)$id])) {
                                    $assignedNames[] = $employees[(int)$id]->full_name;
                                } else {
                                    $assignedNames[] = "Employee #{$id}";
                                }
                            }
                        } else if (Schema::hasTable('employees')) {
                            // Try employees table with users join
                            $employees = DB::table('employees')
                                ->join('users', 'employees.user_id', '=', 'users.user_id')
                                ->whereIn('employees.employee_id', $assignedIds)
                                ->select('employees.employee_id', 'users.full_name')
                                ->get()
                                ->keyBy('employee_id');
                            
                            foreach ($assignedIds as $id) {
                                if (isset($employees[(int)$id])) {
                                    $assignedNames[] = $employees[(int)$id]->full_name;
                                } else {
                                    $assignedNames[] = "Employee #{$id}";
                                }
                            }
                        } else {
                            // If no employee table, just show IDs
                            $assignedNames = array_map(fn($id) => "Employee #{$id}", $assignedIds);
                        }
                    } else {
                        // If assigned_to is not numeric IDs (maybe names or emails)
                        $assignedNames = [$task->assigned_to];
                    }
                }
                
                $task->assigned_names = !empty($assignedNames) ? implode(', ', $assignedNames) : 'Unassigned';
                return $task;
            })
            ->toArray();
    }

    /**************************
     * ✅ UPDATE PHASE STATUS
     **************************/
    public function updatePhaseStatus()
    {
        $tasks = DB::table('tasks')->where('phase_id', $this->phase_id)->get();

        if ($tasks->isEmpty()) {
            DB::table('project_phases')
                ->where('phase_id', $this->phase_id)
                ->update(['status' => 'pending', 'updated_at' => now()]);
            return;
        }

        $total = $tasks->count();
        $completed = $tasks->where('status', 'Completed')->count();
        $inProgress = $tasks->whereIn('status', ['In Progress', 'Submit for Checking'])->count();

        $newStatus = 'pending';
        if ($completed === $total) {
            $newStatus = 'completed';
        } elseif ($inProgress > 0 || $completed > 0) {
            $newStatus = 'in_progress';
        }

        DB::table('project_phases')
            ->where('phase_id', $this->phase_id)
            ->update(['status' => $newStatus, 'updated_at' => now()]);
    }

    /**************************
     * ✅ TASK STATUS CONTROLS
     **************************/
    public function startTask($task_id)
    {
        DB::table('tasks')->where('task_id', $task_id)->update([
            'status' => 'In Progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);
        $this->updatePhaseStatus();
        $this->loadTasks();
        session()->flash('success', 'Task started successfully.');
    }

    public function submitTaskForChecking($task_id)
    {
        DB::table('tasks')->where('task_id', $task_id)->update([
            'status' => 'Submit for Checking',
            'progress_percentage' => 90,
            'updated_at' => now(),
        ]);
        $this->updatePhaseStatus();
        $this->loadTasks();
        session()->flash('success', 'Task submitted for checking.');
    }

    public function approveTask($task_id)
    {
        DB::table('tasks')->where('task_id', $task_id)->update([
            'status' => 'Completed',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);
        $this->updatePhaseStatus();
        $this->loadTasks();
        session()->flash('success', 'Task approved and marked as completed.');
    }

    public function rejectTask($task_id)
    {
        DB::table('tasks')->where('task_id', $task_id)->update([
            'status' => 'In Progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);
        $this->updatePhaseStatus();
        $this->loadTasks();
        session()->flash('success', 'Task returned to in progress status.');
    }

    /**************************
     * ✅ TASK MODAL CONTROLS
     **************************/
    public function openTaskModal()
    {
        $this->resetTaskForm();
        $this->isEditing = false;
        $this->showTaskModal = true;
    }

    public function openEditTaskModal($task_id)
    {
        $task = DB::table('tasks')->where('task_id', $task_id)->first();
        if (!$task) return;

        $this->isEditing = true;
        $this->editingTaskId = $task_id;
        $this->task_name = $task->task_name;
        $this->task_start_date = $task->start_date;
        $this->task_end_date = $task->end_date;
        $this->task_description = $task->description ?? '';

        $this->showTaskModal = true;
    }

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
        $this->taskError = '';
    }

    public function resetTaskForm()
    {
        $this->task_name = '';
        $this->task_start_date = '';
        $this->task_end_date = '';
        $this->task_description = '';
    }

    /**************************
     * ✅ SAVE / UPDATE TASK
     **************************/
    public function saveTask()
    {
        // Validate required fields
        if (!$this->task_name || !$this->task_start_date || !$this->task_end_date) {
            $this->taskError = 'Please fill in all required fields.';
            return;
        }

        // Validate dates
        if (Carbon::parse($this->task_end_date)->lt(Carbon::parse($this->task_start_date))) {
            $this->taskError = 'End date cannot be earlier than start date.';
            return;
        }

        if ($this->isEditing) {
            // Update existing task
            DB::table('tasks')->where('task_id', $this->editingTaskId)->update([
                'task_name' => $this->task_name,
                'description' => $this->task_description,
                'start_date' => $this->task_start_date,
                'end_date' => $this->task_end_date,
                'updated_at' => now(),
            ]);
            
            session()->flash('success', 'Task updated successfully.');
        } else {
            // Insert new task
            DB::table('tasks')->insert([
                'phase_id' => $this->phase_id,
                'task_name' => $this->task_name,
                'description' => $this->task_description,
                'start_date' => $this->task_start_date,
                'end_date' => $this->task_end_date,
                'status' => 'Pending',
                'progress_percentage' => 0,
                'assigned_to' => null, // Start with no assignment
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            session()->flash('success', 'Task created successfully.');
        }

        $this->updatePhaseStatus();
        $this->loadTasks();
        $this->closeTaskModal();
    }

    /**************************
     * ✅ DELETE TASK
     **************************/
    public function confirmDeleteTask($task_id)
    {
        $task = DB::table('tasks')->where('task_id', $task_id)->first();
        if (!$task) return;

        $this->deleteTaskId = $task_id;
        $this->deleteTaskName = $task->task_name;
        $this->showDeleteModal = true;
    }

    public function deleteTaskConfirmed()
    {
        DB::table('tasks')->where('task_id', $this->deleteTaskId)->delete();
        $this->updatePhaseStatus();
        $this->loadTasks();
        $this->showDeleteModal = false;
        session()->flash('success', 'Task deleted successfully.');
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
    }
}
?>
<div style="padding: 1.5rem; background: #fffef6; min-height: calc(100vh - 60px); margin-left: 65px; margin-top: 60px; transition: margin-left 0.3s ease;">
    <div class="phase-container" style="max-width: 1200px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); font-family: 'Segoe UI', sans-serif;">
        <div class="phase-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #22c55e; padding-bottom: 0.75rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.6rem; font-weight: 700; color: #166534;">
                Tasks — {{ $phase_name }} ({{ $project_name }})
            </h2>
            <a href="javascript:history.back()" style="display: inline-block; padding: 0.5rem 1.2rem; border-radius: 9999px; background: transparent; color: #15803d; font-weight: 600; text-decoration: none; transition: background 0.2s ease, color 0.2s ease, transform 0.1s ease;">
                ← Back
            </a>
        </div>

        @if(session()->has('success'))
            <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #14532d; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                {{ session('success') }}
            </div>
        @endif

        <div style="margin-bottom: 1.5rem; text-align: right;">
            <button type="button" wire:click="openTaskModal" 
                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 10px 20px; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                + Add New Task
            </button>
        </div>

        <div class="phase-table-container" style="overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; background: #f9fafb;">
            @if(count($tasks))
                <table class="phase-table" style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
                    <thead>
                        <tr style="background: #dcfce7; color: #166534;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Task Name</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Description</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Assigned To</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Start Date</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">End Date</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Status</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Progress</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600; width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            @php
                                $statusColor = match($task->status) {
                                    'Pending' => 'bg-yellow-100 text-yellow-800',
                                    'In Progress' => 'bg-blue-100 text-blue-800',
                                    'Submit for Checking' => 'bg-purple-100 text-purple-800',
                                    'Completed' => 'bg-green-100 text-green-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                
                                $progressColor = match(true) {
                                    $task->progress_percentage >= 100 => 'bg-green-500',
                                    $task->progress_percentage >= 75 => 'bg-blue-500',
                                    $task->progress_percentage >= 50 => 'bg-yellow-500',
                                    $task->progress_percentage >= 25 => 'bg-orange-500',
                                    default => 'bg-red-500'
                                };
                            @endphp
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.2s ease;">
                                <td style="padding: 12px; color: #1f2937; font-weight: 500;">{{ $task->task_name }}</td>
                                <td style="padding: 12px; color: #6b7280;">{{ Str::limit($task->description ?? 'No description', 50) }}</td>
                                <td style="padding: 12px; color: #4b5563;">{{ $task->assigned_names }}</td>
                                <td style="padding: 12px; color: #4b5563;">{{ $task->start_date }}</td>
                                <td style="padding: 12px; color: #4b5563;">{{ $task->end_date }}</td>
                                <td style="padding: 12px;">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; {{ $statusColor }}">
                                        {{ $task->status }}
                                    </span>
                                </td>
                                <td style="padding: 12px; color: #4b5563;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: {{ $task->progress_percentage }}%; {{ $progressColor }}"></div>
                                        </div>
                                        <span style="font-weight: 600; color: #1f2937;">{{ $task->progress_percentage }}%</span>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        @if($task->status === 'Pending')
                                            <button wire:click="startTask({{ $task->task_id }})" 
                                                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                                Start
                                            </button>
                                        @elseif($task->status === 'In Progress')
                                            <button wire:click="submitTaskForChecking({{ $task->task_id }})" 
                                                    style="background: #8b5cf6; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                                Submit
                                            </button>
                                        @elseif($task->status === 'Submit for Checking')
                                            <button wire:click="approveTask({{ $task->task_id }})" 
                                                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                                Approve
                                            </button>
                                            <button wire:click="rejectTask({{ $task->task_id }})" 
                                                    style="background: #f59e0b; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                                Reject
                                            </button>
                                        @endif
                                        
                                        <button wire:click="openEditTaskModal({{ $task->task_id }})" 
                                                style="background: #facc15; color: #1f2937; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                            Edit
                                        </button>
                                        <button wire:click="confirmDeleteTask({{ $task->task_id }})" 
                                                style="background: #ef4444; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div style="text-align: center; color: #6b7280; padding: 3rem 0; font-style: italic;">
                    No tasks found for this phase. Click "Add New Task" to create one.
                </div>
            @endif
        </div>

        <!-- Add/Edit Task Modal -->
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: {{ $showTaskModal ? 'flex' : 'none' }}; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.2rem; font-weight: 600;">
                        {{ $isEditing ? 'Edit Task' : 'New Task for Phase:' }} <span style="color: #bbf7d0;">{{ $phase_name }}</span>
                    </h3>
                    <button type="button" wire:click="closeTaskModal" 
                            style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">×</button>
                </div>
                
                <div class="modal-body" style="padding: 1.5rem;">
                    @if($taskError)
                        <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                            {{ $taskError }}
                        </div>
                    @endif
                    
                    <form wire:submit.prevent="saveTask" style="display: grid; gap: 1rem;">
                        <div>
                            <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                Task Name *
                            </label>
                            <input type="text" wire:model="task_name" required 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem; transition: border-color 0.2s ease;">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                    Start Date *
                                </label>
                                <input type="date" wire:model="task_start_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                    End Date *
                                </label>
                                <input type="date" wire:model="task_end_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                Description
                            </label>
                            <textarea wire:model="task_description" 
                                      rows="3"
                                      style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;"></textarea>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                            <button type="button" wire:click="closeTaskModal"
                                    style="background: #6b7280; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                                Cancel
                            </button>
                            <button type="submit"
                                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                                {{ $isEditing ? 'Update Task' : 'Save Task' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: {{ $showDeleteModal ? 'flex' : 'none' }}; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.2rem; font-weight: 600;">Confirm Delete</h3>
                    <button type="button" wire:click="cancelDelete" 
                            style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">×</button>
                </div>
                
                <div class="modal-body" style="padding: 1.5rem; text-align: center;">
                    <p style="color: #4b5563; margin-bottom: 1.5rem;">
                        Are you sure you want to delete <strong style="color: #1f2937;">{{ $deleteTaskName }}</strong>?
                    </p>
                    <div style="display: flex; justify-content: center; gap: 0.5rem;">
                        <button wire:click="deleteTaskConfirmed"
                                style="background: #ef4444; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                            Yes, Delete
                        </button>
                        <button wire:click="cancelDelete"
                                style="background: #6b7280; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>