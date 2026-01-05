@extends('layouts.app')

@section('title', 'Warehouse Logs')

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

<h2 class="text-5xl text-center mt-6 mb-6" style="font-weight: 1000;">
    Warehouse Logs
</h2>

<!-- Search Form -->
<div style="text-align: center; margin-bottom: 20px;">
    <form method="GET" action="{{ route('inventories.warehouselogs') }}" style="display: inline-block;">
        <input type="text" name="item_name" placeholder="Search by Item Name" value="{{ request('item_name') }}" style="padding: 5px;">
        <input type="date" name="date_from" value="{{ request('date_from') }}" style="padding: 5px;">
        <input type="date" name="date_to" value="{{ request('date_to') }}" style="padding: 5px;">
        <button type="submit" style="padding: 5px 10px;">Search</button>
        <a href="{{ route('inventories.warehouselogs') }}" style="padding: 5px 10px;">Clear</a>
    </form>
</div>

<!-- Export Form -->
<div style="text-align: center; margin-bottom: 20px;">
    <form action="{{ route('warehouse.logs.export.pdf') }}" method="POST" target="_blank">
        @csrf
        <!-- Hidden inputs for search params -->
        <input type="hidden" name="item_name" value="{{ request('item_name') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <button type="submit" class="btn-potato px-4 py-2 mr-4"style="background-color:#f6b93b; color:white;">
            Export to PDF
        </button>
    </form>
</div>





<div class="flex justify-center mb-4">
    <a href="{{ route('warehouses.manage') }}" class="btn-potato" style="background-color:#2c5530; color:white;">
        ← Back to Manage Warehouses
    </a>
</div>

<div class="overflow-x-auto">
    <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
        <thead>
            <tr>
                <th class="px-4 py-2 border">Item Name</th>
                <th class="px-4 py-2 border">Action</th>
                <th class="px-4 py-2 border">Changes</th>
                <th class="px-4 py-2 border">Performed By</th>
                <th class="px-4 py-2 border">Date & Time</th>
            </tr>
        </thead>
        <tbody>
@forelse($logs as $log)
<tr>
    <td>{{ $log->inventory->name ?? 'Deleted Item' }}</td>
    <td>{{ ucfirst($log->action) }}</td>
    <td>
        @php
            $changes = json_decode($log->changes, true);
        @endphp

        @if($log->action === 'transferred' && $changes)
            {{-- Show old → new warehouse/zone --}}
            @php
                $oldWarehouse = $changes['warehouse']['old'] ?? 'N/A';
                $newWarehouse = $changes['warehouse']['new'] ?? 'N/A';
                $oldZone      = $changes['zone']['old'] ?? 'N/A';
                $newZone      = $changes['zone']['new'] ?? 'N/A';
            @endphp
            {{ $oldWarehouse }}/{{ $oldZone }} → {{ $newWarehouse }}/{{ $newZone }}
        @elseif($log->action === 'assigned' && $changes)
            {{-- Show new assigned location --}}
            @php
                $newWarehouse = $changes['warehouse']['new'] ?? 'N/A';
                $newZone      = $changes['zone']['new'] ?? 'N/A';
            @endphp
            {{ $newWarehouse }}/{{ $newZone }}
        @else
            N/A
        @endif
    </td>
    <td>{{ $log->user->name ?? 'Unknown' }}</td>
    <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center">No warehouse logs found.</td>
</tr>
@endforelse
</tbody>

    </table>
</div>
@endsection
