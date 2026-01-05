@extends('layouts.app')

@section('title', 'Stock Transaction History')

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
    Stock Transaction History
</h2>

<!-- Search Form -->
<div style="text-align: center; margin-bottom: 20px;">
    <form method="GET" action="{{ route('transactions.transactionHistory') }}" style="display: inline-block;"> <!-- Adjust route name if different -->
        <input type="text" name="item_name" placeholder="Search by Item Name" value="{{ request('item_name') }}" style="padding: 5px;">
        <input type="date" name="date_from" value="{{ request('date_from') }}" style="padding: 5px;">
        <input type="date" name="date_to" value="{{ request('date_to') }}" style="padding: 5px;">
        <button type="submit" style="padding: 5px 10px;">Search</button>
        <a href="{{ route('transactions.transactionHistory') }}" style="padding: 5px 10px;">Clear</a> <!-- Adjust route name -->
    </form>
</div>

<!-- Export Form -->
<div style="text-align: center; margin-bottom: 20px;">
    <form action="{{ route('transaction.history.export.pdf') }}" method="POST" target="_blank">
        @csrf
        <!-- Hidden inputs for search params -->
        <input type="hidden" name="item_name" value="{{ request('item_name') }}">
        <input type="hidden" name="date_from" value="{{ request('date_from') }}">
        <input type="hidden" name="date_to" value="{{ request('date_to') }}">
        <button type="submit" class="btn btn-primary" style="background-color:#f6b93b; color:white; padding: 10px 20px; border: none; border-radius: 5px;">
            Export to PDF
        </button>
        
    </form>
</div>

<div class="overflow-x-auto">
    <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
        <thead class="bg-yellow-300">
            <tr>
                <th class="px-4 py-2 border">Date</th>
                <th class="px-4 py-2 border">Item</th>
                <th class="px-4 py-2 border">Category</th> <!-- ✅ Added Category Column -->
                <th class="px-4 py-2 border">Type</th>
                <th class="px-4 py-2 border">Quantity</th>
                <th class="px-4 py-2 border">User</th>
                <th class="px-4 py-2 border">Supplier</th> <!-- ✅ Added Supplier Column -->
            </tr>
        </thead>
        <tbody>
        @foreach($transactions as $t)
            <tr class="hover:bg-yellow-100">
                <td class="px-4 py-2 border">{{ $t->created_at->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-2 border">{{ $t->inventory->name ?? '—' }}</td>
                <td class="px-4 py-2 border">{{ $t->inventory->category ?? '—' }}</td> <!-- ✅ Display Category -->
                <td class="px-4 py-2 border">
                    @if($t->type === 'in')
                        <span class="text-green-600 font-bold">IN</span>
                    @else
                        <span class="text-red-600 font-bold">OUT</span>
                    @endif
                </td>
                <td class="px-4 py-2 border">{{ $t->quantity }}</td>
                <td class="px-4 py-2 border">{{ $t->user->name ?? '—' }}</td>
                <td class="px-4 py-2 border">{{ $t->inventory->supplier_name ?? 'N/A' }}</td> <!-- ✅ Display Supplier -->
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $transactions->links() }}
</div>
@endsection


