<!DOCTYPE html>
<html>
<head>
    <title>Purchase Orders PDF</title>
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
            background-color: #2e7d32;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
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
    <div class="header">
        <div class="logo">TGIF - THANKS G, IT'S FRIESDAY!</div>
    </div>

    <h1>Purchase Orders Report</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
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
                            <span style="color: 
                                @if($order->status == 'Pending') orange
                                @elseif($order->status == 'Approved') green
                                @else gray
                                @endif; font-weight: bold;">
                                {{ ucfirst($order->status ?? 'Pending') }}
                            </span>
                        </td>
                        <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</body>
</html>