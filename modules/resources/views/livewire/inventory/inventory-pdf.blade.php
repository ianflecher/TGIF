<!DOCTYPE html>
<html>
<head>
    <title>Inventory PDF</title>
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
    table-layout: fixed; /* important */
}

th, td {
    border: 1px solid #000;
    padding: 6px;
    text-align: left;
    font-size: 14px; /* reduce font size to fit */
    word-wrap: break-word; /* wrap long text */
    overflow-wrap: break-word; /* for compatibility */
}


        th {
            font-size: 16px;
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

    <h1>Inventory Report</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Min Quantity</th>
                <th>Max Quantity</th>
                <th>Expiration Date</th>
                <th>Supplier</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->category }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->min_quantity }}</td>
                    <td>{{ $item->max_quantity }}</td>
                    <td>{{ $item->expiration_date ? \Carbon\Carbon::parse($item->expiration_date)->format('Y-m-d') : 'N/A' }}</td>
                    <td>{{ $item->supplier_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>