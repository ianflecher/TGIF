<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class FinancialStatementExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $statementType;
    protected $period;
    
    public function __construct($data, $statementType, $period)
    {
        $this->data = $data;
        $this->statementType = $statementType;
        $this->period = $period;
    }
    
    public function collection()
    {
        $rows = collect();
        
        if ($this->statementType === 'balance_sheet') {
            // Add Assets
            $rows->push(['ASSETS', '', '']);
            foreach ($this->data['assets'] as $asset) {
                $rows->push([$asset->account_name, '', '₱' . number_format($asset->balance, 2)]);
            }
            $rows->push(['Total Assets', '', '₱' . number_format($this->data['total_assets'], 2)]);
            $rows->push(['', '', '']);
            
            // Add Liabilities
            $rows->push(['LIABILITIES', '', '']);
            foreach ($this->data['liabilities'] as $liability) {
                $rows->push([$liability->account_name, '', '₱' . number_format($liability->balance, 2)]);
            }
            $rows->push(['Total Liabilities', '', '₱' . number_format($this->data['total_liabilities'], 2)]);
            $rows->push(['', '', '']);
            
            // Add Equity
            $rows->push(['EQUITY', '', '']);
            foreach ($this->data['equity'] as $equity) {
                $rows->push([$equity->account_name, '', '₱' . number_format($equity->balance, 2)]);
            }
            $rows->push(['Total Equity', '', '₱' . number_format($this->data['total_equity'], 2)]);
            $rows->push(['', '', '']);
            
            // Add Summary
            $rows->push(['TOTAL LIABILITIES & EQUITY', '', '₱' . number_format($this->data['total_liabilities'] + $this->data['total_equity'], 2)]);
            
        } else {
            // Income Statement
            $rows->push(['INCOME STATEMENT', '', '']);
            
            // Add Revenue
            $rows->push(['Revenue', '', '']);
            foreach ($this->data['revenues'] as $revenue) {
                if ($revenue->amount > 0) {
                    $rows->push([$revenue->account_name, '₱' . number_format($revenue->amount, 2), '']);
                }
            }
            $rows->push(['Total Revenue', '₱' . number_format($this->data['total_revenue'], 2), '']);
            $rows->push(['', '', '']);
            
            // Add Expenses
            $rows->push(['Expenses', '', '']);
            foreach ($this->data['expenses'] as $expense) {
                if ($expense->amount > 0) {
                    $rows->push([$expense->account_name, '₱' . number_format($expense->amount, 2), '']);
                }
            }
            $rows->push(['Total Expenses', '₱' . number_format($this->data['total_expense'], 2), '']);
            $rows->push(['', '', '']);
            
            // Add Net Income
            $netIncome = $this->data['total_revenue'] - $this->data['total_expense'];
            $rows->push(['Net Income', '₱' . number_format(abs($netIncome), 2), $netIncome >= 0 ? 'Profit' : 'Loss']);
        }
        
        return $rows;
    }
    
    public function headings(): array
    {
        $periodName = ucfirst(str_replace('_', ' ', $this->period));
        $statementName = $this->statementType === 'balance_sheet' ? 'Balance Sheet' : 'Income Statement';
        
        return [
            ['FINANCIAL STATEMENT'],
            [$statementName],
            ['Period: ' . $periodName],
            ['Generated: ' . now()->format('F d, Y H:i:s')],
            ['Currency: Philippine Peso (₱)'],
            [''],
            ['Account Description', 'Amount', 'Remarks']
        ];
    }
    
    public function title(): string
    {
        return $this->statementType === 'balance_sheet' ? 'Balance Sheet' : 'Income Statement';
    }
    
    public function styles(Worksheet $sheet)
    {
        // Style for headers
        $sheet->getStyle('A1:C7')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFont()->setSize(16);
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');
        $sheet->mergeCells('A3:C3');
        $sheet->mergeCells('A4:C4');
        $sheet->mergeCells('A5:C5');
        
        // Center align headers
        $sheet->getStyle('A1:C7')->getAlignment()->setHorizontal('center');
        
        // Add borders
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A7:C' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);
        
        // Style for totals
        foreach (range(1, $lastRow) as $row) {
            $value = $sheet->getCell('A' . $row)->getValue();
            if (strpos($value, 'Total') === 0 || strpos($value, 'TOTAL') === 0) {
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
            }
        }
    }
}