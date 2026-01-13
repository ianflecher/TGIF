<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public array $requisitions = [];
    public string $tab = 'draft'; // default tab
    public array $supplierStats = [];
    public array $recentReceipts = [];
    
    // All possible statuses from your ENUM
    public array $statusTabs = ['draft', 'submitted', 'approved', 'rejected', 'converted_to_po'];

    public function mount(): void
    {
        $this->loadRequisitions();
        $this->loadSupplierStats();
        $this->loadRecentReceipts();
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
                'purchase_requisitions.estimated_cost',
                'purchase_requisitions.justification',
                'purchase_requisitions.required_date',
                'purchase_requisitions.created_at'
            )
            ->orderBy('purchase_requisitions.date_requested', 'desc')
            ->get()
            ->map(function ($req) {
                // Fetch items for this requisition
                $items = DB::table('requisition_items')
                    ->leftJoin('inventories', 'requisition_items.inventory_id', '=', 'inventories.inventory_id')
                    ->where('requisition_id', $req->requisition_id)
                    ->select(
                        'requisition_items.quantity',
                        'requisition_items.remarks',
                        'requisition_items.purpose',
                        'inventories.product_name',
                        'inventories.sku'
                    )
                    ->get();

                $req->items = $items;
                $req->item_count = count($items);
                
                // Human-readable code
                $req->code = 'REQ-' . date('Ymd', strtotime($req->date_requested)) . '-' . str_pad($req->requisition_id, 4, '0', STR_PAD_LEFT);
                
                // Format dates for display
                $req->formatted_date = date('M d, Y', strtotime($req->date_requested));
                $req->formatted_required_date = $req->required_date ? date('M d, Y', strtotime($req->required_date)) : 'Not specified';
                
                // Format cost
                $req->formatted_cost = $req->estimated_cost ? '₱' . number_format($req->estimated_cost, 2) : 'Not estimated';
                
                // Status display with appropriate colors
                $req->status_display = $this->getStatusDisplay($req->status);
                
                return $req;
            })
            ->toArray();
    }

    protected function loadSupplierStats(): void
    {
        $this->supplierStats = DB::table('suppliers')
            ->leftJoin('purchase_orders', 'suppliers.supplier_id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('goods_receipts', 'purchase_orders.po_id', '=', 'goods_receipts.po_id')
            ->select(
                'suppliers.supplier_id',
                'suppliers.name as supplier_name',
                'suppliers.contact_person',
                'suppliers.rating',
                'suppliers.status',
                DB::raw('COUNT(DISTINCT purchase_orders.po_id) as total_orders'),
                DB::raw('COUNT(goods_receipts.receipt_id) as total_receipts'),
                DB::raw('COALESCE(AVG(goods_receipts.quality_status = "passed"), 1) * 100 as quality_rate')
            )
            ->where('suppliers.status', 'active')
            ->groupBy('suppliers.supplier_id', 'suppliers.name', 'suppliers.contact_person', 'suppliers.rating', 'suppliers.status')
            ->orderBy('suppliers.rating', 'desc')
            ->orderBy('total_orders', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($supplier) {
                $supplier->star_rating = $this->getStarRating($supplier->rating);
                $supplier->rating_color = $this->getRatingColor($supplier->rating);
                return $supplier;
            })
            ->toArray();
    }

    protected function loadRecentReceipts(): void
    {
        $this->recentReceipts = DB::table('goods_receipts')
            ->join('purchase_orders', 'goods_receipts.po_id', '=', 'purchase_orders.po_id')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.supplier_id')
            ->join('inventories', 'goods_receipts.inventory_id', '=', 'inventories.inventory_id')
            ->leftJoin('users', 'goods_receipts.received_by', '=', 'users.user_id')
            ->select(
                'goods_receipts.receipt_id',
                'goods_receipts.po_id',
                'purchase_orders.po_number',
                'suppliers.name as supplier_name',
                'inventories.product_name',
                'goods_receipts.quantity_received',
                'goods_receipts.date_received',
                'goods_receipts.quality_status',
                'goods_receipts.batch_number',
                'goods_receipts.notes',
                'users.full_name as received_by_name'
            )
            ->orderBy('goods_receipts.date_received', 'desc')
            ->orderBy('goods_receipts.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($receipt) {
                $receipt->quality_badge = $this->getQualityBadge($receipt->quality_status);
                $receipt->formatted_date = date('M d, Y', strtotime($receipt->date_received));
                return $receipt;
            })
            ->toArray();
    }

    private function getStatusDisplay(string $status): array
    {
        $statusConfig = [
            'draft' => [
                'label' => 'Draft',
                'color' => 'bg-gray-200 text-gray-800',
                'icon' => '📝'
            ],
            'submitted' => [
                'label' => 'Submitted',
                'color' => 'bg-blue-200 text-blue-800',
                'icon' => '📤'
            ],
            'approved' => [
                'label' => 'Approved',
                'color' => 'bg-green-200 text-green-800',
                'icon' => '✅'
            ],
            'rejected' => [
                'label' => 'Rejected',
                'color' => 'bg-red-200 text-red-800',
                'icon' => '❌'
            ],
            'converted_to_po' => [
                'label' => 'Converted to PO',
                'color' => 'bg-purple-200 text-purple-800',
                'icon' => '📋'
            ]
        ];

        return $statusConfig[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'bg-gray-200 text-gray-800',
            'icon' => '❓'
        ];
    }

    private function getStarRating($rating): string
    {
        $stars = '';
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        for ($i = 0; $i < $fullStars; $i++) {
            $stars .= '★';
        }
        
        if ($halfStar) {
            $stars .= '½';
        }
        
        for ($i = 0; $i < $emptyStars; $i++) {
            $stars .= '☆';
        }
        
        return $stars;
    }

    private function getRatingColor($rating): string
    {
        if ($rating >= 4.5) return 'text-green-600';
        if ($rating >= 3.5) return 'text-yellow-600';
        if ($rating >= 2.5) return 'text-orange-600';
        return 'text-red-600';
    }

    private function getQualityBadge($status): string
    {
        $badges = [
            'passed' => '<span class="px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs font-medium">Passed</span>',
            'failed' => '<span class="px-2 py-1 rounded-full bg-red-100 text-red-800 text-xs font-medium">Failed</span>',
            'pending' => '<span class="px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-medium">Pending</span>',
            'partial' => '<span class="px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-medium">Partial</span>'
        ];
        
        return $badges[$status] ?? '<span class="px-2 py-1 rounded-full bg-gray-100 text-gray-800 text-xs font-medium">Unknown</span>';
    }

    public function deleteRequisition(int $requisitionId): void
    {
        DB::table('purchase_requisitions')->where('requisition_id', $requisitionId)->delete();
        DB::table('requisition_items')->where('requisition_id', $requisitionId)->delete();
        $this->loadRequisitions();
    }
    
    public function updateRequisitionStatus(int $requisitionId, string $newStatus): void
    {
        DB::table('purchase_requisitions')
            ->where('requisition_id', $requisitionId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);
        
        $this->loadRequisitions();
    }
};
?>

<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold text-green-800 mb-6 text-center">Requisition Status Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Supplier Rating Stats -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Rated Suppliers</h3>
            @if(count($supplierStats) > 0)
                <div class="space-y-3">
                    @foreach(array_slice($supplierStats, 0, 3) as $supplier)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div>
                            <h4 class="font-medium text-gray-900">{{ $supplier->supplier_name }}</h4>
                            <p class="text-sm text-gray-600">{{ $supplier->contact_person ?? 'No contact' }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold {{ $supplier->rating_color }}">{{ number_format($supplier->rating, 1) }}</div>
                            <div class="text-yellow-500 text-sm">{{ $supplier->star_rating }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center">No supplier data available</p>
            @endif
        </div>

        <!-- Recent Receipts -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Goods Receipts</h3>
            @if(count($recentReceipts) > 0)
                <div class="space-y-3">
                    @foreach(array_slice($recentReceipts, 0, 3) as $receipt)
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-medium text-gray-900">{{ $receipt->product_name }}</h4>
                                <p class="text-sm text-gray-600">PO#{{ $receipt->po_number }} • {{ $receipt->supplier_name }}</p>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-900">{{ $receipt->quantity_received }} units</div>
                                <div class="text-xs text-gray-500">{{ $receipt->formatted_date }}</div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm text-gray-600">Batch: {{ $receipt->batch_number ?? 'N/A' }}</span>
                            {!! $receipt->quality_badge !!}
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center">No recent receipts</p>
            @endif
        </div>

        <!-- Quick Stats -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Stats</h3>
            <div class="space-y-4">
                @php
                    $totalSuppliers = DB::table('suppliers')->where('status', 'active')->count();
                    $avgRating = DB::table('suppliers')->where('status', 'active')->avg('rating');
                    $totalReceipts = DB::table('goods_receipts')->count();
                    $passedReceipts = DB::table('goods_receipts')->where('quality_status', 'passed')->count();
                    $passRate = $totalReceipts > 0 ? ($passedReceipts / $totalReceipts) * 100 : 0;
                    
                    // Requisition stats
                    $totalRequisitions = DB::table('purchase_requisitions')->count();
                    $approvedRequisitions = DB::table('purchase_requisitions')->where('status', 'approved')->count();
                    $approvalRate = $totalRequisitions > 0 ? ($approvedRequisitions / $totalRequisitions) * 100 : 0;
                @endphp
                
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Active Suppliers</span>
                    <span class="font-bold text-green-700">{{ $totalSuppliers }}</span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Avg Supplier Rating</span>
                    <span class="font-bold {{ $avgRating >= 3.5 ? 'text-green-700' : ($avgRating >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($avgRating, 1) }}/5
                    </span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">Requisition Approval Rate</span>
                    <span class="font-bold {{ $approvalRate >= 80 ? 'text-green-700' : ($approvalRate >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($approvalRate, 1) }}%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs - All Statuses -->
    <div class="flex justify-center mb-6 space-x-2 flex-wrap">
        @foreach($statusTabs as $statusTab)
            <button
                wire:click="$set('tab', '{{ $statusTab }}')"
                class="px-4 py-2 rounded-lg font-semibold transition mb-2
                       {{ $tab === $statusTab 
                           ? 'bg-green-600 text-white' 
                           : 'bg-white text-gray-700 border border-gray-300 hover:bg-green-50' }}">
                {{ $this->getStatusDisplay($statusTab)['icon'] }}
                {{ $this->getStatusDisplay($statusTab)['label'] }}
            </button>
        @endforeach
    </div>

    <!-- Status Summary -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Requisition Summary ({{ count($requisitions) }} found)</h3>
        <div class="flex flex-wrap gap-4">
            @php
                $statusCounts = [];
                foreach ($statusTabs as $status) {
                    $statusCounts[$status] = DB::table('purchase_requisitions')->where('status', $status)->count();
                }
            @endphp
            
            @foreach($statusCounts as $status => $count)
                <div class="flex items-center">
                    <span class="mr-2">{{ $this->getStatusDisplay($status)['icon'] }}</span>
                    <span class="px-2 py-1 rounded-full text-xs font-medium {{ $this->getStatusDisplay($status)['color'] }}">
                        {{ $this->getStatusDisplay($status)['label'] }}: {{ $count }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @if(count($requisitions) === 0)
        <div class="text-center py-12 bg-white rounded-lg shadow">
            <div class="text-5xl mb-4">{{ $this->getStatusDisplay($tab)['icon'] }}</div>
            <p class="text-gray-600 text-lg mb-2">No {{ $this->getStatusDisplay($tab)['label'] }} requisitions found</p>
            <p class="text-gray-500">Change status tab or check back later</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow">
                <thead>
                    <tr class="bg-green-600 text-white">
                        <th class="px-6 py-3 text-left">Requisition Code</th>
                        <th class="px-6 py-3 text-left">Requested By</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-left">Date Requested</th>
                        <th class="px-6 py-3 text-left">Required By</th>
                        <th class="px-6 py-3 text-left">Estimated Cost</th>
                        <th class="px-6 py-3 text-left">Items</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requisitions as $req)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-gray-700">{{ $req->code }}</td>
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $req->employee_name }}</div>
                                <div class="text-sm text-gray-600">{{ $req->dept_name ?? 'No department' }}</div>
                            </td>
                            <td class="px-6 py-4">{{ $req->dept_name ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                <div>{{ $req->formatted_date }}</div>
                                <div class="text-xs text-gray-500">{{ date('h:i A', strtotime($req->created_at)) }}</div>
                            </td>
                            <td class="px-6 py-4">{{ $req->formatted_required_date }}</td>
                            <td class="px-6 py-4 font-medium {{ $req->estimated_cost ? 'text-green-700' : 'text-gray-500' }}">
                                {{ $req->formatted_cost }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <span class="text-gray-700">{{ $req->item_count }} item(s)</span>
                                    @if($req->item_count > 0)
                                        <button 
                                            onclick="showItems({{ $req->requisition_id }})"
                                            class="ml-2 text-blue-600 hover:text-blue-800 text-sm"
                                            title="View Items">
                                            View
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full font-semibold {{ $req->status_display['color'] }}">
                                    {{ $req->status_display['icon'] }} {{ $req->status_display['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 space-x-2">
                                @if($req->status === 'draft')
                                    <button 
                                        wire:click="deleteRequisition({{ $req->requisition_id }})" 
                                        onclick="return confirm('Are you sure you want to delete this requisition?')"
                                        class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm">
                                        Delete
                                    </button>
                                @endif
                                
                                @if(in_array($req->status, ['draft', 'submitted']))
                                    <button 
                                        wire:click="updateRequisitionStatus({{ $req->requisition_id }}, 'approved')" 
                                        onclick="return confirm('Approve this requisition?')"
                                        class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                                        Approve
                                    </button>
                                    <button 
                                        wire:click="updateRequisitionStatus({{ $req->requisition_id }}, 'rejected')" 
                                        onclick="return confirm('Reject this requisition?')"
                                        class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm">
                                        Reject
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
function showItems(requisitionId) {
    // You can implement a modal or dropdown to show items
    alert('Items for requisition #' + requisitionId + ' would be displayed here.');
    // In a real implementation, you might use Livewire to load and display items in a modal
}
</script>