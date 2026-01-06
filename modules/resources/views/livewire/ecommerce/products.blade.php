<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

new #[Layout('components.layouts.ecommerce')] class extends Component
{
    use WithFileUploads;

    protected array $rules = [
        'newName' => 'required|string|max:255',
        'newCategory' => 'required|string|max:255',
        'newDescription' => 'required|string|max:1000',
        'newPrice' => 'required|numeric|min:0.01',
        'newShortDescription' => 'nullable|string|max:255',
        'newStatus' => 'required|in:draft,published,archived',
        'newStockQuantity' => 'required|integer|min:0',
        'newImage' => 'nullable|image|max:2048',
        'newFlavors' => 'sometimes|array',
        'newSizes' => 'sometimes|array',
        'newVarieties' => 'sometimes|array',
    ];

    // Add product
    public string $newName = '';
    public string $newCategory = '';
    public string $newDescription = '';
    public float $newPrice = 0;
    public string $newShortDescription = '';
    public string $newStatus = 'published';
    public int $newStockQuantity = 0;
    public ?TemporaryUploadedFile $newImage = null;
    public array $newFlavors = [];
    public array $newSizes = [];
    public array $newVarieties = [];
    public string $newFlavorInput = '';
    public string $newSizeInput = '';
    public string $newVarietyInput = '';

    // Edit product
    public ?int $editId = null;
    public ?string $editName = null;
    public ?string $editCategory = null;
    public ?string $editDescription = null;
    public float $editPrice = 0;
    public ?string $editShortDescription = null;
    public string $editStatus = 'published';
    public int $editStockQuantity = 0;
    public ?TemporaryUploadedFile $editImage = null;
    public array $editFlavors = [];
    public array $editSizes = [];
    public array $editVarieties = [];
    public string $editFlavorInput = '';
    public string $editSizeInput = '';
    public string $editVarietyInput = '';

    // Search & Filter
    public string $search = '';
    public string $categoryFilter = 'all';

    // Dynamic lists
    public array $categories = [];

    public function mount()
    {
        $this->loadCategories();
    }

    // Computed property: products filtered dynamically
    public function getProductsProperty(): array
    {
        $query = DB::table('products');

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('product_name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('product_name', 'asc')
                     ->get()
                     ->map(fn($p) => (array)$p)
                     ->toArray();
    }

    // Load unique categories from attributes
    public function loadCategories()
    {
        $products = DB::table('products')->get();
        $categories = [];
        
        foreach ($products as $product) {
            if ($product->attributes) {
                $attributes = json_decode($product->attributes, true);
                if (isset($attributes['category']) && !in_array($attributes['category'], $categories)) {
                    $categories[] = $attributes['category'];
                }
            }
        }
        
        $this->categories = $categories;
    }

    // Add flavor to new product
    public function addNewFlavor()
    {
        if ($this->newFlavorInput && !in_array($this->newFlavorInput, $this->newFlavors)) {
            $this->newFlavors[] = trim($this->newFlavorInput);
            $this->newFlavorInput = '';
        }
    }

    // Remove flavor from new product
    public function removeNewFlavor($index)
    {
        unset($this->newFlavors[$index]);
        $this->newFlavors = array_values($this->newFlavors);
    }

    // Add size to new product
    public function addNewSize()
    {
        if ($this->newSizeInput && !in_array($this->newSizeInput, $this->newSizes)) {
            $this->newSizes[] = trim($this->newSizeInput);
            $this->newSizeInput = '';
        }
    }

    // Remove size from new product
    public function removeNewSize($index)
    {
        unset($this->newSizes[$index]);
        $this->newSizes = array_values($this->newSizes);
    }

    // Add variety to new product
    public function addNewVariety()
    {
        if ($this->newVarietyInput && !in_array($this->newVarietyInput, $this->newVarieties)) {
            $this->newVarieties[] = trim($this->newVarietyInput);
            $this->newVarietyInput = '';
        }
    }

    // Remove variety from new product
    public function removeNewVariety($index)
    {
        unset($this->newVarieties[$index]);
        $this->newVarieties = array_values($this->newVarieties);
    }

    // Add new product
    public function addProduct()
    {
        $this->validate();

        // Create slug
        $slug = Str::slug($this->newName);
        
        // Handle image - store path as JSON array
        $images = [];
        if ($this->newImage) {
            $imagePath = $this->newImage->store('products', 'public');
            $images = [$imagePath];
        }

        // Create attributes JSON with category
        $attributes = [
            'flavors' => $this->newFlavors,
            'sizes' => $this->newSizes,
            'varieties' => $this->newVarieties,
            'category' => $this->newCategory,
        ];

        // First create inventory record if needed
        $inventoryId = DB::table('inventories')->insertGetId([
    'sku' => 'INV-' . time() . '-' . rand(100, 999), // Generate a unique SKU
    'product_name' => $product->product_name ?? 'Unknown Product', // You need to get product name
    'quantity' => $this->newStockQuantity,
    'min_quantity' => 10,
    'unit_price' => $product->unit_price ?? 0.00, // You need product unit price
    'cost_price' => $product->cost_price ?? 0.00, // You need product cost price
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(), // Don't forget updated_at
]);

        // Insert product with correct schema
        $productId = DB::table('products')->insertGetId([
            'product_name' => $this->newName,
            'price' => $this->newPrice,
            'description' => $this->newDescription,
            'short_description' => $this->newShortDescription,
            'slug' => $slug,
            'images' => !empty($images) ? json_encode($images) : null,
            'attributes' => !empty($attributes) ? json_encode($attributes) : null,
            'status' => $this->newStatus,
            'stock_quantity' => $this->newStockQuantity,
            'sold_count' => 0,
            'inventory_id' => $inventoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->resetNewFields();
        $this->loadCategories();
    }

    // Edit product
    public function editProduct(int $id)
    {
        $product = DB::table('products')->where('product_id', $id)->first();
        if (!$product) return;

        $this->editId = $id;
        $this->editName = $product->product_name ?? '';
        $this->editDescription = $product->description ?? '';
        $this->editPrice = $product->price ?? 0;
        $this->editShortDescription = $product->short_description ?? '';
        $this->editStatus = $product->status ?? 'published';
        $this->editStockQuantity = $product->stock_quantity ?? 0;
        $this->editImage = null;

        // Decode attributes if they exist
        $attributes = $product->attributes ? json_decode($product->attributes, true) : [];
        $this->editFlavors = $attributes['flavors'] ?? [];
        $this->editSizes = $attributes['sizes'] ?? [];
        $this->editVarieties = $attributes['varieties'] ?? [];
        $this->editCategory = $attributes['category'] ?? '';
    }

    // Add flavor to edit product
    public function addEditFlavor()
    {
        if ($this->editFlavorInput && !in_array($this->editFlavorInput, $this->editFlavors)) {
            $this->editFlavors[] = trim($this->editFlavorInput);
            $this->editFlavorInput = '';
        }
    }

    // Remove flavor from edit product
    public function removeEditFlavor($index)
    {
        unset($this->editFlavors[$index]);
        $this->editFlavors = array_values($this->editFlavors);
    }

    // Add size to edit product
    public function addEditSize()
    {
        if ($this->editSizeInput && !in_array($this->editSizeInput, $this->editSizes)) {
            $this->editSizes[] = trim($this->editSizeInput);
            $this->editSizeInput = '';
        }
    }

    // Remove size from edit product
    public function removeEditSize($index)
    {
        unset($this->editSizes[$index]);
        $this->editSizes = array_values($this->editSizes);
    }

    // Add variety to edit product
    public function addEditVariety()
    {
        if ($this->editVarietyInput && !in_array($this->editVarietyInput, $this->editVarieties)) {
            $this->editVarieties[] = trim($this->editVarietyInput);
            $this->editVarietyInput = '';
        }
    }

    // Remove variety from edit product
    public function removeEditVariety($index)
    {
        unset($this->editVarieties[$index]);
        $this->editVarieties = array_values($this->editVarieties);
    }

    // Update product
    public function updateProduct()
    {
        if (!$this->editId) return;

        $product = DB::table('products')->where('product_id', $this->editId)->first();
        
        // Handle image update
        $images = $product->images ? json_decode($product->images, true) : [];
        if ($this->editImage) {
            $imagePath = $this->editImage->store('products', 'public');
            $images = [$imagePath];
        }

        // Create attributes JSON with category
        $attributes = [
            'flavors' => $this->editFlavors,
            'sizes' => $this->editSizes,
            'varieties' => $this->editVarieties,
            'category' => $this->editCategory,
        ];

        // Create slug if name changed
        $slug = $product->slug;
        if ($product->product_name !== $this->editName) {
            $slug = Str::slug($this->editName);
        }

        DB::table('products')->where('product_id', $this->editId)->update([
            'product_name' => $this->editName ?? '',
            'price' => $this->editPrice ?? 0,
            'description' => $this->editDescription ?? '',
            'short_description' => $this->editShortDescription ?? '',
            'slug' => $slug,
            'images' => !empty($images) ? json_encode($images) : null,
            'attributes' => !empty($attributes) ? json_encode($attributes) : null,
            'status' => $this->editStatus,
            'stock_quantity' => $this->editStockQuantity,
            'updated_at' => now(),
        ]);

        // Also update inventory if needed
        DB::table('inventories')->where('id', $product->inventory_id)->update([
            'stock_level' => $this->editStockQuantity,
            'last_updated' => now(),
        ]);

        $this->resetEditFields();
        $this->loadCategories();
    }

    // Reset edit fields method
    public function resetEditFields()
    {
        $this->editId = null;
        $this->editName = '';
        $this->editCategory = '';
        $this->editDescription = '';
        $this->editPrice = 0;
        $this->editShortDescription = '';
        $this->editStatus = 'published';
        $this->editStockQuantity = 0;
        $this->editImage = null;
        $this->editFlavors = [];
        $this->editSizes = [];
        $this->editVarieties = [];
        $this->editFlavorInput = '';
        $this->editSizeInput = '';
        $this->editVarietyInput = '';
    }

    // Delete product
    public function confirmDelete(int $id)
    {
        $product = DB::table('products')->where('product_id', $id)->first();
        
        if ($product) {
            // Delete inventory record first
            DB::table('inventories')->where('id', $product->inventory_id)->delete();
            // Then delete product
            DB::table('products')->where('product_id', $id)->delete();
        }
        
        $this->loadCategories();
    }

    private function resetNewFields()
    {
        $this->newName = '';
        $this->newCategory = '';
        $this->newDescription = '';
        $this->newShortDescription = '';
        $this->newPrice = 0;
        $this->newStockQuantity = 0;
        $this->newStatus = 'published';
        $this->newImage = null;
        $this->newFlavors = [];
        $this->newSizes = [];
        $this->newVarieties = [];
        $this->newFlavorInput = '';
        $this->newSizeInput = '';
        $this->newVarietyInput = '';
    }
};
?>

<div class="p-6 space-y-6">
    <!-- Header / Total Products -->
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-gray-800">Product Management</h1>
        <span class="bg-green-100 text-green-800 px-4 py-2 rounded-full font-semibold shadow">
            Total Products: {{ count($this->products) }}
        </span>
    </div>

    <!-- Add Product Form -->
    <div class="bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-xl font-bold text-green-800 mb-4">Add New Product</h2>
        <div class="space-y-4">
            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" placeholder="Enter product name" wire:model.defer="newName" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" placeholder="Enter category" wire:model.defer="newCategory" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                    <input type="number" placeholder="0.00" wire:model.defer="newPrice" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                    <input type="number" placeholder="0" wire:model.defer="newStockQuantity" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200" required>
                </div>
            </div>

            <!-- Short Description & Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                    <input type="text" placeholder="Brief description" wire:model.defer="newShortDescription" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model="newStatus" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label>
                <textarea placeholder="Full product description" wire:model.defer="newDescription" class="border rounded-lg px-3 py-2 w-full focus:ring focus:ring-green-200 h-32" required></textarea>
            </div>

            <!-- Image -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Image</label>
                    <input type="file" wire:model="newImage" class="border rounded-lg px-3 py-2 w-full" accept="image/*">
                </div>
                @if ($newImage)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preview</label>
                        <img src="{{ $newImage->temporaryUrl() }}" class="w-32 h-32 object-cover rounded-lg border">
                    </div>
                @endif
            </div>

            <!-- Flavors -->
            <div class="border rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Flavors</h3>
                <div class="flex gap-2 mb-2">
                    <input type="text" wire:model="newFlavorInput" placeholder="Add flavor" class="flex-1 border rounded-lg px-3 py-1">
                    <button wire:click="addNewFlavor" type="button" class="bg-blue-500 text-white px-3 py-1 rounded-lg">Add</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($newFlavors as $index => $flavor)
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full flex items-center gap-2">
                            {{ $flavor }}
                            <button type="button" wire:click="removeNewFlavor({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                        </span>
                    @endforeach
                </div>
            </div>

            <!-- Sizes -->
            <div class="border rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Sizes</h3>
                <div class="flex gap-2 mb-2">
                    <input type="text" wire:model="newSizeInput" placeholder="Add size" class="flex-1 border rounded-lg px-3 py-1">
                    <button wire:click="addNewSize" type="button" class="bg-green-500 text-white px-3 py-1 rounded-lg">Add</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($newSizes as $index => $size)
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full flex items-center gap-2">
                            {{ $size }}
                            <button type="button" wire:click="removeNewSize({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                        </span>
                    @endforeach
                </div>
            </div>

            <!-- Varieties -->
            <div class="border rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-2">Varieties</h3>
                <div class="flex gap-2 mb-2">
                    <input type="text" wire:model="newVarietyInput" placeholder="Add variety" class="flex-1 border rounded-lg px-3 py-1">
                    <button wire:click="addNewVariety" type="button" class="bg-purple-500 text-white px-3 py-1 rounded-lg">Add</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($newVarieties as $index => $variety)
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full flex items-center gap-2">
                            {{ $variety }}
                            <button type="button" wire:click="removeNewVariety({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                        </span>
                    @endforeach
                </div>
            </div>

            <button wire:click="addProduct" class="bg-green-700 text-white px-6 py-3 rounded-lg hover:bg-green-800 transition-all duration-200">
                Add Product
            </button>
        </div>

        @if ($errors->any())
            <div class="mt-4 text-red-600">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <!-- Search & Filter -->
    <div class="flex flex-col md:flex-row gap-3 mb-4 items-center">
        <input type="text" placeholder="Search products..." 
               wire:keyup="$set('search', $event.target.value)" 
               class="border rounded-lg px-3 py-2 w-full md:w-1/2 focus:ring focus:ring-green-200">

        <select wire:change="$set('categoryFilter', $event.target.value)" 
                class="border rounded-lg px-3 py-2 w-full md:w-1/4 focus:ring focus:ring-green-200">
            <option value="all">All Categories</option>
            @foreach($categories as $category)
                <option value="{{ $category }}">{{ $category }}</option>
            @endforeach
        </select>
    </div>

    <!-- Products Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-x-auto mt-4">
        <table class="w-full table-auto border-collapse">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-2 text-left">Image</th>
                    <th class="px-4 py-2 text-left">Product</th>
                    <th class="px-4 py-2 text-left">Description</th>
                    <th class="px-4 py-2 text-left">Price</th>
                    <th class="px-4 py-2 text-left">Stock</th>
                    <th class="px-4 py-2 text-left">Category</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left w-64">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->products as $product)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <div class="flex flex-col gap-2">
                                    @if($editImage)
                                        <img src="{{ $editImage->temporaryUrl() }}" class="w-24 h-24 object-cover rounded-lg border">
                                    @elseif($product['images'])
                                        @php
                                            $images = json_decode($product['images'], true);
                                            $firstImage = $images[0] ?? null;
                                        @endphp
                                        @if($firstImage)
                                            <img src="{{ asset('storage/' . $firstImage) }}" class="w-24 h-24 object-cover rounded-lg border">
                                        @endif
                                    @endif
                                    <input type="file" wire:model="editImage" class="border rounded-lg px-2 py-1" accept="image/*">
                                </div>
                            @else
                                @if($product['images'])
                                    @php
                                        $images = json_decode($product['images'], true);
                                        $firstImage = $images[0] ?? null;
                                    @endphp
                                    @if($firstImage)
                                        <img src="{{ asset('storage/' . $firstImage) }}" class="w-24 h-24 object-cover rounded-lg border">
                                    @else
                                        <span class="text-gray-400">No image</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">No image</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <input type="text" wire:model.defer="editName" class="border rounded-lg w-full px-2 py-1">
                                <div class="mt-2">
                                    <input type="text" wire:model.defer="editShortDescription" placeholder="Short Description" class="border rounded-lg w-full px-2 py-1 text-sm">
                                </div>
                            @else
                                <div class="font-medium">{{ $product['product_name'] }}</div>
                                <div class="text-sm text-gray-600 mt-1">{{ $product['short_description'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <textarea wire:model.defer="editDescription" class="border rounded-lg w-full px-2 py-1 h-24"></textarea>
                            @else
                                {{ Str::limit($product['description'], 100) }}
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <input type="number" wire:model.defer="editPrice" class="border rounded-lg w-full px-2 py-1">
                            @else
                                ₱{{ number_format($product['price'], 2) }}
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <input type="number" wire:model.defer="editStockQuantity" class="border rounded-lg w-full px-2 py-1">
                            @else
                                {{ $product['stock_quantity'] }}
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <input type="text" wire:model.defer="editCategory" class="border rounded-lg w-full px-2 py-1">
                            @else
                                @php
                                    $attributes = $product['attributes'] ? json_decode($product['attributes'], true) : [];
                                    $category = $attributes['category'] ?? 'No category';
                                @endphp
                                {{ $category }}
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($editId === $product['product_id'])
                                <select wire:model="editStatus" class="border rounded-lg w-full px-2 py-1">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="archived">Archived</option>
                                </select>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    @if($product['status'] === 'published') bg-green-100 text-green-800
                                    @elseif($product['status'] === 'draft') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($product['status']) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 space-x-2 flex">
                            @if($editId === $product['product_id'])
                                <button wire:click="updateProduct" class="bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700 flex-1 transition">Save</button>
                                <button wire:click="resetEditFields" class="bg-gray-400 text-white px-3 py-1 rounded-lg hover:bg-gray-500 flex-1 transition">Cancel</button>
                            @else
                                <button wire:click="editProduct({{ $product['product_id'] }})" class="bg-yellow-500 text-white px-3 py-1 rounded-lg hover:bg-yellow-600 flex-1 transition">Edit</button>
                                <button onclick="if(confirm('Are you sure you want to delete {{ $product['product_name'] }}?')) { @this.call('confirmDelete', {{ $product['product_id'] }}) }" class="bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700 flex-1 transition">Delete</button>
                            @endif
                        </td>
                    </tr>

                    <!-- Edit Mode - Attributes Section -->
                    @if($editId === $product['product_id'])
                        <tr>
                            <td colspan="8" class="px-4 py-4 bg-gray-50">
                                <div class="space-y-4">
                                    <!-- Flavors Edit -->
                                    <div class="border rounded-lg p-4">
                                        <h3 class="font-semibold text-gray-700 mb-2">Flavors</h3>
                                        <div class="flex gap-2 mb-2">
                                            <input type="text" wire:model="editFlavorInput" placeholder="Add flavor" class="flex-1 border rounded-lg px-3 py-1">
                                            <button wire:click="addEditFlavor" type="button" class="bg-blue-500 text-white px-3 py-1 rounded-lg">Add</button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($editFlavors as $index => $flavor)
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full flex items-center gap-2">
                                                    {{ $flavor }}
                                                    <button type="button" wire:click="removeEditFlavor({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Sizes Edit -->
                                    <div class="border rounded-lg p-4">
                                        <h3 class="font-semibold text-gray-700 mb-2">Sizes</h3>
                                        <div class="flex gap-2 mb-2">
                                            <input type="text" wire:model="editSizeInput" placeholder="Add size" class="flex-1 border rounded-lg px-3 py-1">
                                            <button wire:click="addEditSize" type="button" class="bg-green-500 text-white px-3 py-1 rounded-lg">Add</button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($editSizes as $index => $size)
                                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full flex items-center gap-2">
                                                    {{ $size }}
                                                    <button type="button" wire:click="removeEditSize({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Varieties Edit -->
                                    <div class="border rounded-lg p-4">
                                        <h3 class="font-semibold text-gray-700 mb-2">Varieties</h3>
                                        <div class="flex gap-2 mb-2">
                                            <input type="text" wire:model="editVarietyInput" placeholder="Add variety" class="flex-1 border rounded-lg px-3 py-1">
                                            <button wire:click="addEditVariety" type="button" class="bg-purple-500 text-white px-3 py-1 rounded-lg">Add</button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($editVarieties as $index => $variety)
                                                <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full flex items-center gap-2">
                                                    {{ $variety }}
                                                    <button type="button" wire:click="removeEditVariety({{ $index }})" class="text-red-500 hover:text-red-700">×</button>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>