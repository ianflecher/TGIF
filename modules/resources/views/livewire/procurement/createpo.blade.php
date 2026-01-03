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

    public function mount(): void
    {
        $this->loadProducts();
        $this->loadSuppliers();
        $this->loadManagers();
        $this->loadRequisitions();
    }

    public function loadProducts(): void
    {
        // Updated to match your products table structure
        $this->products = DB::table('products as p')
            ->leftJoin('inventories as i', 'p.inventory_id', '=', 'i.inventory_id')
            ->select(
                'p.product_id',
                'p.product_name',
                'p.category',
                'p.price',
                'p.stock_quantity as stock',
                'p.reorder_level', // Note: You might need to add this column
                'p.status'
            )
            ->where('p.status', 'published')
            ->orderBy('p.product_name')
            ->get()
            ->map(function ($product) {
                // Calculate pending quantities from Processing POs
                $product->pending = DB::table('procurement_items as pi')
                    ->join('procurement_orders as po', 'pi.order_id', '=', 'po.order_id')
                    ->where('pi.product_id', $product->product_id)
                    ->where('po.status', 'Processing')
                    ->sum('pi.quantity');
                
                // Calculate reorder level (if column doesn't exist, default to 10)
                $product->reorder_level = property_exists($product, 'reorder_level') 
                    ? $product->reorder_level 
                    : 10;
                    
                return $product;
            })
            ->toArray();
    }

    public function loadRequisitions(): void
{
    // Updated to match your requisition table structure
    $this->urgentRequisitions = DB::table('requisition_items as ri')
        ->join('purchase_requisitions as r', 'ri.requisition_id', '=', 'r.requisition_id')
        ->join('products as p', 'ri.product_id', '=', 'p.product_id')
        ->join('users as u', 'r.requested_by', '=', 'u.user_id')
        ->where('r.status', 'Urgent')
        ->select(
            'ri.requisition_id',
            'r.requisition_id',
            'p.product_id',
            'p.product_name',
            'p.category',
            'p.price',
            'p.stock_quantity as stock',
            'ri.quantity as requested_qty',
            'u.full_name as requested_by'
        )
        ->orderBy('p.product_name')
        ->get()
        ->toArray();
}

public function loadManagers(): void
{
    // Assuming managers are users with manager or admin role
    $this->managers = DB::table('users')
        ->whereIn('role', ['manager', 'admin'])
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

    // ---------- Automatic PO ----------
    public function generateAutomaticPO(): void
    {
        $poDate = now()->toDateString();
        $orderDate = $poDate;
        $addedCount = 0;

        // For automatic PO, we need to match products with suppliers
        // Since products don't have supplier_id, we need to find suppliers based on category or other criteria
        foreach ($this->products as $product) {
            // Find supplier for this product (simplified - you might want to create a product_supplier table)
            $supplier = $this->findSupplierForProduct($product);
            
            if (!$supplier) continue;

            // Check if product needs reorder
            if (($product->stock + $product->pending) > ($product->reorder_level ?? 10)) {
                continue;
            }

            $quantity = ($product->reorder_level ?? 10) - $product->stock;
            if ($quantity <= 0) continue;

            // Create PO
            $poId = DB::table('purchase_orders')->insertGetId([
                'requisition_id' => null,
                'supplier_id' => $supplier->supplier_id,
                'status' => 'Processing',
                'order_date' => $poDate,
                'total_amount' => $quantity * $product->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create procurement order
            $orderId = DB::table('procurement_orders')->insertGetId([
                'po_id' => $poId,
                'supplier_id' => $supplier->supplier_id,
                'order_date' => $orderDate,
                'status' => 'Processing',
                'total_amount' => $quantity * $product->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create procurement item
            DB::table('procurement_items')->insert([
                'order_id' => $orderId,
                'product_id' => $product->product_id,
                'quantity' => $quantity,
                'price' => $product->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $addedCount++;
        }

        $this->message = $addedCount > 0
            ? "✅ Automatic Purchase Orders created successfully for {$addedCount} item(s)!"
            : '⚠️ No eligible products found for automatic PO.';

        $this->loadProducts();
    }

    // Helper to find supplier for product
    private function findSupplierForProduct($product)
    {
        // Simplistic approach - find by category
        return DB::table('suppliers')
            ->where('status', 'active')
            ->first();
    }

    // ---------- Manual PO ----------
    public function generateManualPO(): void
    {
        if (!$this->selectedSupplier || empty($this->selectedProducts) || !$this->selectedManager) {
            $this->message = '⚠️ Select a supplier, manager, and at least one product.';
            return;
        }

        if (empty($this->urgentRequisitions)) {
            $this->message = '⚠️ No urgent requisitions found.';
            return;
        }

        $supplierId = $this->selectedSupplier;
        $managerId = $this->selectedManager;
        $poDate = now()->toDateString();

        // Group urgent requisitions by requisition_id
        $productsGroupedByRequisition = collect($this->urgentRequisitions)
            ->whereIn('product_id', $this->selectedProducts)
            ->groupBy('requisition_id');

        foreach ($productsGroupedByRequisition as $requisitionId => $items) {
            // Update requisition status
            DB::table('requisitions')->where('requisition_id', $requisitionId)
                ->update(['status' => 'Approved', 'updated_at' => now()]);

            // Create PO
            $poId = DB::table('purchase_orders')->insertGetId([
                'requisition_id' => $requisitionId,
                'supplier_id' => $supplierId,
                'status' => 'Processing',
                'order_date' => $poDate,
                'total_amount' => collect($items)->sum(fn($item) => 
                    ($this->quantities[$item->product_id] ?? $item->requested_qty) * $item->price
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $quantity = $this->quantities[$item->product_id] ?? $item->requested_qty;
                $price = $item->price;

                // Create procurement order
                $orderId = DB::table('procurement_orders')->insertGetId([
                    'po_id' => $poId,
                    'supplier_id' => $supplierId,
                    'order_date' => $poDate,
                    'status' => 'Processing',
                    'total_amount' => $quantity * $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create procurement item
                DB::table('procurement_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->message = "✅ Purchase Order(s) created successfully!";
        $this->resetForm();
        $this->loadProducts();
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
    <h1 class="text-3xl font-bold text-blue-800 mb-6">Generate Purchase Orders</h1>

    @if($message)
        <div class="mb-4 p-3 rounded-lg {{ str_contains($message, '✅') ? 'bg-green-100 text-green-800 border-l-4 border-green-600' : 'bg-yellow-100 text-yellow-800 border-l-4 border-yellow-600' }}">
            {{ $message }}
        </div>
    @endif

    <!-- Automatic PO -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Automatic Purchase Order</h2>
        <p class="text-gray-600 mb-4">
            Automatically generate POs for products below reorder level.
        </p>
        <button wire:click="generateAutomaticPO" 
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition duration-200">
            <i class="fas fa-robot"></i>
            Generate Automatically
        </button>
    </div>

    <!-- Manual PO -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Manual Purchase Order</h2>

        @if (empty($urgentRequisitions))
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    No urgent requisitions found.
                </p>
            </div>
        @else
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded">
                <p class="text-green-700 font-medium">
                    <i class="fas fa-check-circle mr-2"></i>
                    Found {{ count($urgentRequisitions) }} urgent requisition(s)
                </p>
            </div>

            <!-- Urgent Requisitions Table -->
            <div class="overflow-x-auto mb-6">
                <table class="min-w-full text-sm border border-gray-300 rounded-lg overflow-hidden">
                    <thead class="bg-green-600 text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-left">Requested Qty</th>
                            <th class="px-4 py-3 text-left">Requested By</th>
                            <th class="px-4 py-3 text-left">Stock</th>
                            <th class="px-4 py-3 text-left">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($urgentRequisitions as $req)
                            <tr class="hover:bg-green-50 border-b border-gray-200">
                                <td class="px-4 py-3">{{ $req->product_name }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $req->requested_qty }}</td>
                                <td class="px-4 py-3">{{ $req->requested_by }}</td>
                                <td class="px-4 py-3 {{ $req->stock < $req->requested_qty ? 'text-red-600 font-semibold' : '' }}">
                                    {{ $req->stock }}
                                </td>
                                <td class="px-4 py-3">₱{{ number_format($req->price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                <label class="block text-gray-700 mb-2 font-medium">Select Manager</label>
                <select wire:model="selectedManager" 
                        class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    <option value="">— Select Manager —</option>
                    @foreach($managers as $manager)
                        <option value="{{ $manager->employee_id }}">{{ $manager->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 mb-3 font-medium">Select Products & Quantities</label>
            <div class="space-y-3 max-h-80 overflow-y-auto p-2 border border-gray-300 rounded-lg">
                @foreach($products as $prod)
                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition {{ $prod->stock < ($prod->reorder_level ?? 10) ? 'bg-red-50 border-red-200' : '' }}">
                        <div class="flex items-center space-x-3">
                            <input type="checkbox" 
                                   wire:model="selectedProducts" 
                                   value="{{ $prod->product_id }}"
                                   class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                            <div>
                                <span class="font-medium text-gray-800">{{ $prod->product_name }}</span>
                                <div class="text-sm text-gray-600 flex items-center gap-2">
                                    <span class="bg-gray-100 px-2 py-0.5 rounded">{{ $prod->category }}</span>
                                    <span>Stock: {{ $prod->stock }}</span>
                                    @if($prod->pending > 0)
                                        <span class="text-blue-600">(+{{ $prod->pending }} pending)</span>
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
                                value="{{ $prod->stock < ($prod->reorder_level ?? 10) ? (($prod->reorder_level ?? 10) - $prod->stock) : 1 }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button wire:click="generateManualPO" 
                class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200 shadow hover:shadow-lg">
            <i class="fas fa-file-invoice-dollar"></i>
            Generate Purchase Order
        </button>
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
                        <th class="px-6 py-3 text-left">Price</th>
                        <th class="px-6 py-3 text-left">Stock Level</th>
                        <th class="px-6 py-3 text-left">Reorder Level</th>
                        <th class="px-6 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $prod)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3 font-medium text-gray-800">{{ $prod->product_name }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-block bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">{{ $prod->category }}</span>
                            </td>
                            <td class="px-6 py-3 font-semibold">₱{{ number_format($prod->price, 2) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold {{ $prod->stock < ($prod->reorder_level ?? 10) ? 'text-red-600' : 'text-green-700' }}">
                                        {{ $prod->stock }}
                                    </span>
                                    @if($prod->pending > 0)
                                        <span class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                                            +{{ $prod->pending }} pending
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-3">{{ $prod->reorder_level ?? 10 }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $prod->stock < ($prod->reorder_level ?? 10) 
                                        ? 'bg-red-100 text-red-800' 
                                        : 'bg-green-100 text-green-800' }}">
                                    {{ $prod->stock < ($prod->reorder_level ?? 10) ? 'Low Stock' : 'In Stock' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-boxes text-4xl mb-3 text-gray-300"></i>
                                    <p>No products found in inventories</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>