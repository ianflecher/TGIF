<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public array $suppliers = [];
    public array $inventories = [];
    public array $warehouses = [];
    public array $transfers = [];
    public array $zones = [];

    // Supplier properties
    public int $supplier_id = 0;
    public string $supplier_name = '';
    public string $contact_person = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $supplier_status = 'active';

    // Inventory properties
    public ?int $inventory_id_edit = null;
    public string $sku = '';
    public string $product_name = '';
    public string $description = '';
    public string $category = '';
    public ?int $inventory_supplier_id = null;
    public int $quantity = 0;
    public string $expiration_date = '';
    public int $min_quantity = 10;
    public int $max_quantity = 100;
    public string $inventory_warehouse = '';
    public string $inventory_zone = '';
    public float $unit_price = 0.00;
    public float $cost_price = 0.00;
    public string $inventory_status = 'active';

    // Warehouse properties
    public ?int $warehouse_edit_id = null;
    public string $warehouse_code = '';
    public string $warehouse_name = '';
    public string $location_address = '';
    public string $warehouse_contact_person = '';
    public string $warehouse_phone = '';
    public float $capacity = 0.00;
    public string $warehouse_status = 'active';

    // Transfer properties
    public ?string $from_warehouse = null;
    public ?string $to_warehouse = null;
    public ?int $transfer_inventory_id = null;
    public int $transfer_quantity = 1;
    public string $transfer_remarks = '';
    public string $transfer_date = '';

    public bool $isEditingSupplier = false;
    public bool $isEditingInventory = false;
    public bool $isEditingWarehouse = false;
    public string $supplierMessage = '';
    public string $inventoryMessage = '';
    public string $warehouseMessage = '';
    public string $transferMessage = '';

    public function mount(): void
    {
        $this->loadSuppliers();
        $this->loadInventories();
        $this->loadWarehouses();
        $this->loadTransfers();
        $this->loadZones();
        $this->transfer_date = date('Y-m-d');
    }

    // ---------------- LOAD FUNCTIONS ----------------
    public function loadSuppliers(): void
    {
        $this->suppliers = DB::table('suppliers')
            ->orderBy('supplier_id')
            ->get()
            ->toArray();
    }

    public function loadInventories(): void
    {
        $this->inventories = DB::table('inventories as i')
            ->leftJoin('suppliers as s', 'i.supplier_id', '=', 's.supplier_id')
            ->select(
                'i.*',
                's.name as supplier_name'
            )
            ->orderBy('i.product_name')
            ->get()
            ->toArray();
    }

    public function loadWarehouses(): void
    {
        $this->warehouses = DB::table('warehouse_locations')
            ->orderBy('warehouse_name')
            ->get()
            ->toArray();
    }

    public function loadZones(): void
    {
        $this->zones = DB::table('inventories')
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->distinct()
            ->pluck('zone')
            ->toArray();
    }

    public function loadTransfers(): void
    {
        $this->transfers = DB::table('stock_transactions as st')
            ->leftJoin('inventories as i', 'st.inventory_id', '=', 'i.inventory_id')
            ->select(
                'st.*',
                'i.product_name',
                'i.sku',
                'i.warehouse as to_warehouse_name',
                DB::raw('NULL as from_warehouse_name')
            )
            ->where('st.type', 'transfer')
            ->orderBy('st.transaction_date', 'desc')
            ->get()
            ->toArray();
    }

    // ---------------- SUPPLIER CRUD ----------------
    public function saveSupplier(): void
    {
        if (trim($this->supplier_name) === '') {
            $this->supplierMessage = 'Supplier name is required.';
            return;
        }

        $data = [
            'name' => $this->supplier_name,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->supplier_status,
            'updated_at' => now(),
        ];

        if ($this->isEditingSupplier && $this->supplier_id > 0) {
            DB::table('suppliers')->where('supplier_id', $this->supplier_id)->update($data);
            $this->supplierMessage = 'Supplier updated successfully.';
        } else {
            $data['created_at'] = now();
            DB::table('suppliers')->insert($data);
            $this->supplierMessage = 'Supplier added successfully.';
        }

        $this->resetSupplierForm();
        $this->loadSuppliers();
        $this->loadInventories();
    }

    public function editSupplier(int $id): void
    {
        $supplier = DB::table('suppliers')->where('supplier_id', $id)->first();
        if ($supplier) {
            $this->supplier_id = $supplier->supplier_id;
            $this->supplier_name = $supplier->name ?? '';
            $this->contact_person = $supplier->contact_person ?? '';
            $this->email = $supplier->email ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->address = $supplier->address ?? '';
            $this->supplier_status = $supplier->status ?? 'active';
            $this->isEditingSupplier = true;
            $this->supplierMessage = '';
        }
    }

    public function deleteSupplier(int $id): void
    {
        // Check if supplier has linked inventories
        $inventoryCount = DB::table('inventories')->where('supplier_id', $id)->count();

        if ($inventoryCount > 0) {
            $this->supplierMessage = "Cannot delete supplier. It has $inventoryCount inventory item(s).";
            return;
        }

        // Delete the supplier
        DB::table('suppliers')->where('supplier_id', $id)->delete();

        $this->supplierMessage = 'Supplier deleted successfully.';
        $this->loadSuppliers();
        $this->loadInventories();
    }

    public function resetSupplierForm(): void
    {
        $this->supplier_id = 0;
        $this->supplier_name = '';
        $this->contact_person = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->supplier_status = 'active';
        $this->isEditingSupplier = false;
    }

    // ---------------- INVENTORY CRUD ----------------
    public function saveInventory(): void
    {
        // Validate required fields
        if (trim($this->product_name) === '') {
            $this->inventoryMessage = 'Product name is required.';
            return;
        }

        if ($this->unit_price <= 0) {
            $this->inventoryMessage = 'Valid unit price is required.';
            return;
        }

        // Generate SKU if not provided
        if (trim($this->sku) === '') {
            $this->sku = $this->generateSKU($this->product_name);
        }

        // Validate SKU uniqueness
        if ($this->isEditingInventory && $this->inventory_id_edit) {
            $existingSku = DB::table('inventories')
                ->where('sku', $this->sku)
                ->where('inventory_id', '!=', $this->inventory_id_edit)
                ->exists();
        } else {
            $existingSku = DB::table('inventories')->where('sku', $this->sku)->exists();
        }

        if ($existingSku) {
            $this->inventoryMessage = 'SKU already exists. Please use a different SKU.';
            return;
        }

        $data = [
            'sku' => $this->sku,
            'product_name' => $this->product_name,
            'description' => $this->description,
            'category' => $this->category,
            'supplier_id' => $this->inventory_supplier_id,
            'quantity' => $this->quantity,
            'expiration_date' => $this->expiration_date ?: null,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'warehouse' => $this->inventory_warehouse,
            'zone' => $this->inventory_zone,
            'unit_price' => $this->unit_price,
            'cost_price' => $this->cost_price,
            'status' => $this->inventory_status,
            'updated_at' => now(),
        ];

        if ($this->isEditingInventory && $this->inventory_id_edit) {
            DB::table('inventories')->where('inventory_id', $this->inventory_id_edit)->update($data);
            $this->inventoryMessage = 'Inventory item updated successfully.';
        } else {
            $data['created_at'] = now();
            DB::table('inventories')->insert($data);
            $this->inventoryMessage = 'Inventory item added successfully.';
        }

        $this->resetInventoryForm();
        $this->loadInventories();
        $this->loadZones();
    }

    private function generateSKU(string $productName): string
    {
        $prefix = 'SKU';
        $productCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productName), 0, 6));
        $random = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        $timestamp = date('ymd');
        
        return $prefix . '-' . $productCode . '-' . $random . '-' . $timestamp;
    }

    public function editInventory(int $id): void
    {
        $inventory = DB::table('inventories')->where('inventory_id', $id)->first();
        if ($inventory) {
            $this->inventory_id_edit = $inventory->inventory_id;
            $this->sku = $inventory->sku;
            $this->product_name = $inventory->product_name;
            $this->description = $inventory->description ?? '';
            $this->category = $inventory->category ?? '';
            $this->inventory_supplier_id = $inventory->supplier_id;
            $this->quantity = (int)$inventory->quantity;
            $this->expiration_date = $inventory->expiration_date ? date('Y-m-d', strtotime($inventory->expiration_date)) : '';
            $this->min_quantity = (int)$inventory->min_quantity;
            $this->max_quantity = (int)$inventory->max_quantity;
            $this->inventory_warehouse = $inventory->warehouse ?? '';
            $this->inventory_zone = $inventory->zone ?? '';
            $this->unit_price = (float)$inventory->unit_price;
            $this->cost_price = (float)$inventory->cost_price;
            $this->inventory_status = $inventory->status ?? 'active';
            $this->isEditingInventory = true;
            $this->inventoryMessage = '';
        }
    }

    public function resetInventoryForm(): void
    {
        $this->inventory_id_edit = null;
        $this->sku = '';
        $this->product_name = '';
        $this->description = '';
        $this->category = '';
        $this->inventory_supplier_id = null;
        $this->quantity = 0;
        $this->expiration_date = '';
        $this->min_quantity = 10;
        $this->max_quantity = 100;
        $this->inventory_warehouse = '';
        $this->inventory_zone = '';
        $this->unit_price = 0.00;
        $this->cost_price = 0.00;
        $this->inventory_status = 'active';
        $this->isEditingInventory = false;
        $this->inventoryMessage = '';
    }

    public function deleteInventory(int $id): void
    {
        DB::table('inventories')->where('inventory_id', $id)->delete();
        $this->inventoryMessage = 'Inventory item deleted successfully.';
        $this->loadInventories();
        $this->loadZones();
    }

    // ---------------- WAREHOUSE CRUD ----------------
    public function saveWarehouse(): void
    {
        if (trim($this->warehouse_name) === '') {
            $this->warehouseMessage = 'Warehouse name is required.';
            return;
        }

        if (trim($this->warehouse_code) === '') {
            $this->warehouseMessage = 'Warehouse code is required.';
            return;
        }

        // Check for duplicate warehouse code
        $existingCode = DB::table('warehouse_locations')
            ->where('warehouse_code', $this->warehouse_code)
            ->when($this->isEditingWarehouse, function ($query) {
                return $query->where('id', '!=', $this->warehouse_edit_id);
            })
            ->exists();

        if ($existingCode) {
            $this->warehouseMessage = 'Warehouse code already exists.';
            return;
        }

        $data = [
            'warehouse_code' => $this->warehouse_code,
            'warehouse_name' => $this->warehouse_name,
            'location_address' => $this->location_address,
            'contact_person' => $this->warehouse_contact_person,
            'phone' => $this->warehouse_phone,
            'capacity' => $this->capacity,
            'status' => $this->warehouse_status,
            'updated_at' => now(),
        ];

        if ($this->isEditingWarehouse && $this->warehouse_edit_id) {
            DB::table('warehouse_locations')->where('id', $this->warehouse_edit_id)->update($data);
            $this->warehouseMessage = 'Warehouse updated successfully.';
        } else {
            $data['created_at'] = now();
            DB::table('warehouse_locations')->insert($data);
            $this->warehouseMessage = 'Warehouse added successfully.';
        }

        $this->resetWarehouseForm();
        $this->loadWarehouses();
    }

    public function editWarehouse(int $id): void
    {
        $warehouse = DB::table('warehouse_locations')->where('id', $id)->first();
        if ($warehouse) {
            $this->warehouse_edit_id = $warehouse->id;
            $this->warehouse_code = $warehouse->warehouse_code;
            $this->warehouse_name = $warehouse->warehouse_name;
            $this->location_address = $warehouse->location_address;
            $this->warehouse_contact_person = $warehouse->contact_person ?? '';
            $this->warehouse_phone = $warehouse->phone ?? '';
            $this->capacity = (float)$warehouse->capacity;
            $this->warehouse_status = $warehouse->status ?? 'active';
            $this->isEditingWarehouse = true;
            $this->warehouseMessage = '';
        }
    }

    public function deleteWarehouse(int $id): void
    {
        DB::table('warehouse_locations')->where('id', $id)->delete();
        $this->warehouseMessage = 'Warehouse deleted successfully.';
        $this->loadWarehouses();
    }

    public function resetWarehouseForm(): void
    {
        $this->warehouse_edit_id = null;
        $this->warehouse_code = '';
        $this->warehouse_name = '';
        $this->location_address = '';
        $this->warehouse_contact_person = '';
        $this->warehouse_phone = '';
        $this->capacity = 0.00;
        $this->warehouse_status = 'active';
        $this->isEditingWarehouse = false;
        $this->warehouseMessage = '';
    }

    // ---------------- TRANSFER FUNCTIONS ----------------
    public function getAvailableQuantity($inventoryId): int
    {
        $inventory = DB::table('inventories')->where('inventory_id', $inventoryId)->first();
        return $inventory ? (int)$inventory->quantity : 0;
    }

    public function saveTransfer(): void
    {
        // Validate
        if (!$this->from_warehouse) {
            $this->transferMessage = 'Please select source warehouse.';
            return;
        }

        if (!$this->to_warehouse) {
            $this->transferMessage = 'Please select destination warehouse.';
            return;
        }

        if ($this->from_warehouse == $this->to_warehouse) {
            $this->transferMessage = 'Source and destination warehouses must be different.';
            return;
        }

        if (!$this->transfer_inventory_id) {
            $this->transferMessage = 'Please select inventory item.';
            return;
        }

        if ($this->transfer_quantity <= 0) {
            $this->transferMessage = 'Transfer quantity must be greater than 0.';
            return;
        }

        // Get inventory details
        $inventory = DB::table('inventories')->where('inventory_id', $this->transfer_inventory_id)->first();

        if (!$inventory) {
            $this->transferMessage = 'Inventory item not found.';
            return;
        }

        // Check if item is in source warehouse
        if ($inventory->warehouse != $this->from_warehouse) {
            $this->transferMessage = "This item is not in {$this->from_warehouse}. Current location: {$inventory->warehouse}";
            return;
        }

        // Check available stock
        if ($inventory->quantity < $this->transfer_quantity) {
            $this->transferMessage = "Insufficient stock. Only {$inventory->quantity} items available.";
            return;
        }

        $unitCost = $inventory->cost_price ?? 0.00;

        // Start database transaction
        DB::beginTransaction();

        try {
            // Create transfer transaction
            $transactionData = [
                'inventory_id' => $this->transfer_inventory_id,
                'type' => 'transfer',
                'quantity' => $this->transfer_quantity,
                'unit_cost' => $unitCost,
                'reference_type' => 'warehouse_transfer',
                'remarks' => $this->transfer_remarks . " [From: {$this->from_warehouse} → To: {$this->to_warehouse}]",
                'transaction_date' => $this->transfer_date ?: now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('stock_transactions')->insert($transactionData);

            // Update inventory quantity and warehouse location
            DB::table('inventories')
                ->where('inventory_id', $this->transfer_inventory_id)
                ->update([
                    'quantity' => $inventory->quantity - $this->transfer_quantity,
                    'warehouse' => $this->to_warehouse,
                    'updated_at' => now(),
                ]);

            DB::commit();

            $this->transferMessage = 'Transfer completed successfully!';
            $this->resetTransferForm();
            $this->loadInventories();
            $this->loadTransfers();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->transferMessage = 'Transfer failed. Please try again.';
        }
    }

    public function resetTransferForm(): void
    {
        $this->from_warehouse = null;
        $this->to_warehouse = null;
        $this->transfer_inventory_id = null;
        $this->transfer_quantity = 1;
        $this->transfer_remarks = '';
        $this->transfer_date = date('Y-m-d');
        $this->transferMessage = '';
    }

    public function deleteTransfer(int $id): void
    {
        $transaction = DB::table('stock_transactions')->where('transaction_id', $id)->first();
        
        if (!$transaction) {
            $this->transferMessage = 'Transfer record not found.';
            return;
        }

        DB::beginTransaction();
        
        try {
            // Get inventory details
            $inventory = DB::table('inventories')->where('inventory_id', $transaction->inventory_id)->first();
            
            if ($inventory) {
                // Parse from and to warehouse from remarks
                preg_match('/\[From: (.*?) → To: (.*?)\]/', $transaction->remarks, $matches);
                
                if (count($matches) >= 3) {
                    $fromWarehouse = $matches[1];
                    
                    // Restore inventory to original warehouse and quantity
                    DB::table('inventories')
                        ->where('inventory_id', $transaction->inventory_id)
                        ->update([
                            'quantity' => $inventory->quantity + $transaction->quantity,
                            'warehouse' => $fromWarehouse,
                            'updated_at' => now(),
                        ]);
                }
            }
            
            // Delete the transfer record
            DB::table('stock_transactions')->where('transaction_id', $id)->delete();
            
            DB::commit();
            
            $this->transferMessage = 'Transfer record deleted successfully.';
            $this->loadInventories();
            $this->loadTransfers();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->transferMessage = 'Failed to delete transfer record.';
        }
    }
};
?>
<div class="p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold mb-6 text-green-800">Supplier, Inventory & Warehouse Management</h1>

    <!-- Supplier Messages -->
    @if($supplierMessage)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($supplierMessage, 'successfully') ? 'bg-green-100 text-green-800 border-l-4 border-green-600' : 'bg-red-100 text-red-800 border-l-4 border-red-600' }}">
            <div class="flex items-center">
                @if(str_contains($supplierMessage, 'successfully'))
                    <i class="fas fa-check-circle mr-3"></i>
                @else
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                @endif
                <span>{{ $supplierMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Warehouse Messages -->
    @if($warehouseMessage)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($warehouseMessage, 'successfully') ? 'bg-green-100 text-green-800 border-l-4 border-green-600' : 'bg-red-100 text-red-800 border-l-4 border-red-600' }}">
            <div class="flex items-center">
                @if(str_contains($warehouseMessage, 'successfully'))
                    <i class="fas fa-check-circle mr-3"></i>
                @else
                    <i class="fas fa-exclamation-triangle mr-3"></i>
                @endif
                <span>{{ $warehouseMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Add/Edit Supplier Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-green-700">
            <i class="fas fa-truck mr-2"></i>{{ $isEditingSupplier ? 'Edit Supplier' : 'Add New Supplier' }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">Supplier Name *</label>
                <input wire:model="supplier_name" type="text" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-green-500" placeholder="Enter supplier name">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Contact Person</label>
                <input wire:model="contact_person" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Contact person name">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Email</label>
                <input wire:model="email" type="email" class="w-full border border-gray-300 rounded p-2" placeholder="supplier@example.com">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Phone</label>
                <input wire:model="phone" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Phone number">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Address</label>
                <input wire:model="address" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Full address">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Status</label>
                <select wire:model="supplier_status" class="w-full border border-gray-300 rounded p-2">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex space-x-3">
            <button wire:click="saveSupplier" 
                    class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg transition duration-200">
                @if($isEditingSupplier)
                    <i class="fas fa-save"></i> Update Supplier
                @else
                    <i class="fas fa-plus"></i> Add Supplier
                @endif
            </button>
            @if($isEditingSupplier)
                <button wire:click="resetSupplierForm" 
                        class="flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    <!-- Add/Edit Warehouse Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-purple-700">
            <i class="fas fa-warehouse mr-2"></i>{{ $isEditingWarehouse ? 'Edit Warehouse' : 'Add New Warehouse' }}
        </h2>
        
        @if($warehouseMessage)
            <div class="mb-4 p-3 {{ str_contains($warehouseMessage, 'successfully') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded">
                <div class="flex items-center">
                    @if(str_contains($warehouseMessage, 'successfully'))
                        <i class="fas fa-check-circle mr-2"></i>
                    @else
                        <i class="fas fa-exclamation-circle mr-2"></i>
                    @endif
                    <span>{{ $warehouseMessage }}</span>
                </div>
            </div>
        @endif
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">Warehouse Code *</label>
                <input wire:model="warehouse_code" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="e.g., WH-001">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Warehouse Name *</label>
                <input wire:model="warehouse_name" type="text" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-purple-500" placeholder="Main Warehouse">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Status</label>
                <select wire:model="warehouse_status" class="w-full border border-gray-300 rounded p-2">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Address *</label>
                <input wire:model="location_address" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Warehouse address">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Capacity (sqm)</label>
                <input wire:model="capacity" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded p-2" placeholder="0.00">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Contact Person</label>
                <input wire:model="warehouse_contact_person" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Contact person">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Phone</label>
                <input wire:model="warehouse_phone" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Phone number">
            </div>
        </div>
        <div class="mt-4 flex space-x-3">
            <button wire:click="saveWarehouse" 
                    class="flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg transition duration-200">
                @if($isEditingWarehouse)
                    <i class="fas fa-save"></i> Update Warehouse
                @else
                    <i class="fas fa-plus"></i> Add Warehouse
                @endif
            </button>
            @if($isEditingWarehouse)
                <button wire:click="resetWarehouseForm" 
                        class="flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    <!-- Add/Edit Inventory Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">
            <i class="fas fa-box mr-2"></i>{{ $isEditingInventory ? 'Edit Inventory Item' : 'Add New Inventory Item' }}
        </h2>
        
        @if($inventoryMessage)
            <div class="mb-4 p-3 {{ str_contains($inventoryMessage, 'successfully') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded">
                <div class="flex items-center">
                    @if(str_contains($inventoryMessage, 'successfully'))
                        <i class="fas fa-check-circle mr-2"></i>
                    @else
                        <i class="fas fa-exclamation-circle mr-2"></i>
                    @endif
                    <span>{{ $inventoryMessage }}</span>
                </div>
            </div>
        @endif
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">SKU *</label>
                <input wire:model="sku" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Auto-generated or enter custom">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Inventory Name *</label>
                <input wire:model="product_name" type="text" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-blue-500" placeholder="Enter inventory name">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Category</label>
                <input wire:model="category" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="e.g., Electronics">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Supplier</label>
                <select wire:model="inventory_supplier_id" class="w-full border border-gray-300 rounded p-2">
                    <option value="">— Select Supplier —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->supplier_id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Quantity</label>
                <input wire:model="quantity" type="number" min="0" class="w-full border border-gray-300 rounded p-2" placeholder="Current stock">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Expiration Date</label>
                <input wire:model="expiration_date" type="date" class="w-full border border-gray-300 rounded p-2">
            </div>
            <div>
    <label class="block text-gray-700 mb-2">Warehouse</label>
    <select wire:model="inventory_warehouse" class="w-full border border-gray-300 rounded p-2">
        <option value="">— Select Warehouse —</option>
        @foreach($warehouses as $warehouse)
            <option value="{{ $warehouse->warehouse_name }}">{{ $warehouse->warehouse_name }} ({{ $warehouse->warehouse_code }})</option>
        @endforeach
        <option value="other">Other (specify below)</option>
    </select>
    @if($inventory_warehouse === 'other')
        <div class="mt-2">
            <input wire:model="inventory_warehouse" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Enter custom warehouse name">
        </div>
    @endif
</div>
            <div>
                <label class="block text-gray-700 mb-2">Zone</label>
                <input wire:model="inventory_zone" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="e.g., Zone A">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Min Quantity</label>
                <input wire:model="min_quantity" type="number" min="0" class="w-full border border-gray-300 rounded p-2" placeholder="Reorder level">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Max Quantity</label>
                <input wire:model="max_quantity" type="number" min="0" class="w-full border border-gray-300 rounded p-2" placeholder="Maximum stock">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Unit Price (₱) *</label>
                <input wire:model="unit_price" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded p-2" placeholder="Selling price">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Cost Price (₱)</label>
                <input wire:model="cost_price" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded p-2" placeholder="Purchase cost">
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Status</label>
                <select wire:model="inventory_status" class="w-full border border-gray-300 rounded p-2">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="discontinued">Discontinued</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-gray-700 mb-2">Description</label>
                <textarea wire:model="description" rows="2" class="w-full border border-gray-300 rounded p-2" placeholder="Inventory description"></textarea>
            </div>
        </div>
        <div class="mt-4 flex space-x-3">
            <button wire:click="saveInventory" 
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg transition duration-200">
                @if($isEditingInventory)
                    <i class="fas fa-save"></i> Update Inventory
                @else
                    <i class="fas fa-plus"></i> Add Inventory
                @endif
            </button>
            @if($isEditingInventory)
                <button wire:click="resetInventoryForm" 
                        class="flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-5 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times"></i> Cancel
                </button>
            @endif
        </div>
    </div>

    <!-- Warehouse Transfer Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4 text-orange-700">
            <i class="fas fa-exchange-alt mr-2"></i>Warehouse Transfer
        </h2>
        
        @if($transferMessage)
            <div class="mb-4 p-3 {{ str_contains($transferMessage, 'successfully') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} rounded">
                <div class="flex items-center">
                    @if(str_contains($transferMessage, 'successfully'))
                        <i class="fas fa-check-circle mr-2"></i>
                    @else
                        <i class="fas fa-exclamation-circle mr-2"></i>
                    @endif
                    <span>{{ $transferMessage }}</span>
                </div>
            </div>
        @endif
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-gray-700 mb-2">From Warehouse *</label>
                <select wire:model="from_warehouse" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-orange-500">
                    <option value="">— Select Source —</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->warehouse_name }}">{{ $warehouse->warehouse_name }} ({{ $warehouse->warehouse_code }})</option>
                    @endforeach
                </select>
                @if($from_warehouse && $transfer_inventory_id)
                    @php
                        $inventory = DB::table('inventories')
                            ->where('inventory_id', $transfer_inventory_id)
                            ->where('warehouse', $from_warehouse)
                            ->first();
                    @endphp
                    @if($inventory)
                        <div class="mt-1 text-sm text-gray-600">
                            Available: {{ $inventory->quantity }} units
                        </div>
                    @else
                        <div class="mt-1 text-sm text-red-600">
                            Item not found in this warehouse
                        </div>
                    @endif
                @endif
            </div>
            <div>
                <label class="block text-gray-700 mb-2">To Warehouse *</label>
                <select wire:model="to_warehouse" class="w-full border border-gray-300 rounded p-2">
                    <option value="">— Select Destination —</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->warehouse_name }}">{{ $warehouse->warehouse_name }} ({{ $warehouse->warehouse_code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Inventory Item *</label>
                <select wire:model="transfer_inventory_id" class="w-full border border-gray-300 rounded p-2">
                    <option value="">— Select Item —</option>
                    @foreach($inventories as $inventory)
                        <option value="{{ $inventory->inventory_id }}">{{ $inventory->product_name }} ({{ $inventory->sku }})</option>
                    @endforeach
                </select>
                @if($transfer_inventory_id)
                    @php
                        $selectedItem = collect($inventories)->firstWhere('inventory_id', $transfer_inventory_id);
                    @endphp
                    @if($selectedItem)
                        <div class="mt-1 text-sm text-gray-600">
                            Current: {{ $selectedItem->warehouse ?? 'No warehouse' }} | Stock: {{ $selectedItem->quantity }}
                        </div>
                    @endif
                @endif
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Quantity *</label>
                <input wire:model="transfer_quantity" type="number" min="1" class="w-full border border-gray-300 rounded p-2" placeholder="Number of units">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Transfer Date</label>
                <input wire:model="transfer_date" type="date" class="w-full border border-gray-300 rounded p-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 mb-2">Remarks</label>
                <input wire:model="transfer_remarks" type="text" class="w-full border border-gray-300 rounded p-2" placeholder="Optional notes">
            </div>
        </div>
        <div class="mt-4">
            <button wire:click="saveTransfer" 
                    class="flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white px-5 py-2 rounded-lg transition duration-200">
                <i class="fas fa-exchange-alt"></i> Execute Transfer
            </button>
        </div>
    </div>

    <!-- Transfers History Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow mb-8">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-history mr-2 text-orange-600"></i>Transfer History ({{ count($transfers) }})
            </h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-orange-700 text-white">
                <tr>
                    <th class="px-6 py-3">Date</th>
                    <th class="px-6 py-3">Item</th>
                    <th class="px-6 py-3">Quantity</th>
                    <th class="px-6 py-3">Unit Cost</th>
                    <th class="px-6 py-3">Remarks</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($transfers as $transfer)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            {{ date('Y-m-d', strtotime($transfer->transaction_date)) }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium">{{ $transfer->product_name }}</div>
                            <div class="text-xs text-gray-500">{{ $transfer->sku }}</div>
                        </td>
                        <td class="px-6 py-4 font-semibold text-orange-700">
                            {{ $transfer->quantity }}
                        </td>
                        <td class="px-6 py-4">
                            ₱{{ number_format($transfer->unit_cost, 2) }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $transfer->remarks ? Str::limit($transfer->remarks, 50) : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center">
                                <button onclick="if(confirm('Delete this transfer record?')) { @this.deleteTransfer({{ $transfer->transaction_id }}) }" 
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
                                <i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i>
                                <p class="text-lg">No transfers found.</p>
                                <p class="text-sm mt-1">Execute your first transfer above.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Suppliers Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow mb-8">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-truck mr-2 text-green-600"></i>Suppliers ({{ count($suppliers) }})
            </h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-6 py-3">ID</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Contact Person</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Phone</th>
                    <th class="px-6 py-3">Status</th>
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
                        <td class="px-6 py-4">{{ $supplier->phone ?? '—' }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-yellow-100 text-yellow-800',
                                    'suspended' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$supplier->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($supplier->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="editSupplier({{ $supplier->supplier_id }})" 
                                        class="flex items-center gap-1 bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-edit text-sm"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="if(confirm('Delete supplier: {{ addslashes($supplier->name) }}?')) { @this.deleteSupplier({{ $supplier->supplier_id }}) }" 
                                        class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
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

    <!-- Warehouses Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow mb-8">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-warehouse mr-2 text-purple-600"></i>Warehouses ({{ count($warehouses) }})
            </h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-purple-700 text-white">
                <tr>
                    <th class="px-6 py-3">Code</th>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Address</th>
                    <th class="px-6 py-3">Contact</th>
                    <th class="px-6 py-3">Capacity</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($warehouses as $warehouse)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-mono font-semibold text-purple-700">{{ $warehouse->warehouse_code }}</td>
                        <td class="px-6 py-4 font-medium">{{ $warehouse->warehouse_name }}</td>
                        <td class="px-6 py-4">{{ Str::limit($warehouse->location_address, 30) }}</td>
                        <td class="px-6 py-4">
                            <div>{{ $warehouse->contact_person ?? '—' }}</div>
                            @if($warehouse->phone)
                                <div class="text-sm text-gray-600">{{ $warehouse->phone }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-semibold text-purple-700">{{ number_format($warehouse->capacity, 2) }} sqm</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-yellow-100 text-yellow-800',
                                    'maintenance' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$warehouse->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($warehouse->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="editWarehouse({{ $warehouse->id }})" 
                                        class="flex items-center gap-1 bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-edit text-sm"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="if(confirm('Delete warehouse: {{ addslashes($warehouse->warehouse_name) }}?')) { @this.deleteWarehouse({{ $warehouse->id }}) }" 
                                        class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-warehouse text-4xl mb-3 text-gray-300"></i>
                                <p class="text-lg">No warehouses found.</p>
                                <p class="text-sm mt-1">Add your first warehouse above.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Inventories Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-boxes mr-2 text-blue-600"></i>Inventory Items ({{ count($inventories) }})
            </h3>
        </div>
        <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-blue-700 text-white">
                <tr>
                    <th class="px-6 py-3">SKU</th>
                    <th class="px-6 py-3">Product Name</th>
                    <th class="px-6 py-3">Category</th>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3">Warehouse</th>
                    <th class="px-6 py-3">Quantity</th>
                    <th class="px-6 py-3">Unit Price</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($inventories as $inventory)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-mono text-sm">{{ $inventory->sku }}</td>
                        <td class="px-6 py-4 font-medium">
                            <div>{{ $inventory->product_name }}</div>
                            @if($inventory->description)
                                <div class="text-xs text-gray-500 truncate max-w-xs">{{ Str::limit($inventory->description, 50) }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                {{ $inventory->category ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($inventory->supplier_name)
                                <span class="text-green-700">{{ $inventory->supplier_name }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div>
                                <span class="font-medium">{{ $inventory->warehouse ?? '—' }}</span>
                                @if($inventory->zone)
                                    <div class="text-xs text-gray-500">{{ $inventory->zone }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-semibold {{ $inventory->quantity <= $inventory->min_quantity ? 'text-red-600' : 'text-gray-700' }}">
                                    {{ $inventory->quantity }}
                                </span>
                                <div class="text-xs text-gray-500">
                                    Min: {{ $inventory->min_quantity }} / Max: {{ $inventory->max_quantity }}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-semibold text-green-700">
                            ₱{{ number_format($inventory->unit_price, 2) }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-yellow-100 text-yellow-800',
                                    'discontinued' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$inventory->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($inventory->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex justify-center space-x-2">
                                <button wire:click="editInventory({{ $inventory->inventory_id }})" 
                                        class="flex items-center gap-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-edit text-sm"></i>
                                    <span>Edit</span>
                                </button>
                                <button onclick="if(confirm('Delete inventory item: {{ addslashes($inventory->product_name) }}?')) { @this.deleteInventory({{ $inventory->inventory_id }}) }"
                                        class="flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-8 text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-boxes text-4xl mb-3 text-gray-300"></i>
                                <p class="text-lg">No inventory items found.</p>
                                <p class="text-sm mt-1">Add your first inventory item above.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>