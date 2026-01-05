@extends('layouts.app')

@section('title', 'Inventory Threshold Report')

@section('content')
<h2 class="text-2xl font-bold text-center my-6">📊 Inventory Threshold Report</h2>

<h3 class="text-xl font-semibold mt-4 mb-2">Low Stock Items</h3>
<table class="table-auto w-full border border-gray-300 mb-6">
    <thead>
        <tr>
            <th>Item Name</th>
            <th>Quantity</th>
            <th>Min Quantity</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lowStock as $item)
        <tr class="bg-red-100">
            <td>{{ $item->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ $item->min_quantity }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center">No items below minimum quantity.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<h3 class="text-xl font-semibold mt-4 mb-2">High Stock Items</h3>
<table class="table-auto w-full border border-gray-300">
    <thead>
        <tr>
            <th>Item Name</th>
            <th>Quantity</th>
            <th>Max Quantity</th>
        </tr>
    </thead>
    <tbody>
        @forelse($highStock as $item)
        <tr class="bg-yellow-100">
            <td>{{ $item->name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ $item->max_quantity }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center">No items above maximum quantity.</td>
        </tr>
        @endforelse
    </tbody>
</table>
@endsection
