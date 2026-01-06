<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public array $requisitions = [];
    public string $tab = 'draft'; // default tab - changed to match ENUM

    public function mount(): void
    {
        $this->loadRequisitions();
    }

    public function updatedTab(): void
    {
        $this->loadRequisitions();
    }

    protected function loadRequisitions(): void
    {
        $this->requisitions = DB::table('purchase_requisitions')
            ->join('users', 'purchase_requisitions.requested_by', '=', 'users.user_id')
            ->leftJoin('employees', 'users.user_id', '=', 'employees.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('purchase_requisitions.status', $this->tab)
            ->select(
                'purchase_requisitions.requisition_id',
                'users.full_name as employee_name',
                'departments.department_name as dept_name',
                'purchase_requisitions.status',
                'purchase_requisitions.date_requested',
                'purchase_requisitions.created_at'
            )
            ->orderBy('purchase_requisitions.date_requested', 'desc')
            ->get()
            ->map(function ($req) {
                // Fetch remarks for this requisition
                $remarks = DB::table('requisition_items')
                    ->where('requisition_id', $req->requisition_id)
                    ->pluck('remarks')
                    ->filter()
                    ->implode('; ');

                $req->remarks = $remarks ?: '-';
                
                // Human-readable code
                $req->code = 'REQ-' . date('Ymd', strtotime($req->date_requested)) . '-' . str_pad($req->requisition_id, 4, '0', STR_PAD_LEFT);
                
                // Format date for display
                $req->formatted_date = date('M d, Y', strtotime($req->date_requested));
                
                return $req;
            })
            ->toArray();
    }

    public function deleteRequisition(int $requisitionId): void
    {
        // Correct table name - should be 'purchase_requisitions' not 'requisition'
        DB::table('purchase_requisitions')->where('requisition_id', $requisitionId)->delete();

        // Optional: Also delete related items
        DB::table('requisition_items')->where('requisition_id', $requisitionId)->delete();

        $this->loadRequisitions();
    }
};
?>

<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold text-green-800 mb-6 text-center">Requisition Status</h1>

    <!-- Tabs - Updated to match your ENUM values -->
    <div class="flex justify-center mb-6 space-x-4">
        @foreach(['draft', 'submitted', 'approved', 'rejected', 'converted_to_po'] as $statusTab)
            <button
                wire:click="$set('tab', '{{ $statusTab }}')"
                class="px-4 py-2 rounded-lg font-semibold transition
                       {{ $tab === $statusTab ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-green-50' }}">
                {{ ucfirst(str_replace('_', ' ', $statusTab)) }}
            </button>
        @endforeach
    </div>

    @if(count($requisitions) === 0)
        <p class="text-gray-600 text-center">No {{ ucfirst(str_replace('_', ' ', $tab)) }} requisitions found.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow">
                <thead>
                    <tr class="bg-green-600 text-white">
                        <th class="px-6 py-3 text-left">Requisition Code</th>
                        <th class="px-6 py-3 text-left">Requested By</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-left">Date Requested</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Remarks</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requisitions as $req)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-gray-700">{{ $req->code }}</td>
                            <td class="px-6 py-4">{{ $req->employee_name }}</td>
                            <td class="px-6 py-4">{{ $req->dept_name ?? 'N/A' }}</td>
                            <td class="px-6 py-4">{{ $req->formatted_date }}</td>
                            <td class="px-6 py-4">
                                @if($req->status === 'draft')
                                    <span class="px-3 py-1 rounded-full bg-gray-200 text-gray-800 font-semibold">Draft</span>
                                @elseif($req->status === 'submitted')
                                    <span class="px-3 py-1 rounded-full bg-blue-200 text-blue-800 font-semibold">Submitted</span>
                                @elseif($req->status === 'approved')
                                    <span class="px-3 py-1 rounded-full bg-green-200 text-green-800 font-semibold">Approved</span>
                                @elseif($req->status === 'rejected')
                                    <span class="px-3 py-1 rounded-full bg-red-200 text-red-800 font-semibold">Rejected</span>
                                @elseif($req->status === 'converted_to_po')
                                    <span class="px-3 py-1 rounded-full bg-purple-200 text-purple-800 font-semibold">Converted to PO</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $req->remarks }}</td>
                            <td class="px-6 py-4 space-x-2">
                                @if($req->status === 'draft')
                                    <button 
                                        wire:click="deleteRequisition({{ $req->requisition_id }})" 
                                        onclick="return confirm('Are you sure you want to delete this requisition?')"
                                        class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                        Delete
                                    </button>
                                    <!-- <button 
                                        wire:click="submitRequisition({{ $req->requisition_id }})"
                                        class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                                        Submit
                                    </button> -->
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>