@extends('layouts.app')

@section('title', 'Inventory Alerts')

@section('content')
<div class="header">
    <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
    <div class="user-info">
        <span>Inventory Module</span>
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

<h2 class="text-5xl text-center mt-6 mb-6" style="font-weight: 1000;">
    Inventory Alerts
</h2>

<div class="container">
    @if($outOfStock->count() == 0 && $lowStock->count() == 0 && $isExpired->count() == 0 && $nearExpired->count() == 0)
        <div class="alert alert-success p-4 rounded-lg shadow">
            ✅ No alerts. All stocks are good!
        </div>
    @endif

    {{-- Out of Stock --}}
    @if($outOfStock->count() > 0)
        <div class="alert alert-danger p-4 rounded-lg shadow mb-2">
            ❌ Out of Stock ({{ $outOfStock->count() }})
        </div>
        <div class="overflow-x-auto mb-4">
            <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
                <thead class="bg-red-200">
                    <tr>
                        <th class="px-4 py-2 border">SKU</th>
                        <th class="px-4 py-2 border">Name</th>
                        <th class="px-4 py-2 border">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($outOfStock as $item)
                        <tr class="hover:bg-red-100">
                            <td class="px-4 py-2 border">{{ $item->sku }}</td>
                            <td class="px-4 py-2 border">{{ $item->name }}</td>
                            <td class="px-4 py-2 border font-bold">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Low Stock --}}
    @if($lowStock->count() > 0)
        <div class="alert alert-warning p-4 rounded-lg shadow mb-2">
            ⚠️ Low Stock ({{ $lowStock->count() }})
        </div>
        <div class="overflow-x-auto mb-4">
            <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
                <thead class="bg-yellow-200">
                    <tr>
                        <th class="px-4 py-2 border">SKU</th>
                        <th class="px-4 py-2 border">Name</th>
                        <th class="px-4 py-2 border">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowStock as $item)
                        <tr class="hover:bg-yellow-100">
                            <td class="px-4 py-2 border">{{ $item->sku }}</td>
                            <td class="px-4 py-2 border">{{ $item->name }}</td>
                            <td class="px-4 py-2 border font-bold text-yellow-800">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Expired Items --}}
    @if($isExpired->count() > 0)
        <div class="alert alert-dark p-4 rounded-lg shadow mb-2">
            ☠️ Expired Items ({{ $isExpired->count() }})
        </div>
        <div class="overflow-x-auto mb-4">
            <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
                <thead class="bg-gray-300">
                    <tr>
                        <th class="px-4 py-2 border">SKU</th>
                        <th class="px-4 py-2 border">Name</th>
                        <th class="px-4 py-2 border">Expiration Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($isExpired as $item)
                        <tr class="hover:bg-gray-200">
                            <td class="px-4 py-2 border">{{ $item->sku }}</td>
                            <td class="px-4 py-2 border">{{ $item->name }}</td>
                            <td class="px-4 py-2 border text-red-600 font-bold">
                                {{ \Carbon\Carbon::parse($item->expiration_date)->format('F d, Y') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Near Expired Items --}}
    @if($nearExpired->count() > 0)
        <div class="alert alert-info p-4 rounded-lg shadow mb-2">
            ⏳ Near Expiry ({{ $nearExpired->count() }}) — within 30 days
        </div>
        <div class="overflow-x-auto mb-4">
            <table class="table-auto border border-gray-300 rounded-lg w-full text-left mx-auto bg-white shadow-lg">
                <thead class="bg-blue-200">
                    <tr>
                        <th class="px-4 py-2 border">SKU</th>
                        <th class="px-4 py-2 border">Name</th>
                        <th class="px-4 py-2 border">Expiration Date</th>
                        <th class="px-4 py-2 border">Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($nearExpired as $item)
                        <tr class="hover:bg-blue-100">
                            <td class="px-4 py-2 border">{{ $item->sku }}</td>
                            <td class="px-4 py-2 border">{{ $item->name }}</td>
                            <td class="px-4 py-2 border">{{ \Carbon\Carbon::parse($item->expiration_date)->format('F d, Y') }}</td>
                            <td class="px-4 py-2 border">{{ \Carbon\Carbon::parse($item->expiration_date)->diffInDays(now()) }} days</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
