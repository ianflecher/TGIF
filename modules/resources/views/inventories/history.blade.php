@extends('layouts.app') 

@section('title', 'Inventory History')

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
    




<h1 class="mb-6 text-3xl font-bold text-center">📜 Inventory History</h1>

<!-- Search Form (Keep this above the export form) -->
<div style="text-align: center; margin-bottom: 20px;">
    <form method="GET" action="{{ route('inventories.history') }}" style="display: inline-block;">
        <input type="text" name="item_name" placeholder="Search by Item Name" value="{{ request('item_name') }}" style="padding: 5px;">
        <input type="date" name="date_from" value="{{ request('date_from') }}" style="padding: 5px;">
        <input type="date" name="date_to" value="{{ request('date_to') }}" style="padding: 5px;">
        <button type="submit" style="padding: 5px 10px;">Search</button>
        <a href="{{ route('inventories.history') }}" style="padding: 5px 10px;">Clear</a>
    </form>
</div>

<!-- Export Form (Your updated code) -->
<div style="text-align: center; margin-bottom: 20px;">
    <form id="export-form" action="{{ route('inventory.history.export.pdf') }}" method="POST" target="_blank">
        @csrf
        <input type="hidden" name="selected_ids" id="selected-ids" value="">
        <!-- Hidden inputs for search params -->
        <input type="hidden" name="item_name" value="{{ request('item_name') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <button type="submit" class="btn-potato px-4 py-2 mr-4"style="background-color:#f6b93b; color:white;">
            Export to PDF
        </button>
    </form>
</div>







<div class="overflow-x-auto">
    <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
        <thead>
            <tr>
                <th class="px-4 py-2 border">Item</th>
                <th class="px-4 py-2 border">Action</th>
                <th class="px-4 py-2 border">Changed By</th>
                <th class="px-4 py-2 border">Change</th>
                <th class="px-4 py-2 border">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($histories as $history)
                @php
                    $changes = json_decode($history->changes, true);
                    $userName = $history->user->name ?? '—';
                    $date = $history->created_at->format('Y-m-d H:i');
                    $inventory = $history->inventory;

                    $itemName    = $inventory->name ?? ($changes['name']['new'] ?? '—');
                    $description = $inventory->description ?? ($changes['description']['new'] ?? 'N/A');
                    $quantity    = $inventory->quantity ?? ($changes['quantity']['new'] ?? '—');
                    $category    = $inventory->category ?? ($changes['category']['new'] ?? '—');
                    $expiration  = $inventory->expiration_date ?? ($changes['expiration_date']['new'] ?? '—');
                    $sku         = $inventory->sku ?? ($changes['sku']['new'] ?? '—');
                     $supplier      = $inventory->supplier_name ?? ($changes['supplier_name']['new'] ?? 'N/A');

                    // ✅ Get min and max quantities
                    $minQty = $inventory->min_quantity ?? ($changes['min_quantity']['new'] ?? '—');
                    $maxQty = $inventory->max_quantity ?? ($changes['max_quantity']['new'] ?? '—');
                @endphp

                @if($history->action === 'deleted')
                    <tr class="hover:bg-yellow-100">
                        <td class="px-4 py-2 border">{{ $itemName }}</td>
                        <td class="px-4 py-2 border">{{ ucfirst($history->action) }}</td>
                        <td class="px-4 py-2 border">{{ $userName }}</td>
                        <td class="px-4 py-2 border"></td>
                        <td class="px-4 py-2 border">{{ $date }}</td>
                    </tr>
                @elseif($history->action === 'added')
                    @php
                        $warehouse = $inventory->warehouse ?? ($changes['warehouse']['new'] ?? '—');
                        $zone      = $inventory->zone ?? ($changes['zone']['new'] ?? '—');
                    @endphp
                    <tr class="hover:bg-yellow-100">
                        <td class="px-4 py-2 border">{{ $itemName }}</td>
                        <td class="px-4 py-2 border">{{ ucfirst($history->action) }}</td>
                        <td class="px-4 py-2 border">{{ $userName }}</td>
                        <td class="px-4 py-2 border">
                            Name: {{ $itemName }}<br>
                            Description: {{ $description }}<br>
                            Quantity: {{ $quantity }}<br>
                            Category: {{ $category }}<br>
                            Expiration_date: {{ $expiration != '—' ? \Carbon\Carbon::parse($expiration)->format('Y-m-d') : 'N/A' }}<br>
                            SKU: {{ $sku }}<br>
                            Warehouse: {{ $warehouse }}<br>
                            Zone: {{ $zone }}<br>
                            <!-- ✅ Added Min & Max display -->
                            Minimum: {{ $minQty }}<br>
                            Maximum: {{ $maxQty }}<br>
                            Supplier: {{ $supplier }}
                        </td>
                        <td class="px-4 py-2 border">{{ $date }}</td>
                    </tr>
                @elseif($history->action === 'updated')
                    @foreach($changes as $field => $values)
                        <tr class="hover:bg-yellow-100">
                            <td class="px-4 py-2 border">{{ $itemName }}</td>
                            <td class="px-4 py-2 border">{{ ucfirst($history->action) }}</td>
                            <td class="px-4 py-2 border">{{ $userName }}</td>
                            <td class="px-4 py-2 border">
                                {{ ucfirst(str_replace('_', ' ', $field)) }}: 
                                {{ $values['old'] ?? '' }} → {{ $values['new'] ?? '' }}
                            </td>
                            <td class="px-4 py-2 border">{{ $date }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</div>
@endsection
