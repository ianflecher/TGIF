@extends('layouts.app')

@section('title', 'Supplier List')

@section('content')
<div class="container">
    <h1 style="margin-bottom: 20px;">📦 Supplier List</h1>

    @if($suppliers->isEmpty())
        <div class="alert alert-warning">
            No suppliers found in the inventory.
        </div>
    @else
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier Name</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $index => $supplier)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $supplier->supplier_name }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
