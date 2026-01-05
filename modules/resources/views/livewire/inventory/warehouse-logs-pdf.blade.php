<!DOCTYPE html>
<html>
<head>
    <title>Warehouse Logs PDF</title>
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

    <h1>Warehouse Logs Report</h1>
    <p style="text-align: center;">Generated on {{ now()->format('Y-m-d H:i:s') }}</p>
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
</body>
</html>