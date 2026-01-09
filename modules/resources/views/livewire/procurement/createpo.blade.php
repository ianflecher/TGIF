<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public ?int $selectedSupplier = null;
    public array $selectedProducts = [];
    public array $quantities = [];
    public string $message = '';
    public array $urgentRequisitions = [];
    public ?int $selectedManager = null;
    public array $managers = [];
    public array $products = [];
    public array $suppliers = [];
    public array $pendingOrders = [];
    public bool $showSendForm = false;
    public string $deliveryDate = '';
    public string $terms = 'Net 30 days';

    public function mount(): void
    {
        $this->loadProducts();
        $this->loadSuppliers();
        $this->loadManagers();
        $this->loadPendingOrders();
        $this->deliveryDate = now()->addDays(14)->toDateString();
    }

    public function loadProducts(): void
{
    $this->products = DB::table('products as p')
        ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
        ->select(
            'p.product_id',
            'p.product_name',
            'p.category',
            'p.price',
            'p.stock_quantity',
            'p.reorder_level',
            'p.supplier_id',
            'p.inventory_id',
            's.name as supplier_name'
        )
        ->where('p.status', 'draft')
        ->orderBy('p.product_name')
        ->get()
        ->map(function ($product) {
            // Calculate pending quantities from active POs
            // Only calculate if inventory_id exists
            if ($product->inventory_id) {
                $product->pending = DB::table('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'poi.po_id', '=', 'po.po_id')
                    ->where('poi.inventory_id', $product->inventory_id)
                    ->whereIn('po.status', ['draft', 'sent', 'confirmed', 'partially_received'])
                    ->sum('poi.quantity');
            } else {
                $product->pending = 0; // Set to 0 if no inventory_id
            }
            
            return $product;
        })
        ->toArray();
}

    public function loadManagers(): void
    {
        $this->managers = DB::table('users')
            ->whereIn('role', ['procurement_manager', 'admin', 'manager'])
            ->select('user_id as employee_id', 'full_name as name')
            ->orderBy('full_name')
            ->get()
            ->toArray();
    }

    public function loadSuppliers(): void
    {
        $this->suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->orderByDesc('rating')
            ->get()
            ->toArray();
    }

    public function loadPendingOrders(): void
    {
        $this->pendingOrders = DB::table('purchase_orders as po')
            ->join('suppliers as s', 'po.supplier_id', '=', 's.supplier_id')
            ->join('users as u', 'po.created_by', '=', 'u.user_id')
            ->whereIn('po.status', ['draft', 'sent', 'confirmed', 'partially_received'])
            ->select(
                'po.po_id',
                'po.po_number',
                'po.order_date',
                'po.expected_delivery_date',
                'po.total_amount',
                'po.status',
                's.name as supplier_name',
                'u.full_name as created_by_name'
            )
            ->orderBy('po.order_date', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    // ---------- Automatic PO Generation ----------
   public function generateAutomaticPO(): void
{
    if (!$this->selectedSupplier) {
        $this->message = '⚠️ Please select a supplier first.';
        return;
    }

    $supplier = DB::table('suppliers')->where('supplier_id', $this->selectedSupplier)->first();
    if (!$supplier) {
        $this->message = '⚠️ Supplier not found.';
        return;
    }

    $orderDate = now()->toDateString();
    $poItems = [];

    // Generate PO number
    $poCount = DB::table('purchase_orders')->whereDate('created_at', today())->count();
    $poNumber = 'PO-' . date('Ymd') . '-' . str_pad($poCount + 1, 4, '0', STR_PAD_LEFT);

    // Find products that need reorder from this supplier
    $supplierProducts = array_filter($this->products, function($product) {
        return $product->supplier_id == $this->selectedSupplier;
    });

    foreach ($supplierProducts as $product) {
        $totalAvailable = $product->stock_quantity + $product->pending;
        $reorderLevel = $product->reorder_level ?? 10;

        // Check if total available (stock + pending orders) is below reorder level
        if ($totalAvailable >= $reorderLevel) {
            continue;
        }

        // Calculate quantity needed to reach reorder level + safety margin
        $safetyBuffer = 5; // Add 5 extra units for safety
        $quantity = ($reorderLevel + $safetyBuffer) - $totalAvailable;
        
        if ($quantity <= 0) continue;

        // Skip products without inventory_id
        if (empty($product->inventory_id)) {
            \Log::warning('Skipping product without inventory_id', [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name
            ]);
            continue;
        }

        $poItems[] = [
            'product_id' => $product->product_id,
            'inventory_id' => $product->inventory_id,
            'product_name' => $product->product_name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total_price' => $quantity * $product->price
        ];
    }

    if (empty($poItems)) {
        $this->message = '⚠️ No products need reorder from this supplier at this time, or all products lack inventory_id.';
        return;
    }

    // Calculate total amount
    $totalAmount = collect($poItems)->sum('total_price');

    // Create Purchase Order
    $poId = DB::table('purchase_orders')->insertGetId([
        'supplier_id' => $this->selectedSupplier,
        'requisition_id' => null,
        'po_number' => $poNumber,
        'order_date' => $orderDate,
        'expected_delivery_date' => $this->deliveryDate,
        'total_amount' => $totalAmount,
        'status' => 'draft',
        'terms' => $this->terms,
        'created_by' => auth()->id(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create PO items - using inventory_id as per schema
    foreach ($poItems as $item) {
        // Double-check inventory_id is not null
        if (empty($item['inventory_id'])) {
            \Log::error('Attempting to insert PO item with null inventory_id', $item);
            continue;
        }

        DB::table('purchase_order_items')->insert([
            'po_id' => $poId,
            'inventory_id' => $item['inventory_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $item['total_price'],
            'description' => "Automatic reorder for {$item['product_name']}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->message = "✅ Purchase Order #{$poNumber} created successfully for {$supplier->name}! Total: ₱" . number_format($totalAmount, 2);
    $this->loadPendingOrders();
    $this->loadProducts();
    $this->showSendForm = true;
}

    // ---------- Manual PO Generation ----------
    public function generateManualPO(): void
    {
        if (!$this->selectedSupplier || empty($this->selectedProducts) || !$this->selectedManager) {
            $this->message = '⚠️ Select a supplier, manager, and at least one product.';
            return;
        }

        $supplier = DB::table('suppliers')->where('supplier_id', $this->selectedSupplier)->first();
        if (!$supplier) {
            $this->message = '⚠️ Supplier not found.';
            return;
        }

        $orderDate = now()->toDateString();
        $poItems = [];
        $totalAmount = 0;

        // Generate PO number
        $poCount = DB::table('purchase_orders')->whereDate('created_at', today())->count();
        $poNumber = 'PO-' . date('Ymd') . '-' . str_pad($poCount + 1, 4, '0', STR_PAD_LEFT);

        // Get selected products with quantities
        foreach ($this->products as $product) {
            if (in_array($product->product_id, $this->selectedProducts)) {
                // Check if product belongs to selected supplier
                if ($product->supplier_id != $this->selectedSupplier) {
                    $this->message = "⚠️ Product '{$product->product_name}' does not belong to selected supplier.";
                    return;
                }

                $quantity = $this->quantities[$product->product_id] ?? 1;
                if ($quantity <= 0) continue;

                $itemTotal = $quantity * $product->price;
                $poItems[] = [
                    'product_id' => $product->product_id,
                    'inventory_id' => $product->inventory_id,
                    'product_name' => $product->product_name,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total_price' => $itemTotal
                ];
                $totalAmount += $itemTotal;
            }
        }

        if (empty($poItems)) {
            $this->message = '⚠️ No valid products selected.';
            return;
        }

        // Create Purchase Order
        $poId = DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $this->selectedSupplier,
            'requisition_id' => null,
            'po_number' => $poNumber,
            'order_date' => $orderDate,
            'expected_delivery_date' => $this->deliveryDate,
            'total_amount' => $totalAmount,
            'status' => 'draft',
            'terms' => $this->terms,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create PO items - using inventory_id
        foreach ($poItems as $item) {
            DB::table('purchase_order_items')->insert([
                'po_id' => $poId,
                'inventory_id' => $item['inventory_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'description' => "Manual order for {$item['product_name']}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->message = "✅ Purchase Order #{$poNumber} created successfully for {$supplier->name}! Total: ₱" . number_format($totalAmount, 2);
        $this->resetForm();
        $this->loadPendingOrders();
        $this->loadProducts();
        $this->showSendForm = true;
    }

    // ---------- Send PO to Supplier ----------
    public function sendOrderToSupplier($poId): void
    {
        $po = DB::table('purchase_orders')->where('po_id', $poId)->first();
        
        if (!$po) {
            $this->message = '⚠️ Purchase Order not found.';
            return;
        }

        if ($po->status !== 'draft') {
            $this->message = '⚠️ Only draft orders can be sent.';
            return;
        }

        // Update PO status to 'sent'
        DB::table('purchase_orders')
            ->where('po_id', $poId)
            ->update([
                'status' => 'sent',
                'updated_at' => now()
            ]);

        $supplier = DB::table('suppliers')->where('supplier_id', $po->supplier_id)->first();
        
        $this->message = "✅ Purchase Order #{$po->po_number} sent to {$supplier->name}!";
        $this->loadPendingOrders();
    }

    // ---------- Mark PO as Received (Complete) ----------
    public function markAsReceived($poId): void
    {
        $po = DB::table('purchase_orders')->where('po_id', $poId)->first();
        
        if (!$po) {
            $this->message = '⚠️ Purchase Order not found.';
            return;
        }

        if ($po->status === 'completed') {
            $this->message = '⚠️ This PO is already completed.';
            return;
        }

        // Get all items in this PO
        $items = DB::table('purchase_order_items as poi')
            ->where('poi.po_id', $poId)
            ->get();

        // Update product stock for each item
        foreach ($items as $item) {
            // Find the product using inventory_id
            $product = DB::table('products')
                ->where('inventory_id', $item->inventory_id)
                ->first();
                
            if ($product) {
                DB::table('products')
                    ->where('product_id', $product->product_id)
                    ->increment('stock_quantity', $item->quantity);
            }
        }

        // Update PO status to 'completed'
        DB::table('purchase_orders')
            ->where('po_id', $poId)
            ->update([
                'status' => 'completed',
                'updated_at' => now()
            ]);

        $this->message = "✅ Purchase Order #{$po->po_number} marked as received and stock updated!";
        $this->loadPendingOrders();
        $this->loadProducts();
    }

    // ---------- View PO Details ----------
    // ---------- View PO Details ----------
public function viewOrderDetails($poId): void
{
    $po = DB::table('purchase_orders')->where('po_id', $poId)->first();
    if ($po) {
        // Try to get items directly from purchase_order_items first
        $items = DB::table('purchase_order_items')
            ->where('po_id', $poId)
            ->get();
        
        // Debug: Log what we found
        \Log::info('PO Details View', [
            'po_id' => $poId,
            'item_count' => $items->count(),
            'items' => $items->toArray()
        ]);
        
        $itemCount = $items->count();
        $totalAmount = $items->sum('total_price');
        
        $this->message = "📋 PO #{$po->po_number} has {$itemCount} item(s). Total: ₱" . number_format($totalAmount, 2);
        
        // Show a more detailed message with item names if possible
        if ($itemCount > 0) {
            $itemNames = [];
            foreach ($items as $item) {
                // Try to get product name from products table using inventory_id
                $product = DB::table('products')
                    ->where('inventory_id', $item->inventory_id)
                    ->first();
                    
                if ($product) {
                    $itemNames[] = "{$product->product_name} (Qty: {$item->quantity})";
                } else {
                    // Try to get from inventories table as fallback
                    $inventory = DB::table('inventories')
                        ->where('inventory_id', $item->inventory_id)
                        ->first();
                        
                    if ($inventory) {
                        $itemNames[] = "{$inventory->product_name} (Qty: {$item->quantity})";
                    } else {
                        $itemNames[] = "Item ID: {$item->inventory_id} (Qty: {$item->quantity})";
                    }
                }
            }
            
            $this->message .= "\nItems: " . implode(', ', $itemNames);
        }
    } else {
        $this->message = '⚠️ Purchase Order not found.';
    }
}

    private function resetForm(): void
    {
        $this->selectedSupplier = null;
        $this->selectedProducts = [];
        $this->quantities = [];
        $this->selectedManager = null;
    }
};
?>

<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold text-blue-800 mb-6">Purchase Order Management</h1>

    @if($message)
        <div class="mb-4 p-3 rounded-lg {{ str_contains($message, '✅') ? 'bg-green-100 text-green-800 border-l-4 border-green-600' : 'bg-yellow-100 text-yellow-800 border-l-4 border-yellow-600' }}">
            {{ $message }}
        </div>
    @endif

    <!-- Active Orders Section -->
    @if(count($pendingOrders) > 0)
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Active Purchase Orders</h2>
            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                {{ count($pendingOrders) }} active order(s)
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-300 rounded-lg overflow-hidden">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-4 py-3 text-left">PO Number</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-left">Order Date</th>
                        <th class="px-4 py-3 text-left">Expected Delivery</th>
                        <th class="px-4 py-3 text-left">Total Amount</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingOrders as $order)
                    <tr class="hover:bg-gray-50 border-b border-gray-200">
                        <td class="px-4 py-3 font-semibold text-blue-700">{{ $order->po_number }}</td>
                        <td class="px-4 py-3">{{ $order->supplier_name }}</td>
                        <td class="px-4 py-3">{{ date('M d, Y', strtotime($order->order_date)) }}</td>
                        <td class="px-4 py-3">{{ date('M d, Y', strtotime($order->expected_delivery_date)) }}</td>
                        <td class="px-4 py-3 font-semibold">₱{{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                if ($order->status === 'draft') {
                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                } elseif ($order->status === 'sent') {
                                    $statusClass = 'bg-blue-100 text-blue-800';
                                } elseif ($order->status === 'confirmed') {
                                    $statusClass = 'bg-purple-100 text-purple-800';
                                } elseif ($order->status === 'partially_received') {
                                    $statusClass = 'bg-orange-100 text-orange-800';
                                } elseif ($order->status === 'completed') {
                                    $statusClass = 'bg-green-100 text-green-800';
                                } elseif ($order->status === 'cancelled') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                }
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex space-x-2">
                                <button wire:click="viewOrderDetails({{ $order->po_id }})" 
                                        class="text-blue-600 hover:text-blue-800 p-1"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @if($order->status === 'draft')
                                <button wire:click="sendOrderToSupplier({{ $order->po_id }})" 
                                        class="text-green-600 hover:text-green-800 p-1"
                                        title="Send to Supplier">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                @endif
                                @if(in_array($order->status, ['sent', 'confirmed', 'partially_received']))
                                <button wire:click="markAsReceived({{ $order->po_id }})" 
                                        class="text-green-600 hover:text-green-800 p-1"
                                        title="Mark as Received"
                                        onclick="return confirm('Mark this PO as received and update stock?')">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- PO Generation Options -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Automatic PO Card -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4 text-blue-700">Automatic Purchase Order</h2>
            <p class="text-gray-600 mb-4">
                Automatically generate POs for products below reorder level from selected supplier.
            </p>
            
            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Select Supplier</label>
                    <select wire:model="selectedSupplier" 
                            class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="">— Select Supplier —</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->supplier_id }}">
                                {{ $sup->name }}
                                @if($sup->rating)
                                    <span class="text-yellow-600 ml-1">(★ {{ number_format($sup->rating, 1) }})</span>
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Expected Delivery Date</label>
                    <input type="date" 
                           wire:model="deliveryDate" 
                           class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Payment Terms</label>
                    <select wire:model="terms" 
                            class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="Net 30 days">Net 30 days</option>
                        <option value="Net 45 days">Net 45 days</option>
                        <option value="Net 60 days">Net 60 days</option>
                        <option value="Cash on Delivery">Cash on Delivery</option>
                        <option value="50% Advance, 50% on Delivery">50% Advance, 50% on Delivery</option>
                    </select>
                </div>
            </div>

            <button wire:click="generateAutomaticPO" 
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition duration-200 w-full justify-center">
                <i class="fas fa-robot"></i>
                Generate Automatic PO
            </button>

            <!-- Debug info -->
            @if($selectedSupplier)
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <h3 class="font-medium text-gray-700 mb-2">Supplier Products Overview:</h3>
                @php
                    $supplierProducts = array_filter($products, fn($p) => $p->supplier_id == $selectedSupplier);
                    $needsReorderCount = 0;
                @endphp
                @if(count($supplierProducts) > 0)
                    @foreach($supplierProducts as $prod)
                        @php
                            $needsReorder = $prod->stock_quantity < ($prod->reorder_level ?? 10);
                            if ($needsReorder) $needsReorderCount++;
                        @endphp
                        <div class="text-sm {{ $needsReorder ? 'text-red-600' : 'text-green-600' }} mb-1">
                            {{ $prod->product_name }}: Stock {{ $prod->stock_quantity }} / Reorder {{ $prod->reorder_level ?? 10 }} {{ $needsReorder ? '⚠️' : '✓' }}
                        </div>
                    @endforeach
                    <div class="text-sm font-medium mt-2">
                        {{ $needsReorderCount }} of {{ count($supplierProducts) }} products need reorder
                    </div>
                @else
                    <p class="text-gray-500">No products found for this supplier</p>
                @endif
            </div>
            @endif
        </div>

        <!-- Manual PO Card -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4 text-green-700">Manual Purchase Order</h2>
            <p class="text-gray-600 mb-4">
                Manually select products and quantities for PO creation from selected supplier.
            </p>

            <div class="space-y-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Select Supplier</label>
                        <select wire:model="selectedSupplier" 
                                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition">
                            <option value="">— Select Supplier —</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->supplier_id }}">
                                    {{ $sup->name }}
                                    @if($sup->rating)
                                        <span class="text-yellow-600 ml-1">(★ {{ number_format($sup->rating, 1) }})</span>
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Select Manager</label>
                        <select wire:model="selectedManager" 
                                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition">
                            <option value="">— Select Manager —</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->employee_id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-3 font-medium">Select Products & Quantities</label>
                    <div class="space-y-3 max-h-80 overflow-y-auto p-2 border border-gray-300 rounded-lg">
                        @php
                            $displayProducts = $selectedSupplier ? 
                                array_filter($products, fn($p) => $p->supplier_id == $selectedSupplier) : 
                                $products;
                        @endphp
                        
                        @foreach($displayProducts as $prod)
                            @php
                                $totalAvailable = $prod->stock_quantity + $prod->pending;
                                $needsReorder = $prod->stock_quantity < ($prod->reorder_level ?? 10);
                                $defaultQty = $needsReorder ? (($prod->reorder_level ?? 10) - $prod->stock_quantity) : 1;
                            @endphp
                            <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition {{ $needsReorder ? 'bg-red-50 border-red-200' : '' }}">
                                <div class="flex items-center space-x-3">
                                    <input type="checkbox" 
                                           wire:model="selectedProducts" 
                                           value="{{ $prod->product_id }}"
                                           class="w-5 h-5 text-green-600 rounded focus:ring-green-500"
                                           @if($selectedSupplier && $prod->supplier_id != $selectedSupplier) disabled @endif>
                                    <div>
                                        <span class="font-medium text-gray-800">{{ $prod->product_name }}</span>
                                        <div class="text-sm text-gray-600 flex items-center gap-2">
                                            <span class="bg-gray-100 px-2 py-0.5 rounded">{{ $prod->category }}</span>
                                            <span>Stock: {{ $prod->stock_quantity }}</span>
                                            @if($prod->pending > 0)
                                                <span class="text-blue-600">(+{{ $prod->pending }} pending)</span>
                                            @endif
                                            @if($prod->supplier_name)
                                                <span class="text-purple-600">{{ $prod->supplier_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-600">₱{{ number_format($prod->price, 2) }}</span>
                                    <input 
                                        wire:model="quantities.{{ $prod->product_id }}" 
                                        type="number" 
                                        min="1" 
                                        step="1" 
                                        placeholder="Qty" 
                                        class="w-24 border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-green-500"
                                        value="{{ $defaultQty }}"
                                        @if($selectedSupplier && $prod->supplier_id != $selectedSupplier) disabled @endif>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($selectedSupplier)
                    <p class="text-sm text-gray-500 mt-2">
                        Showing products from {{ collect($suppliers)->firstWhere('supplier_id', $selectedSupplier)->name ?? 'selected supplier' }}
                    </p>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Expected Delivery Date</label>
                        <input type="date" 
                               wire:model="deliveryDate" 
                               class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Payment Terms</label>
                        <select wire:model="terms" 
                                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="Net 30 days">Net 30 days</option>
                            <option value="Net 45 days">Net 45 days</option>
                            <option value="Net 60 days">Net 60 days</option>
                            <option value="Cash on Delivery">Cash on Delivery</option>
                            <option value="50% Advance, 50% on Delivery">50% Advance, 50% on Delivery</option>
                        </select>
                    </div>
                </div>
            </div>

            <button wire:click="generateManualPO" 
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200 shadow hover:shadow-lg w-full justify-center">
                <i class="fas fa-file-invoice-dollar"></i>
                Generate Manual PO
            </button>
        </div>
    </div>

    <!-- Warehouse Stock Table -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Current Warehouse Stock</h2>
            <span class="text-sm text-gray-600">{{ count($products) }} products</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-blue-700 text-white">
                    <tr>
                        <th class="px-6 py-3 text-left">Product Name</th>
                        <th class="px-6 py-3 text-left">Category</th>
                        <th class="px-6 py-3 text-left">Supplier</th>
                        <th class="px-6 py-3 text-left">Price</th>
                        <th class="px-6 py-3 text-left">Current Stock</th>
                        <th class="px-6 py-3 text-left">Pending Orders</th>
                        <th class="px-6 py-3 text-left">Reorder Level</th>
                        <th class="px-6 py-3 text-left">Reorder Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $prod)
                        @php
                            $needsReorder = $prod->stock_quantity < ($prod->reorder_level ?? 10);
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3 font-medium text-gray-800">{{ $prod->product_name }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">{{ $prod->category }}</span>
                            </td>
                            <td class="px-6 py-3">
                                @if($prod->supplier_name)
                                    <span class="text-purple-600 text-sm">{{ $prod->supplier_name }}</span>
                                @else
                                    <span class="text-gray-400">No supplier</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 font-semibold">₱{{ number_format($prod->price, 2) }}</td>
                            <td class="px-6 py-3">
                                <span class="font-semibold {{ $needsReorder ? 'text-red-600' : 'text-green-700' }}">
                                    {{ $prod->stock_quantity }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                @if($prod->pending > 0)
                                    <span class="text-blue-600 font-semibold">{{ $prod->pending }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $prod->reorder_level ?? 10 }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $needsReorder 
                                        ? 'bg-red-100 text-red-800' 
                                        : 'bg-green-100 text-green-800' }}">
                                    {{ $needsReorder ? 'Reorder Needed' : 'In Stock' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-boxes text-4xl mb-3 text-gray-300"></i>
                                    <p>No products found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>