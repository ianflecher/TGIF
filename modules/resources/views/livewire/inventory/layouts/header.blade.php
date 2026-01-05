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
