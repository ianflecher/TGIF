<!DOCTYPE html>
<html>
<head>
    <title>Inventory History PDF</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f6b93b; color: white; }
        .section-title { font-size: 16px; margin: 15px 0 5px; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Inventory History Report</h2>
    <div class="section-title">All Inventory Actions</div>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Action</th>
                <th>User</th>
                <th>Changes</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($histories as $history)
                @php
                    $item = $history->inventory;
                    $user = $history->user;
                    $changes = json_decode($history->changes, true);
                    $itemName = $item->name ?? ($changes['name']['new'] ?? 'N/A');
                    $description = $item->description ?? ($changes['description']['new'] ?? 'N/A');
                    $quantity = $item->quantity ?? ($changes['quantity']['new'] ?? 'N/A');
                    $category = $item->category ?? ($changes['category']['new'] ?? '—');
                    $expiration = $item->expiration_date ? \Carbon\Carbon::parse($item->expiration_date)->format('Y-m-d') : ($changes['expiration_date']['new'] ?? 'N/A');
                    $sku = $item->sku ?? ($changes['sku']['new'] ?? 'N/A');
                    $warehouse = $item->warehouse ?? ($changes['warehouse']['new'] ?? 'N/A');
                    $zone = $item->zone ?? ($changes['zone']['new'] ?? 'N/A');
                    $minQty = $item->min_quantity ?? ($changes['minimum']['new'] ?? 'N/A');
                    $maxQty = $item->max_quantity ?? ($changes['maximum']['new'] ?? 'N/A');
                    $supplier = $item->supplier_name ?? ($changes['supplier_name']['new'] ?? 'Unknown');
                    $userName = $user->name ?? 'Unknown';
                    $date = $history->created_at->format('Y-m-d H:i:s'); // Adjust date field
                @endphp

                @if($history->action === 'added')
                    <tr>
                        <td>{{ $itemName }}</td>
                        <td>{{ ucfirst($history->action) }}</td>
                        <td>{{ $userName }}</td>
                        <td>
                            Name: {{ $itemName }}<br>
                            Description: {{ $description }}<br>
                            Quantity: {{ $quantity }}<br>
                            Category: {{ $category }}<br>
                            Expiration Date: {{ $expiration }}<br>
                            SKU: {{ $sku }}<br>
                            Warehouse: {{ $warehouse }}<br>
                            Zone: {{ $zone }}<br>
                            Minimum: {{ $minQty }}<br>
                            Maximum: {{ $maxQty }}<br>
                            Supplier: {{ $supplier }}
                        </td>
                        <td>{{ $date }}</td>
                    </tr>
                @elseif($history->action === 'updated')
                    @foreach($changes as $field => $values)
                        <tr>
                            <td>{{ $itemName }}</td>
                            <td>{{ ucfirst($history->action) }}</td>
                            <td>{{ $userName }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $field)) }}: {{ $values['old'] ?? '' }} → {{ $values['new'] ?? '' }}</td>
                            <td>{{ $date }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>