<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.warehouse')] class extends Component
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
        $products = DB::table('product as p')
            ->leftJoin('inventory as i', 'p.product_id', '=', 'i.product_id')
            ->select(
                'p.product_id',
                'p.product_name',
                'p.supplier_id',
                'p.reorder_level',
                'p.price',
                DB::raw('COALESCE(i.stock_level,0) as stock')
            )
            ->orderBy('p.product_name')
            ->get()
            ->toArray();

        // Calculate pending quantities from Processing POs
        foreach ($products as &$prod) {
            $pendingQty = DB::table('procurement_item as pi')
                ->join('procurement_order as po', 'pi.order_id', '=', 'po.order_id')
                ->where('pi.product_id', $prod->product_id)
                ->where('po.status', 'Processing')
                ->sum('pi.quantity');

            $prod->pending = $pendingQty;
        }

        $this->products = $products;

        // Load urgent requisitions for manual PO
        $this->urgentRequisitions = DB::table('requisition_item as ri')
            ->join('requisition as r', 'ri.requisition_id', '=', 'r.requisition_id')
            ->join('product as p', 'ri.product_id', '=', 'p.product_id')
            ->leftJoin('inventory as i', 'p.product_id', '=', 'i.product_id')
            ->join('employee as e', 'r.employee_id', '=', 'e.employee_id')
            ->where('r.status', 'Urgent')
            ->select(
                'ri.requisition_item_id',
                'r.requisition_id',
                'p.product_id',
                'p.product_name',
                'p.supplier_id',
                'p.reorder_level',
                DB::raw('COALESCE(i.stock_level,0) as stock'),
                'p.price',
                'ri.quantity as requested_qty',
                'e.name as requested_by'
            )
            ->orderBy('p.product_name')
            ->get()
            ->toArray();
    }

    public function loadRequisitions(): void
{
    $this->urgentRequisitions = DB::table('requisition_item as ri')
        ->join('requisition as r', 'ri.requisition_id', '=', 'r.requisition_id')
        ->join('product as p', 'ri.product_id', '=', 'p.product_id')
        ->leftJoin('inventory as i', 'p.product_id', '=', 'i.product_id')
        ->join('employee as e', 'r.employee_id', '=', 'e.employee_id')
        ->where('r.status', 'Urgent')
        ->select(
            'ri.requisition_item_id',
            'r.requisition_id',
            'p.product_id',
            'p.product_name',
            'p.supplier_id',
            'p.reorder_level',
            DB::raw('COALESCE(i.stock_level,0) as stock'),
            'p.price',
            'ri.quantity as requested_qty',
            'e.name as requested_by'
        )
        ->orderBy('p.product_name')
        ->get()
        ->toArray();
}


    public function loadManagers(): void
    {
        $this->managers = DB::table('employee')
            ->where('role', 'Warehouse Staff')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function loadSuppliers(): void
    {
        $this->suppliers = DB::table('supplier')->orderByDesc('rating')->get()->toArray();
    }

    // ---------- Automatic PO ----------
    public function generateAutomaticPO(): void
    {
        $poDate = now()->toDateString();
        $orderDate = $poDate;
        $productsBySupplier = [];
        $addedCount = 0;

        foreach ($this->products as $prod) {
            $supplierId = $prod->supplier_id ?? 0;
            if (!$supplierId) continue;

            $qty = $prod->requested_qty ?? ($prod->reorder_level - $prod->stock);
            if ($qty <= 0) continue;

            $productsBySupplier[$supplierId][] = [
                'product_id' => $prod->product_id,
                'quantity'   => $qty,
                'price'      => $prod->price
            ];
        }

        if (empty($productsBySupplier)) {
            $this->message = '⚠️ No products meet the reorder or urgent requisition criteria.';
            return;
        }

        foreach ($productsBySupplier as $supplierId => $items) {
            $poId = DB::table('purchase_order')->insertGetId([
                'requisition_id' => null,
                'supplier_id' => $supplierId,
                'status' => 'Processing', // now Processing
                'order_date' => $poDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $orderId = DB::table('procurement_order')->insertGetId([
                    'po_id' => $poId,
                    'supplier_id' => $supplierId,
                    'order_date' => $orderDate,
                    'status' => 'Processing', // now Processing
                    'total_amount' => $item['quantity'] * $item['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('procurement_item')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $addedCount++;
            }

            DB::table('erp_po_sync')->insert([
                'po_id' => $poId,
                'sync_status' => 'Pending',
                'last_sync_time' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->message = $addedCount > 0
            ? "✅ Automatic Purchase Orders created successfully for {$addedCount} item(s)!"
            : '⚠️ No eligible products found for automatic PO.';

        $this->loadProducts();
    }

    // ---------- Manual PO ----------
    public function generateManualPO(): void
    {
        if (!$this->selectedSupplier || empty($this->selectedProducts) || !$this->selectedManager) {
            $this->message = '⚠️ Select a supplier, manager, and at least one product.';
            return;
        }

        if (empty($this->urgentRequisitions)) {
            $this->message = '⚠️ No urgent requisitions found. Please create one before generating a manual purchase order.';
            return;
        }

        $supplierId = $this->selectedSupplier;
        $managerId = $this->selectedManager;
        $poDate = now()->toDateString();
        $orderDate = $poDate;

        $productsGroupedByRequisition = collect($this->urgentRequisitions)
            ->whereIn('product_id', $this->selectedProducts)
            ->groupBy('requisition_id');

        foreach ($productsGroupedByRequisition as $requisitionId => $items) {

            DB::table('requisition')->where('requisition_id', $requisitionId)
                ->update(['status' => 'Approved', 'updated_at' => now()]);

            DB::table('requisition_item')
                ->where('requisition_id', $requisitionId)
                ->whereIn('product_id', $this->selectedProducts)
                ->update(['remarks' => 'We will send it', 'updated_at' => now()]);

            DB::table('approval')->insert([
                'requisition_id' => $requisitionId,
                'manager_id' => $managerId,
                'approval_status' => 'Approved',
                'approval_date' => $poDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $poId = DB::table('purchase_order')->insertGetId([
                'requisition_id' => $requisitionId,
                'supplier_id' => $supplierId,
                'status' => 'Processing', // now Processing
                'order_date' => $poDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $qty = $this->quantities[$item->product_id] ?? $item->requested_qty;
                $price = $item->price;

                $orderId = DB::table('procurement_order')->insertGetId([
                    'po_id' => $poId,
                    'supplier_id' => $supplierId,
                    'order_date' => $orderDate,
                    'status' => 'Processing', // now Processing
                    'total_amount' => $qty * $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('procurement_item')->insert([
                    'order_id' => $orderId,
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'price' => $price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('erp_po_sync')->insert([
                'po_id' => $poId,
                'sync_status' => 'Pending',
                'last_sync_time' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">
            {{ $message }}
        </div>
    @endif

    <!-- Automatic PO -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Automatic Purchase Order</h2>
        <p class="text-gray-600 mb-4">
            Automatically generate POs for urgent requisitions and products below their reorder level.
        </p>
        <button wire:click="generateAutomaticPO" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
            Generate Automatically
        </button>
    </div>

    <!-- Manual PO -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Manual Purchase Order</h2>

        @if (empty($urgentRequisitions))
        <p class="text-red-600 font-bold">
            ⚠️ No urgent requisitions found.
        </p>
    @else
        <p class="text-green-600 font-bold mb-2">
            ✅ There are {{ count($urgentRequisitions) }} urgent requisition(s).
        </p>

        <!-- Display urgent requisition details -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm border-collapse border border-gray-300">
                <thead class="bg-green-600 text-white">
                    <tr>
                        <th class="px-4 py-2 border">Product Name</th>
                        <th class="px-4 py-2 border">Requested Qty</th>
                        <th class="px-4 py-2 border">Requested By</th>
                        <th class="px-4 py-2 border">Stock</th>
                        <th class="px-4 py-2 border">Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($urgentRequisitions as $req)
                        <tr class="hover:bg-green-50">
                            <td class="px-4 py-2 border">{{ $req->product_name }}</td>
                            <td class="px-4 py-2 border">{{ $req->requested_qty }}</td>
                            <td class="px-4 py-2 border">{{ $req->requested_by }}</td>
                            <td class="px-4 py-2 border">{{ $req->stock }}</td> 
                            <td class="px-4 py-2 border">
                                @php
                                    $supplier = collect($suppliers)->first(fn($s) => $s->supplier_id == $req->supplier_id);
                                @endphp
                                {{ $supplier->supplier_name ?? 'N/A' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Supplier</label>
            <select wire:model="selectedSupplier" class="w-full border border-gray-300 rounded p-2">
                <option value="">— Select Supplier —</option>
                @foreach($suppliers as $sup)
                    <option value="{{ $sup->supplier_id }}">
                        {{ $sup->supplier_name }} (Rating: {{ $sup->rating ?? 'N/A' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Manager (Warehouse Staff)</label>
            <select wire:model="selectedManager" class="w-full border border-gray-300 rounded p-2">
                <option value="">— Select Manager —</option>
                @foreach($managers as $manager)
                    <option value="{{ $manager->employee_id }}">{{ $manager->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-2">Select Products</label>
            <div class="space-y-2">
                @foreach($products as $prod)
                    <div class="flex items-center space-x-3 border p-2 rounded 
                        {{ isset($prod->requested_qty) || $prod->stock <= $prod->reorder_level ? 'bg-red-50' : '' }}">
                        <input type="checkbox" wire:model="selectedProducts" value="{{ $prod->product_id }}">
                        <span>
                            {{ $prod->product_name }}
                            @if(isset($prod->requested_qty))
                                (Requested: {{ $prod->requested_qty }} by {{ $prod->requested_by ?? 'N/A' }})
                            @endif
                        </span>
                        <input 
                            wire:model="quantities.{{ $prod->product_id }}" 
                            type="number" 
                            min="1" step="1" 
                            placeholder="Qty" 
                            class="w-20 border border-gray-300 rounded p-1"
                            value="{{ $prod->requested_qty ?? ($prod->reorder_level - $prod->stock) }}">
                    </div>
                @endforeach
            </div>
        </div>

        <button wire:click="generateManualPO" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded">
            Generate Manually
        </button>
    </div>

    <!-- Warehouse Stock Table -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Current Warehouse Stock</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm border-collapse">
                <thead class="bg-blue-700 text-white">
                    <tr>
                        <th class="px-6 py-3">Product Name</th>
                        <th class="px-6 py-3">Reorder Level</th>
                        <th class="px-6 py-3">Price</th>
                        <th class="px-6 py-3">Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $prod)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3">{{ $prod->product_name }}</td>
                            <td class="px-6 py-3">{{ $prod->reorder_level }}</td>
                            <td class="px-6 py-3">₱{{ number_format($prod->price,2) }}</td>
                            <td class="px-6 py-3 font-semibold 
                                {{ ($prod->stock + $prod->pending) <= $prod->reorder_level ? 'text-red-600' : 'text-green-700' }}">
                                {{ $prod->stock }}
                                @if($prod->pending > 0)
                                    <span class="text-gray-500">(+{{ $prod->pending }} pending)</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-6 text-gray-500">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

