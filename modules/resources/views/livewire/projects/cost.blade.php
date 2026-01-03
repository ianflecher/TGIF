<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.project')] class extends Component
{
    public ?int $taskId = null;
    public $task;
    public $costsByType = [];

    public function mount($task)
    {
        $this->taskId = $task;
        $this->task = DB::table('tasks')->where('task_id', $this->taskId)->first();

        // Fetch resource allocations
        $resources = DB::table('resource_allocations as ra')
            ->join('resources as r', 'r.resource_id', '=', 'ra.resource_id')
            ->where('ra.task_id', $this->taskId)
            ->select(
                'r.resource_type',
                'r.resource_name',
                'ra.quantity',
                DB::raw('ra.quantity * r.unit_cost as cost')
            )
            ->get();


        // Group by type safely
        $this->costsByType = [];
        foreach(['Labor','Materials','Overhead','Tool','Facility'] as $type) {
            $this->costsByType[$type] = $resources->where('resource_type', $type)->values();
        }
    }

    public function getTotalByType($type)
    {
        return isset($this->costsByType[$type]) 
            ? $this->costsByType[$type]->sum('cost') 
            : 0;
    }
}
?>
<div class="task-costs-container">

    <!-- Task Title -->
    <h2 class="task-title">💲 Actual Costs for Task: {{ $task->task_name ?? 'N/A' }}</h2>

    <!-- Costs by Type -->
    @foreach(['Labor', 'Materials','Tool'] as $type)
        <div class="cost-card">
            
            <!-- Type Header -->
            <h3 class="cost-type">{{ $type }}</h3>
            
            <!-- List of items -->
            @if(isset($costsByType[$type]) && $costsByType[$type]->isNotEmpty())
                <ul class="cost-list">
                    @foreach($costsByType[$type] as $item)
                        <li class="cost-item">
                            <span>{{ $item->resource_name }} — Qty: {{ $item->allocated_quantity }}</span>
                            <span class="cost-value">₱{{ number_format($item->cost,2) }}</span>
                        </li>
                    @endforeach
                </ul>

                <!-- Total for this type -->
                <p class="cost-total">
                    Total {{ $type }}: <span class="cost-value">₱{{ number_format($this->getTotalByType($type),2) }}</span>
                </p>
            @else
                <p class="cost-empty">No {{ strtolower($type) }} costs recorded.</p>
            @endif

        </div>
    @endforeach

    <!-- Total Task Cost -->
    <div class="total-task-cost">
        <span>Total Task Cost:</span>
        <span class="total-value">₱{{ number_format(
            array_sum(array_map(fn($type) => $this->getTotalByType($type), ['Labor','Materials','Overhead','Tool','Facility'])), 2
        ) }}</span>
    </div>

    <!-- Back Button -->
    <a href="{{ route('projects.budget') }}" class="back-button">← Back to Budget</a>

</div>
