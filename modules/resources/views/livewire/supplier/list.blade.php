<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public array $suppliers = [];
    public array $products = [];
    public array $selectedProducts = [];

    // Supplier properties
    public int $supplier_id = 0;
    public string $name = '';
    public string $contact_person = '';
    public string $email = '';

    // Product properties
    public ?int $product_id_edit = null;
    public string $product_name = '';
    public string $category = '';
    public ?int $product_supplier_id = null;
    public ?float $price = null;

    public bool $isEditing = false;
    public bool $isEditingProduct = false;
    public string $message = '';
    public string $productMessage = '';

    public function mount(): void
    {
        $this->loadSuppliers();
        $this->loadProducts();
    }

    // ---------------- SUPPLIER FUNCTIONS ----------------
    public function loadSuppliers(): void
    {
        $this->suppliers = DB::table('suppliers')
            ->orderBy('supplier_id')
            ->get()
            ->map(function ($supplier) {
                $products = DB::table('products')
                    ->where('supplier_id', $supplier->supplier_id)
                    ->select('product_name', 'category')
                    ->get()
                    ->map(fn($p) => "{$p->product_name} ({$p->category})")
                    ->toArray();
                $supplier->products = $products;
                return $supplier;
            })
            ->toArray();
    }

    public function loadProducts(): void
    {
        $this->products = DB::table('products')
            ->select('product_id', 'product_name', 'category', 'supplier_id', 'price')
            ->orderBy('product_name')
            ->get()
            ->toArray();
    }

    // ---------------- SUPPLIER CRUD ----------------
    public function save(): void
    {
        if (trim($this->name) === '') {
            $this->message = 'Supplier name is required.';
            return;
        }

        if ($this->isEditing && $this->supplier_id > 0) {
            DB::table('suppliers')->where('supplier_id', $this->supplier_id)
                ->update([
                    'name' => $this->name,
                    'contact_person' => $this->contact_person,
                    'email' => $this->email,
                ]);

            // Update products to this supplier
            foreach ($this->selectedProducts as $productId) {
                DB::table('products')->where('product_id', $productId)->update(['supplier_id' => $this->supplier_id]);
            }

            $this->message = 'Supplier updated successfully.';
        } else {
            $supplierId = DB::table('suppliers')->insertGetId([
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'email' => $this->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->selectedProducts as $productId) {
                DB::table('products')->where('product_id', $productId)->update(['supplier_id' => $supplierId]);
            }

            $this->message = 'Supplier added successfully.';
        }

        $this->resetForm();
        $this->loadSuppliers();
        $this->loadProducts();
    }

    public function edit(int $id): void
    {
        $supplier = DB::table('suppliers')->where('supplier_id', $id)->first();
        if ($supplier) {
            $this->supplier_id = $supplier->supplier_id;
            $this->name = $supplier->name ?? '';
            $this->contact_person = $supplier->contact_person ?? '';
            $this->email = $supplier->email ?? '';
            $this->selectedProducts = DB::table('products')->where('supplier_id', $id)->pluck('product_id')->toArray();
            $this->isEditing = true;
            $this->message = '';
        }
    }

    public function delete(int $id): void
    {
        // Check if supplier has linked purchase orders
        $orderCount = DB::table('purchase_orders')->where('supplier_id', $id)->count();

        if ($orderCount > 0) {
            // Show a warning message instead of deleting automatically
            $this->message = "Cannot delete supplier. It has $orderCount purchase order(s).";
            return;
        }

        // Remove supplier assignment from products
        DB::table('products')->where('supplier_id', $id)->update(['supplier_id' => null]);

        // Delete the supplier
        DB::table('suppliers')->where('supplier_id', $id)->delete();

        $this->message = 'Supplier deleted successfully.';
        $this->loadSuppliers();
        $this->loadProducts();
    }

    public function resetForm(): void
    {
        $this->supplier_id = 0;
        $this->name = '';
        $this->contact_person = '';
        $this->email = '';
        $this->selectedProducts = [];
        $this->isEditing = false;
    }

    // ---------------- PRODUCT CRUD ----------------
    public function saveProduct(): void
{
    if (trim($this->product_name) === '') {
        $this->productMessage = 'Product name is required.';
        return;
    }

    if (trim($this->category) === '') {
        $this->productMessage = 'Product category is required.';
        return;
    }

    if ($this->price === null || $this->price <= 0) {
        $this->productMessage = 'Valid product price is required.';
        return;
    }

    DB::beginTransaction();

    try {
        if ($this->isEditingProduct && $this->product_id_edit) {
            // EDIT MODE
            $product = DB::table('products')->where('product_id', $this->product_id_edit)->first();
            
            // Update the product
            DB::table('products')->where('product_id', $this->product_id_edit)->update([
                'product_name' => $this->product_name,
                'category' => $this->category,
                'supplier_id' => $this->product_supplier_id,
                'price' => $this->price,
                'updated_at' => now(),
            ]);

            // Update the corresponding inventory entry if it exists
            if ($product->inventory_id) {
                DB::table('inventories')->where('inventory_id', $product->inventory_id)->update([
                    'product_name' => $this->product_name,
                    'unit_price' => $this->price,
                    'updated_at' => now(),
                ]);
            }

            $this->productMessage = 'Product updated successfully.';
        } else {
            // CREATE MODE
            // First, create the inventory entry
            $inventoryId = DB::table('inventories')->insertGetId([
                'product_name' => $this->product_name,
                'sku' => $this->generateSKU($this->product_name),
                'description' => $this->category, // Using category as description
                'quantity' => 0, // Start with 0 stock
                'min_quantity' => 10, // Default minimum quantity
                'unit_price' => $this->price,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Then create the product with the inventory_id
            DB::table('products')->insert([
                'product_name' => $this->product_name,
                'category' => $this->category,
                'supplier_id' => $this->product_supplier_id,
                'price' => $this->price,
                'inventory_id' => $inventoryId, // Link to inventory
                'stock_quantity' => 0, // Start with 0 stock
                'reorder_level' => 10, // Default reorder level
                'slug' => Str::slug($this->product_name),
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->productMessage = 'Product added successfully with inventory entry.';
        }

        DB::commit();

    } catch (\Exception $e) {
        DB::rollBack();
        $this->productMessage = 'Error saving product: ' . $e->getMessage();
        return;
    }

    $this->resetProductForm();
    $this->loadProducts();
    $this->loadSuppliers();
}

// Add this helper method to generate SKU
private function generateSKU($productName)
{
    // Generate a unique SKU
    $prefix = 'SKU';
    $productCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 6));
    $random = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
    $timestamp = date('ymd');
    
    return $prefix . '-' . $productCode . '-' . $random . '-' . $timestamp;
}

public function editProduct(int $id): void
{
    $product = DB::table('products')->where('product_id', $id)->first();
    if ($product) {
        $this->product_id_edit = $product->product_id;
        $this->product_name = $product->product_name;
        $this->category = $product->category ?? '';
        $this->product_supplier_id = $product->supplier_id;
        $this->price = (float)$product->price;
        $this->isEditingProduct = true;
        $this->productMessage = '';
    }
}
    public function resetProductForm(): void
    {
        $this->product_id_edit = null;
        $this->product_name = '';
        $this->category = '';
        $this->product_supplier_id = null;
        $this->price = null;
        $this->isEditingProduct = false;
        $this->productMessage = '';
    }

    public function deleteProduct(int $id): void
{
    DB::beginTransaction();
    
    try {
        // Get the product to find inventory_id
        $product = DB::table('products')->where('product_id', $id)->first();
        
        if (!$product) {
            $this->message = 'Product not found.';
            return;
        }
        
        // Delete the inventory entry if it exists
        if ($product->inventory_id) {
            DB::table('inventories')->where('inventory_id', $product->inventory_id)->delete();
        }

        // Delete the product
        DB::table('products')->where('product_id', $id)->delete();

        DB::commit();
        
        $this->message = 'Product and inventory entry deleted successfully.';
        $this->loadProducts();
        $this->loadSuppliers();
        
    } catch (\Exception $e) {
        DB::rollBack();
        $this->message = 'Error deleting product: ' . $e->getMessage();
    }
}
};
?>
<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold mb-6 text-green-800">Supplier & Product Management</h1>

    <!-- Supplier Messages -->
    @if($message)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($message, 'successfully') ? 'bg-green-100 text-green-800 border-l-4 border-green-600' : 'bg-red-100 text-red-800 border-l-4 border-red-600' }}">
            <div class="flex items-center">
                @if(str_contains($message, 'successfully'))
                    <i class="fas fa-check-circle mr-3"></i>
                @else
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                @endif
                <span>{{ $message }}</span>
            </div>
        </div>
    @endif

    <!-- Add/Edit Supplier -->
    <div class="bg-white p-6 rounded-lg shadow mb-8 max-w-3xl">
        <h2 class="text-xl font-semibold mb-4">{{ $isEditing ? 'Edit Supplier' : 'Add New Supplier' }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">Supplier Name *</label>
                <input wire:model="name" type="text" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Enter supplier name">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Contact Person</label>
                <input wire:model="contact_person" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Contact person name">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Email</label>
                <input wire:model="email" type="email" class="w-full border border-gray-300 rounded p-2" placeholder="supplier@example.com">
            </div>
        </div>
        <div class="mt-4 flex space-x-3">
            <button wire:click="save" 
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg transition duration-200">
                @if($isEditing)
                    <i class="fas fa-save"></i> Update Supplier
                @else
                    <i class="fas fa-plus"></i> Add Supplier
                @endif
            </button>
            @if($isEditing)
                <button wire:click="resetForm" 
                        class="flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    <!-- Add/Edit Product -->
    <div class="bg-white p-6 rounded-lg shadow mb-8 max-w-3xl">
        <h2 class="text-xl font-semibold mb-4 text-green-700">{{ $isEditingProduct ? 'Edit Product' : 'Add New Product' }}</h2>
        
        @if($productMessage)
            <div class="mb-4 p-3 {{ str_contains($productMessage, 'successfully') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded">
                <div class="flex items-center">
                    @if(str_contains($productMessage, 'successfully'))
                        <i class="fas fa-check-circle mr-2"></i>
                    @else
                        <i class="fas fa-exclamation-circle mr-2"></i>
                    @endif
                    <span>{{ $productMessage }}</span>
                </div>
            </div>
        @endif
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">Product Name *</label>
                <input wire:model="product_name" type="text" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-green-500" placeholder="Enter product name">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Category *</label>
                <input wire:model="category" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="e.g., Electronics">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Supplier</label>
                <select wire:model="product_supplier_id" class="w-full border border-gray-300 rounded p-2">
                    <option value="">— None —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->supplier_id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Price *</label>
                <input wire:model="price" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded p-2" placeholder="₱0.00">
            </div>
        </div>
        <div class="mt-4 flex space-x-3">
            <button wire:click="saveProduct" 
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition duration-200">
                @if($isEditingProduct)
                    <i class="fas fa-save"></i> Update Product
                @else
                    <i class="fas fa-plus"></i> Add Product
                @endif
            </button>
            @if($isEditingProduct)
                <button wire:click="resetProductForm" 
                        class="flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    <!-- Supplier Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow mb-8">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Suppliers ({{ count($suppliers) }})</h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-6 py-3">ID</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Contact</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Products</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($suppliers as $supplier)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-gray-700">{{ $supplier->supplier_id }}</td>
                        <td class="px-6 py-4 font-medium">{{ $supplier->name ?? '—' }}</td>
                        <td class="px-6 py-4">{{ $supplier->contact_person ?? '—' }}</td>
                        <td class="px-6 py-4">
                            @if($supplier->email)
                                <a href="mailto:{{ $supplier->email }}" class="text-blue-600 hover:underline">{{ $supplier->email }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if(!empty($supplier->products))
                                <div class="max-w-xs">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                                        {{ count($supplier->products) }} products
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="edit({{ $supplier->supplier_id }})" 
                                        class="flex items-center gap-1 bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-edit text-sm"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="if(confirm('Delete supplier: {{ addslashes($supplier->name) }}?')) { @this.delete({{ $supplier->supplier_id }}) }" 
                                        class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-truck text-4xl mb-3 text-gray-300"></i>
                                <p class="text-lg">No suppliers found.</p>
                                <p class="text-sm mt-1">Add your first supplier above.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Product Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Products ({{ count($products) }})</h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-blue-700 text-white">
                <tr>
                    <th class="px-6 py-3">ID</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Category</th>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3">Price</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($products as $product)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-semibold text-gray-700">{{ $product->product_id }}</td>
                        <td class="px-6 py-4 font-medium">{{ $product->product_name }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                {{ $product->category ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($product->supplier_id)
                                @php
                                    $supplier = collect($suppliers)->firstWhere('supplier_id', $product->supplier_id);
                                @endphp
                                <span class="text-green-700">{{ $supplier->name ?? '—' }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-semibold text-green-700">
                            ₱{{ number_format($product->price ?? 0, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="editProduct({{ $product->product_id }})" 
                                        class="flex items-center gap-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-edit text-sm"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="if(confirm('Delete product: {{ addslashes($product->product_name) }}?')) { @this.deleteProduct({{ $product->product_id }}) }"
                                        class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-box text-4xl mb-3 text-gray-300"></i>
                                <p class="text-lg">No products found.</p>
                                <p class="text-sm mt-1">Add your first product above.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>