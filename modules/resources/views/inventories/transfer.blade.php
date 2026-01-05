@extends('layouts.app')
@section('title', 'Transfer Item')

@section('content')
<div class="header">
        <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
        <div class="user-info">
            <span>Inventory Module</span>
          
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
    Transfer {{ $item->name }}
</h2>
 @if ($errors->any())
    <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<div class="flex justify-center">
   

    <form action="{{ route('inventories.transfer', $item->id) }}" method="POST" class="bg-white p-4 rounded-lg shadow-md w-1/2">
        @csrf

        <div class="mb-3">
            <label for="warehouse" class="block font-semibold mb-1">Destination Warehouse:</label>
            <select name="warehouse" id="warehouse" class="btn-potato-input w-full" required>
                <option value="" disabled selected>Select Warehouse</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse }}" {{ $item->warehouse == $warehouse ? 'selected' : '' }}>
                        {{ $warehouse }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="zone" class="block font-semibold mb-1">Destination Zone:</label>
            <select name="zone" id="zone" class="btn-potato-input w-full" required>
                <option value="" disabled selected>Select Zone</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone }}" {{ $item->zone == $zone ? 'selected' : '' }}>
                        {{ $zone }}
                    </option>
                @endforeach
            </select>
        </div>



        <button type="submit" class="btn-potato" style="background-color:#f6b93b; color:white;">
            Transfer Item
        </button>

        @if($item->warehouse && $item->zone)
<div class="flex justify-center">
    <div class="bg-white p-4 rounded-lg shadow-md w-1/2 text-center">
        <p><strong>Current Warehouse:</strong> {{ $item->warehouse }}</p>
        <p><strong>Current Zone:</strong> {{ $item->zone }}</p>
    </div>
</div>
@endif
    </form>

</div>
@endsection
