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
    public $expiredItems = [];
    public $outOfStockItems = [];
    
    // Search and filter properties
    public $search = '';
    public $statusFilter = 'active';
    public $lowStockOnly = false;
    public $showExpired = false;
    public $showOutOfStock = false;
    
    // Modal properties
    public $showModal = false;
    public $showExpiredModal = false;
    public $showOutOfStockModal = false;
    public $currentRequisitionId = null;
    public $requisitionItems = [];
    public $selectedSupplierId = null;
    public $modalData = [
        'requested_by' => '',
        'date_requested' => '',
        'estimated_cost' => 0,
    ];
    
    // New modal for creating PO from out-of-stock items
    public $outOfStockSupplierId = null;
    public $selectedOutOfStockItems = [];
    
    public function mount()
    {
        $this->loadData();
    }
    
    public function loadData()
{
    // Load inventories with filtering and supplier info
    $query = DB::table('inventories')
        ->select(
            'inventories.*', 
            'suppliers.name as supplier_name', 
            'suppliers.supplier_id as supplier_id'
        )
        ->leftJoin('suppliers', 'inventories.supplier_id', '=', 'suppliers.supplier_id')
        ->where('inventories.status', '!=', 'deleted');
        
    if ($this->search) {
        $query->where(function($q) {
            $q->where('inventories.product_name', 'like', '%' . $this->search . '%')
              ->orWhere('inventories.sku', 'like', '%' . $this->search . '%')
              ->orWhere('suppliers.name', 'like', '%' . $this->search . '%');
        });
    }
    
    if ($this->statusFilter) {
        $query->where('inventories.status', $this->statusFilter);
    }
    
    if ($this->lowStockOnly) {
        $query->whereRaw('inventories.quantity <= inventories.min_quantity')
              ->where('inventories.quantity', '>', 0);
    }
    
    $this->inventories = $query->orderBy('inventories.product_name')->get();
    
    // Load expired items (within 30 days or already expired)
    $today = Carbon::now()->toDateString();
    $thirtyDaysFromNow = Carbon::now()->addDays(30)->toDateString();
    
    $this->expiredItems = DB::table('inventories')
        ->where('inventories.status', 'active')
        ->whereNotNull('expiration_date')
        ->where(function($query) use ($today, $thirtyDaysFromNow) {
            $query->where('expiration_date', '<', $today) // Already expired
                  ->orWhereBetween('expiration_date', [$today, $thirtyDaysFromNow]); // Expiring soon
        })
        ->orderBy('expiration_date')
        ->get();
    
    // Load out of stock items
    $this->outOfStockItems = DB::table('inventories')
        ->where('inventories.status', 'active')
        ->where('inventories.quantity', '<=', 0)
        ->orderBy('product_name')
        ->get();
    
    // Load approved requisitions
    $this->requisitions = DB::table('purchase_requisitions')
        ->select('purchase_requisitions.*', 'users.full_name as requested_by_name')
        ->leftJoin('users', 'purchase_requisitions.requested_by', '=', 'users.user_id')
        ->where('purchase_requisitions.status', 'draft')
        ->orderBy('purchase_requisitions.date_requested', 'desc')
        ->get();
    
    // Load active suppliers
    $this->suppliers = DB::table('suppliers')
        ->where('suppliers.status', 'active')
        ->orderBy('name')
        ->get();
}
    
    public function updated($property)
    {
        if (in_array($property, ['search', 'statusFilter', 'lowStockOnly', 'showExpired', 'showOutOfStock'])) {
            $this->loadData();
        }
    }
    
    public function getStockLevelColor($quantity, $minQuantity, $expirationDate = null)
    {
        // Check expiration first
        if ($expirationDate && Carbon::parse($expirationDate)->lt(Carbon::now())) {
            return 'bg-red-100 text-red-800';
        }
        
        if ($quantity <= 0) {
            return 'bg-red-100 text-red-800';
        } elseif ($quantity <= $minQuantity) {
            return 'bg-yellow-100 text-yellow-800';
        } else {
            return 'bg-green-100 text-green-800';
        }
    }
    
    public function getStockLevelText($quantity, $minQuantity, $expirationDate = null)
    {
        if ($expirationDate && Carbon::parse($expirationDate)->lt(Carbon::now())) {
            return 'Expired';
        }
        
        if ($quantity <= 0) {
            return 'Out of Stock';
        } elseif ($quantity <= $minQuantity) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
    
    public function getExpirationStatus($expirationDate)
    {
        if (!$expirationDate) return null;
        
        $expDate = Carbon::parse($expirationDate);
        $today = Carbon::now();
        
        if ($expDate->lt($today)) {
            return [
                'status' => 'expired',
                'text' => 'Expired ' . $expDate->diffForHumans(),
                'color' => 'text-red-600'
            ];
        }
        
        $daysDiff = $today->diffInDays($expDate);
        
        if ($daysDiff <= 7) {
            return [
                'status' => 'critical',
                'text' => 'Expires in ' . $daysDiff . ' days',
                'color' => 'text-red-600'
            ];
        } elseif ($daysDiff <= 30) {
            return [
                'status' => 'warning',
                'text' => 'Expires in ' . $daysDiff . ' days',
                'color' => 'text-yellow-600'
            ];
        }
        
        return [
            'status' => 'safe',
            'text' => 'Expires ' . $expDate->format('M d, Y'),
            'color' => 'text-green-600'
        ];
    }
    
    public function openProcessModal($requisitionId)
    {
        // ... existing code ...
    }
    
    public function openExpiredItemsModal()
    {
        $this->showExpiredModal = true;
    }
    
    public function openOutOfStockModal()
    {
        $this->selectedOutOfStockItems = [];
        $this->outOfStockSupplierId = null;
        $this->showOutOfStockModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->showExpiredModal = false;
        $this->showOutOfStockModal = false;
        $this->currentRequisitionId = null;
        $this->requisitionItems = [];
        $this->selectedSupplierId = null;
        $this->modalData = [
            'requested_by' => '',
            'date_requested' => '',
            'estimated_cost' => 0,
        ];
    }
    
    public function markAsDamaged($inventoryId, $quantity, $remarks = 'Expired item disposal')
    {
        DB::beginTransaction();
        
        try {
            // Get inventory item
            $inventory = DB::table('inventories')->where('inventory_id', $inventoryId)->first();
            
            if (!$inventory) {
                $this->dispatch('show-notification', [
                    'type' => 'error',
                    'message' => 'Inventory item not found!'
                ]);
                return;
            }
            
            // Calculate actual quantity to mark as damaged
            $actualQuantity = min($quantity, $inventory->quantity);
            
            // Update inventory quantity
            DB::table('inventories')
                ->where('inventory_id', $inventoryId)
                ->decrement('quantity', $actualQuantity);
            
            // Create stock transaction for damaged goods
            DB::table('stock_transactions')->insert([
                'inventory_id' => $inventoryId,
                'type' => 'damage',
                'quantity' => $actualQuantity,
                'unit_cost' => $inventory->cost_price,
                'reference_type' => 'expiration',
                'remarks' => $remarks,
                'created_by' => Auth::id(),
                'transaction_date' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            DB::commit();
            
            $this->dispatch('show-notification', [
                'type' => 'success',
                'message' => 'Marked ' . $actualQuantity . ' of ' . $inventory->product_name . ' as damaged due to expiration.'
            ]);
            
            $this->loadData();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->dispatch('show-notification', [
                'type' => 'error',
                'message' => 'Failed to mark as damaged: ' . $e->getMessage()
            ]);
        }
    }
    
    public function createOutOfStockPO()
    {
        if (!$this->outOfStockSupplierId || empty($this->selectedOutOfStockItems)) {
            $this->dispatch('show-notification', [
                'type' => 'error',
                'message' => 'Please select a supplier and at least one item!'
            ]);
            return;
        }
        
        DB::beginTransaction();
        
        try {
            // Generate PO number
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate total amount
            $totalAmount = 0;
            $poItems = [];
            
            foreach ($this->selectedOutOfStockItems as $itemId) {
                $inventory = DB::table('inventories')->where('inventory_id', $itemId)->first();
                
                if ($inventory) {
                    // Calculate needed quantity (typically min_quantity * 2 or based on historical usage)
                    $neededQuantity = max($inventory->min_quantity * 2, 10);
                    
                    $itemTotal = $neededQuantity * $inventory->unit_price;
                    $totalAmount += $itemTotal;
                    
                    $poItems[] = [
                        'inventory' => $inventory,
                        'quantity' => $neededQuantity,
                        'total_price' => $itemTotal
                    ];
                }
            }
            
            // Create purchase order
            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $this->outOfStockSupplierId,
                'order_date' => Carbon::now()->toDateString(),
                'expected_delivery_date' => Carbon::now()->addDays(14)->toDateString(),
                'total_amount' => $totalAmount,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            // Create purchase order items
            foreach ($poItems as $itemData) {
                $inventory = $itemData['inventory'];
                
                DB::table('purchase_order_items')->insert([
                    'po_id' => $poId,
                    'inventory_id' => $inventory->inventory_id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $inventory->unit_price,
                    'total_price' => $itemData['total_price'],
                    'description' => 'Restocking out of stock item',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
            
            DB::commit();
            
            $this->dispatch('show-notification', [
                'type' => 'success',
                'message' => 'Purchase Order #' . $poNumber . ' created for ' . count($poItems) . ' out of stock items!'
            ]);
            
            $this->closeModal();
            $this->loadData();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->dispatch('show-notification', [
                'type' => 'error',
                'message' => 'Failed to create purchase order: ' . $e->getMessage()
            ]);
        }
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
                    
                    // Create stock transaction for allocation
                    DB::table('stock_transactions')->insert([
                        'inventory_id' => $item['inventory_id'],
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_price'] ?? 0,
                        'reference_type' => 'requisition',
                        'reference_id' => $this->currentRequisitionId,
                        'remarks' => 'Allocated for requisition #' . $this->currentRequisitionId,
                        'created_by' => Auth::id(),
                        'transaction_date' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    
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
                            'cost_price' => $item['unit_price'] ?? 0,
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
    
    public function getTransactionHistory($inventoryId)
    {
        return DB::table('stock_transactions')
            ->select('stock_transactions.*', 'users.full_name as created_by_name')
            ->leftJoin('users', 'stock_transactions.created_by', '=', 'users.user_id')
            ->where('stock_transactions.inventory_id', $inventoryId)
            ->orderBy('stock_transactions.transaction_date', 'desc')
            ->limit(50)
            ->get();
    }
    
    // Calculate allocation statistics
    public function getItemsStatistics()
    {
        // ... existing code ...
    }
    
    public function toggleSelectOutOfStockItem($itemId)
    {
        if (in_array($itemId, $this->selectedOutOfStockItems)) {
            $this->selectedOutOfStockItems = array_diff($this->selectedOutOfStockItems, [$itemId]);
        } else {
            $this->selectedOutOfStockItems[] = $itemId;
        }
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
    
    <!-- Alerts for expired and out of stock items -->
    @if(count($expiredItems) > 0)
    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-red-700">Expired/Near Expiry Items ({{ count($expiredItems) }})</h3>
                    <p class="text-sm text-red-600">Some items have expired or are nearing expiration</p>
                </div>
            </div>
            <button wire:click="openExpiredItemsModal"
                    class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded-lg transition-colors">
                View & Manage
            </button>
        </div>
    </div>
    @endif
    
    @if(count($outOfStockItems) > 0)
    <div class="mb-6 bg-orange-50 border border-orange-200 rounded-xl p-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-box-open text-orange-500 text-xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-orange-700">Out of Stock Items ({{ count($outOfStockItems) }})</h3>
                    <p class="text-sm text-orange-600">Some items need to be reordered</p>
                </div>
            </div>
            <button wire:click="openOutOfStockModal"
                    class="px-4 py-2 bg-orange-100 hover:bg-orange-200 text-orange-700 font-medium rounded-lg transition-colors">
                Create Purchase Order
            </button>
        </div>
    </div>
    @endif
    
    <!-- Search and Filter -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                    <span class="text-emerald-800 font-medium">Low Stock Only</span>
                </label>
            </div>
            <div class="flex items-center space-x-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" 
                           wire:model.live="showExpired" 
                           class="mr-2 h-5 w-5 text-red-600 rounded focus:ring-red-500">
                    <span class="text-red-800 font-medium">Show Expired</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" 
                           wire:model.live="showOutOfStock" 
                           class="mr-2 h-5 w-5 text-orange-600 rounded focus:ring-orange-500">
                    <span class="text-orange-800 font-medium">Out of Stock</span>
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
                                <th class="p-3 text-left text-emerald-800 font-semibold">Expiration</th>
                                <th class="p-3 text-left text-emerald-800 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($inventories as $inv)
                            @php
                                $expStatus = $this->getExpirationStatus($inv->expiration_date);
                            @endphp
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
                                    @if($inv->expiration_date)
                                    <div class="{{ $expStatus['color'] }} text-sm">
                                        {{ $expStatus['text'] }}
                                    </div>
                                    @else
                                    <span class="text-gray-500 text-sm">N/A</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $this->getStockLevelColor($inv->quantity, $inv->min_quantity, $inv->expiration_date) }}">
                                        {{ $this->getStockLevelText($inv->quantity, $inv->min_quantity, $inv->expiration_date) }}
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
    <div class="mt-8 grid grid-cols-1 md:grid-cols-5 gap-4">
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
                <div class="bg-red-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Expired/Near Expiry</p>
                    <p class="text-2xl font-bold text-red-800">{{ count($expiredItems) }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="bg-orange-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-box-open text-orange-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Out of Stock</p>
                    <p class="text-2xl font-bold text-orange-800">{{ count($outOfStockItems) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Modal (Existing) -->
@if($showModal)
<!-- ... existing modal code ... -->
@endif

<!-- Expired Items Modal -->
@if($showExpiredModal)
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Expired/Near Expiry Items
                    </h3>
                    <p class="text-red-200 text-sm mt-1">
                        {{ count($expiredItems) }} items expiring soon or already expired
                    </p>
                </div>
                <button wire:click="closeModal" 
                        class="text-white hover:text-red-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div class="grid grid-cols-1 gap-4">
                @foreach($expiredItems as $item)
                @php
                    $expStatus = $this->getExpirationStatus($item->expiration_date);
                    $isExpired = $expStatus['status'] === 'expired';
                @endphp
                <div class="border rounded-lg p-4 {{ $isExpired ? 'bg-red-50 border-red-200' : 'bg-yellow-50 border-yellow-200' }}">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-bold {{ $isExpired ? 'text-red-800' : 'text-yellow-800' }}">
                                {{ $item->product_name }}
                                <span class="text-sm font-normal ml-2">(SKU: {{ $item->sku }})</span>
                            </h4>
                            <div class="mt-1 text-sm {{ $isExpired ? 'text-red-700' : 'text-yellow-700' }}">
                                <span class="font-medium">{{ $expStatus['text'] }}</span>
                                • Current Stock: {{ $item->quantity }}
                                @if($item->expiration_date)
                                • Exp Date: {{ Carbon::parse($item->expiration_date)->format('M d, Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            @if($isExpired && $item->quantity > 0)
                            <button wire:click="markAsDamaged({{ $item->inventory_id }}, {{ $item->quantity }})"
                                    class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded-lg transition-colors text-sm">
                                Mark as Damaged
                            </button>
                            @endif
                            <button wire:click="markAsDamaged({{ $item->inventory_id }}, 1, 'Sample disposal')"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors text-sm">
                                Dispose Sample
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            
            @if(count($expiredItems) === 0)
            <div class="text-center py-12">
                <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
                <p class="text-gray-600">No expired or near-expiry items found</p>
            </div>
            @endif
        </div>
        
        <div class="border-t px-6 py-4 bg-gray-50">
            <div class="flex justify-end">
                <button wire:click="closeModal"
                        class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Out of Stock Items Modal -->
@if($showOutOfStockModal)
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-orange-600 to-orange-800 px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-box-open mr-2"></i>
                        Create PO for Out of Stock Items
                    </h3>
                    <p class="text-orange-200 text-sm mt-1">
                        {{ count($outOfStockItems) }} items need to be reordered
                    </p>
                </div>
                <button wire:click="closeModal" 
                        class="text-white hover:text-orange-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <!-- Supplier Selection -->
            <div class="mb-6 bg-blue-50 p-4 rounded-lg">
                <h4 class="font-semibold text-blue-800 mb-2">Select Supplier</h4>
                <select wire:model.live="outOfStockSupplierId"
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
                
                @if($outOfStockSupplierId)
                    @php
                        $selectedSupplier = collect($suppliers)->firstWhere('supplier_id', $outOfStockSupplierId);
                    @endphp
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-700">
                            <i class="fas fa-check-circle mr-2"></i>
                            Selected Supplier: <strong>{{ $selectedSupplier->name }}</strong>
                            @if($selectedSupplier->contact_person)
                                (Contact: {{ $selectedSupplier->contact_person }})
                            @endif
                        </p>
                    </div>
                @endif
            </div>
            
            <!-- Items List -->
            <div class="mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-semibold text-gray-800">Select Items to Reorder</h4>
                    <div class="text-sm text-gray-600">
                        <span class="mr-4">Total: {{ count($outOfStockItems) }}</span>
                        <span class="text-orange-600 font-bold">Selected: {{ count($selectedOutOfStockItems) }}</span>
                    </div>
                </div>
                
                @if(count($outOfStockItems) > 0)
                <div class="grid grid-cols-1 gap-3 mb-4">
                    @foreach($outOfStockItems as $item)
                    <div class="border rounded-lg p-4 hover:bg-orange-50 transition-colors {{ in_array($item->inventory_id, $selectedOutOfStockItems) ? 'bg-orange-50 border-orange-300' : 'bg-white' }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="selectedOutOfStockItems"
                                       value="{{ $item->inventory_id }}"
                                       id="item_{{ $item->inventory_id }}"
                                       class="h-5 w-5 text-orange-600 rounded focus:ring-orange-500 mr-3">
                                <label for="item_{{ $item->inventory_id }}" class="cursor-pointer flex-1">
                                    <div class="font-bold text-gray-900">{{ $item->product_name }}</div>
                                    <div class="text-sm text-gray-600">
                                        SKU: {{ $item->sku }} • 
                                        Min Qty: {{ $item->min_quantity }} • 
                                        Unit Price: ₱{{ number_format($item->unit_price, 2) }}
                                        @if($item->description)
                                        • {{ Str::limit($item->description, 50) }}
                                        @endif
                                    </div>
                                </label>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-red-600">Out of Stock</div>
                                <div class="text-sm text-gray-600">
                                    Suggested Qty: <span class="font-bold">{{ max($item->min_quantity * 2, 10) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <!-- Select All / None -->
                <div class="flex justify-between items-center mb-6 p-3 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium">Selected Items Preview:</span>
                        @if(count($selectedOutOfStockItems) > 0)
                            @php
                                $selectedNames = [];
                                foreach($outOfStockItems as $item) {
                                    if(in_array($item->inventory_id, $selectedOutOfStockItems)) {
                                        $selectedNames[] = $item->product_name;
                                    }
                                }
                            @endphp
                            <span class="text-green-700 ml-2">
                                {{ implode(', ', array_slice($selectedNames, 0, 3)) }}
                                @if(count($selectedNames) > 3)
                                    and {{ count($selectedNames) - 3 }} more...
                                @endif
                            </span>
                        @else
                            <span class="text-red-600 ml-2">No items selected</span>
                        @endif
                    </div>
                </div>
                
                @else
                <div class="text-center py-12">
                    <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
                    <p class="text-gray-600">No out of stock items found</p>
                    <p class="text-sm text-gray-500 mt-1">All items are currently in stock</p>
                </div>
                @endif
            </div>
        </div>
        
        <div class="border-t px-6 py-4 bg-gray-50">
            <div class="flex justify-between items-center">
                <div>
                    @if($outOfStockSupplierId && count($selectedOutOfStockItems) > 0)
                    <div class="text-sm text-green-700 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Ready to create purchase order for {{ count($selectedOutOfStockItems) }} items
                    </div>
                    @elseif($outOfStockSupplierId && count($selectedOutOfStockItems) === 0)
                    <div class="text-sm text-orange-700 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Supplier selected, but no items selected
                    </div>
                    @elseif(count($selectedOutOfStockItems) > 0 && !$outOfStockSupplierId)
                    <div class="text-sm text-orange-700 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Items selected, but no supplier selected
                    </div>
                    @else
                    <div class="text-sm text-red-700 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Please select a supplier and at least one item
                    </div>
                    @endif
                </div>
                <div class="flex gap-3">
                    <button wire:click="closeModal"
                            class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    
                    <button wire:click="createOutOfStockPO"
                            @disabled(!$outOfStockSupplierId || empty($selectedOutOfStockItems))
                            class="px-6 py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Create Purchase Order
                        @if(count($selectedOutOfStockItems) > 0)
                        <span class="ml-2 bg-orange-700 text-white text-xs px-2 py-1 rounded-full">
                            {{ count($selectedOutOfStockItems) }}
                        </span>
                        @endif
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