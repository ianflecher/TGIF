<!DOCTYPE html>
<html>
<head>
    <title>Transaction History PDF</title>
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

    <h1>Transaction History Report</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
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
                            <span style="color: green; font-weight: bold;">IN</span>
                        @else
                            <span style="color: red; font-weight: bold;">OUT</span>
                        @endif
                    </td>
                    <td>{{ $t->quantity }}</td>
                    <td>{{ $t->user->name ?? '—' }}</td>
                    <td>{{ $t->inventory->supplier_name ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>