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
    public int $project_id;
    
    public string $project_name = '';
    public array $viewPhases = [];

    // Modal state + form fields
    public bool $showPhaseModal = false;
    public string $phase_name = '';
    public string $phase_start_date = '';
    public string $phase_end_date = '';
    public string $phase_description = '';
    public string $phaseError = '';
    public int $currentProjectId = 0;

    // Editing
    public bool $isEdit = false;
    public ?int $editPhaseId = null;

    public function mount()
    {
        if (!$this->project_id) {
            $this->project_id = request()->query('project_id') ?? session('current_project_id') ?? 0;
        }
        
        if ($this->project_id) {
            $this->loadProjectData();
            $this->loadPhases();
        }
    }

    public function updatedProjectId($value)
    {
        if ($value) {
            $this->loadProjectData();
            $this->loadPhases();
        }
    }

    private function loadProjectData()
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        if ($project) {
            $this->project_name = $project->project_name ?? 'Unnamed Project';
        }
    }

    /**************************
     * ✅ LOAD PHASES
     **************************/
    public function loadPhases()
    {
        $this->viewPhases = DB::table('project_phases')
            ->where('project_id', $this->project_id)
            ->orderBy('start_date', 'asc')
            ->get()
            ->toArray();
    }

    public function openPhaseModal(int $projectId)
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->currentProjectId = $projectId;
        $this->showPhaseModal = true;
    }

    public function openEditPhaseModal($phaseId)
    {
        $phase = DB::table('project_phases')->where('phase_id', $phaseId)->first();

        if ($phase) {
            $this->editPhaseId = $phase->phase_id;
            $this->phase_name = $phase->phase_name;
            $this->phase_start_date = $phase->start_date;
            $this->phase_end_date = $phase->end_date;
            $this->phase_description = $phase->description ?? '';
            $this->currentProjectId = $phase->project_id;
            $this->isEdit = true;
            $this->showPhaseModal = true;
        }
    }

    public function closePhaseModal()
    {
        $this->showPhaseModal = false;
        $this->phaseError = '';
        $this->isEdit = false;
        $this->editPhaseId = null;
    }

    public function resetForm()
    {
        $this->phase_name = '';
        $this->phase_start_date = '';
        $this->phase_end_date = '';
        $this->phase_description = '';
        $this->phaseError = '';
    }

    public function savePhase()
    {
        // Validate required fields
        if (!$this->phase_name || !$this->phase_start_date || !$this->phase_end_date) {
            $this->phaseError = 'Phase name, start date, and end date are required.';
            return;
        }

        // Validate dates
        if (Carbon::parse($this->phase_end_date)->lt(Carbon::parse($this->phase_start_date))) {
            $this->phaseError = 'End date cannot be earlier than start date.';
            return;
        }

        if ($this->isEdit && $this->editPhaseId) {
            // Update existing phase
            DB::table('project_phases')->where('phase_id', $this->editPhaseId)->update([
                'phase_name' => $this->phase_name,
                'start_date' => $this->phase_start_date,
                'end_date' => $this->phase_end_date,
                'description' => $this->phase_description,
                'updated_at' => now(),
            ]);

            session()->flash('success', 'Phase updated successfully!');
        } else {
            // Insert new phase
            DB::table('project_phases')->insert([
                'project_id' => $this->currentProjectId,
                'phase_name' => $this->phase_name,
                'start_date' => $this->phase_start_date,
                'end_date' => $this->phase_end_date,
                'description' => $this->phase_description,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            session()->flash('success', 'Phase added successfully!');
        }

        $this->loadPhases();
        $this->closePhaseModal();
    }

    public function deletePhase($phaseId)
    {
        if (!$phaseId) return;

        // Check if phase has tasks (if project_tasks table exists)
        if (Schema::hasTable('project_tasks')) {
            $taskCount = DB::table('project_tasks')
                ->where('phase_id', $phaseId)
                ->count();

            if ($taskCount > 0) {
                session()->flash('error', 'Cannot delete phase that contains tasks. Delete the tasks first.');
                return;
            }
        }

        // Check if phase has budgets (if budgets table exists)
        if (Schema::hasTable('budgets')) {
            $budgetCount = DB::table('budgets')
                ->where('phase_id', $phaseId)
                ->count();

            if ($budgetCount > 0) {
                session()->flash('error', 'Cannot delete phase that has budget records. Delete the budget records first.');
                return;
            }
        }

        // Delete the phase
        DB::table('project_phases')->where('phase_id', $phaseId)->delete();
        
        $this->loadPhases();
        session()->flash('success', 'Phase deleted successfully.');
    }
}
?>

<div style="padding: 1.5rem; background: #fffef6; min-height: calc(100vh - 60px); margin-left: 65px; margin-top: 60px; transition: margin-left 0.3s ease;">
    <div class="phase-container" style="max-width: 1200px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); font-family: 'Segoe UI', sans-serif;">
        <div class="phase-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #22c55e; padding-bottom: 0.75rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.6rem; font-weight: 700; color: #166534;">
                Project Phases — {{ $project_name }}
            </h2>
            <a href="{{ route('projects.projects') }}" class="back-link" style="display: inline-block; padding: 0.5rem 1.2rem; border-radius: 9999px; background: transparent; color: #15803d; font-weight: 600; text-decoration: none; transition: background 0.2s ease, color 0.2s ease, transform 0.1s ease;">
                ← Back to Projects
            </a>
        </div>

        @if(session()->has('success'))
            <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #14532d; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session()->has('error'))
            <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                {{ session('error') }}
            </div>
        @endif

        <div style="margin-bottom: 1.5rem; text-align: right;">
            <button type="button" wire:click="openPhaseModal({{ $project_id }})" 
                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 10px 20px; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                + Add New Phase
            </button>
        </div>

        <div class="phase-table-container" style="overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; background: #f9fafb;">
            @if(count($viewPhases))
                <table class="phase-table" style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
                    <thead>
                        <tr style="background: #dcfce7; color: #166534;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Phase Name</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Description</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Start Date</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">End Date</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600;">Status</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #bbf7d0; font-weight: 600; width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($viewPhases as $ph)
                            @php
                                $statusColor = match($ph->status) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'blocked' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                            @endphp
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.2s ease;">
                                <td style="padding: 12px; color: #1f2937; font-weight: 500;">{{ $ph->phase_name }}</td>
                                <td style="padding: 12px; color: #6b7280;">{{ Str::limit($ph->description ?? 'No description', 50) }}</td>
                                <td style="padding: 12px; color: #4b5563;">{{ $ph->start_date }}</td>
                                <td style="padding: 12px; color: #4b5563;">{{ $ph->end_date }}</td>
                                <td style="padding: 12px;">
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; {{ $statusColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $ph->status)) }}
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="{{ route('projects.task', ['phase_id' => $ph->phase_id]) }}" 
                                           style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 6px 12px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: background 0.2s ease;">
                                            View Tasks
                                        </a>
                                        <button type="button" wire:click="openEditPhaseModal({{ $ph->phase_id }})" 
                                                style="background: #facc15; color: #1f2937; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease;">
                                            Edit
                                        </button>
                                        <button type="button"
                                                onclick="if(confirm('Are you sure you want to delete this phase?')) { @this.call('deletePhase', {{ $ph->phase_id }}) }"
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
                    No phases found for this project. Click "Add New Phase" to create one.
                </div>
            @endif
        </div>

        <!-- Add/Edit Phase Modal -->
        <div class="modal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: {{ $showPhaseModal ? 'flex' : 'none' }}; align-items: center; justify-content: center; z-index: 1000;">
            <div class="modal-dialog" style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #15803d 0%, #14532d 100%); color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.2rem; font-weight: 600;">
                        {{ $isEdit ? 'Edit Phase' : 'Add New Phase' }}
                    </h3>
                    <button type="button" wire:click="closePhaseModal" 
                            style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">×</button>
                </div>
                
                <div class="modal-body" style="padding: 1.5rem;">
                    @if($phaseError)
                        <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem;">
                            {{ $phaseError }}
                        </div>
                    @endif
                    
                    <form wire:submit.prevent="savePhase" style="display: grid; gap: 1rem;">
                        <div>
                            <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                Phase Name *
                            </label>
                            <input type="text" wire:model="phase_name" required 
                                   style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem; transition: border-color 0.2s ease;">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                    Start Date *
                                </label>
                                <input type="date" wire:model="phase_start_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </div>
                            
                            <div>
                                <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                    End Date *
                                </label>
                                <input type="date" wire:model="phase_end_date" required 
                                       style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;">
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">
                                Description
                            </label>
                            <textarea wire:model="phase_description" 
                                      rows="3"
                                      style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.5rem;"></textarea>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                            <button type="button" wire:click="closePhaseModal"
                                    style="background: #6b7280; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                                Cancel
                            </button>
                            <button type="submit"
                                    style="background: #22c55e; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 500;">
                                {{ $isEdit ? 'Update Phase' : 'Save Phase' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>