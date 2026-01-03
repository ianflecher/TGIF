<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.purchasing')] class extends Component
{
    public array $requisitions = [];
    public string $tab = 'Pending'; // default tab

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
        $this->requisitions = DB::table('requisition')
            ->join('employee', 'requisition.employee_id', '=', 'employee.employee_id')
            ->join('department', 'employee.dept_id', '=', 'department.dept_id')
            ->where('requisition.status', $this->tab)
            ->select(
                'requisition.requisition_id',
                'employee.name as employee_name',
                'department.dept_name',
                'requisition.status',
                'requisition.date'
            )
            ->orderBy('requisition.date', 'desc')
            ->get()
            ->map(function ($req) {
                // Fetch remarks for this requisition
                $remarks = DB::table('requisition_item')
                    ->where('requisition_id', $req->requisition_id)
                    ->pluck('remarks')
                    ->filter()
                    ->implode('; ');

                $req->remarks = $remarks ?: '-';
                // Human-readable code
                $req->code = 'REQ-' . date('Ymd', strtotime($req->date)) . '-' . str_pad($req->requisition_id, 4, '0', STR_PAD_LEFT);
                return $req;
            })
            ->toArray();
    }

    public function deleteRequisition(int $requisitionId): void
{
    DB::table('requisition')->where('requisition_id', $requisitionId)->delete();

    // Optional: Also delete related items
    DB::table('requisition_item')->where('requisition_id', $requisitionId)->delete();

    $this->loadRequisitions();
}

};
?>

<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold text-green-800 mb-6 text-center">Requisition Status</h1>

    <!-- Tabs -->
    <div class="flex justify-center mb-6 space-x-4">
        @foreach(['Pending', 'Approved', 'Rejected'] as $statusTab)
            <button
                wire:click="$set('tab', '{{ $statusTab }}')"
                class="px-4 py-2 rounded-lg font-semibold transition
                       {{ $tab === $statusTab ? 'bg-green-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-green-50' }}">
                {{ $statusTab }}
            </button>
        @endforeach
    </div>

    @if(count($requisitions) === 0)
        <p class="text-gray-600 text-center">No {{ $tab }} requisitions found.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow">
                <thead>
    <tr class="bg-green-600 text-white">
        <th class="px-6 py-3 text-left">Requisition Code</th>
        <th class="px-6 py-3 text-left">Employee</th>
        <th class="px-6 py-3 text-left">Department</th>
        <th class="px-6 py-3 text-left">Date</th>
        <th class="px-6 py-3 text-left">Status</th>
        <th class="px-6 py-3 text-left">Remarks</th>
        <th class="px-6 py-3 text-left">Delete</th> <!-- New header -->
    </tr>
</thead>
<tbody>
    @foreach($requisitions as $req)
        <tr class="border-b hover:bg-gray-50">
            <td class="px-6 py-4 font-mono text-gray-700">{{ $req->code }}</td>
            <td class="px-6 py-4">{{ $req->employee_name }}</td>
            <td class="px-6 py-4">{{ $req->dept_name }}</td>
            <td class="px-6 py-4">{{ $req->date }}</td>
            <td class="px-6 py-4">
                @if($req->status === 'Pending')
                    <span class="px-3 py-1 rounded-full bg-yellow-200 text-yellow-800 font-semibold">{{ $req->status }}</span>
                @elseif($req->status === 'Approved')
                    <span class="px-3 py-1 rounded-full bg-green-200 text-green-800 font-semibold">{{ $req->status }}</span>
                @else
                    <span class="px-3 py-1 rounded-full bg-red-200 text-red-800 font-semibold">{{ $req->status }}</span>
                @endif
            </td>
            <td class="px-6 py-4">{{ $req->remarks }}</td>
            <td class="px-6 py-4">
                <button 
                    wire:click="deleteRequisition({{ $req->requisition_id }})" 
                    class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition">
                    Delete
                </button>
            </td>
        </tr>
    @endforeach
</tbody>

            </table>
        </div>
    @endif
</div>
