<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layouts.project')] class extends Component
{
    #[Url]
    public int $project_id;
    
    public string $project_name = '';
    public array $projectMembers = [];
    public array $employees = [];
    public string $newMemberName = '';
    public ?int $selectedMemberId = null;

    public function mount()
    {
        if (!$this->project_id) {
            // Try to get from URL or session
            $this->project_id = request()->query('project_id') ?? session('current_project_id') ?? 0;
        }
        
        if ($this->project_id) {
            $this->loadProjectData();
        }
    }

    public function updatedProjectId($value)
    {
        if ($value) {
            $this->loadProjectData();
        }
    }

    private function loadProjectData()
    {
        // Get project with manager info
        $project = DB::table('projects')
            ->leftJoin('employees', 'projects.project_manager_id', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->where('projects.project_id', $this->project_id)
            ->select('projects.*', 'users.full_name as manager_name')
            ->first();

        if ($project) {
            $this->project_name = $project->project_name ?? 'Unnamed Project';
            
            // Get all employees (non-admin)
            $this->employees = DB::table('employees')
                ->join('users', 'employees.user_id', '=', 'users.user_id')
                ->where('users.role', '!=', 'admin')
                ->orderBy('users.full_name')
                ->select('employees.employee_id', 'users.full_name', 'users.role')
                ->get()
                ->toArray();

            // Get project members from team_members JSON field
            $memberIds = json_decode($project->team_members ?? '[]', true) ?? [];
            
            $this->projectMembers = [];

            if (count($memberIds)) {
                $memberData = DB::table('employees')
                    ->join('users', 'employees.user_id', '=', 'users.user_id')
                    ->whereIn('employees.employee_id', $memberIds)
                    ->select('employees.employee_id', 'users.full_name', 'users.role')
                    ->get()
                    ->keyBy('employee_id');

                foreach ($memberIds as $id) {
                    if (isset($memberData[$id])) {
                        $emp = $memberData[$id];
                        $this->projectMembers[] = [
                            'employee_id' => (int)$emp->employee_id,
                            'full_name' => $emp->full_name,
                            'role' => $emp->role,
                            'is_manager' => ($project->project_manager_id == $emp->employee_id),
                        ];
                    }
                }
            }
        } else {
            $this->project_name = 'Project not found';
            $this->employees = [];
            $this->projectMembers = [];
        }
    }

    public function addMember()
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        
        if (!$project) {
            session()->flash('error', 'Project not found.');
            return;
        }

        // Get current team members
        $memberIds = json_decode($project->team_members ?? '[]', true) ?? [];

        // Add new external member
        if ($this->newMemberName) {
            $name = trim($this->newMemberName);
            
            // Create a new user for the external member
            $userId = DB::table('users')->insertGetId([
                'full_name' => $name,
                'username' => Str::slug($name) . '_' . rand(1000, 9999),
                'password' => bcrypt('password123'), // Default password, should be changed
                'role' => 'employee',
                'email' => Str::slug($name) . '@company.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create employee record
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $userId,
                'job_title' => 'Project Member',
                'hire_date' => now()->toDateString(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $memberIds[] = $employeeId;
            $this->newMemberName = '';
            
            session()->flash('success', 'New member added successfully.');
        }

        // Add existing employee
        if ($this->selectedMemberId) {
            if (!in_array($this->selectedMemberId, $memberIds)) {
                $memberIds[] = $this->selectedMemberId;
                session()->flash('success', 'Employee added to project.');
            } else {
                session()->flash('info', 'Employee is already a member of this project.');
            }
            $this->selectedMemberId = null;
        }

        // Update project with new team members
        DB::table('projects')->where('project_id', $this->project_id)
            ->update([
                'team_members' => json_encode(array_values(array_unique($memberIds))),
                'updated_at' => now(),
            ]);

        $this->loadProjectData();
    }

    public function removeMember($employeeId)
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        
        if (!$project) {
            session()->flash('error', 'Project not found.');
            return;
        }

        // Get current team members
        $memberIds = json_decode($project->team_members ?? '[]', true) ?? [];
        
        // Remove the employee from the team
        $memberIds = array_filter($memberIds, fn($id) => $id != $employeeId);
        
        // Don't remove the project manager
        if ($project->project_manager_id == $employeeId) {
            session()->flash('error', 'Cannot remove the project manager. Assign a new manager first.');
            return;
        }

        // Update project
        DB::table('projects')->where('project_id', $this->project_id)
            ->update([
                'team_members' => json_encode(array_values($memberIds)),
                'updated_at' => now(),
            ]);

        $this->loadProjectData();
        session()->flash('success', 'Member removed from project.');
    }
};
?>

<div class="member-container" style="max-width: 920px; margin: 2.5rem auto; background: linear-gradient(135deg, #ffffff 0%, #f8fdfa 100%); border-radius: 18px; padding: 2.2rem; box-shadow: 0 8px 24px rgba(0,0,0,0.06); font-family: 'Inter', 'Segoe UI', sans-serif; margin-left: 65px; margin-top: 80px;">
    <div class="member-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e7f5ee; padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.6rem; font-weight: 700; color: #166534; margin: 0;">
            Project Members — {{ $project_name }}
        </h2>
        <a href="{{ route('projects.projects') }}" style="display: inline-block; padding: 0.5rem 1.2rem; border-radius: 9999px; background: transparent; color: #15803d; font-weight: 600; text-decoration: none; transition: background 0.2s ease, color 0.2s ease, transform 0.1s ease;">
            ← Back to Projects
        </a>
    </div>

    <div class="member-list" style="margin-bottom: 2rem;">
        @if(count($projectMembers))
            @foreach($projectMembers as $member)
                <div class="member-card" style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 14px; padding: 1rem 1.2rem; margin-bottom: 0.8rem; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease;" wire:key="member-{{ $member['employee_id'] }}">
                    <div class="member-details" style="flex: 1;">
                        <div class="member-name" style="font-weight: 600; font-size: 1.05rem; color: #1e293b;">
                            {{ $member['full_name'] }}
                            @if($member['is_manager'])
                                <span style="background: #fbbf24; color: #92400e; border-radius: 9999px; padding: 0.2rem 0.6rem; font-size: 0.75rem; margin-left: 0.5rem;">
                                    Manager
                                </span>
                            @endif
                        </div>
                        <div class="member-role" style="display: inline-block; margin-top: 0.25rem; background: #bbf7d0; color: #14532d; border-radius: 9999px; padding: 0.25rem 0.7rem; font-size: 0.78rem; font-weight: 500;">
                            {{ $member['role'] }}
                        </div>
                    </div>
                    <div class="member-actions" style="display: flex; gap: 0.5rem;">
                        @if(!$member['is_manager'])
                            <button class="remove-btn" style="background: #ef4444; color: white; border: none; border-radius: 6px; padding: 0.4rem 0.7rem; font-size: 0.85rem; cursor: pointer; transition: background 0.2s ease;" 
                                    wire:click="removeMember({{ $member['employee_id'] }})"
                                    onclick="return confirm('Are you sure you want to remove this member from the project?')">
                                Remove
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <div style="text-align: center; color: #94a3b8; padding: 2rem 0; font-style: italic; font-size: 0.95rem;">
                No members yet. Add members using the form below.
            </div>
        @endif
    </div>

    <div class="member-form" style="background: #fafafa; border-radius: 12px; border: 1px solid #e5e7eb; padding: 1.5rem;">
        <h3 style="margin-top: 0; font-size: 1.2rem; color: #14532d; margin-bottom: 1rem;">Add New Member</h3>
        <form wire:submit.prevent="addMember">
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; font-size: 0.9rem; color: #374151; margin-bottom: 0.35rem;">
                    New Member Name (External)
                </label>
                <input type="text" wire:model.defer="newMemberName" placeholder="Enter full name for external member"
                       style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: white; transition: border 0.2s ease;">
            </div>

            <div style="text-align: center; margin: 1rem 0; color: #6b7280; font-size: 0.9rem;">
                — OR —
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 600; font-size: 0.9rem; color: #374151; margin-bottom: 0.35rem;">
                    Select Existing Employee
                </label>
                <select wire:model="selectedMemberId" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: white;">
                    <option value="">-- Select Employee --</option>
                    @foreach ($employees as $emp)
                        @if(!collect($projectMembers)->pluck('employee_id')->contains($emp->employee_id))
                            <option value="{{ $emp->employee_id }}">{{ $emp->full_name }} ({{ $emp->role }})</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" 
                        style="background: #22c55e; color: white; border: none; border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; cursor: pointer; transition: background 0.2s ease;">
                    Add Member
                </button>
            </div>
        </form>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div style="margin-top: 1rem; padding: 0.75rem; background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px; color: #14532d;">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div style="margin-top: 1rem; padding: 0.75rem; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b;">
            {{ session('error') }}
        </div>
    @endif
    
    @if(session()->has('info'))
        <div style="margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; color: #92400e;">
            {{ session('info') }}
        </div>
    @endif
</div>