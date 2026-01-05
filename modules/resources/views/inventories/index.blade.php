@extends('layouts.app')

@section('title','Inventory List')

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
    </div>
 
<div class="main-content" style="margin-left: 220px; padding: 20px; box-sizing: border-box; overflow-x: auto;">
    <div class="flex flex-wrap items-center gap-2 mb-4">

        <!-- Add New Item Button -->
        <div>
            <a href="{{ route('inventories.create') }}" 
               class="btn-potato px-2 py-1 text-sm"
               style="background-color:#f6b93b; color:white;">
                Add New Item
            </a>
        </div>

           <!-- Live Search -->
           
<div class="flex flex-wrap items-center gap-2">
            <form id="live-search-form" class="flex items-center gap-3" style="flex: 5;">
                <input 
                    type="text" 
                    name="search" 
                    id="live-search"
                    value="{{ request('search') }}" 
                    placeholder="Search by SKU, Name, or Category"
                    class="btn-potato-input"
                />
            </form>

      <!-- Filters Container -->
    <form action="{{ route('inventories.index') }}" method="GET" class="flex flex-wrap items-center gap-2">

        <!-- Category Filter -->
        <select name="category" class="btn-potato-input">
    <option value="">All Categories</option>
    @foreach($categories as $category)
        <option value="{{ $category }}" {{ request('category') == $category ? 'selected' : '' }}>
            {{ $category }}
        </option>
    @endforeach
</select>



        <!-- Stock Status -->
        <select name="stock_status" class="btn-potato-input">
            <option value="">All Stock</option>
            <option value="out" {{ request('stock_status') == 'out' ? 'selected' : '' }}>Out of Stock</option>
            <option value="in" {{ request('stock_status') == 'in' ? 'selected' : '' }}>In Stock</option>
            <option value="near_out" {{ request('stock_status') == 'near_out' ? 'selected' : '' }}>Near Out of Stock</option>
        </select>

        <!-- Expiration Status -->
        <select name="expired_status" class="btn-potato-input">
            <option value="">All Items</option>
            <option value="expired" {{ request('expired_status') == 'expired' ? 'selected' : '' }}>Expired</option>
            <option value="valid" {{ request('expired_status') == 'valid' ? 'selected' : '' }}>Not Expired</option>
            <option value="near" {{ request('expired_status') == 'near' ? 'selected' : '' }}>Near Expiration</option>
        </select>

        <!-- Apply Button -->
        <button type="submit" class="btn-potato px-4 py-2 mr-4" style="background-color:#f6b93b; color:white;">
            Apply
        </button>



    </form>
     <form action="{{ route('inventories.export.pdf') }}" method="POST" target="_blank" style="display: inline;">
                @csrf
                <!-- Hidden inputs for current filter params -->
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="category" value="{{ request('category') }}">
                <input type="hidden" name="stock_status" value="{{ request('stock_status') }}">
                <input type="hidden" name="expired_status" value="{{ request('expired_status') }}">
                <button type="submit" class="btn-potato px-2 py-1 text-sm" style="background-color:#f6b93b; color:white;">
                    Export PDF
                </button>
            </form>
</div>

    </div>
</div>


<!-- Inventory Results -->
<div id="inventory-results" class="w-full">
    @include('inventories.partials.inventory-table', ['items' => $items])
</div>






<script>
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('alert-dropdown');
    const badge = dropdown.previousElementSibling;
    if (!badge.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});

// ✅ Live search AJAX
const liveSearch = document.getElementById('live-search');
liveSearch.addEventListener('input', function() {
    const query = this.value;
    fetch(`{{ route('inventories.index') }}?search=${encodeURIComponent(query)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
        // Replace table results
        document.getElementById('inventory-results').innerHTML = html;
    })
    .catch(err => console.error(err));
});

// ✅ Update export form dynamically based on live search
const exportForm = document.querySelector('form[action="{{ route('inventories.export.pdf') }}"]');
const exportSearchInput = exportForm.querySelector('input[name="search"]');

liveSearch.addEventListener('input', function() {
    exportSearchInput.value = this.value; // update export input dynamically
});
</script>


@endsection
