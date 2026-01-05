<!DOCTYPE html>
<html>
<head>
    <title>Inventory History PDF</title>
    <style>
        /* Copied and adapted styles from potato.css for PDF compatibility */
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fffef6;
            color: #1f2b16;
            margin: 0;
            padding: 0;
        }

        .header {
        box-sizing: border-box;
        width: 100%;
        background-color: #2e7d32; /* Solid green instead of gradient for PDF compatibility */
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    h1 {
        font-size: 30px;
        text-align: center;
        margin: 20px 0;
        
        color: black;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }


        .logo {
            font-size: 2rem;
            font-weight: bold;
        }

       

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            font-size: 20px;
          
        }

        th {
           font-size: 20px;
            color: white;
            font-weight: bold;
             background-color: #2e7d32;
        }
    </style>

   
</head>
<body>
    <!-- Header div with the updated gradient background -->
    <div class="header">
        <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
        <!-- Note: User info (bell, dropdown) removed as PDFs can't render interactive elements -->
    </div>

    <h1>Inventory History Report</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
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
</body>
</html>