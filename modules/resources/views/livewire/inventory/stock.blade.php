<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.inventory')] class extends Component
{
    use WithPagination;
    
    public $inventories = [];
    public $requisitions = [];
    public $suppliers = [];
    
    // Search and filter properties
    public $search = '';
    public $statusFilter = 'active';
    public $lowStockOnly = false;
    
    // Modal properties
    public $showModal = false;
    public $currentRequisitionId = null;
    public $requisitionItems = [];
    public $selectedSupplierId = null;
    public $modalData = [
        'requested_by' => '',
        'date_requested' => '',
        'estimated_cost' => 0,
    ];
    
    public function mount()
    {
        $this->loadData();
    }
    
    public function loadData()
    {
        // Load inventories with filtering
        $query = DB::table('inventories')
            ->where('status', '!=', 'deleted');
            
        if ($this->search) {
            $query->where(function($q) {
                $q->where('product_name', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }
        
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        
        if ($this->lowStockOnly) {
            $query->whereRaw('quantity <= min_quantity')
                  ->where('quantity', '>', 0);
        }
        
        $this->inventories = $query->orderBy('product_name')->get();
        
        // Load approved requisitions
        $this->requisitions = DB::table('purchase_requisitions')
            ->select('purchase_requisitions.*', 'users.full_name as requested_by_name')
            ->leftJoin('users', 'purchase_requisitions.requested_by', '=', 'users.user_id')
            ->where('purchase_requisitions.status', 'draft')
            ->orderBy('purchase_requisitions.date_requested', 'desc')
            ->get();
        
        // Load active suppliers
        $this->suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
    
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'lowStockOnly'])) {
            $this->loadData();
        }
    }
    
    public function getStockLevelColor($quantity, $minQuantity)
    {
        if ($quantity <= 0) {
            return 'bg-red-100 text-red-800';
        } elseif ($quantity <= $minQuantity) {
            return 'bg-yellow-100 text-yellow-800';
        } else {
            return 'bg-green-100 text-green-800';
        }
    }
    
    public function getStockLevelText($quantity, $minQuantity)
    {
        if ($quantity <= 0) {
            return 'Out of Stock';
        } elseif ($quantity <= $minQuantity) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
    
    public function openProcessModal($requisitionId)
{
    $this->currentRequisitionId = $requisitionId;
    
    // Load requisition details
    $requisition = DB::table('purchase_requisitions')
        ->select('purchase_requisitions.*', 'users.full_name as requested_by_name')
        ->leftJoin('users', 'purchase_requisitions.requested_by', '=', 'users.user_id')
        ->where('requisition_id', $requisitionId)
        ->first();
    
    if ($requisition) {
        $this->modalData = [
            'requested_by' => $requisition->requested_by_name ?? 'User #' . $requisition->requested_by,
            'date_requested' => Carbon::parse($requisition->date_requested)->format('M d, Y'),
            'estimated_cost' => $requisition->estimated_cost ?? 0,
        ];
    }
    
    // Load requisition items with inventory data
    $this->requisitionItems = DB::table('requisition_items')
        ->where('requisition_items.requisition_id', $requisitionId)
        ->get()
        ->map(function($item) {
            // Debug: Log what we're finding
            // \Log::info('Processing requisition item:', ['item' => $item]);
            
            // Check if item has product_id (from your requisition creation form)
            if ($item->product_id) {
                $product = DB::table('products')
                    ->where('product_id', $item->product_id)
                    ->first();
                
                if ($product) {
                    // Try to find matching inventory item
                    $inventory = DB::table('inventories')
                        ->where('product_name', 'like', '%' . $product->product_name . '%')
                        ->first();
                    
                    if ($inventory) {
                        $item->sku = $inventory->sku;
                        $item->product_name = $inventory->product_name;
                        $item->current_stock = $inventory->quantity;
                        $item->min_quantity = $inventory->min_quantity;
                        $item->unit_price = $inventory->unit_price;
                        $item->description = $inventory->description;
                        $item->inventory_id = $inventory->inventory_id; // Make sure inventory_id is set
                    } else {
                        $item->sku = 'N/A';
                        $item->product_name = $product->product_name;
                        $item->current_stock = 0;
                        $item->min_quantity = 0;
                        $item->unit_price = $product->price;
                        $item->description = $product->description ?? null;
                        $item->inventory_id = null;
                    }
                } else {
                    $item->sku = 'N/A';
                    $item->product_name = 'Unknown Product';
                    $item->current_stock = 0;
                    $item->min_quantity = 0;
                    $item->unit_price = 0;
                    $item->description = null;
                    $item->inventory_id = null;
                }
            } else {
                // No product_id or inventory_id
                $item->sku = 'N/A';
                $item->product_name = 'Unknown Product';
                $item->current_stock = 0;
                $item->min_quantity = 0;
                $item->unit_price = 0;
                $item->description = null;
                $item->inventory_id = null;
            }
            
            return $item;
        })
        ->toArray();
    
    $this->selectedSupplierId = null;
    $this->showModal = true;
}
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->currentRequisitionId = null;
        $this->requisitionItems = [];
        $this->selectedSupplierId = null;
        $this->modalData = [
            'requested_by' => '',
            'date_requested' => '',
            'estimated_cost' => 0,
        ];
    }
    
    public function processRequisition()
{
    if (!$this->currentRequisitionId) {
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'No requisition selected'
        ]);
        return;
    }
    
    // Check if PO already exists for this requisition
    $existingPO = DB::table('purchase_orders')
        ->where('requisition_id', $this->currentRequisitionId)
        ->first();
        
    if ($existingPO) {
        $this->dispatch('show-notification', [
            'type' => 'warning',
            'message' => 'Purchase Order already exists for this requisition! PO#' . $existingPO->po_number
        ]);
        return;
    }
    
    // Check if supplier is needed and selected
    $needsSupplier = false;
    foreach ($this->requisitionItems as $item) {
        if ($item->current_stock < $item->quantity) {
            $needsSupplier = true;
            break;
        }
    }
    
    if ($needsSupplier && !$this->selectedSupplierId) {
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'Please select a supplier for items that need a purchase order!'
        ]);
        return;
    }
    
    DB::beginTransaction();
    
    try {
        // Load requisition details
        $requisitionDetails = DB::table('purchase_requisitions')
            ->where('requisition_id', $this->currentRequisitionId)
            ->first();
            
        if (!$requisitionDetails) {
            $this->dispatch('show-notification', [
                'type' => 'error',
                'message' => 'Requisition not found!'
            ]);
            return;
        }
        
        $allocatedItems = [];
        $poItems = [];
        $totalAmount = 0;
        
        // Process each item
        foreach ($this->requisitionItems as $item) {
            // Cast to array if it's an object
            $item = (array) $item;
            
            if ($item['current_stock'] >= $item['quantity'] && !empty($item['inventory_id'])) {
                // Allocate from existing stock
                $allocatedItems[] = [
                    'product_name' => $item['product_name'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'current_stock' => $item['current_stock'],
                    'new_stock' => $item['current_stock'] - $item['quantity']
                ];
                
                // Update inventory
                DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->decrement('quantity', $item['quantity']);
                
                // Create allocation record if table exists
                if (DB::getSchemaBuilder()->hasTable('inventory_allocations')) {
                    DB::table('inventory_allocations')->insert([
                        'inventory_id' => $item['inventory_id'],
                        'requisition_id' => $this->currentRequisitionId,
                        'quantity' => $item['quantity'],
                        'allocated_by' => Auth::id(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }
                
            } else {
                // Create PO for this item
                $poItems[] = $item;
                $itemTotal = $item['quantity'] * ($item['unit_price'] ?? 0);
                $totalAmount += $itemTotal;
            }
        }
        
        // Create PO if there are PO items
        $poId = null;
        $poNumber = null;
        
        if (!empty($poItems)) {
            // Generate PO number
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad($this->currentRequisitionId, 4, '0', STR_PAD_LEFT);
            
            // Create purchase order
            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'requisition_id' => $this->currentRequisitionId,
                'supplier_id' => $this->selectedSupplierId,
                'order_date' => Carbon::now()->toDateString(),
                'expected_delivery_date' => Carbon::parse($requisitionDetails->required_date ?? Carbon::now()->addDays(7))->toDateString(),
                'total_amount' => $totalAmount,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            // Create purchase order items
            foreach ($poItems as $item) {
                // Check if inventory_id exists, if not, create inventory entry
                if (empty($item['inventory_id'])) {
                    // Generate SKU
                    $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item['product_name']), 0, 6)) . '-' . date('ymd');
                    
                    // Create new inventory entry
                    $inventoryId = DB::table('inventories')->insertGetId([
                        'product_name' => $item['product_name'],
                        'sku' => $sku,
                        'description' => $item['description'] ?? null,
                        'quantity' => 0, // Will be updated when PO is received
                        'min_quantity' => $item['min_quantity'] ?? 10,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'status' => 'active',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    
                    $item['inventory_id'] = $inventoryId;
                }
                
                // Calculate total price for this item
                $totalPrice = $item['quantity'] * ($item['unit_price'] ?? 0);
                
                // Insert into purchase_order_items table
                DB::table('purchase_order_items')->insert([
                    'po_id' => $poId,
                    'inventory_id' => $item['inventory_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $totalPrice,
                    'description' => $item['description'] ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }
        
        // Update requisition status based on actions
        $requisitionStatus = !empty($poItems) ? 'converted_to_po' : 'fulfilled';
        
        DB::table('purchase_requisitions')
            ->where('requisition_id', $this->currentRequisitionId)
            ->update([
                'status' => $requisitionStatus,
                'updated_at' => Carbon::now()
            ]);
        
        DB::commit();
        
        // Prepare success message
        $message = '';
        if (!empty($allocatedItems)) {
            $message .= "✓ " . count($allocatedItems) . " items allocated from inventory. ";
        }
        if (!empty($poItems)) {
            $message .= "✓ " . count($poItems) . " items added to Purchase Order #{$poNumber}. ";
        }
        
        $this->dispatch('show-notification', [
            'type' => 'success',
            'message' => $message
        ]);
        
        $this->closeModal();
        $this->loadData(); // Refresh data
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('Failed to process requisition', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'Failed to process requisition: ' . $e->getMessage()
        ]);
    }
}
    
    // Calculate allocation statistics
    public function getItemsStatistics()
    {
        if (empty($this->requisitionItems)) {
            return [
                'allocate_count' => 0,
                'po_count' => 0,
                'total_amount' => 0,
                'po_amount' => 0,
            ];
        }
        
        $allocate_count = 0;
        $po_count = 0;
        $total_amount = 0;
        $po_amount = 0;
        
        foreach ($this->requisitionItems as $item) {
            $itemTotal = $item->quantity * ($item->unit_price ?? 0);
            $total_amount += $itemTotal;
            
            if ($item->current_stock >= $item->quantity) {
                $allocate_count++;
            } else {
                $po_count++;
                $po_amount += $itemTotal;
            }
        }
        
        return [
            'allocate_count' => $allocate_count,
            'po_count' => $po_count,
            'total_amount' => $total_amount,
            'po_amount' => $po_amount,
        ];
    }
};
?>
<div>
<div class="p-6 min-h-screen bg-gradient-to-br from-green-50 to-emerald-50">
    
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-emerald-900">Requisition Processing</h1>
        <p class="text-emerald-700 mt-2">Allocate from inventory or create purchase orders for requisitions</p>
    </div>
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search by SKU or product name..."
                       class="w-full px-4 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
            </div>
            <div>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="discontinued">Discontinued</option>
                </select>
            </div>
            <div class="flex items-center">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" 
                           wire:model.live="lowStockOnly" 
                           class="mr-2 h-5 w-5 text-emerald-600 rounded focus:ring-emerald-500">
                    <span class="text-emerald-800 font-medium">Show Low Stock Only</span>
                </label>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- INVENTORY -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-800 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-boxes mr-3"></i>
                    Inventory Stock ({{ count($inventories) }} items)
                </h2>
            </div>
            
            <div class="p-6">
                @if(count($inventories) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-emerald-50">
                            <tr>
                                <th class="p-3 text-left text-emerald-800 font-semibold">SKU</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Product</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Stock</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Min</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($inventories as $inv)
                            <tr class="hover:bg-emerald-50 transition-colors">
                                <td class="p-3 font-mono font-medium text-emerald-900">{{ $inv->sku }}</td>
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $inv->product_name }}</div>
                                    <div class="text-xs text-gray-600">{{ Str::limit($inv->description ?? '', 40) }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold {{ $inv->quantity < $inv->min_quantity ? 'text-red-600' : 'text-emerald-700' }}">
                                        {{ $inv->quantity }}
                                    </div>
                                </td>
                                <td class="p-3 text-gray-700">{{ $inv->min_quantity }}</td>
                                <td class="p-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $this->getStockLevelColor($inv->quantity, $inv->min_quantity) }}">
                                        {{ $this->getStockLevelText($inv->quantity, $inv->min_quantity) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-12">
                    <div class="text-emerald-400 mb-4">
                        <i class="fas fa-inbox text-5xl"></i>
                    </div>
                    <p class="text-gray-600">No inventory items found</p>
                    <p class="text-sm text-gray-500 mt-1">Try adjusting your search or filters</p>
                </div>
                @endif
            </div>
        </div>
        
        <!-- PURCHASE REQUISITIONS -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-alt mr-3"></i>
                    Purchase Requisitions ({{ count($requisitions) }})
                </h2>
            </div>
            
            <div class="p-6">
                @if(count($requisitions) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="p-3 text-left text-blue-800 font-semibold">Req #</th>
                                <th class="p-3 text-left text-blue-800 font-semibold">Requested By</th>
                                <th class="p-3 text-left text-blue-800 font-semibold">Date</th>
                                <th class="p-3 text-left text-blue-800 font-semibold">Estimated</th>
                                <th class="p-3 text-left text-blue-800 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($requisitions as $req)
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="p-3 font-mono font-bold text-blue-900">#{{ $req->requisition_id }}</td>
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $req->requested_by_name ?? 'User #' . $req->requested_by }}</div>
                                    <div class="text-xs text-gray-600">Dept: {{ $req->department_id ?? 'N/A' }}</div>
                                </td>
                                <td class="p-3 text-gray-700">
                                    {{ Carbon::parse($req->date_requested)->format('M d, Y') }}
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-emerald-700">₱{{ number_format($req->estimated_cost ?? 0, 2) }}</div>
                                </td>
                                <td class="p-3">
                                    <button wire:click="openProcessModal({{ $req->requisition_id }})"
                                            class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                                        <i class="fas fa-cogs mr-2"></i>
                                        Process
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-12">
                    <div class="text-blue-400 mb-4">
                        <i class="fas fa-file-alt text-5xl"></i>
                    </div>
                    <p class="text-gray-600">No requisitions found</p>
                    <p class="text-sm text-gray-500 mt-1">All requisitions have been processed or are pending approval</p>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="bg-emerald-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-box text-emerald-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Items</p>
                    <p class="text-2xl font-bold text-emerald-800">{{ count($inventories) }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Requisitions</p>
                    <p class="text-2xl font-bold text-blue-800">{{ count($requisitions) }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Active Suppliers</p>
                    <p class="text-2xl font-bold text-purple-800">{{ count($suppliers) }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div>
                    @php
                        $lowStockCount = collect($inventories)->where('quantity', '<=', DB::raw('min_quantity'))->where('quantity', '>', 0)->count();
                    @endphp
                    <p class="text-sm text-gray-600">Low Stock Items</p>
                    <p class="text-2xl font-bold text-yellow-800">{{ $lowStockCount }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
@if($showModal)
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-emerald-600 to-emerald-800 px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">
                        Process Purchase Requisition
                    </h3>
                    <p class="text-emerald-200 text-sm mt-1">
                        Requisition #{{ $currentRequisitionId }} • {{ $modalData['requested_by'] }}
                    </p>
                </div>
                <button wire:click="closeModal" 
                        class="text-white hover:text-emerald-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            @php
                $stats = $this->getItemsStatistics();
            @endphp
            
            <!-- Debug info - remove after testing -->
            <!-- <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="text-sm">
                    <p><strong>Debug Info:</strong></p>
                    <p>PO Count: {{ $stats['po_count'] }}</p>
                    <p>Selected Supplier ID: {{ $selectedSupplierId ?: 'None selected' }}</p>
                    <p>Suppliers loaded: {{ count($suppliers) }}</p>
                    <p>Button disabled: {{ $stats['po_count'] > 0 && !$selectedSupplierId ? 'YES' : 'NO' }}</p>
                </div>
            </div> -->
            
            <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-emerald-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-emerald-800 mb-2">Requisition Information</h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-emerald-600">Requested Date:</span>
                            <span class="font-medium">{{ $modalData['date_requested'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-emerald-600">Estimated Cost:</span>
                            <span class="font-bold text-emerald-700">₱{{ number_format($modalData['estimated_cost'], 2) }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Supplier Selection -->
                @if($stats['po_count'] > 0)
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">Select Supplier for PO Items</h4>
                    <select wire:model.live="selectedSupplierId"
                            class="w-full px-4 py-3 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Choose a supplier...</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->supplier_id }}">
                                {{ $sup->name }}
                                @if($sup->contact_person)
                                    • {{ $sup->contact_person }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    
                    <!-- Show selected supplier info -->
                    @if($selectedSupplierId)
                        @php
                            $selectedSupplier = collect($suppliers)->firstWhere('supplier_id', $selectedSupplierId);
                        @endphp
                        <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded">
                            <p class="text-sm text-green-700">
                                <i class="fas fa-check-circle mr-1"></i>
                                Selected: <strong>{{ $selectedSupplier->name }}</strong>
                                @if($selectedSupplier->contact_person)
                                    ({{ $selectedSupplier->contact_person }})
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
                @endif
            </div>
            
            <!-- Items List -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        <span>Items Processing Plan ({{ count($requisitionItems) }})</span>
                    </h4>
                    <div class="text-sm text-gray-600">
                        <span class="inline-flex items-center mr-3">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                            Allocate: {{ $stats['allocate_count'] }}
                        </span>
                        <span class="inline-flex items-center">
                            <span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                            PO: {{ $stats['po_count'] }}
                        </span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-3 text-left text-gray-700 font-semibold">Product</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Current Stock</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Needed Qty</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Action</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Reason</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Unit Price</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($requisitionItems as $item)
                            @php
                                $itemTotal = $item->quantity * ($item->unit_price ?? 0);
                                $canAllocate = $item->current_stock >= $item->quantity;
                                $action = $canAllocate ? 'allocate' : 'po';
                            @endphp
                            <tr class="{{ $canAllocate ? 'bg-green-50' : 'bg-blue-50' }}">
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $item->product_name ?? 'N/A' }}</div>
                                    @if($item->remarks)
                                    <div class="text-xs text-gray-600">{{ $item->remarks }}</div>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <div class="font-bold {{ !$canAllocate ? 'text-red-600' : 'text-emerald-700' }}">
                                        {{ $item->current_stock ?? 0 }}
                                    </div>
                                </td>
                                <td class="p-3 font-bold text-blue-700">{{ $item->quantity }}</td>
                                <td class="p-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $canAllocate ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $canAllocate ? 'Allocate from Stock' : 'Create PO' }}
                                    </span>
                                </td>
                                <td class="p-3 text-sm text-gray-700">
                                    {{ $canAllocate ? "Enough stock available (Current: {$item->current_stock}, Needed: {$item->quantity})" : "Insufficient stock (Current: {$item->current_stock}, Needed: {$item->quantity})" }}
                                </td>
                                <td class="p-3 text-gray-700">₱{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                <td class="p-3 font-bold {{ $canAllocate ? 'text-emerald-700' : 'text-blue-700' }}">₱{{ number_format($itemTotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            @if($stats['po_count'] > 0)
                            <tr>
                                <td colspan="6" class="p-3 text-right font-bold text-gray-800">PO Items Total:</td>
                                <td colspan="2" class="p-3 font-bold text-lg text-blue-700">₱{{ number_format($stats['po_amount'], 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="6" class="p-3 text-right font-bold text-gray-800">Grand Total:</td>
                                <td colspan="2" class="p-3 font-bold text-lg text-emerald-700">₱{{ number_format($stats['total_amount'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="border-t px-6 py-4 bg-gray-50">
            <div class="flex justify-between items-center">
                <div>
                    @if($stats['po_count'] === 0)
                    <div class="text-sm text-green-700">
                        <i class="fas fa-check-circle mr-1"></i>
                        All items can be allocated from stock
                    </div>
                    @elseif($selectedSupplierId)
                    <div class="text-sm text-blue-700">
                        <i class="fas fa-check-circle mr-1"></i>
                        Supplier selected for PO items
                    </div>
                    @else
                    <div class="text-sm text-red-700">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Please select a supplier
                    </div>
                    @endif
                </div>
                <div class="flex gap-3">
                    <button wire:click="closeModal"
                            class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    
                    <button wire:click="processRequisition"
                            @disabled($stats['po_count'] > 0 && !$selectedSupplierId)
                            class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                        <i class="fas fa-cogs mr-2"></i>
                        <span>
                            @if($stats['allocate_count'] > 0 && $stats['po_count'] > 0)
                                Allocate & Create PO
                            @elseif($stats['allocate_count'] > 0)
                                Allocate from Stock
                            @else
                                Create Purchase Order
                            @endif
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Notification Script -->
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('show-notification', (event) => {
            showNotification(event.type, event.message);
        });
    });

    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg border-l-4 ${
            type === 'success' ? 'bg-green-100 border-green-500 text-green-700' :
            type === 'error' ? 'bg-red-100 border-red-500 text-red-700' :
            type === 'warning' ? 'bg-yellow-100 border-yellow-500 text-yellow-700' :
            'bg-blue-100 border-blue-500 text-blue-700'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-3 text-xl"></i>
                <div>
                    <p class="font-medium">${message}</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 500);
        }, 5000);
    }
</script>
</div>