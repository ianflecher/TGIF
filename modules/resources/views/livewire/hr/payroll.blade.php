<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithPagination;
    
    public $search = '';
    public $departmentFilter = '';
    public $payPeriod = '';
    public $statusFilter = 'active';
    
    // Payroll details modal
    public $showPayrollDetails = false;
    public $selectedEmployee = null;
    public $payrollBreakdown = [
        'sss' => 0,
        'philhealth' => 0,
        'pagibig' => 0,
        'tax' => 0,
        'other_deductions' => 0,
        'total_deductions' => 0
    ];
    
    public function mount()
    {
        // Set default pay period to current month
        $this->payPeriod = date('Y-m');
    }
    
    public function getPayPeriods()
    {
        // Generate last 12 months for dropdown
        $periods = [];
        for ($i = 0; $i < 12; $i++) {
            $date = date('Y-m', strtotime("-$i months"));
            $periods[] = [
                'value' => $date,
                'label' => date('F Y', strtotime($date))
            ];
        }
        return $periods;
    }
    
    public function getEmployeesProperty()
    {
        return DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->leftJoin('hr_payroll', function($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', 'LIKE', $this->payPeriod . '%');
            })
            ->select(
                'employees.employee_id',
                'employees.user_id',
                'users.full_name',
                'users.email',
                'employees.job_title',
                'departments.department_name',
                'employees.salary',
                'employees.status as employee_status',
                'hr_payroll.payroll_id',
                'hr_payroll.gross_pay',
                'hr_payroll.deductions',
                'hr_payroll.net_pay',
                DB::raw('COALESCE(hr_payroll.status, "not_processed") as payroll_status'),
                'hr_payroll.period_start',
                'hr_payroll.period_end',
                'hr_payroll.notes'
            )
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('users.full_name', 'like', '%' . $this->search . '%')
                      ->orWhere('users.email', 'like', '%' . $this->search . '%')
                      ->orWhere('employees.job_title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->departmentFilter, function ($query) {
                $query->where('departments.department_id', $this->departmentFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('employees.status', $this->statusFilter);
            })
            ->orderBy('users.full_name')
            ->paginate(20);
    }
    
    public function getDepartmentsProperty()
    {
        return DB::table('departments')
            ->orderBy('department_name')
            ->get();
    }
    
    public function getPayrollStatsProperty()
    {
        return DB::table('employees')
            ->join('hr_payroll', function($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', 'LIKE', $this->payPeriod . '%')
                    ->where('hr_payroll.status', 'paid');
            })
            ->select(
                DB::raw('COUNT(DISTINCT employees.employee_id) as total_paid'),
                DB::raw('SUM(hr_payroll.gross_pay) as total_gross'),
                DB::raw('SUM(hr_payroll.deductions) as total_deductions'),
                DB::raw('SUM(hr_payroll.net_pay) as total_net')
            )
            ->first();
    }
    
    public function viewPayrollDetails($employeeId)
    {
        $this->selectedEmployee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->leftJoin('hr_payroll', function($join) {
                $join->on('employees.employee_id', '=', 'hr_payroll.employee_id')
                    ->where('hr_payroll.period_start', 'LIKE', $this->payPeriod . '%');
            })
            ->select(
                'employees.employee_id',
                'employees.user_id',
                'users.full_name',
                'users.email',
                'employees.job_title',
                'departments.department_name',
                'employees.salary',
                'employees.hire_date',
                'employees.status as employee_status',
                'hr_payroll.*'
            )
            ->where('employees.employee_id', $employeeId)
            ->first();
        
        // Calculate Philippines deductions for display
        $this->calculatePhilippinesDeductions();
        
        $this->showPayrollDetails = true;
    }
    
    private function calculatePhilippinesDeductions()
    {
        if (!$this->selectedEmployee) return;
        
        $basicSalary = $this->selectedEmployee->salary;
        
        // Calculate Philippines-specific deductions
        $this->payrollBreakdown['sss'] = $this->calculateSSS($basicSalary);
        $this->payrollBreakdown['philhealth'] = $this->calculatePhilHealth($basicSalary);
        $this->payrollBreakdown['pagibig'] = 100; // Fixed Pag-IBIG contribution
        $this->payrollBreakdown['tax'] = $this->calculateTax($basicSalary);
        
        // Calculate total Philippines deductions
        $phDeductions = $this->payrollBreakdown['sss'] + 
                       $this->payrollBreakdown['philhealth'] + 
                       $this->payrollBreakdown['pagibig'] + 
                       $this->payrollBreakdown['tax'];
        
        // If payroll exists, calculate other deductions
        if ($this->selectedEmployee->payroll_id) {
            $this->payrollBreakdown['other_deductions'] = max(0, $this->selectedEmployee->deductions - $phDeductions);
        }
        
        $this->payrollBreakdown['total_deductions'] = $phDeductions + $this->payrollBreakdown['other_deductions'];
    }
    
    public function closePayrollDetails()
    {
        $this->showPayrollDetails = false;
        $this->selectedEmployee = null;
        $this->payrollBreakdown = [
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'tax' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0
        ];
    }
    
    public function processPayroll($employeeId)
    {
        // Check if payroll already exists for this period
        $existingPayroll = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('period_start', 'LIKE', $this->payPeriod . '%')
            ->first();
        
        if ($existingPayroll) {
            session()->flash('error', 'Payroll already exists for this period!');
            return;
        }
        
        try {
            // Get employee basic salary
            $employee = DB::table('employees')
                ->where('employee_id', $employeeId)
                ->first();
            
            $basicSalary = $employee->salary;
            
            // Calculate Philippines-specific deductions
            $sss = $this->calculateSSS($basicSalary);
            $philhealth = $this->calculatePhilHealth($basicSalary);
            $pagibig = 100; // Fixed Pag-IBIG contribution
            $tax = $this->calculateTax($basicSalary);
            
            $totalDeductions = $sss + $philhealth + $pagibig + $tax;
            $netPay = $basicSalary - $totalDeductions;
            
            // Store breakdown in notes field
            $notes = "SSS: ₱" . number_format($sss, 2) . " | " .
                    "PhilHealth: ₱" . number_format($philhealth, 2) . " | " .
                    "Pag-IBIG: ₱" . number_format($pagibig, 2) . " | " .
                    "Tax: ₱" . number_format($tax, 2);
            
            // Create payroll record
            DB::table('hr_payroll')->insert([
                'employee_id' => $employeeId,
                'period_start' => $this->payPeriod . '-01',
                'period_end' => date('Y-m-t', strtotime($this->payPeriod . '-01')),
                'gross_pay' => $basicSalary,
                'deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'status' => 'calculated',
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            session()->flash('success', 'Payroll processed successfully!');
            
            // Refresh the view
            $this->viewPayrollDetails($employeeId);
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error processing payroll: ' . $e->getMessage());
        }
    }
    
    private function calculateSSS($salary)
    {
        // Simplified SSS calculation based on Philippines brackets
        if ($salary <= 10000) return 450;
        if ($salary <= 20000) return 900;
        if ($salary <= 30000) return 1350;
        if ($salary <= 40000) return 1800;
        if ($salary <= 50000) return 2250;
        return 2700; // Max contribution for salary > 50,000
    }
    
    private function calculatePhilHealth($salary)
    {
        // PhilHealth: 4% of salary, shared 50/50 between employee and employer
        // Employee pays half (2% of salary)
        $premium = $salary * 0.04; // Total premium
        return $premium / 2; // Employee share
    }
    
    private function calculateTax($salary)
    {
        // Simplified Philippines tax calculation (2024 rates)
        // For single, no dependents
        
        // Tax exemption threshold
        if ($salary <= 20833) return 0;
        
        $taxableIncome = $salary;
        
        if ($taxableIncome <= 33333) {
            return 0;
        } elseif ($taxableIncome <= 66667) {
            return ($taxableIncome - 33333) * 0.15;
        } elseif ($taxableIncome <= 166667) {
            return 5000 + ($taxableIncome - 66667) * 0.20;
        } elseif ($taxableIncome <= 666667) {
            return 25000 + ($taxableIncome - 166667) * 0.25;
        } else {
            return 125000 + ($taxableIncome - 666667) * 0.30;
        }
    }
    
    public function approvePayroll($employeeId)
    {
        $payroll = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('period_start', 'LIKE', $this->payPeriod . '%')
            ->first();
            
        if ($payroll) {
            DB::table('hr_payroll')
                ->where('payroll_id', $payroll->payroll_id)
                ->update(['status' => 'approved']);
                
            session()->flash('success', 'Payroll approved successfully!');
            
            // Refresh the view
            $this->viewPayrollDetails($employeeId);
        }
    }
    
    public function markAsPaid($employeeId)
    {
        $payroll = DB::table('hr_payroll')
            ->where('employee_id', $employeeId)
            ->where('period_start', 'LIKE', $this->payPeriod . '%')
            ->first();
            
        if ($payroll) {
            DB::table('hr_payroll')
                ->where('payroll_id', $payroll->payroll_id)
                ->update(['status' => 'paid']);
                
            session()->flash('success', 'Payroll marked as paid!');
            
            // Refresh the view
            $this->viewPayrollDetails($employeeId);
        }
    }
    
    public function exportPayroll()
    {
        $employees = $this->employees->items();
        $period = date('F Y', strtotime($this->payPeriod));
        
        $csvData = "Employee Name,Department,Position,Basic Salary,Gross Pay,Deductions,Net Pay,Status\n";
        
        foreach ($employees as $emp) {
            $csvData .= "\"{$emp->full_name}\",";
            $csvData .= "\"{$emp->department_name}\",";
            $csvData .= "\"{$emp->job_title}\",";
            $csvData .= number_format($emp->salary, 2) . ",";
            $csvData .= number_format($emp->gross_pay ?? 0, 2) . ",";
            $csvData .= number_format($emp->deductions ?? 0, 2) . ",";
            $csvData .= number_format($emp->net_pay ?? 0, 2) . ",";
            $csvData .= $emp->payroll_status;
            $csvData .= "\n";
        }
        
        $filename = "payroll-export-{$this->payPeriod}-" . date('YmdHis') . ".csv";
        
        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Payroll Overview
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    View and manage all employee payroll for the selected period
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                <button wire:click="exportPayroll" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Filters and Controls -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Pay Period Selector -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pay Period</label>
                    <select wire:model.live="payPeriod" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @foreach($this->getPayPeriods() as $period)
                            <option value="{{ $period['value'] }}">{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Department Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select wire:model.live="departmentFilter" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">All Departments</option>
                        @foreach($this->departments as $dept)
                            <option value="{{ $dept->department_id }}">{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee Status</label>
                    <select wire:model.live="statusFilter" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                        <option value="">All Status</option>
                    </select>
                </div>
                
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search" 
                           placeholder="Search by name, email, or job title..."
                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Employees</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $this->employees->total() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Gross Pay</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_gross ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Deductions</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_deductions ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Net Pay</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    ₱{{ number_format($this->payrollStats?->total_net ?? 0, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Employee
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Department & Position
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Basic Salary
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gross Pay
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Deductions
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Net Pay
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payroll Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($this->employees as $employee)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-blue-600 font-medium">
                                                    {{ substr($employee->full_name, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $employee->full_name }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $employee->email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $employee->department_name ?? 'N/A' }}</div>
                                    <div class="text-sm text-gray-500">{{ $employee->job_title }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">
                                        ₱{{ number_format($employee->salary, 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">
                                        ₱{{ number_format($employee->gross_pay ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-red-600">
                                        -₱{{ number_format($employee->deductions ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-green-600">
                                        ₱{{ number_format($employee->net_pay ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'draft' => 'bg-gray-100 text-gray-800',
                                            'calculated' => 'bg-blue-100 text-blue-800',
                                            'approved' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'not_processed' => 'bg-gray-100 text-gray-800',
                                        ];
                                        $payrollStatus = $employee->payroll_status;
                                        $colorClass = $statusColors[$payrollStatus] ?? 'bg-gray-100 text-gray-800';
                                        $statusText = $payrollStatus === 'not_processed' ? 'Not Processed' : ucfirst($payrollStatus);
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button wire:click="viewPayrollDetails({{ $employee->employee_id }})" 
                                            class="text-blue-600 hover:text-blue-900">
                                        View
                                    </button>
                                    
                                    @if($employee->payroll_status === 'not_processed')
                                        <button wire:click="processPayroll({{ $employee->employee_id }})" 
                                                class="text-green-600 hover:text-green-900">
                                            Process
                                        </button>
                                    @elseif($employee->payroll_status === 'calculated')
                                        <button wire:click="approvePayroll({{ $employee->employee_id }})" 
                                                class="text-yellow-600 hover:text-yellow-900">
                                            Approve
                                        </button>
                                    @elseif($employee->payroll_status === 'approved')
                                        <button wire:click="markAsPaid({{ $employee->employee_id }})" 
                                                class="text-green-600 hover:text-green-900">
                                            Mark Paid
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    No employees found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($this->employees->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $this->employees->links() }}
                </div>
            @endif
        </div>
        
        <!-- Summary Section -->
        <div class="mt-6 bg-gray-50 p-4 rounded-lg">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Pay Period Summary: {{ date('F Y', strtotime($this->payPeriod)) }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ $this->payrollStats?->total_paid ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-600">Employees Paid</div>
                </div>
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-green-600">
                        ₱{{ number_format($this->payrollStats?->total_net ?? 0, 2) }}
                    </div>
                    <div class="text-sm text-gray-600">Total Net Pay</div>
                </div>
                <div class="text-center p-3 bg-white rounded shadow">
                    <div class="text-2xl font-bold text-gray-600">
                        @php
                            $gross = $this->payrollStats?->total_gross ?? 0;
                            $deductions = $this->payrollStats?->total_deductions ?? 0;
                            $rate = $gross > 0 ? ($deductions / $gross * 100) : 0;
                        @endphp
                        {{ round($rate, 1) }}%
                    </div>
                    <div class="text-sm text-gray-600">Average Deduction Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Details Modal -->
    @if($showPayrollDetails && $selectedEmployee)
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Payroll Details - {{ $selectedEmployee->full_name }}
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Period: {{ date('F d, Y', strtotime($selectedEmployee->period_start ?? $this->payPeriod . '-01')) }} 
                                to {{ date('F d, Y', strtotime($selectedEmployee->period_end ?? date('Y-m-t', strtotime($this->payPeriod . '-01')))) }}
                            </p>
                        </div>
                        <button wire:click="closePayrollDetails" type="button" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Employee Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Employee Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Employee ID:</span>
                                    <p class="text-sm">{{ $selectedEmployee->employee_id }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Department:</span>
                                    <p class="text-sm">{{ $selectedEmployee->department_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Position:</span>
                                    <p class="text-sm">{{ $selectedEmployee->job_title }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Hire Date:</span>
                                    <p class="text-sm">{{ date('M d, Y', strtotime($selectedEmployee->hire_date)) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Contact Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Email:</span>
                                    <p class="text-sm">{{ $selectedEmployee->email }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Status:</span>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $selectedEmployee->employee_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($selectedEmployee->employee_status) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Basic Salary:</span>
                                    <p class="text-sm font-medium">₱{{ number_format($selectedEmployee->salary, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payroll Breakdown -->
                    @if($selectedEmployee->payroll_id)
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-3">Payroll Breakdown</h4>
                            
                            <!-- Earnings -->
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-green-600 mb-2">EARNINGS</h5>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-sm">Basic Salary:</span>
                                        <span class="text-sm">₱{{ number_format($selectedEmployee->salary, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-1 font-medium">
                                        <span>Total Gross Pay:</span>
                                        <span class="text-green-600">₱{{ number_format($selectedEmployee->gross_pay, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Philippines-Specific Deductions -->
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-red-600 mb-2">PHILIPPINES DEDUCTIONS</h5>
                                <div class="space-y-1">
                                    @if($this->payrollBreakdown['sss'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">SSS Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['sss'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['philhealth'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">PhilHealth Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['philhealth'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['pagibig'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">Pag-IBIG Contribution:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['pagibig'], 2) }}</span>
                                    </div>
                                    @endif
                                    @if($this->payrollBreakdown['tax'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-sm">Tax Withheld:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['tax'], 2) }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Other Deductions -->
                            @if($this->payrollBreakdown['other_deductions'] > 0)
                            <div class="mb-4">
                                <h5 class="text-xs font-medium text-red-600 mb-2">OTHER DEDUCTIONS</h5>
                                <div class="space-y-1">
                                    <div class="flex justify-between">
                                        <span class="text-sm">Other Deductions:</span>
                                        <span class="text-sm">-₱{{ number_format($this->payrollBreakdown['other_deductions'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Total Summary -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium">Total Deductions:</span>
                                    <span class="font-medium text-red-600">-₱{{ number_format($selectedEmployee->deductions, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold">
                                    <span>NET PAY:</span>
                                    <span class="text-green-600">₱{{ number_format($selectedEmployee->net_pay, 2) }}</span>
                                </div>
                            </div>
                            
                            <!-- Payroll Status -->
                            <div class="mt-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <span class="text-xs text-gray-500">Payroll Status:</span>
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-gray-100 text-gray-800',
                                                'calculated' => 'bg-blue-100 text-blue-800',
                                                'approved' => 'bg-yellow-100 text-yellow-800',
                                                'paid' => 'bg-green-100 text-green-800',
                                            ];
                                            $payrollStatus = $selectedEmployee->status ?? 'draft';
                                            $colorClass = $statusColors[$payrollStatus] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $colorClass }}">
                                            {{ ucfirst($payrollStatus) }}
                                        </span>
                                    </div>
                                    <div class="space-x-2">
                                        @if($selectedEmployee->status === 'calculated')
                                            <button wire:click="approvePayroll({{ $selectedEmployee->employee_id }})" 
                                                    class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm rounded hover:bg-yellow-200">
                                                Approve
                                            </button>
                                        @endif
                                        @if($selectedEmployee->status === 'approved')
                                            <button wire:click="markAsPaid({{ $selectedEmployee->employee_id }})" 
                                                    class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded hover:bg-green-200">
                                                Mark as Paid
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No payroll data</h3>
                            <p class="mt-1 text-sm text-gray-500">Payroll has not been processed for this period.</p>
                            <div class="mt-6">
                                <button wire:click="processPayroll({{ $selectedEmployee->employee_id }})" 
                                        type="button" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Process Payroll
                                </button>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Notes -->
                    @if($selectedEmployee->notes)
                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Notes</h4>
                        <p class="text-sm text-gray-700 bg-gray-50 p-2 rounded">{{ $selectedEmployee->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>