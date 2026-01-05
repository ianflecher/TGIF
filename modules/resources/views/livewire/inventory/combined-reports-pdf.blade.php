<!DOCTYPE html>
<html>
<head>
    <title>Combined Reports PDF</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; } /* Increased from 10px for better readability */
        h1 { font-size: 30px; text-align: center; margin: 20px 0; } /* Larger title font */
        h2 { font-size: 20px; text-align: center; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; margin-top: 50px; page-break-inside: avoid; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; font-size: 11px; } /* Slightly larger table font */
        th { background-color:#2c5530; color: white; font-weight: bold; }
        .section-title { font-size: 30px; margin: 15px 0 5px; text-align: center; font-weight: bold; }
        .alert-summary { margin-bottom: 20px; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        .bg-warning { background-color: #ffc107; color: black; }
        .bg-success { background-color: #28a745; color: white; }
        .bg-secondary { background-color: #6c757d; color: white; }
        .section { page-break-inside: avoid; } /* Ensures title and table stay on the same page */
    </style>
</head>
<body>
    <h1>Combined Inventory Reports</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>

    <!-- Inventory History Section -->
    @if(isset($histories))
        <div class="section">
            <div class="section-title">Inventory History</div>
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
                            $date = $history->created_at->format('Y-m-d H:i:s');
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
        </div>
    @endif

    <!-- Warehouse Logs Section -->
    @if(isset($logs))
        <div class="section">
            <div class="section-title">Warehouse Logs</div>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Action</th>
                        <th>Changes</th>
                        <th>Performed By</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->inventory->name ?? 'Deleted Item' }}</td>
                            <td>{{ ucfirst($log->action) }}</td>
                            <td>
                                @php
                                    $changes = json_decode($log->changes, true);
                                @endphp
                                @if($log->action === 'transferred' && $changes)
                                    @php
                                        $oldWarehouse = $changes['warehouse']['old'] ?? 'N/A';
                                        $newWarehouse = $changes['warehouse']['new'] ?? 'N/A';
                                        $oldZone = $changes['zone']['old'] ?? 'N/A';
                                        $newZone = $changes['zone']['new'] ?? 'N/A';
                                    @endphp
                                    {{ $oldWarehouse }}/{{ $oldZone }} → {{ $newWarehouse }}/{{ $newZone }}
                                @elseif($log->action === 'assigned' && $changes)
                                    @php
                                        $newWarehouse = $changes['warehouse']['new'] ?? 'N/A';
                                        $newZone = $changes['zone']['new'] ?? 'N/A';
                                    @endphp
                                    {{ $newWarehouse }}/{{ $newZone }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $log->user->name ?? 'Unknown' }}</td>
                            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No warehouse logs found.</td>
                            </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <!-- Transaction History Section -->
    @if(isset($transactions))
        <div class="section">
            <div class="section-title">Stock Transaction History</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>User</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $t)
                        <tr>
                            <td>{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $t->inventory->name ?? '—' }}</td>
                            <td>{{ $t->inventory->category ?? '—' }}</td>
                            <td>
                                @if($t->type === 'in')
                                    <span class="badge bg-success">IN</span>
                                @else
                                    <span class="badge bg-warning">OUT</span>
                                @endif
                            </td>
                            <td>{{ $t->quantity }}</td>
                            <td>{{ $t->user->name ?? '—' }}</td>
                            <td>{{ $t->inventory->supplier_name ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Purchase Orders Section -->
    @if(isset($orders))
        <div class="section">
            <div class="section-title">Purchase Orders</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Supplier Name</th>
                        <th>Max Quantity</th>
                        <th>Status</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    @if($orders->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center">No purchase orders have been generated yet.</td>
                        </tr>
                    @else
                        @foreach($orders as $order)
                            <tr>
                                <td>{{ $order->id }}</td>
                                <td>{{ $order->inventory->name ?? 'N/A' }}</td>
                                <td>{{ $order->supplier_name ?? 'N/A' }}</td>
                                <td>{{ $order->max_quantity ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge 
                                        @if($order->status == 'Pending') bg-warning
                                        @elseif($order->status == 'Approved') bg-success
                                        @else bg-secondary
                                        @endif">
                                        {{ ucfirst($order->status ?? 'Pending') }}
                                    </span>
                                </td>
                                <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>