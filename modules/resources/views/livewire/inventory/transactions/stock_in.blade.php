@extends('layouts.app')
@section('title','Stock-In Delivery')

@section('content')
<div class="header">
        <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
        <div class="user-info">
            <span>Procurement Module</span>
          
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
<h1 class="text-2xl font-bold mb-4">Record Delivery for {{ $inventory->name }}</h1>

<form action="{{ route('stock-in.store', $inventory) }}" method="POST" class="max-w-md">
    @csrf
    <label class="block mb-2">Quantity Received</label>
    <input type="number" name="quantity" class="border p-2 w-full mb-4" min="1" required>

    <label class="block mb-2">Supplier (optional)</label>
    <input type="text" name="supplier" class="border p-2 w-full mb-4">

    <button type="submit" class="btn-potato px-4 py-2 rounded bg-green-500 text-white" style="background-color:#f6b93b; color:white;">>
        Save Delivery
    </button>
</form>
@endsection
