@extends('layouts.app')


@section('title', isset($inventory) ? 'Edit Item' : 'Add Product or Stock')

@section('content')
@php
use Carbon\Carbon;
use App\Models\Inventory;

// ✅ Out of stock items
$outOfStock = Inventory::where('quantity', 0)->get();

// ✅ Low stock items (quantity > 0 but <= min_quantity or fallback 5)
$lowStock = Inventory::where('quantity', '>', 0)
                     ->where(function($q){
                         $q->whereColumn('quantity', '<=', 'min_quantity')
                           ->orWhere(function($q2){
                               $q2->where('min_quantity', 0)
                                  ->where('quantity', '<=', 5);
                           });
                     })->get();

// ✅ Expired items
$isExpired = Inventory::whereNotNull('expiration_date')
                      ->whereDate('expiration_date', '<', Carbon::now())
                      ->get();

// ✅ Near expiration items (within 1 month)
$nearExpired = Inventory::whereNotNull('expiration_date')
                        ->whereDate('expiration_date', '>=', Carbon::now())
                        ->whereDate('expiration_date', '<=', Carbon::now()->addMonth())
                        ->get();

// ✅ Total new alert count
$newAlertCount = $outOfStock->count() + $lowStock->count() + $isExpired->count() + $nearExpired->count();
@endphp

<div class="header">
    <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
    <div class="user-info">
        <span style="position: relative; display: inline-block;">

            {{-- 🔔 Notification Icon --}}
            <button onclick="document.getElementById('alert-dropdown').classList.toggle('hidden');"
        class="notif-btn"
        title="View Alerts">
    &#128276;{{-- Bell icon 🔔 --}}
                
                {{-- 🔴 Small Count Circle --}}
                @if($newAlertCount > 0)
                     <span class="notif-count">
                        {{ $newAlertCount }}
                    </span>
                @endif
            </button>

{{-- 🔽 Alert Dropdown Positioned Below Icon --}}
<div id="alert-dropdown" class="notif-dropdown hidden">

@php
    $allAlerts = collect();

    foreach($outOfStock as $item){
        $allAlerts->push([
            'message' => "{$item->name} is OUT OF STOCK",
            'time' => $item->updated_at ?? now()
        ]);
    }
    foreach($lowStock as $item){
        $allAlerts->push([
            'message' => "{$item->name} is LOW STOCK",
            'time' => $item->updated_at ?? now()
        ]);
    }
    foreach($isExpired as $item){
        $allAlerts->push([
            'message' => "{$item->name} is EXPIRED",
            'time' => $item->updated_at ?? now()
        ]);
    }
    foreach($nearExpired as $item){
        $allAlerts->push([
            'message' => "{$item->name} is NEAR EXPIRATION",
            'time' => $item->updated_at ?? now()
        ]);
    }

    // Most recent on top
    $allAlerts = $allAlerts->sortByDesc('time');
@endphp

@foreach($allAlerts as $alert)
    <div style="margin-bottom:5px;">
        {{ $alert['message'] }}
    </div>
@endforeach

</div>


        </span>
          
                <!-- Authentication -->
                <div class="user-dropdown">
    <!-- Trigger Button -->
    <button class="dropdown-trigger">
        {{ Auth::user()->name }}
        <span class="arrow">&#9662;</span>
    </button>

    <!-- Dropdown Menu -->
    <div class="dropdown-menu">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item">Logout</button>
        </form>
    </div>
</div>

                       
      </div>
        </div>

<div class="max-w-3xl mx-auto mt-8">
    <h1 class="text-3xl font-bold text-center mb-6">
        {{ isset($inventory) ? '✏️ Edit Item' : '➕ Add Product or Stock' }}
    </h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

   <form id="inventory-form" 
      action="{{ isset($inventory) ? route('inventories.update', $inventory) : route('inventories.store') }}" 
      method="POST" 
      class="bg-white p-6 rounded-lg shadow-lg">
    @csrf
    @if(isset($inventory))
        @method('PUT')
    @endif

    {{-- Product Name --}}
    <div class="mb-4">
        <label for="name" class="block font-medium mb-1">Product Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" 
               value="{{ old('name', $inventory->name ?? '') }}" 
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" 
               placeholder="e.g. Coke 1.5L" required>
        <p id="stock-in-note" class="text-sm text-gray-500 mt-1">
            If the name already exists, this will be treated as a stock-in.
        </p>
    </div>

    {{-- Minimum Quantity --}}
    <div class="mb-4">
        <label for="minimum" class="block font-medium mb-1">Minimum Quantity <span class="text-red-500">*</span></label>
        <input type="number" name="minimum" id="minimum"
               value="{{ old('minimum', $inventory->minimum ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300"
               min="0">
    </div>

    {{-- Maximum Quantity --}}
    <div class="mb-4">
        <label for="maximum" class="block font-medium mb-1">Maximum Quantity <span class="text-red-500">*</span></label>
        <input type="number" name="maximum" id="maximum"
               value="{{ old('maximum', $inventory->maximum ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300"
               min="0">
    </div>

    {{-- Quantity --}}
    <div class="mb-4">
        <label for="quantity" class="block font-medium mb-1">Quantity <span class="text-red-500">*</span></label>
        <input type="number" name="quantity" id="quantity" 
               value="{{ old('quantity', $inventory->quantity ?? '') }}" 
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" 
               min="1" required>
    </div>

    {{-- Category --}}
    <div class="mb-4">
        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
        <select name="category" id="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300">
            <option value="">-- Select Category (Optional if existing item) --</option>
            @foreach($categories as $category)
                <option value="{{ $category }}" {{ (old('category', $inventory->category ?? '') == $category) ? 'selected' : '' }}>
                    {{ $category }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Description --}}
    <div class="mb-4">
        <label for="description" class="block font-medium mb-1">Description</label>
        <textarea name="description" id="description" rows="3" 
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" 
                  placeholder="Optional if existing item">{{ old('description', $inventory->description ?? '') }}</textarea>
    </div>

    {{-- Warehouse --}}
    <div class="mb-4">
        <label for="warehouse" class="block font-medium mb-1">Select Warehouse <span class="text-red-500">*</span></label>
        <select name="warehouse" id="warehouse" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" required>
            <option value="" disabled {{ old('warehouse') ? '' : 'selected' }}>Select Warehouse</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse }}" {{ old('warehouse', $inventory->warehouse ?? '') == $warehouse ? 'selected' : '' }}>
                    {{ $warehouse }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="warehouse_hidden" id="warehouse_hidden" value="{{ old('warehouse', $inventory->warehouse ?? '') }}">
    </div>

    {{-- Zone --}}
    <div class="mb-4">
        <label for="zone" class="block font-medium mb-1">Select Zone <span class="text-red-500">*</span></label>
        <select name="zone" id="zone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" required>
            <option value="" disabled {{ old('zone') ? '' : 'selected' }}>Select Zone</option>
            @foreach($zones as $zone)
                <option value="{{ $zone }}" {{ old('zone', $inventory->zone ?? '') == $zone ? 'selected' : '' }}>
                    {{ $zone }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="zone_hidden" id="zone_hidden" value="{{ old('zone', $inventory->zone ?? '') }}">
    </div>

    <div class="mb-4">
    <label for="supplier_name" class="block font-medium mb-1">Supplier (optional)</label>
    <input type="text" name="supplier_name" id="supplier_name"
           value="{{ old('supplier_name', $inventory->supplier_name ?? '') }}"
           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300"
           placeholder="Enter supplier name">
</div>


    {{-- Expiration Date --}}
    <div class="mb-4">
        <label for="expiration_date" class="block font-medium mb-1">Expiration Date</label>
        <input id="expiration_date"
               type="date"
               name="expiration_date"
               value="{{ old('expiration_date', $inventory->expiration_date ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300">
    </div>

    {{-- Submit --}}
    <div class="mt-6 flex justify-center gap-4">
        <button type="submit" class="btn-potato px-6 py-2 rounded-lg font-bold" style="background-color:#f6b93b; color:white;">
            {{ isset($inventory) ? 'Update Item' : 'Save' }}
        </button>
        <a href="{{ route('inventories.index') }}" class="px-6 py-2 rounded-lg font-bold border border-gray-300 hover:bg-gray-100 transition">
            Cancel
        </a>
    </div>
</form>
</div>

<script>
    const existingItems = @json($items);
    const nameInput = document.getElementById('name');
    const categorySelect = document.getElementById('category');
    const descriptionTextarea = document.getElementById('description');
    const warehouseInput = document.getElementById('warehouse');
    const zoneInput = document.getElementById('zone');
    const warehouseHidden = document.getElementById('warehouse_hidden');
    const zoneHidden = document.getElementById('zone_hidden');
    const stockInNote = document.getElementById('stock-in-note');
    const minimumInput = document.getElementById('minimum');
    const maximumInput = document.getElementById('maximum');
    const quantityInput = document.getElementById('quantity');
    const supplierInput = document.getElementById('supplier_name'); // 🆕 added

    const minFieldContainer = minimumInput.parentElement;
    const maxFieldContainer = maximumInput.parentElement;

    let infoContainer = document.getElementById('item-info');
    if (!infoContainer) {
        infoContainer = document.createElement('div');
        infoContainer.id = 'item-info';
        stockInNote.insertAdjacentElement('afterend', infoContainer);
    }

    nameInput.addEventListener('input', function() {
        const value = this.value.trim().toLowerCase();
        if (value === '') {
            resetFields();
            return;
        }

        const existingItem = existingItems.find(item => item.name.toLowerCase() === value);

        if (existingItem) {
            console.log("🧾 Existing Item (Local):", existingItem);
            fillItemFields(existingItem);
        }

        fetch(`/inventory-info/${encodeURIComponent(value)}`)
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                if (data && data.exists) {
                    const updatedItem = {
                        ...existingItem,
                        name: value,
                        category: data.category,
                        description: data.description,
                        warehouse: data.warehouse,
                         supplier_name: data.supplier_name ?? existingItem?.supplier_name ?? '', // 🆕 fetch latest supplier
                        minimum: data.minimum,
                        maximum: data.maximum,
                        total_quantity: data.quantity
                    };
                    console.log("🌐 Updated Item (Server):", updatedItem);
                    fillItemFields(updatedItem);
                } else if (!existingItem) {
                    resetFields();
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
            });
    });

    function fillItemFields(item) {
        categorySelect.value = item.category ?? '';
        descriptionTextarea.value = item.description ?? '';
        warehouseInput.value = item.warehouse ?? '';
        zoneInput.value = item.zone ?? '';
        supplierInput.value = item.supplier_name ?? ''; // 🆕 fill supplier (still editable)

        warehouseHidden.value = item.warehouse ?? '';
        zoneHidden.value = item.zone ?? '';

        minimumInput.value = item.minimum ?? '';
        maximumInput.value = item.maximum ?? '';
        minimumInput.disabled = true;
        maximumInput.disabled = true;
        warehouseInput.disabled = true;
        zoneInput.disabled = true;
        // supplierInput.disabled = true; <-- removed

        if (minFieldContainer) minFieldContainer.style.display = 'none';
        if (maxFieldContainer) maxFieldContainer.style.display = 'none';

        const currentQty = parseInt(item.total_quantity ?? 0);
        const minVal = parseInt(item.minimum ?? 0);
        const maxVal = parseInt(item.maximum ?? 0);
        const remaining = maxVal - currentQty;

        if (isNaN(maxVal) || isNaN(currentQty)) {
            infoContainer.innerHTML = `
                <div class="alert alert-warning p-2 rounded">
                    ⚠️ Missing maximum or current quantity data. Please check the item setup.
                </div>
            `;
            quantityInput.disabled = false;
        } else if (remaining <= 0) {
            infoContainer.innerHTML = `
                <div class="alert alert-danger p-2 rounded">
                    ⚠️ The maximum capacity of this item is already reached.
                </div>
            `;
            quantityInput.disabled = true;
        } else {
            infoContainer.innerHTML = `
                <div class="alert alert-info p-2 rounded">
                     Minimum: <strong>${minVal}</strong><br>
                     Maximum: <strong>${maxVal}</strong><br>
                     Current Quantity: <strong>${currentQty}</strong><br>
                     Remaining Capacity: <strong>${remaining}</strong>
                </div>
            `;
            quantityInput.disabled = false;
            quantityInput.max = remaining;
        }

        stockInNote.textContent = "Name exists! Minimum, Maximum, Category, Description, Warehouse & Zone are auto-filled and locked. Supplier is auto-filled but editable.";
    }

    function resetFields() {
        categorySelect.value = '';
        descriptionTextarea.value = '';
        warehouseInput.disabled = false;
        zoneInput.disabled = false;
        supplierInput.disabled = false; // editable
        warehouseInput.value = '';
        zoneInput.value = '';
        supplierInput.value = ''; // reset supplier

        warehouseHidden.value = '';
        zoneHidden.value = '';
        minimumInput.disabled = false;
        maximumInput.disabled = false;
        minimumInput.value = '';
        maximumInput.value = '';

        if (minFieldContainer) minFieldContainer.style.display = '';
        if (maxFieldContainer) maxFieldContainer.style.display = '';

        quantityInput.removeAttribute('max');
        quantityInput.disabled = false;

        infoContainer.innerHTML = '';

        stockInNote.textContent = "If the name already exists, this will be treated as a stock-in.";
    }

    quantityInput.addEventListener('input', function () {
        if (this.max && parseInt(this.value) > parseInt(this.max)) {
            this.value = this.max;
        }
    });
</script>







@endsection
