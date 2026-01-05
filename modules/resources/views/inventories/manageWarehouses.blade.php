@extends('layouts.app') 
@section('title','Manage Warehouses')

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
<h2 class="text-2xl text-center mt-6 mb-6" style="font-weight: 800;">
    Manage Warehouses
</h2>

<div class="flex justify-center mb-4">
    <form action="{{ route('warehouses.manage') }}" method="GET" class="flex justify-center items-center mb-4 space-x-2">
    <!-- Search by product name -->
    <input type="text" name="search" placeholder="Search products..." value="{{ request('search') }}"
           class="btn-potato-input" style="margin-right: 10px;">

    <!-- Warehouse filter -->
    <select name="warehouse" class="btn-potato-input" style="margin-right: 10px;">
        <option value="">All Warehouses</option>
        @foreach($warehouses as $warehouse)
            <option value="{{ $warehouse }}" {{ request('warehouse') == $warehouse ? 'selected' : '' }}>
                {{ $warehouse }}
            </option>
        @endforeach
    </select>

    <button type="submit" class="btn-potato" style="background-color:#f6b93b; color:white;">
        Apply
    </button>
</form>

</div>


@if(session('success'))
    <div class="bg-green-100 text-green-700 p-3 rounded mb-4">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<table class="inventory-table">
    <thead>
        <tr>
            <th>Product Name</th>
            <th>SKU(s)</th>
            <th>Total Quantity</th>
            <th>Warehouse</th>
            <th>Zone</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $productName => $rows)
            @php
                $totalQty = $rows->sum('quantity');
                $skus = $rows->pluck('sku')->join(', ');
                $warehouses = $rows->pluck('warehouse')->unique()->join(', ');
                $zones = $rows->pluck('zone')->unique()->join(', ');
            @endphp
            <tr>
                <td>{{ $productName }}</td>
                <td>{{ $skus }}</td>
                <td>{{ $totalQty }}</td>
                <td>{{ $warehouses ?: 'N/A' }}</td>
                <td>{{ $zones ?: 'N/A' }}</td>
               <td class="action-cell">
    <a href="{{ route('inventories.transferForm', $rows->first()->id) }}" 
       class="btn-potato inline-block" 
       style="background-color:#f6b93b; color:white;">
       Transfer
    </a>

    
</td>

            </tr>
        @endforeach
    </tbody>
</table>

@endsection
