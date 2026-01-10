    <?php

    namespace App\Http\Livewire\Volt;

    use Livewire\Volt\Component;
    use Livewire\Attributes\Layout;
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;

    new #[Layout('components.layouts.reports')] class extends Component
    {
        public $dateRange = 'monthly';
        public $startDate;
        public $endDate;
        public $module = 'overview';
        public $charts = [];
        public $summaryData = [];
        public $loading = false;
        
        public function mount()
        {
            $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
            $this->generateSummary();
        }
        
        public function updatedDateRange($value)
        {
            switch($value) {
                case 'daily':
                    $this->startDate = Carbon::now()->format('Y-m-d');
                    $this->endDate = Carbon::now()->format('Y-m-d');
                    break;
                case 'weekly':
                    $this->startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                    $this->endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                    break;
                case 'monthly':
                    $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                    $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                    break;
                case 'quarterly':
                    $this->startDate = Carbon::now()->startOfQuarter()->format('Y-m-d');
                    $this->endDate = Carbon::now()->endOfQuarter()->format('Y-m-d');
                    break;
                case 'yearly':
                    $this->startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                    $this->endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                    break;
                case 'custom':
                    // Keep current dates for custom selection
                    break;
            }
            $this->generateSummary();
        }
        
        public function updatedStartDate()
        {
            $this->dateRange = 'custom';
            $this->generateSummary();
        }
        
        public function updatedEndDate()
        {
            $this->dateRange = 'custom';
            $this->generateSummary();
        }
        
        public function updatedModule()
        {
            $this->generateSummary();
        }
        
        public function generateSummary()
        {
            $this->loading = true;
            
            try {
                $this->summaryData = $this->getSummaryData();
                $this->charts = $this->generateCharts();
                
                // Dispatch event for JavaScript to render charts
                $this->dispatch('charts-ready', charts: $this->charts);
            } catch (\Exception $e) {
                session()->flash('error', 'Error generating report: ' . $e->getMessage());
            }
            
            $this->loading = false;
        }
        
        private function getSummaryData()
        {
            $data = [];
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            
            if ($this->module === 'overview' || $this->module === 'hr') {
                // HR Summary
                $data['hr'] = [
                    'total_employees' => DB::table('employees')->where('status', 'active')->count(),
                    'present_today' => DB::table('hr_attendance')
                        ->whereDate('date', Carbon::today())
                        ->where('status', 'present')
                        ->count(),
                    'on_leave' => DB::table('employees')->where('status', 'on_leave')->count(),
                    'total_payroll' => DB::table('hr_payroll')
                        ->where('status', 'paid')
                        ->whereBetween('period_end', [$start, $end])
                        ->sum('net_pay'),
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'inventory') {
                // Inventory Summary
                $data['inventory'] = [
                    'total_products' => DB::table('inventories')->count(),
                    'low_stock' => DB::table('inventories')
                        ->whereColumn('quantity', '<=', 'min_quantity')
                        ->count(),
                    'out_of_stock' => DB::table('inventories')->where('quantity', 0)->count(),
                    'total_value' => DB::table('inventories')
                        ->sum(DB::raw('quantity * cost_price')),
                    'stock_movements' => DB::table('stock_transactions')
                        ->whereBetween('transaction_date', [$start, $end])
                        ->count(),
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'purchase') {
                // Purchase Summary
                $data['purchase'] = [
                    'total_orders' => DB::table('purchase_orders')
                        ->whereBetween('order_date', [$start, $end])
                        ->count(),
                    'pending_orders' => DB::table('purchase_orders')
                        ->where('status', 'draft')
                        ->orWhere('status', 'sent')
                        ->count(),
                    'total_spent' => DB::table('purchase_orders')
                        ->where('status', 'completed')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('total_amount'),
                    'pending_requisitions' => DB::table('purchase_requisitions')
                        ->whereIn('status', ['draft', 'submitted'])
                        ->count(),
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'sales') {
                // Sales Summary
                $data['sales'] = [
                    'total_orders' => DB::table('sales_orders')
                        ->whereBetween('order_date', [$start, $end])
                        ->count(),
                    'total_revenue' => DB::table('sales_orders')
                        ->where('status', 'delivered')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('grand_total'),
                    'pending_orders' => DB::table('sales_orders')
                        ->whereNotIn('status', ['delivered', 'cancelled'])
                        ->whereBetween('order_date', [$start, $end])
                        ->count(),
                    'average_order_value' => DB::table('sales_orders')
                        ->where('status', 'delivered')
                        ->whereBetween('order_date', [$start, $end])
                        ->avg('grand_total'),
                    'new_customers' => DB::table('customers')
                        ->whereBetween('date_registered', [$start, $end])
                        ->count(),
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'ecommerce') {
                // E-commerce Summary
                $data['ecommerce'] = [
                    'platform_orders' => DB::table('ecommerce_orders')
                        ->whereBetween('order_date', [$start, $end])
                        ->count(),
                    'total_sales' => DB::table('ecommerce_orders')
                        ->where('payment_status', 'paid')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('total_amount'),
                    'top_platform' => DB::table('ecommerce_orders')
                        ->select('platform', DB::raw('COUNT(*) as count'))
                        ->whereBetween('order_date', [$start, $end])
                        ->groupBy('platform')
                        ->orderBy('count', 'desc')
                        ->first(),
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'support') {
                // Support Summary
                $data['support'] = [
                    'open_tickets' => DB::table('tickets')->where('status', 'open')->count(),
                    'total_tickets' => DB::table('tickets')
                        ->whereBetween('created_at', [$start, $end])
                        ->count(),
                    'avg_response_time' => DB::table('tickets')
                        ->whereNotNull('first_response_at')
                        ->whereBetween('created_at', [$start, $end])
                        ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_time'))
                        ->first()->avg_time ?? 0,
                    'resolution_rate' => DB::table('tickets')
                        ->whereBetween('created_at', [$start, $end])
                        ->select(DB::raw('(SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) / COUNT(*) * 100) as rate'))
                        ->first()->rate ?? 0,
                ];
            }
            
            if ($this->module === 'overview' || $this->module === 'finance') {
                // Finance Summary
                $data['finance'] = [
                    'total_revenue' => DB::table('sales_orders')
                        ->where('payment_status', 'paid')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('grand_total'),
                    'total_expenses' => DB::table('purchase_orders')
                        ->where('status', 'completed')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('total_amount') + DB::table('hr_payroll')
                        ->where('status', 'paid')
                        ->whereBetween('period_end', [$start, $end])
                        ->sum('net_pay'),
                    'profit_loss' => 0,
                    'accounts_receivable' => DB::table('sales_orders')
                        ->where('payment_status', '!=', 'paid')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('grand_total'),
                    'cash_balance' => DB::table('payments')
                        ->where('status', 'completed')
                        ->whereBetween('payment_date', [$start, $end])
                        ->sum('amount'),
                ];
                $data['finance']['profit_loss'] = $data['finance']['total_revenue'] - $data['finance']['total_expenses'];
            }
            
            if ($this->module === 'overview' || $this->module === 'projects') {
                // Projects Summary
                $data['projects'] = [
                    'active_projects' => DB::table('projects')->where('status', 'in_progress')->count(),
                    'completed_projects' => DB::table('projects')
                        ->where('status', 'completed')
                        ->whereBetween('end_date', [$start, $end])
                        ->count(),
                    'total_budget' => DB::table('projects')->sum('budget_total'),
                    'total_spent' => DB::table('projects')->sum('actual_cost'),
                    'overdue_tasks' => DB::table('tasks')
                        ->where('end_date', '<', Carbon::now())
                        ->where('status', '!=', 'completed')
                        ->count(),
                ];
            }
            
            return $data;
        }
        
        private function generateCharts()
        {
            $charts = [];
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            
            // Determine interval based on date range
            $diffDays = $start->diffInDays($end);
            $interval = $diffDays > 365 ? 'monthly' : ($diffDays > 30 ? 'weekly' : 'daily');
            
            // Sales Trend Chart
            if ($this->module === 'overview' || $this->module === 'sales' || $this->module === 'finance') {
                $salesData = $this->getTimeSeriesData(
                    'sales_orders',
                    'order_date',
                    'grand_total',
                    ['status' => 'delivered'],
                    $start,
                    $end,
                    $interval
                );
                
                $charts['sales_trend'] = [
                    'id' => 'salesTrendChart',
                    'type' => 'line',
                    'title' => 'Sales Trend',
                    'labels' => $salesData['labels'],
                    'datasets' => [
                        [
                            'label' => 'Revenue ($)',
                            'data' => $salesData['data'],
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                            'borderWidth' => 2,
                            'fill' => true,
                            'tension' => 0.4
                        ]
                    ]
                ];
            }
            
            // Inventory Status Chart
            if ($this->module === 'overview' || $this->module === 'inventory') {
                $inventoryStats = DB::table('inventories')
                    ->select(
                        DB::raw("SUM(CASE WHEN quantity > max_quantity THEN 1 ELSE 0 END) as overstock"),
                        DB::raw("SUM(CASE WHEN quantity <= max_quantity AND quantity > min_quantity THEN 1 ELSE 0 END) as optimal"),
                        DB::raw("SUM(CASE WHEN quantity <= min_quantity AND quantity > 0 THEN 1 ELSE 0 END) as low_stock"),
                        DB::raw("SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock")
                    )
                    ->first();
                
                $charts['inventory_status'] = [
                    'id' => 'inventoryStatusChart',
                    'type' => 'doughnut',
                    'title' => 'Inventory Status',
                    'labels' => ['Overstock', 'Optimal', 'Low Stock', 'Out of Stock'],
                    'datasets' => [
                        [
                            'data' => [
                                $inventoryStats->overstock ?? 0,
                                $inventoryStats->optimal ?? 0,
                                $inventoryStats->low_stock ?? 0,
                                $inventoryStats->out_of_stock ?? 0
                            ],
                            'backgroundColor' => [
                                'rgb(239, 68, 68)',
                                'rgb(34, 197, 94)',
                                'rgb(249, 115, 22)',
                                'rgb(156, 163, 175)'
                            ],
                            'borderWidth' => 1
                        ]
                    ]
                ];
            }
            
            // Ticket Status Chart
            if ($this->module === 'overview' || $this->module === 'support') {
                $ticketStatus = DB::table('tickets')
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->whereBetween('created_at', [$start, $end])
                    ->groupBy('status')
                    ->get();
                
                $statusLabels = $ticketStatus->pluck('status')
                    ->map(fn($status) => ucfirst(str_replace('_', ' ', $status)))
                    ->toArray();
                
                $charts['ticket_status'] = [
                    'id' => 'ticketStatusChart',
                    'type' => 'pie',
                    'title' => 'Ticket Status Distribution',
                    'labels' => $statusLabels,
                    'datasets' => [
                        [
                            'data' => $ticketStatus->pluck('count')->toArray(),
                            'backgroundColor' => [
                                'rgb(59, 130, 246)',
                                'rgb(249, 115, 22)',
                                'rgb(34, 197, 94)',
                                'rgb(156, 163, 175)',
                                'rgb(239, 68, 68)'
                            ],
                        ]
                    ]
                ];
            }
            
            // Revenue vs Expenses Chart
            if ($this->module === 'overview' || $this->module === 'finance') {
                $revenueData = $this->getTimeSeriesData(
                    'sales_orders',
                    'order_date',
                    'grand_total',
                    ['payment_status' => 'paid'],
                    $start,
                    $end,
                    $interval
                );
                
                $expenseData = $this->getTimeSeriesData(
                    'purchase_orders',
                    'order_date',
                    'total_amount',
                    ['status' => 'completed'],
                    $start,
                    $end,
                    $interval
                );
                
                // Use revenue labels as base
                $labels = $revenueData['labels'];
                
                $charts['revenue_expenses'] = [
                    'id' => 'revenueExpensesChart',
                    'type' => 'bar',
                    'title' => 'Revenue vs Expenses',
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Revenue ($)',
                            'data' => $revenueData['data'],
                            'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                            'borderColor' => 'rgb(34, 197, 94)',
                            'borderWidth' => 1
                        ],
                        [
                            'label' => 'Expenses ($)',
                            'data' => $expenseData['data'],
                            'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                            'borderColor' => 'rgb(239, 68, 68)',
                            'borderWidth' => 1
                        ]
                    ]
                ];
            }
            
            // Employee Attendance Chart
            if ($this->module === 'overview' || $this->module === 'hr') {
                $attendanceData = DB::table('hr_attendance')
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->whereBetween('date', [$start, $end])
                    ->groupBy('status')
                    ->get();
                
                $attendanceLabels = $attendanceData->pluck('status')
                    ->map(fn($status) => ucfirst(str_replace('_', ' ', $status)))
                    ->toArray();
                
                $charts['attendance'] = [
                    'id' => 'attendanceChart',
                    'type' => 'bar',
                    'title' => 'Attendance Overview',
                    'labels' => $attendanceLabels,
                    'datasets' => [
                        [
                            'label' => 'Count',
                            'data' => $attendanceData->pluck('count')->toArray(),
                            'backgroundColor' => 'rgba(139, 92, 246, 0.7)',
                            'borderColor' => 'rgb(139, 92, 246)',
                            'borderWidth' => 1
                        ]
                    ]
                ];
            }
            
            return $charts;
        }
        
        private function getTimeSeriesData($table, $dateColumn, $valueColumn, $conditions, $start, $end, $interval)
        {
            $query = DB::table($table);
            
            foreach ($conditions as $column => $value) {
                $query->where($column, $value);
            }
            
            $query->whereBetween($dateColumn, [$start, $end]);
            
            switch ($interval) {
                case 'daily':
                    $query->select(
                        DB::raw("DATE($dateColumn) as period"),
                        DB::raw("COALESCE(SUM($valueColumn), 0) as total")
                    )
                    ->groupBy(DB::raw("DATE($dateColumn)"));
                    break;
                    
                case 'weekly':
                    $query->select(
                        DB::raw("CONCAT(YEAR($dateColumn), '-W', LPAD(WEEK($dateColumn), 2, '0')) as period"),
                        DB::raw("COALESCE(SUM($valueColumn), 0) as total")
                    )
                    ->groupBy(DB::raw("YEAR($dateColumn), WEEK($dateColumn)"));
                    break;
                    
                case 'monthly':
                    $query->select(
                        DB::raw("DATE_FORMAT($dateColumn, '%Y-%m') as period"),
                        DB::raw("COALESCE(SUM($valueColumn), 0) as total")
                    )
                    ->groupBy(DB::raw("YEAR($dateColumn), MONTH($dateColumn)"));
                    break;
            }
            
            $result = $query->orderBy('period')->get();
            
            return [
                'labels' => $result->pluck('period')->toArray(),
                'data' => $result->pluck('total')->map(fn($value) => floatval($value))->toArray(),
            ];
        }
        
        public function export($format)
        {
            $this->dispatch('exportReport', format: $format);
        }
        
        public function scheduleReport()
        {
            // This would typically save to a jobs table or use Laravel's scheduler
            session()->flash('success', 'Report scheduled successfully!');
        }
    }

    ?>

    <div>
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">System Summary Report</h1>
                <p class="text-gray-600 mt-2">Comprehensive overview of all business modules</p>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Date Range Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select wire:model.live="dateRange" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="daily">Today</option>
                            <option value="weekly">This Week</option>
                            <option value="monthly" selected>This Month</option>
                            <option value="quarterly">This Quarter</option>
                            <option value="yearly">This Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>

                    <!-- Custom Date Range -->
                    @if($dateRange === 'custom')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" wire:model.live="startDate" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" wire:model.live="endDate" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    @endif

                    <!-- Module Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Module</label>
                        <select wire:model.live="module" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="overview">All Modules Overview</option>
                            <option value="hr">Human Resources</option>
                            <option value="inventory">Inventory</option>
                            <option value="purchase">Purchase</option>
                            <option value="sales">Sales</option>
                            <option value="ecommerce">E-commerce</option>
                            <option value="support">Customer Support</option>
                            <option value="finance">Finance</option>
                            <option value="projects">Projects</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex flex-wrap gap-3">
                    <button wire:click="generateSummary" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50">
                        <svg wire:loading wire:target="generateSummary" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $loading ? 'Generating...' : 'Refresh Report' }}
                    </button>

                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            Export Report
                            <svg class="ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" 
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                            <div class="py-1">
                                <a href="#" @click.prevent="exportAsPDF()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">PDF Document</a>
                                <a href="#" @click.prevent="exportAsExcel()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Excel Spreadsheet</a>
                                <a href="#" @click.prevent="exportAsCSV()" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">CSV File</a>
                            </div>
                        </div>
                    </div>

                    <button wire:click="scheduleReport" 
                            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        Schedule Report
                    </button>
                </div>
            </div>

            <!-- Summary Stats -->
            @if($module === 'overview' || isset($summaryData['hr']) || isset($summaryData['sales']) || isset($summaryData['inventory']) || isset($summaryData['support']))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- HR Stats -->
                @if(isset($summaryData['hr']))
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-600">Total Employees</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $summaryData['hr']['total_employees'] }}</p>
                        </div>
                        <div class="bg-blue-500 rounded-lg p-3">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Sales Stats -->
                @if(isset($summaryData['sales']))
                <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-600">Total Revenue</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">${{ number_format($summaryData['sales']['total_revenue'] ?? 0, 2) }}</p>
                        </div>
                        <div class="bg-green-500 rounded-lg p-3">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Inventory Stats -->
                @if(isset($summaryData['inventory']))
                <div class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-orange-600">Low Stock Items</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $summaryData['inventory']['low_stock'] ?? 0 }}</p>
                        </div>
                        <div class="bg-orange-500 rounded-lg p-3">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Support Stats -->
                @if(isset($summaryData['support']))
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-600">Open Tickets</p>
                            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $summaryData['support']['open_tickets'] ?? 0 }}</p>
                        </div>
                        <div class="bg-purple-500 rounded-lg p-3">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <!-- Module Specific Stats -->
            @if($module !== 'overview' && isset($summaryData[$module]))
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ ucfirst($module) }} Module Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($summaryData[$module] as $key => $value)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ str_replace('_', ' ', $key) }}</p>
                        <p class="text-2xl font-bold text-gray-900 mt-2">
                            @if(is_numeric($value))
                                @if(str_contains($key, 'total') || str_contains($key, 'amount') || str_contains($key, 'revenue') || str_contains($key, 'expense') || str_contains($key, 'spent'))
                                    ${{ number_format($value, 2) }}
                                @elseif(str_contains($key, 'rate') || str_contains($key, 'ratio'))
                                    {{ number_format($value, 2) }}%
                                @elseif(str_contains($key, 'time'))
                                    {{ number_format($value, 0) }} min
                                @else
                                    {{ $value }}
                                @endif
                            @else
                                {{ $value }}
                            @endif
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Charts Section -->
            @if(count($charts) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                @foreach($charts as $key => $chart)
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $chart['title'] }}</h3>
                    <div class="h-64">
                        <canvas id="{{ $chart['id'] }}"></canvas>
                    </div>
                    <div class="mt-4 text-sm text-gray-500">
                        Data for period: {{ $startDate }} to {{ $endDate }}
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <!-- Detailed Data Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Detailed Summary Data</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="summaryTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metric</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($summaryData as $moduleName => $moduleData)
                                @foreach($moduleData as $metric => $value)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ ucfirst($moduleName) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ ucwords(str_replace('_', ' ', $metric)) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        @if(is_numeric($value))
                                            @if(str_contains($metric, 'total') || str_contains($metric, 'amount') || str_contains($metric, 'revenue') || str_contains($metric, 'expense') || str_contains($metric, 'spent'))
                                                ${{ number_format($value, 2) }}
                                            @elseif(str_contains($metric, 'rate') || str_contains($metric, 'ratio'))
                                                {{ number_format($value, 2) }}%
                                            @elseif(str_contains($metric, 'time'))
                                                {{ number_format($value, 0) }} min
                                            @else
                                                {{ $value }}
                                            @endif
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusClass = 'bg-green-100 text-green-800';
                                            if(is_numeric($value)) {
                                                if($value == 0) {
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                } elseif($value < 0) {
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                } elseif(str_contains($metric, 'pending') && $value > 10) {
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                }
                                            }
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                            {{ is_numeric($value) && $value > 0 ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Loading Overlay -->
            @if($loading)
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-8 shadow-xl">
                    <div class="flex items-center space-x-4">
                        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-lg font-medium text-gray-900">Generating Report...</span>
                    </div>
                    <p class="mt-4 text-gray-600">This may take a few moments</p>
                </div>
            </div>
            @endif
        </div>

        <!-- Toast Notifications -->
        @if(session()->has('success'))
        <div class="fixed bottom-4 right-4 z-50">
            <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
        @endif

        @if(session()->has('error'))
        <div class="fixed bottom-4 right-4 z-50">
            <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

    <script>
    // Global chart instances store
    let chartInstances = {};

    // Initialize charts when Livewire component is ready
    document.addEventListener('livewire:initialized', () => {
        // Initialize charts on component load
        setTimeout(() => {
            initializeCharts();
        }, 300);
        
        // Listen for charts-ready event from Livewire
        Livewire.on('charts-ready', (charts) => {
            // Destroy existing charts
            destroyAllCharts();
            
            // Create new charts after DOM update
            setTimeout(() => {
                initializeCharts();
            }, 300);
        });
        
        // Listen for export events
        Livewire.on('exportReport', (format) => {
            exportReport(format);
        });
    });

    // Function to destroy all charts
    function destroyAllCharts() {
        Object.values(chartInstances).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        chartInstances = {};
    }

    // Function to initialize all charts
    function initializeCharts() {
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }
        
        @if(count($charts) > 0)
            @foreach($charts as $key => $chart)
                const ctx{{ $loop->index }} = document.getElementById('{{ $chart['id'] }}');
                if (ctx{{ $loop->index }}) {
                    try {
                        // Destroy existing chart if it exists
                        if (chartInstances['{{ $chart['id'] }}']) {
                            chartInstances['{{ $chart['id'] }}'].destroy();
                        }
                        
                        // Create new chart
                        chartInstances['{{ $chart['id'] }}'] = new Chart(ctx{{ $loop->index }}, {
                            type: '{{ $chart['type'] }}',
                            data: {
                                labels: @json($chart['labels']),
                                datasets: @json($chart['datasets'])
                            },
                            options: getChartOptions('{{ $chart['type'] }}', '{{ $chart['title'] }}')
                        });
                    } catch (error) {
                        console.error('Error creating chart {{ $chart['id'] }}:', error);
                    }
                }
            @endforeach
        @endif
    }

    // Get appropriate chart options based on type
    function getChartOptions(type, title) {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: title
                }
            }
        };
        
        if (type === 'line' || type === 'bar') {
            return {
                ...baseOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (typeof value === 'number') {
                                    return '$' + value.toLocaleString();
                                }
                                return value;
                            }
                        }
                    }
                }
            };
        }
        
        return baseOptions;
    }

    // Export functions
    function exportReport(format) {
        switch(format) {
            case 'pdf':
                exportAsPDF();
                break;
            case 'excel':
                exportAsExcel();
                break;
            case 'csv':
                exportAsCSV();
                break;
        }
    }

    function exportAsPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        
        // Add title
        doc.setFontSize(20);
        doc.text('System Summary Report', 14, 22);
        doc.setFontSize(12);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 30);
        doc.text(`Period: {{ $startDate }} to {{ $endDate }}`, 14, 38);
        doc.text(`Module: {{ ucfirst($module) }}`, 14, 46);
        
        // Add summary stats
        let yPosition = 60;
        doc.setFontSize(16);
        doc.text('Summary Statistics', 14, yPosition);
        yPosition += 10;
        
        // Add charts as images if available
        let chartYPosition = yPosition;
        @foreach($charts as $key => $chart)
            const canvas{{ $loop->index }} = document.getElementById('{{ $chart['id'] }}');
            if (canvas{{ $loop->index }}) {
                try {
                    const chartImage = canvas{{ $loop->index }}.toDataURL('image/png');
                    if (chartYPosition > 200) {
                        doc.addPage();
                        chartYPosition = 20;
                    }
                    doc.setFontSize(12);
                    doc.text('{{ $chart['title'] }}', 14, chartYPosition);
                    chartYPosition += 10;
                    doc.addImage(chartImage, 'PNG', 14, chartYPosition, 120, 60);
                    chartYPosition += 70;
                } catch (error) {
                    console.error('Error exporting chart as image:', error);
                }
            }
        @endforeach
        
        // Add table data
        if (chartYPosition > 150) {
            doc.addPage();
            chartYPosition = 20;
        }
        
        doc.setFontSize(16);
        doc.text('Detailed Data', 14, chartYPosition);
        chartYPosition += 10;
        
        // Prepare table data
        const tableData = [];
        @foreach($summaryData as $moduleName => $moduleData)
            @foreach($moduleData as $metric => $value)
                tableData.push([
                    '{{ ucfirst($moduleName) }}',
                    '{{ ucwords(str_replace('_', ' ', $metric)) }}',
                    @if(is_numeric($value))
                        @if(str_contains($metric, 'total') || str_contains($metric, 'amount') || str_contains($metric, 'revenue') || str_contains($metric, 'expense') || str_contains($metric, 'spent'))
                            '${{ number_format($value, 2) }}'
                        @elseif(str_contains($metric, 'rate') || str_contains($metric, 'ratio'))
                            '{{ number_format($value, 2) }}%'
                        @elseif(str_contains($metric, 'time'))
                            '{{ number_format($value, 0) }} min'
                        @else
                            '{{ $value }}'
                        @endif
                    @else
                        '{{ $value }}'
                    @endif,
                    '{{ is_numeric($value) && $value > 0 ? "Active" : "Inactive" }}'
                ]);
            @endforeach
        @endforeach
        
        // Add table
        doc.autoTable({
            startY: chartYPosition,
            head: [['Module', 'Metric', 'Value', 'Status']],
            body: tableData,
            theme: 'striped',
            headStyles: { fillColor: [59, 130, 246] }
        });
        
        // Save PDF
        const filename = `summary_report_{{ $module }}_{{ $dateRange }}_${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.pdf`;
        doc.save(filename);
    }

    function exportAsExcel() {
        // Prepare workbook
        const wb = XLSX.utils.book_new();
        
        // Summary sheet
        const summaryData = [
            ['System Summary Report'],
            [`Generated: ${new Date().toLocaleString()}`],
            [`Period: {{ $startDate }} to {{ $endDate }}`],
            [`Module: {{ ucfirst($module) }}`],
            [],
            ['Summary Statistics'],
            ['Module', 'Metric', 'Value', 'Status']
        ];
        
        @foreach($summaryData as $moduleName => $moduleData)
            @foreach($moduleData as $metric => $value)
                summaryData.push([
                    '{{ ucfirst($moduleName) }}',
                    '{{ ucwords(str_replace('_', ' ', $metric)) }}',
                    @if(is_numeric($value))
                        @if(str_contains($metric, 'total') || str_contains($metric, 'amount') || str_contains($metric, 'revenue') || str_contains($metric, 'expense') || str_contains($metric, 'spent'))
                            {{ $value }}
                        @elseif(str_contains($metric, 'rate') || str_contains($metric, 'ratio'))
                            {{ $value / 100 }}
                        @else
                            {{ $value }}
                        @endif
                    @else
                        '{{ $value }}'
                    @endif,
                    '{{ is_numeric($value) && $value > 0 ? "Active" : "Inactive" }}'
                ]);
            @endforeach
        @endforeach
        
        const ws = XLSX.utils.aoa_to_sheet(summaryData);
        XLSX.utils.book_append_sheet(wb, ws, 'Summary');
        
        // Chart data sheets
        @foreach($charts as $key => $chart)
            const {{ $key }}Data = [
                ['{{ $chart['title'] }}'],
                ['Period', 'Value']
            ];
            
            @foreach($chart['labels'] as $index => $label)
                {{ $key }}Data.push([
                    '{{ $label }}',
                    {{ $chart['datasets'][0]['data'][$index] }}
                ]);
            @endforeach
            
            const {{ $key }}Ws = XLSX.utils.aoa_to_sheet({{ $key }}Data);
            XLSX.utils.book_append_sheet(wb, {{ $key }}Ws, '{{ $chart['title'] }}'.substring(0, 31));
        @endforeach
        
        // Save Excel file
        const filename = `summary_report_{{ $module }}_{{ $dateRange }}_${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.xlsx`;
        XLSX.writeFile(wb, filename);
    }

    function exportAsCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add header
        csvContent += "System Summary Report\n";
        csvContent += `Generated: ${new Date().toLocaleString()}\n`;
        csvContent += `Period: {{ $startDate }} to {{ $endDate }}\n`;
        csvContent += `Module: {{ ucfirst($module) }}\n\n`;
        
        // Add data
        csvContent += "Module,Metric,Value,Status\n";
        
        @foreach($summaryData as $moduleName => $moduleData)
            @foreach($moduleData as $metric => $value)
                csvContent += `"{{ ucfirst($moduleName) }}","{{ ucwords(str_replace('_', ' ', $metric)) }}",` +
                    @if(is_numeric($value))
                        @if(str_contains($metric, 'total') || str_contains($metric, 'amount') || str_contains($metric, 'revenue') || str_contains($metric, 'expense') || str_contains($metric, 'spent'))
                            `"${{ number_format($value, 2) }}",`
                        @elseif(str_contains($metric, 'rate') || str_contains($metric, 'ratio'))
                            `"{{ number_format($value, 2) }}%",`
                        @elseif(str_contains($metric, 'time'))
                            `"{{ number_format($value, 0) }} min",`
                        @else
                            `"{{ $value }}",`
                        @endif
                    @else
                        `"{{ $value }}",`
                    @endif +
                    `"{{ is_numeric($value) && $value > 0 ? 'Active' : 'Inactive' }}"` + "\n";
            @endforeach
        @endforeach
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        const filename = `summary_report_{{ $module }}_{{ $dateRange }}_${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.csv`;
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Dropdown functions for export button
    function exportAsPDF() {
        exportReport('pdf');
    }

    function exportAsExcel() {
        exportReport('excel');
    }

    function exportAsCSV() {
        exportReport('csv');
    }
    </script>