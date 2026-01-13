<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        Log::info('Requisition Processing Component Mounted');
        $this->loadData();
    }
    
    public function loadData()
    {
        Log::info('Loading data...', ['search' => $this->search, 'statusFilter' => $this->statusFilter]);
        
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
            
        Log::info('Data loaded', [
            'inventories' => count($this->inventories),
            'requisitions' => count($this->requisitions),
            'suppliers' => count($this->suppliers)
        ]);
    }
    
    public function updated($property)
    {
        Log::info('Property updated', ['property' => $property, 'value' => $this->{$property}]);
        
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
        Log::info('openProcessModal called', [
            'requisitionId' => $requisitionId,
            'timestamp' => now(),
            'user' => Auth::id()
        ]);
        
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
            
            Log::info('Requisition loaded', [
                'requisition_id' => $requisition->requisition_id,
                'requested_by' => $this->modalData['requested_by']
            ]);
        } else {
            Log::error('Requisition not found', ['requisitionId' => $requisitionId]);
            $this->dispatch('show-notification', [
                'type' => 'error',
                'message' => 'Requisition not found!'
            ]);
            return;
        }
        
        // NEW CODE:
$this->requisitionItems = DB::table('requisition_items')
    ->select(
        'requisition_items.*',
        'requisition_items.remarks as description', // Map remarks to description
        'inventories.product_name',
        'inventories.sku',
        'inventories.inventory_id',
        'inventories.quantity as current_stock',
        'inventories.min_quantity',
        'inventories.unit_price',
        'inventories.supplier_id'
    )
    ->leftJoin('inventories', 'requisition_items.inventory_id', '=', 'inventories.inventory_id')
    ->where('requisition_id', $requisitionId)
    ->get()
    ->map(function($item) {
        $item = (array) $item;
        
        // Ensure description exists (map from remarks)
        $item['description'] = $item['remarks'] ?? $item['purpose'] ?? null;
        
        // If no inventory_id, try to find by product_id
        if (!$item['inventory_id'] && !empty($item['product_id'])) {
            $inventory = DB::table('inventories')
                ->where('inventory_id', $item['product_id']) // Adjust this based on your schema
                ->orWhere('product_id', $item['product_id']) // If you have product_id field
                ->where('status', 'active')
                ->first();
                
            if ($inventory) {
                $item['inventory_id'] = $inventory->inventory_id;
                $item['current_stock'] = $inventory->quantity;
                $item['unit_price'] = $inventory->unit_price;
            }
        }
        return $item;
    })
    ->toArray();
        
        Log::info('Loaded requisition items', [
            'count' => count($this->requisitionItems),
            'items' => $this->requisitionItems
        ]);
        
        $this->showModal = true;
        
        // Dispatch event for testing
        $this->dispatch('debug-event', [
            'message' => 'Modal opened successfully',
            'requisitionId' => $requisitionId
        ]);
    }
    
    public function debugButtonClick($requisitionId)
    {
        Log::info('DEBUG: Button clicked directly', [
            'requisitionId' => $requisitionId,
            'method' => 'debugButtonClick'
        ]);
        
        $this->dispatch('show-notification', [
            'type' => 'info',
            'message' => 'DEBUG: Button clicked! Check console logs.'
        ]);
        
        // Try to open modal
        $this->openProcessModal($requisitionId);
    }
    
    public function openExpiredItemsModal()
    {
        Log::info('Opening expired items modal');
        $this->showExpiredModal = true;
    }
    
    public function openOutOfStockModal()
    {
        Log::info('Opening out of stock modal');
        $this->selectedOutOfStockItems = [];
        $this->outOfStockSupplierId = null;
        $this->showOutOfStockModal = true;
    }
    
    public function closeModal()
    {
        Log::info('Closing modal');
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
    Log::info('=== PROCESS REQUISITION START ===', ['requisitionId' => $this->currentRequisitionId]);
    
    $userId = Auth::id();
    
    if (!$userId) {
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'User not authenticated! Please login again.'
        ]);
        return;
    }
    
    if (!$this->currentRequisitionId) {
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'No requisition selected'
        ]);
        return;
    }
    
    DB::beginTransaction();
    
    try {
        // 1. Check if requisition exists
        $requisition = DB::table('purchase_requisitions')
            ->where('requisition_id', $this->currentRequisitionId)
            ->first();
            
        if (!$requisition) {
            throw new \Exception('Requisition not found!');
        }
        
        // 2. Check if already processed
        if ($requisition->status !== 'draft') {
            throw new \Exception('Requisition has already been processed! Current status: ' . $requisition->status);
        }
        
        // 3. Check if PO already exists for this requisition
        $existingPO = DB::table('purchase_orders')
            ->where('requisition_id', $this->currentRequisitionId)
            ->first();
            
        if ($existingPO) {
            throw new \Exception('Purchase Order already exists for this requisition! PO#' . $existingPO->po_number);
        }
        
        Log::info('DEBUG: All requisition items:', ['items' => $this->requisitionItems]);
        
        $allocatedFromStock = false;
        $needPO = false;
        
        // 4. Check each item - can we allocate from stock or need PO?
        foreach ($this->requisitionItems as $item) {
            $item = (array) $item;
            
            Log::info('Checking item:', [
                'product' => $item['product_name'],
                'quantity_needed' => $item['quantity'],
                'current_stock' => $item['current_stock'],
                'inventory_id' => $item['inventory_id']
            ]);
            
            if (empty($item['inventory_id'])) {
                $needPO = true;
                Log::info("Needs PO: No inventory ID for '{$item['product_name']}'");
                continue;
            }
            
            if ($item['current_stock'] >= $item['quantity']) {
                $allocatedFromStock = true;
                Log::info("Can allocate from stock: '{$item['product_name']}'");
            } else {
                $needPO = true;
                Log::info("Needs PO: Insufficient stock for '{$item['product_name']}'. Need: {$item['quantity']}, Have: {$item['current_stock']}");
            }
        }
        
        // 5. DECISION: Allocate from stock OR Create PO?
        if (!$needPO && $allocatedFromStock) {
            // ALL ITEMS HAVE ENOUGH STOCK - Allocate from inventory
            Log::info('All items can be allocated from stock. Allocating...');
            
            foreach ($this->requisitionItems as $item) {
                $item = (array) $item;
                
                // Decrement inventory
                DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->decrement('quantity', $item['quantity']);
                    
                Log::info('Inventory decremented:', [
                    'inventory_id' => $item['inventory_id'],
                    'product' => $item['product_name'],
                    'decrement_by' => $item['quantity']
                ]);
                
                // Try to create stock transaction (optional)
                try {
                    DB::table('stock_transactions')->insert([
                        'inventory_id' => $item['inventory_id'],
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_price'] ?? 0,
                        'reference_type' => 'requisition',
                        'reference_id' => $this->currentRequisitionId,
                        'remarks' => 'Allocated for requisition #' . $this->currentRequisitionId,
                        'created_by' => $userId,
                        'transaction_date' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Could not create stock transaction: ' . $e->getMessage());
                }
            }
            
            // Update requisition status to 'approved'
            DB::table('purchase_requisitions')
                ->where('requisition_id', $this->currentRequisitionId)
                ->update([
                    'status' => 'approved',
                    'updated_at' => Carbon::now()
                ]);
            
            $message = "✓ All items allocated from inventory. Requisition status: Approved";
            
        } else {
            // NEED PURCHASE ORDER (some or all items)
            Log::info('Creating Purchase Order...');
            
            if (!$this->selectedSupplierId) {
                throw new \Exception('Please select a supplier to create Purchase Order!');
            }
            
            // Generate unique PO number
            $timestamp = time();
            $poNumber = 'PO-' . date('Ymd') . '-' . $timestamp;
            
            // Calculate total amount
            $totalAmount = 0;
            foreach ($this->requisitionItems as $item) {
                $item = (array) $item;
                $totalAmount += ($item['quantity'] * ($item['unit_price'] ?? 0));
            }
            
            // Create purchase order
            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'requisition_id' => $this->currentRequisitionId,
                'supplier_id' => $this->selectedSupplierId,
                'order_date' => Carbon::now()->toDateString(),
                'expected_delivery_date' => Carbon::now()->addDays(14)->toDateString(),
                'total_amount' => $totalAmount,
                'status' => 'draft',
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            Log::info('Purchase order created:', ['po_id' => $poId, 'po_number' => $poNumber]);
            
            // Create purchase order items (without product_name since column doesn't exist)
            foreach ($this->requisitionItems as $item) {
                $item = (array) $item;
                $totalPrice = $item['quantity'] * ($item['unit_price'] ?? 0);
                
                DB::table('purchase_order_items')->insert([
                    'po_id' => $poId,
                    'inventory_id' => $item['inventory_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $totalPrice,
                    'description' => $item['description'] ?? 'Requisition item',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
            
            // Update requisition status to 'converted_to_po'
            DB::table('purchase_requisitions')
                ->where('requisition_id', $this->currentRequisitionId)
                ->update([
                    'status' => 'converted_to_po',
                    'updated_at' => Carbon::now()
                ]);
            
            $message = "✓ Purchase Order #{$poNumber} created. Requisition status: Converted to PO";
        }
        
        DB::commit();
        
        Log::info('=== PROCESS COMPLETED SUCCESSFULLY ===');
        
        // Show success message
        $this->dispatch('show-notification', [
            'type' => 'success',
            'message' => $message
        ]);
        
        // Close modal and refresh data
        $this->closeModal();
        $this->loadData();
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to process requisition: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->dispatch('show-notification', [
            'type' => 'error',
            'message' => 'Error: ' . $e->getMessage()
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
        $stats = [
            'total_items' => count($this->inventories),
            'low_stock' => 0,
            'out_of_stock' => 0,
            'near_expiry' => 0,
            'expired' => 0
        ];
        
        foreach ($this->inventories as $item) {
            if ($item->quantity <= 0) {
                $stats['out_of_stock']++;
            } elseif ($item->quantity <= $item->min_quantity) {
                $stats['low_stock']++;
            }
            
            if ($item->expiration_date) {
                $expDate = Carbon::parse($item->expiration_date);
                $today = Carbon::now();
                
                if ($expDate->lt($today)) {
                    $stats['expired']++;
                } elseif ($today->diffInDays($expDate) <= 30) {
                    $stats['near_expiry']++;
                }
            }
        }
        
        return $stats;
    }
    
    public function toggleSelectOutOfStockItem($itemId)
    {
        if (in_array($itemId, $this->selectedOutOfStockItems)) {
            $this->selectedOutOfStockItems = array_diff($this->selectedOutOfStockItems, [$itemId]);
        } else {
            $this->selectedOutOfStockItems[] = $itemId;
        }
    }
    
    // Test method for debugging
    public function testMethod()
    {
        Log::info('Test method called successfully');
        $this->dispatch('show-notification', [
            'type' => 'success',
            'message' => 'Livewire is working! Test method executed.'
        ]);
    }
};
?>

<div>
<div class="p-6 min-h-screen bg-gradient-to-br from-green-50 to-emerald-50">
    
    <!-- Debug Test Button -->
    <!-- <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="font-bold text-yellow-800">Debug Panel</h3>
                <p class="text-sm text-yellow-700">Test if Livewire is working</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="testMethod"
                        class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg">
                    Test Livewire
                </button>
                <button wire:click="$refresh"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg">
                    Refresh Data
                </button>
            </div>
        </div>
    </div> -->
    
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
                                    <!-- Debug button first -->
                                    <!-- <button wire:click="debugButtonClick({{ $req->requisition_id }})"
                                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center mb-2">
                                        <i class="fas fa-bug mr-2"></i>
                                        Debug Test
                                    </button> -->
                                    
                                    <!-- Then the actual process button -->
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

<!-- Main Modal -->
@if($showModal)
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-emerald-800 px-6 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-cogs mr-2"></i>
                        Process Requisition #{{ $currentRequisitionId }}
                    </h3>
                    <p class="text-emerald-200 text-sm mt-1">
                        Allocate from inventory or create purchase order
                    </p>
                </div>
                <button wire:click="closeModal" 
                        class="text-white hover:text-emerald-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <!-- Requisition Info -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Requested By</label>
                        <p class="font-bold text-gray-900">{{ $modalData['requested_by'] }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Date Requested</label>
                        <p class="font-bold text-gray-900">{{ $modalData['date_requested'] }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Estimated Cost</label>
                        <p class="font-bold text-emerald-700">₱{{ number_format($modalData['estimated_cost'], 2) }}</p>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Selection -->
            <div class="mb-6">
                <h4 class="font-semibold text-gray-800 mb-2">Select Supplier (for items that need PO)</h4>
                <select wire:model="selectedSupplierId"
                        class="w-full px-4 py-3 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    <option value="">Select a supplier...</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->supplier_id }}">
                            {{ $sup->name }}
                            @if($sup->contact_person)
                                • {{ $sup->contact_person }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Items List -->
            <div class="mb-4">
                <h4 class="font-semibold text-gray-800 mb-3">Requisition Items</h4>
                
                @if(count($requisitionItems) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-3 text-left text-gray-700 font-semibold">Product</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Requested Qty</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Current Stock</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Status</th>
                                <th class="p-3 text-left text-gray-700 font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($requisitionItems as $index => $item)
                            @php
                                $canAllocate = $item['current_stock'] >= $item['quantity'] && !empty($item['inventory_id']);
                                $statusColor = $canAllocate ? 'text-green-600' : 'text-orange-600';
                                $statusText = $canAllocate ? 'Can Allocate' : 'Needs PO';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $item['product_name'] }}</div>
                                    <div class="text-xs text-gray-600">
                                        SKU: {{ $item['sku'] ?? 'N/A' }}
                                        @if($item['description'])
                                        • {{ Str::limit($item['description'], 30) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="p-3 font-bold text-gray-900">{{ $item['quantity'] }}</td>
                                <td class="p-3">
                                    <div class="font-bold {{ $item['current_stock'] >= $item['quantity'] ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $item['current_stock'] ?? 0 }}
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $canAllocate ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    @if($canAllocate)
                                    <span class="text-green-600 font-medium">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Will allocate from stock
                                    </span>
                                    @else
                                    <span class="text-orange-600 font-medium">
                                        <i class="fas fa-shopping-cart mr-1"></i>
                                        Will create PO
                                    </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <i class="fas fa-exclamation-circle text-3xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600">No items found in this requisition</p>
                </div>
                @endif
            </div>
        </div>
        
        <div class="border-t px-6 py-4 bg-gray-50">
            <div class="flex justify-between items-center">
                <div>
                    @if($selectedSupplierId)
                    <div class="text-sm text-green-700 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Supplier selected: {{ collect($suppliers)->firstWhere('supplier_id', $selectedSupplierId)->name ?? 'Unknown' }}
                    </div>
                    @else
                    <div class="text-sm text-orange-700 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Select a supplier for items that need PO
                    </div>
                    @endif
                </div>
                <div class="flex gap-3">
                    <button wire:click="closeModal"
                            class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    
                   <button wire:click="processRequisition"
        class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center">
    <i class="fas fa-play-circle mr-2"></i>
    Process Requisition
</button>
                </div>
            </div>
        </div>
    </div>
</div>
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

<!-- Debug JavaScript -->
<script>
    document.addEventListener('livewire:initialized', () => {
        console.log('Livewire initialized for requisition processing');
        
        // Debug: Log when buttons are clicked
        document.addEventListener('click', function(e) {
            if (e.target.hasAttribute('wire:click')) {
                const method = e.target.getAttribute('wire:click');
                console.log('Livewire button clicked:', {
                    method: method,
                    element: e.target,
                    timestamp: new Date().toISOString()
                });
            }
        });
        
        // Listen to all Livewire events
        document.addEventListener('livewire:request', (event) => {
            console.log('Livewire Request:', {
                method: event.detail.method,
                params: event.detail.params,
                url: event.detail.url
            });
        });
        
        document.addEventListener('livewire:response', (event) => {
            console.log('Livewire Response:', {
                requestId: event.detail.requestId,
                effects: event.detail.effects
            });
        });
        
        document.addEventListener('livewire:message-failed', (event) => {
            console.error('Livewire Message Failed:', event.detail);
            alert('Livewire error: ' + JSON.stringify(event.detail));
        });
        
        // Listen for debug events
        Livewire.on('debug-event', (data) => {
            console.log('Debug Event:', data);
        });
    });

    // Notification function
    Livewire.on('show-notification', (event) => {
        showNotification(event.type, event.message);
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
    
    // Test function
    function testButtonClick() {
        console.log('Test button clicked from JavaScript');
        alert('JavaScript is working!');
    }
</script>
</div>