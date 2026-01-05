@extends('layouts.app')

@section('title', 'Edit Item')

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
        ✏️ Edit Item
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

    <form action="{{ route('inventories.update', $inventory) }}" method="POST" class="bg-white p-6 rounded-lg shadow-lg">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- SKU (readonly) -->
            <div>
                <label for="sku" class="block font-medium mb-1">SKU</label>
                <input type="text" id="sku" value="{{ $inventory->sku }}" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 cursor-not-allowed" disabled>
            </div>

            <!-- Name (editable) -->
            <div>
                <label for="name" class="block font-medium mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $inventory->name) }}" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300" required>
            </div>

            <!-- Quantity (readonly) -->
            <div>
                <label for="quantity" class="block font-medium mb-1">Quantity</label>
                <input type="number" id="quantity" value="{{ $inventory->quantity }}" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 cursor-not-allowed" disabled>
            </div>

            <!-- Category (readonly) -->
            <div>
                <label for="category" class="block font-medium mb-1">Category</label>
                <input type="text" id="category" value="{{ $inventory->category }}" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 cursor-not-allowed" disabled>
            </div>

            <!-- Description (editable) -->
            <div class="sm:col-span-2">
                <label for="description" class="block font-medium mb-1">Description</label>
                <textarea name="description" id="description" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-yellow-300">{{ old('description', $inventory->description) }}</textarea>
            </div>

            <!-- Expiration Date (readonly) -->
          
                 <div class="sm:col-span-2">
                <label for="expiration_date" class="block font-medium mb-1">Expiration Date</label>
                <input type="date" id="expiration_date" value="{{ $inventory->expiration_date }}" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 cursor-not-allowed" disabled>
            </div>
        </div>

        <!-- Buttons -->
        <div class="mt-6 flex justify-center gap-4">
            <button type="submit" class="btn-potato px-6 py-2 rounded-lg font-bold" style="background-color:#f6b93b; color:white;">
                Update Item
            </button>
            <a href="{{ route('inventories.index') }}" 
               class="px-6 py-2 rounded-lg font-bold border border-gray-300 hover:bg-gray-100 transition">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
