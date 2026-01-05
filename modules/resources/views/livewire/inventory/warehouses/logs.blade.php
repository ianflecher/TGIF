@extends('layouts.app')

@section('title', 'Warehouse Logs')

@section('content')
<h1 class="text-2xl font-bold mb-4">🏷 Warehouse Activity Logs</h1>

<table class="w-full border-collapse">
    <thead>
        <tr class="bg-gray-200">
            <th class="p-2 text-left">Date</th>
            <th class="p-2 text-left">Item</th>
            <th class="p-2 text-left">Action</th>
            <th class="p-2 text-left">From</th>
            <th class="p-2 text-left">To</th>
            <th class="p-2 text-left">Qty</th>
            <th class="p-2 text-left">User</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($logs as $log)
        <tr class="border-b hover:bg-gray-50">
            <td class="p-2">{{ $log->created_at->format('Y-m-d H:i') }}</td>
            <td class="p-2">{{ $log->item_name }}</td>
            <td class="p-2">{{ $log->action }}</td>
            <td class="p-2">{{ $log->from_warehouse ?? '-' }}</td>
            <td class="p-2">{{ $log->to_warehouse ?? '-' }}</td>
            <td class="p-2">{{ $log->quantity ?? '-' }}</td>
            <td class="p-2">{{ $log->user_id ? \App\Models\User::find($log->user_id)->name : 'System' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
