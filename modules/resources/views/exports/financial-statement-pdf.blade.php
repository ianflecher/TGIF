<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Statement</title>
    <style>
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .document-info {
            font-size: 11px;
            color: #666;
        }
        .currency-note {
            font-size: 10px;
            color: #999;
            text-align: right;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .total-row {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .section-title {
            background-color: #e8e8e8;
            font-weight: bold;
            padding: 6px 8px;
            margin-top: 15px;
            border: 1px solid #ddd;
        }
        .summary {
            margin-top: 30px;
            padding: 15px;
            border: 2px solid #333;
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .profit {
            color: #28a745;
        }
        .loss {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">YOUR COMPANY NAME</div>
        <div class="document-title">
            @if($statementType === 'balance_sheet')
                BALANCE SHEET
            @else
                INCOME STATEMENT
            @endif
        </div>
        <div class="document-info">
            Period: {{ ucfirst(str_replace('_', ' ', $statementPeriod)) }}<br>
            Generated: {{ $generatedDate }}<br>
            Currency: Philippine Peso (₱)
        </div>
    </div>
    
    <div class="currency-note">
        All amounts in Philippine Peso (₱)
    </div>
    
    @if($statementType === 'balance_sheet')
        <!-- Balance Sheet -->
        <div style="display: flex; justify-content: space-between;">
            <!-- Assets -->
            <div style="width: 48%;">
                <div class="section-title">ASSETS</div>
                <table>
                    <tbody>
                        @foreach($data['assets'] as $asset)
                        <tr>
                            <td>{{ $asset->account_name }}</td>
                            <td class="amount">{{ $currency }}{{ number_format($asset->balance, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td><strong>TOTAL ASSETS</strong></td>
                            <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_assets'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Liabilities & Equity -->
            <div style="width: 48%;">
                <div class="section-title">LIABILITIES</div>
                <table>
                    <tbody>
                        @foreach($data['liabilities'] as $liability)
                        <tr>
                            <td>{{ $liability->account_name }}</td>
                            <td class="amount">{{ $currency }}{{ number_format($liability->balance, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td><strong>TOTAL LIABILITIES</strong></td>
                            <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_liabilities'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="section-title" style="margin-top: 20px;">EQUITY</div>
                <table>
                    <tbody>
                        @foreach($data['equity'] as $equity)
                        <tr>
                            <td>{{ $equity->account_name }}</td>
                            <td class="amount">{{ $currency }}{{ number_format($equity->balance, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td><strong>TOTAL EQUITY</strong></td>
                            <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_equity'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <table style="margin-top: 20px;">
                    <tbody>
                        <tr class="total-row">
                            <td><strong>TOTAL LIABILITIES & EQUITY</strong></td>
                            <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_liabilities'] + $data['total_equity'], 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Balance Check Summary -->
        <div class="summary">
            <div style="text-align: center; margin-bottom: 10px; font-weight: bold;">
                BALANCE SHEET CHECK
            </div>
            <div style="text-align: center; font-size: 16px;">
                ASSETS = LIABILITIES + EQUITY<br>
                <span style="font-size: 20px; font-weight: bold;">
                    {{ $currency }}{{ number_format($data['total_assets'], 2) }}
                    @if(abs($data['total_assets'] - ($data['total_liabilities'] + $data['total_equity'])) < 0.01)
                        <span style="color: #28a745;">= {{ $currency }}{{ number_format($data['total_liabilities'] + $data['total_equity'], 2) }}</span>
                    @else
                        <span style="color: #dc3545;">≠ {{ $currency }}{{ number_format($data['total_liabilities'] + $data['total_equity'], 2) }}</span>
                    @endif
                </span>
            </div>
            <div style="text-align: center; margin-top: 10px;">
                @if(abs($data['total_assets'] - ($data['total_liabilities'] + $data['total_equity'])) < 0.01)
                    <span style="color: #28a745; font-weight: bold;">✓ BALANCE SHEET IS BALANCED</span>
                @else
                    <span style="color: #dc3545; font-weight: bold;">⚠ BALANCE SHEET IS NOT BALANCED</span>
                @endif
            </div>
        </div>
        
    @else
        <!-- Income Statement -->
        <div class="section-title">REVENUE</div>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['revenues'] as $revenue)
                    @if($revenue->amount > 0)
                    <tr>
                        <td>{{ $revenue->account_name }}</td>
                        <td class="amount">{{ $currency }}{{ number_format($revenue->amount, 2) }}</td>
                    </tr>
                    @endif
                @endforeach
                <tr class="total-row">
                    <td><strong>TOTAL REVENUE</strong></td>
                    <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_revenue'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-title">EXPENSES</div>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['expenses'] as $expense)
                    @if($expense->amount > 0)
                    <tr>
                        <td>{{ $expense->account_name }}</td>
                        <td class="amount">{{ $currency }}{{ number_format($expense->amount, 2) }}</td>
                    </tr>
                    @endif
                @endforeach
                <tr class="total-row">
                    <td><strong>TOTAL EXPENSES</strong></td>
                    <td class="amount"><strong>{{ $currency }}{{ number_format($data['total_expense'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Net Income Summary -->
        <div class="summary">
            <div style="text-align: center; margin-bottom: 10px; font-weight: bold;">
                NET INCOME SUMMARY
            </div>
            <table style="margin-bottom: 10px;">
                <tbody>
                    <tr>
                        <td style="width: 60%;">Total Revenue:</td>
                        <td class="amount" style="width: 40%;">{{ $currency }}{{ number_format($data['total_revenue'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Expenses:</td>
                        <td class="amount">{{ $currency }}{{ number_format($data['total_expense'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align: center; font-size: 18px; font-weight: bold; padding: 10px; border-top: 1px solid #333;">
                NET INCOME/LOSS: 
                @if($data['net_income'] >= 0)
                    <span class="profit">{{ $currency }}{{ number_format($data['net_income'], 2) }} PROFIT</span>
                @else
                    <span class="loss">{{ $currency }}{{ number_format(abs($data['net_income']), 2) }} LOSS</span>
                @endif
            </div>
        </div>
    @endif
    
    <div class="footer">
        This document was generated automatically by the Financial System.<br>
        Page 1 of 1
    </div>
</body>
</html>