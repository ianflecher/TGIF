<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.project')]
class extends Component
{
    public int $phase_id;
    public string $phase_name;
    public $estimatedCost;
    public $budgets = [];

    public function mount(int $phase_id)
    {
        $this->phase_id = $phase_id;

        $phase = DB::table('project_phases')->where('phase_id', $phase_id)->first();
        $this->phase_name = $phase ? $phase->phase_name : 'Unknown Phase';

        $this->loadBudgets();
    }

    public function loadBudgets()
    {
        // Only budgets for this phase
        $this->budgets = DB::table('budgets')
            ->leftJoin('budget_approvals', 'budgets.budget_id', '=', 'budget_approvals.budget_id')
            ->where('budgets.phase_id', $this->phase_id)
            ->select(
                'budgets.budget_id as id',
                'budgets.estimated_cost',
                'budgets.created_at',
                'budget_approvals.status',
                'budget_approvals.remarks'
            )
            ->orderBy('budgets.created_at', 'desc')
            ->get();
    }

    public function saveBudget()
    {
        $this->validate([
            'estimatedCost' => 'required|numeric|min:0',
        ]);

        $phase = DB::table('project_phases')->where('phase_id', $this->phase_id)->first();

        $budgetId = DB::table('budgets')->insertGetId([
            'project_id' => $phase->project_id,
            'phase_id' => $this->phase_id,
            'estimated_cost' => $this->estimatedCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('budget_approvals')->insert([
            'budget_id' => $budgetId,
            'requested_by' => auth()->id(),
            'status' => 'pending',
            'remarks' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('project_id', $phase->project_id)
            ->update(['status' => 'Paused', 'updated_at' => now()]);

        session()->flash('success', 'Budget submitted for approval!');
        $this->estimatedCost = null;

        $this->loadBudgets(); // refresh table for this phase only
        return redirect()->route('projects.home');
    }

    public function goBack()
    {
        return redirect()->route('projects.budget');
    }
}
?>


<div class="phase-container">

    <!-- Header with Back Button -->
    <div class="phase-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
        <h2>Add Budget for {{ $phase_name }}</h2>
        <button 
            wire:click="goBack" 
            type="button"
            style="background:none; border:none; color:#16a34a; font-weight:600; cursor:pointer;">
            ← Back
        </button>
    </div>

    <!-- Success Message -->
    @if (session()->has('success'))
        <p style="color:green; margin-bottom:1rem;">{{ session('success') }}</p>
    @endif

    <!-- Budget Form -->
    <form wire:submit.prevent="saveBudget" style="margin-bottom:2rem;">
        <label for="estimatedCost" style="display:block; margin-bottom:0.5rem; font-weight:600;">Estimated Cost</label>
        <input 
            id="estimatedCost" 
            type="number" 
            step="0.01" 
            wire:model="estimatedCost" 
            style="width:100%; padding:0.5rem; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:1rem;"
        >
        @error('estimatedCost')
            <p style="color:red; margin-bottom:1rem;">{{ $message }}</p>
        @enderror

        <button type="submit" style="background:#22c55e; color:white; padding:0.5rem 1rem; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
            💾 Save Budget
        </button>
    </form>

    <!-- Budget Table (Only this phase) -->
    @if(count($budgets) > 0)
    <div class="phase-table-container" style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; border:1px solid #e5e7eb;">
            <thead>
                <tr style="background:#16a34a; color:white; text-align:left;">
                    <th style="padding:0.5rem;">ID</th>
                    <th style="padding:0.5rem;">Estimated Cost</th>
                    <th style="padding:0.5rem;">Status</th>
                    <th style="padding:0.5rem;">Remarks</th>
                    <th style="padding:0.5rem;">Created At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($budgets as $budget)
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:0.5rem;">{{ $budget->id }}</td>
                    <td style="padding:0.5rem;">₱{{ number_format($budget->estimated_cost, 2) }}</td>
                    <td style="padding:0.5rem;">
                        @if($budget->status === 'Approved')
                            <span style="color:green; font-weight:600;">Approved</span>
                        @elseif($budget->status === 'Rejected')
                            <span style="color:red; font-weight:600;">Rejected</span>
                        @else
                            <span style="color:orange; font-weight:600;">Pending</span>
                        @endif
                    </td>
                    <td style="padding:0.5rem;">{{ $budget->remarks ?? '-' }}</td>
                    <td style="padding:0.5rem;">{{ $budget->created_at }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <p style="color:#6b7280;">No budget records found for this phase.</p>
    @endif

</div>
