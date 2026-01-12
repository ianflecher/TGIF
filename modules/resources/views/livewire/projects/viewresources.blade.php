<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.project')] class extends Component
{
    public array $resources = [];
    public array $resourcesByType = [];

    public bool $showResourceModal = false;
    public ?int $editing_id = null;

    public string $resource_name = '';
    public string $type = '';
    public float $unit_cost = 0.00;
    public string $status = '';
    public int $quantity = 1;

    public function mount()
    {
        $this->loadResources();
    }

    public function loadResources()
    {
        $this->resources = DB::table('resources')
            ->orderByDesc('resource_id')
            ->get()
            ->toArray();

        // Group resources by type
        $this->resourcesByType = [
            'Labor' => array_filter($this->resources, fn($r) => $r->type === 'employee'),
            'Materials' => array_filter($this->resources, fn($r) => $r->type === 'equipment'),
            'Overhead' => array_filter($this->resources, fn($r) => $r->type === 'material'),
        ];
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->showResourceModal = true;
    }

    public function openEditModal($id)
    {
        $res = DB::table('resources')->where('resource_id', $id)->first();

        if ($res) {
            $this->editing_id = $res->resource_id;
            $this->resource_name = $res->resource_name;
            $this->type = $res->type;
            $this->unit_cost = $res->unit_cost;
            $this->status = $res->status;
            $this->quantity = $res->availability_quantity ?? 1;

            $this->showResourceModal = true;
        }
    }

    public function closeResourceModal()
    {
        $this->showResourceModal = false;
        $this->resetForm();
    }

    public function saveResource()
    {
        if (!$this->resource_name || !$this->type || !$this->status) return;

        $data = [
            'resource_name' => $this->resource_name,
            'type' => $this->type, // Changed from resource_type to type
            'unit_cost' => $this->unit_cost,
            'availability_quantity' => $this->quantity,
            'status' => $this->status,
            'updated_at' => now(),
        ];

        if ($this->editing_id) {
            DB::table('resources')->where('resource_id', $this->editing_id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('resources')->insert($data);
        }

        $this->closeResourceModal();
        $this->loadResources();
    }

    public function deleteResource($id)
    {
        DB::table('resources')->where('resource_id', $id)->delete();
        $this->loadResources();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->resource_name = '';
        $this->type = '';
        $this->unit_cost = 0.00;
        $this->status = '';
        $this->quantity = 1;
    }
}
?>
<div class="resources-container">

    <div class="task-header" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="javascript:history.back()" class="back-link">← Back</a>
        <h2>Resources Management</h2>
        <button class="resources-btn resources-btn-green" wire:click="openAddModal">+ Add Resource</button>
    </div>

    <div class="resources-table-wrapper">
        @if(count($resources))
            @foreach(['Labor', 'Materials', 'Overhead'] as $type)
                @if(count($resourcesByType[$type] ?? []))
                    <div class="resources-type-section" style="margin-bottom: 2rem;">
                        <h3 style="color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                            {{ $type }} Resources
                        </h3>
                        <table class="resources-table">
                            <thead>
                                <tr>
                                    <th>Resource Name</th>
                                    <th>Type</th>
                                    <th>Unit Cost</th>
                                    <th>Status</th>
                                    <th>Quantity</th>
                                    <th style="width:160px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($resourcesByType[$type] as $r)
                                    <tr>
                                        <td>{{ $r->resource_name }}</td>
                                        <td>
                                            @php
                                                $typeText = match($r->type) {
                                                    'employee' => 'Labor',
                                                    'equipment' => 'Materials',
                                                    'material' => 'Overhead',
                                                    default => $r->type
                                                };
                                            @endphp
                                            {{ $typeText }}
                                        </td>
                                        <td>₱{{ number_format($r->unit_cost, 2) }}</td>
                                        <td>
                                            <span class="resources-status {{ strtolower($r->status) }}">
                                                {{ $r->status }}
                                            </span>
                                        </td>
                                        <td>{{ $r->availability_quantity }}</td>
                                        <td class="resources-actions">
                                            <button wire:click="openEditModal({{ $r->resource_id }})" class="resources-btn resources-btn-yellow">Edit</button>
                                            <button wire:click="deleteResource({{ $r->resource_id }})" class="resources-btn resources-btn-red">Delete</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        @else
            <div class="resources-empty">No resources found</div>
        @endif
    </div>

    <!-- Modal -->
    <div class="resources-modal" style="display: {{ $showResourceModal ? 'flex' : 'none' }};">
        <div class="resources-modal-box">
            <div class="resources-modal-header">
                <h3>{{ $editing_id ? 'Edit Resource' : 'Add Resource' }}</h3>
                <button wire:click="closeResourceModal" class="resources-close-btn">✖</button>
            </div>

            <form wire:submit.prevent="saveResource" class="resources-form">
                <div class="resources-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">

                    <label>
                        <span>Type</span>
                        <select wire:model="type" required>
                            <option value="">-- Select Type --</option>
                            <option value="employee">Labor</option>
                            <option value="equipment">Materials</option>
                            <option value="material">Overhead</option>
                        </select>
                    </label>

                    <label>
                        <span>Unit Cost</span>
                        <input type="number" step="0.01" wire:model="unit_cost" required>
                    </label>

                    <label>
                        <span>Quantity</span>
                        <input type="number" min="1" wire:model="quantity" required>
                    </label>

                    <label style="grid-column:1 / span 2;">
                        <span>Resource Name</span>
                        <input type="text" wire:model="resource_name" placeholder="Enter resource name" required>
                    </label>

                    <label style="grid-column:1 / span 2;">
                        <span>Status</span>
                        <select wire:model="status" required>
                            <option value="">-- Select Status --</option>
                            <option value="available">Active</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="in_use">Reserved</option>
                        </select>
                    </label>
                </div>

                <div class="resources-modal-actions" style="margin-top:1rem;">
                    <button type="submit" class="resources-btn resources-btn-green">{{ $editing_id ? 'Update' : 'Save' }}</button>
                    <button type="button" wire:click="closeResourceModal" class="resources-btn resources-btn-gray">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</div>