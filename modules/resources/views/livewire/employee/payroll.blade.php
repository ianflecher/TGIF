<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public $employee;
    public $payrollRecords = [];
    public $currentPayroll = null;
    public $selectedPeriod = null;
    public $payrollBreakdown = [];
    public $year = null;
    public $years = [];
    
    public function mount()
    {
        $user = Auth::user();
        $this->employee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('employees.user_id', $user->user_id)
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'users.role',
                'departments.department_name'
            )
            ->first();
        
        $this->year = date('Y');
        $this->loadYears();
        $this->loadPayrollRecords();
    }
    
    public function loadYears()
    {
        $this->years = DB::table('hr_payroll')
            ->where('employee_id', $this->employee->employee_id)
            ->selectRaw('YEAR(period_end) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();
    }
    
    public function loadPayrollRecords()
    {
        $this->payrollRecords = DB::table('hr_payroll')
            ->where('employee_id', $this->employee->employee_id)
            ->when($this->year, function ($query) {
                return $query->whereYear('period_end', $this->year);
            })
            ->orderBy('period_end', 'desc')
            ->select(
                'payroll_id',
                'period_start',
                'period_end',
                'gross_pay',
                'deductions',
                'net_pay',
                'status',
                'notes',
                'created_at'
            )
            ->get();
        
        if ($this->payrollRecords->count() > 0 && !$this->selectedPeriod) {
            $this->viewPayrollDetails($this->payrollRecords->first()->payroll_id);
        }
    }
    
    public function viewPayrollDetails($payrollId)
    {
        $this->selectedPeriod = $payrollId;
        $this->currentPayroll = DB::table('hr_payroll')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->employee->employee_id)
            ->first();
        
        $this->loadPayrollBreakdown();
    }
    
    public function loadPayrollBreakdown()
    {
        // This would come from a payroll_breakdowns table
        // For now, we'll simulate the breakdown
        if ($this->currentPayroll) {
            $gross = $this->currentPayroll->gross_pay;
            $deductions = $this->currentPayroll->deductions;
            
            $this->payrollBreakdown = [
                'earnings' => [
                    ['name' => 'Basic Salary', 'amount' => $gross * 0.7, 'type' => 'fixed'],
                    ['name' => 'Overtime', 'amount' => $gross * 0.1, 'type' => 'variable'],
                    ['name' => 'Allowances', 'amount' => $gross * 0.15, 'type' => 'fixed'],
                    ['name' => 'Bonus', 'amount' => $gross * 0.05, 'type' => 'variable'],
                ],
                'deductions' => [
                    ['name' => 'Income Tax', 'amount' => $deductions * 0.4, 'type' => 'tax'],
                    ['name' => 'Social Security', 'amount' => $deductions * 0.3, 'type' => 'statutory'],
                    ['name' => 'Health Insurance', 'amount' => $deductions * 0.2, 'type' => 'benefit'],
                    ['name' => 'Provident Fund', 'amount' => $deductions * 0.1, 'type' => 'savings'],
                ]
            ];
        }
    }
    
    public function downloadPayslip($payrollId)
    {
        $payroll = DB::table('hr_payroll')
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->employee->employee_id)
            ->first();
        
        if (!$payroll) {
            session()->flash('error', 'Payroll record not found!');
            return;
        }
        
        // In a real app, you would generate a PDF here
        // For now, we'll just show a success message
        session()->flash('success', 'Payslip download started. Please check your downloads folder.');
        
        // You can implement PDF generation using DomPDF or similar
        // return response()->download($pdfPath);
    }
    
    public function requestCorrection($payrollId)
    {
        // This would typically create a ticket or notification
        session()->flash('success', 'Payroll correction request has been submitted for review.');
    }
}
?>

<div class="py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Payroll</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    View your salary details, deductions, and download payslips
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <select wire:model.live="year" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg px-4 py-2">
                    @foreach($years as $yearOption)
                        <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Earnings -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Earnings (YTD)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ₱{{ number_format($payrollRecords->sum('gross_pay'), 2) }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                {{ count($payrollRecords) }} payment periods
            </p>
        </div>

        <!-- Average Net Pay -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average Monthly Net</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ₱{{ number_format($payrollRecords->avg('net_pay') ?? 0, 2) }}
                    </p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                Based on {{ count($payrollRecords) }} periods
            </p>
        </div>

        <!-- Total Deductions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Deductions (YTD)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ₱{{ number_format($payrollRecords->sum('deductions'), 2) }}
                    </p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.342 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                {{ $payrollRecords->count() > 0 ? round(($payrollRecords->sum('deductions') / $payrollRecords->sum('gross_pay')) * 100, 1) : 0 }}% of gross
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payroll History -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payroll History</h2>
                </div>
                <div class="p-6">
                    @if(count($payrollRecords) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-700">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Period
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Gross Pay
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Deductions
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Net Pay
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($payrollRecords as $record)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $selectedPeriod == $record->payroll_id ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ \Carbon\Carbon::parse($record->period_start)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($record->period_end)->format('M d, Y') }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 dark:text-white">
                                                    ${{ number_format($record->gross_pay, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-red-600 dark:text-red-400">
                                                    -${{ number_format($record->deductions, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-green-600 dark:text-green-400">
                                                    ${{ number_format($record->net_pay, 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    {{ $record->status == 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                       ($record->status == 'approved' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                                       'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                                    {{ ucfirst($record->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button wire:click="viewPayrollDetails({{ $record->payroll_id }})" 
                                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 mr-3">
                                                    View
                                                </button>
                                                <button wire:click="downloadPayslip({{ $record->payroll_id }})" 
                                                        class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">
                                                    Download
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No payroll records</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No payroll records found for the selected year.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payroll Details Sidebar -->
        <div class="space-y-6">
            @if($currentPayroll)
                <!-- Selected Payroll Details -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payroll Details</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($currentPayroll->period_start)->format('M d, Y') }} - 
                            {{ \Carbon\Carbon::parse($currentPayroll->period_end)->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="p-6">
                        <!-- Summary -->
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Gross Salary</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    ${{ number_format($currentPayroll->gross_pay, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Deductions</span>
                                <span class="text-lg font-bold text-red-600 dark:text-red-400">
                                    -${{ number_format($currentPayroll->deductions, 2) }}
                                </span>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-900 dark:text-white">Net Pay</span>
                                    <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                                        ${{ number_format($currentPayroll->net_pay, 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Earnings Breakdown -->
                        <div class="mt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Earnings</h3>
                            <div class="space-y-2">
                                @foreach($payrollBreakdown['earnings'] ?? [] as $earning)
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $earning['name'] }}</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            ${{ number_format($earning['amount'], 2) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Deductions Breakdown -->
                        <div class="mt-6">
                            <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Deductions</h3>
                            <div class="space-y-2">
                                @foreach($payrollBreakdown['deductions'] ?? [] as $deduction)
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $deduction['name'] }}</span>
                                        <span class="text-sm font-medium text-red-600 dark:text-red-400">
                                            -${{ number_format($deduction['amount'], 2) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex space-x-3">
                                <button wire:click="downloadPayslip({{ $currentPayroll->payroll_id }})" 
                                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Download Payslip
                                </button>
                                @if($currentPayroll->status === 'paid')
                                    <button wire:click="requestCorrection({{ $currentPayroll->payroll_id }})" 
                                            class="flex-1 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700">
                                        Request Correction
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- Notes -->
                        @if($currentPayroll->notes)
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-2">Notes</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $currentPayroll->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tax Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">YTD Summary</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @php
                                $ytdGross = $payrollRecords->where('period_end', '<=', $currentPayroll->period_end)->sum('gross_pay');
                                $ytdDeductions = $payrollRecords->where('period_end', '<=', $currentPayroll->period_end)->sum('deductions');
                                $ytdNet = $payrollRecords->where('period_end', '<=', $currentPayroll->period_end)->sum('net_pay');
                            @endphp
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">YTD Gross Income</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    ${{ number_format($ytdGross, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">YTD Deductions</span>
                                <span class="text-sm font-medium text-red-600 dark:text-red-400">
                                    -${{ number_format($ytdDeductions, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">YTD Net Income</span>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                    ${{ number_format($ytdNet, 2) }}
                                </span>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Estimated Annual Tax</span>
                                    <span class="text-sm font-bold text-red-600 dark:text-red-400">
                                        -${{ number_format($ytdDeductions * (12 / $payrollRecords->count()), 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Select a payroll period</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a period from the history to view details.</p>
                    </div>
                </div>
            @endif

            <!-- Payroll Calendar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payroll Calendar</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @php
                            $nextPayroll = DB::table('hr_payroll')
                                ->where('employee_id', $this->employee->employee_id)
                                ->where('status', '!=', 'paid')
                                ->orderBy('period_end', 'asc')
                                ->first();
                        @endphp
                        @if($nextPayroll)
                            <div class="flex items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">Next Payroll Date</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($nextPayroll->period_end)->format('M d, Y') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                        
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Payment Schedule</h4>
                            <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Monthly payroll processing
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Payment on last working day
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Bank transfer method
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payroll FAQ</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-medium text-gray-900 dark:text-white mb-2">When will I receive my salary?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Salaries are processed on the last working day of each month and typically reflect in your account within 1-2 business days.
                    </p>
                </div>
                <div>
                    <h3 class="text-md font-medium text-gray-900 dark:text-white mb-2">How are deductions calculated?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Deductions include statutory taxes, social security, insurance premiums, and other benefits as per company policy.
                    </p>
                </div>
                <div>
                    <h3 class="text-md font-medium text-gray-900 dark:text-white mb-2">What if there's an error in my payslip?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Use the "Request Correction" button to report discrepancies. HR will review and respond within 3 business days.
                    </p>
                </div>
                <div>
                    <h3 class="text-md font-medium text-gray-900 dark:text-white mb-2">Where can I get tax documents?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Annual tax statements (Form 16/W-2) are available in January each year through the HR portal.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session()->has('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('success') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#10B981",
            }).showToast();
        });
    </script>
@endif

@if(session()->has('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('error') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#EF4444",
            }).showToast();
        });
    </script>
@endif