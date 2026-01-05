@extends('layouts.app')

@section('title', 'Stock-In')

@section('content')
<h1 class="mb-6 text-3xl font-bold text-center">🟢 Stock-In Product</h1>

<div class="max-w-md mx-auto bg-white p-6 rounded shadow">
    <form action="{{ route('stock-in.store', $product->id) }}" method="POST">
        @csrf

        <div class="mb-4">
            <label class="block font-semibold">Product Name</label>
            <input type="text" value="{{ $product->name }}" disabled
                   class="w-full p-2 border rounded bg-gray-100">
        </div>

        <div class="mb-4">
            <label class="block font-semibold">Quantity</label>
            <input type="number" name="quantity" min="1" value="1" required
                   class="w-full p-2 border rounded">
        </div>

        <div class="mb-4">
            <label class="block font-semibold">Expiration Date (optional)</label>
            <input type="date" name="expiration_date"
                   class="w-full p-2 border rounded">
        </div>

        <button type="submit" class="w-full bg-green-500 text-white p-2 rounded hover:bg-green-600">
            Add Stock
        </button>
    </form>
</div>
@endsection
