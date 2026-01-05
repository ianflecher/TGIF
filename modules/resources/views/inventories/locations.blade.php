@extends('layouts.app')

@section('title', 'Assign Item Location')

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
<div class="header">
    <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
    <div class="user-info">
        <span>Procurement Module</span>
        <div class="user-dropdown">
            <button class="dropdown-trigger">
                {{ Auth::user()->name }}
                <span class="arrow">&#9662;</span>
            </button>
            <div class="dropdown-menu">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Logout</button>
                </form>
            </div>
        </div>
    </div>
</div>

<h2 class="text-2xl font-bold text-center mt-6 mb-4">
    {{ $item->name }} - Assign Location
</h2>

<!-- Display warehouse assignment error -->
@if ($errors->has('warehouse'))
    <div class="bg-red-100 text-red-700 p-3 rounded mb-4 w-1/2 mx-auto text-center">
        {{ $errors->first('warehouse') }}
    </div>
@endif


<!-- 🟡 Assign Location Form -->
<div class="flex justify-center mb-6">
    <form action="{{ route('inventories.assignLocation', $item->id) }}" method="POST" class="bg-white p-4 rounded-lg shadow-md w-1/2">
        @csrf
        <div class="mb-3">
            <label for="warehouse" class="block font-semibold mb-1">Select Warehouse:</label>
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
            <label for="zone" class="block font-semibold mb-1">Select Zone:</label>
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
            ➕ Assign Location
        </button>
    </form>
</div>

<!-- 🏷️ Filter by Warehouse -->
<div style="text-align:center; margin-bottom: 20px;">
    <form action="{{ route('inventories.locations', $item->id) }}" method="GET" style="display:inline-flex;">
        <select name="warehouse" class="btn-potato-input" style="margin-right: 10px;" onchange="this.form.submit()">
            <option value="">All Warehouses</option>
           @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse }}" {{ $item->warehouse == $warehouse ? 'selected' : '' }}>
                        {{ $warehouse }}
                    </option>
                @endforeach
        </select>
    </form>
</div>

<!-- 📋 Display Current Assigned Location -->
@if($item->warehouse && $item->zone)
<div class="flex justify-center">
    <div class="bg-white p-4 rounded-lg shadow-md w-1/2 text-center">
        <p><strong>Current Warehouse:</strong> {{ $item->warehouse }}</p>
        <p><strong>Current Zone:</strong> {{ $item->zone }}</p>
    </div>
</div>
@endif

<!-- 📝 Filtered Results Message -->
@if(request('warehouse') && $item->warehouse !== request('warehouse'))
<div class="flex justify-center mt-4">
    <div class="bg-red-100 text-red-600 p-3 rounded-lg w-1/2 text-center">
        No assigned location found for <strong>{{ request('warehouse') }}</strong> in this item.
    </div>
</div>
@endif

<!-- Back Button -->
<div class="flex justify-center mt-6">
    <a href="{{ route('inventories.index') }}" class="btn-potato" style="background-color:#2c5530; color:white;">
        ← Back to Inventory
    </a>
</div>
@endsection
