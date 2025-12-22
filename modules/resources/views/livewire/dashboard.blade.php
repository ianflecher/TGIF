<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public $stats = [];
    public $recentProjects = [];
    public $recentOrders = [];
    public $lowStockItems = [];
    public $activeTickets = [];
    public $topEmployees = [];
    
    public function mount()
    {
        $this->loadDashboardData();
    }
    
    public function loadDashboardData()
    {
        // Load all statistics
        $this->stats = [
            'total_projects' => DB::table('projects')->count(),
            'active_projects' => DB::table('projects')->where('status', 'in_progress')->count(),
            'total_sales' => DB::table('sales_orders')->where('status', 'delivered')->sum('grand_total') ?? 0,
            'pending_orders' => DB::table('sales_orders')->whereIn('status', ['draft', 'confirmed', 'processing'])->count(),
            'total_inventory' => DB::table('inventories')->sum('quantity'),
            'low_stock_items' => DB::table('inventories')
                ->whereRaw('quantity < min_quantity')
                ->orWhereNull('min_quantity')
                ->count(),
            'open_tickets' => DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->count(),
            'total_employees' => DB::table('employees')->where('status', 'active')->count(),
        ];
        
        // Recent Projects with manager info
        $this->recentProjects = DB::table('projects')
            ->select(
                'projects.*',
                'users.full_name as manager_name',
                'employees.employee_id',
                DB::raw('(SELECT AVG(progress) FROM project_tasks WHERE project_tasks.project_id = projects.project_id) as avg_progress')
            )
            ->leftJoin('employees', 'projects.project_manager_id', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->orderBy('projects.created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
            
        // Recent Orders with customer info
        $this->recentOrders = DB::table('sales_orders')
            ->select(
                'sales_orders.*',
                'customers.first_name',
                'customers.last_name',
                'customers.email'
            )
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.customer_id')
            ->orderBy('sales_orders.order_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
            
        // Low Stock Items
        $this->lowStockItems = DB::table('inventories')
            ->whereRaw('quantity < min_quantity OR quantity < 10')
            ->orderBy('quantity', 'asc')
            ->limit(5)
            ->get()
            ->toArray();
            
        // Active Tickets with customer and agent info
        $this->activeTickets = DB::table('tickets')
            ->select(
                'tickets.*',
                'customers.first_name as customer_first_name',
                'customers.last_name as customer_last_name',
                'agent_users.full_name as agent_name'
            )
            ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->leftJoin('employees as agents', 'tickets.assigned_agent', '=', 'agents.employee_id')
            ->leftJoin('users as agent_users', 'agents.user_id', '=', 'agent_users.user_id')
            ->whereIn('tickets.status', ['open', 'in_progress'])
            ->orderByRaw("FIELD(tickets.priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('tickets.created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
            
        // Top Employees with department info
        $this->topEmployees = DB::table('employees')
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'departments.department_name'
            )
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('employees.status', 'active')
            ->orderBy('employees.salary', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }
    
    // Helper methods for the component
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getPriorityColor($priority)
    {
        return match($priority) {
            'critical' => 'bg-red-100 text-red-800',
            'high' => 'bg-orange-100 text-orange-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getStatusColor($status)
    {
        return match($status) {
            'in_progress', 'processing' => 'bg-green-100 text-green-800',
            'completed', 'delivered' => 'bg-blue-100 text-blue-800',
            'cancelled', 'closed' => 'bg-red-100 text-red-800',
            'draft', 'planning' => 'bg-gray-100 text-gray-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }
    
    public function calculateProgress($project)
    {
        return $project->avg_progress ?? 0;
    }
    
    public function getTimeAgo($datetime)
    {
        if (!$datetime) return 'N/A';
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . ' minutes ago';
        if ($diff < 86400) return floor($diff/3600) . ' hours ago';
        if ($diff < 604800) return floor($diff/86400) . ' days ago';
        return date('M d, Y', $time);
    }
}
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
        <p class="text-gray-600 mt-2">Welcome back! Here's what's happening with your business today.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Projects Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Projects</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $stats['total_projects'] ?? 0 }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $stats['active_projects'] ?? 0 }} active</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ ($stats['total_projects'] ?? 0) - ($stats['active_projects'] ?? 0) }} others</span>
                </span>
            </div>
        </div>

        <!-- Sales Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Sales</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($stats['total_sales'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $stats['pending_orders'] ?? 0 }} pending</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Orders processing</span>
                </span>
            </div>
        </div>

        <!-- Inventory Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Inventory</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_inventory'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-red-600 font-medium">{{ $stats['low_stock_items'] ?? 0 }} low stock</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Items need attention</span>
                </span>
            </div>
        </div>

        <!-- Support Card -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Support Tickets</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $stats['open_tickets'] ?? 0 }}</h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-orange-600 font-medium">Active tickets</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Need immediate action</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Projects -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Recent Projects</h2>
                <p class="text-sm text-gray-600">Latest project activities and progress</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentProjects as $project)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-800">{{ $project->project_name ?? 'Untitled Project' }}</h4>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getStatusColor($project->status ?? '') }}">
                                        {{ ucfirst(str_replace('_', ' ', $project->status ?? 'unknown')) }}
                                    </span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 mb-3">
                                    <span class="mr-4">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $this->formatDate($project->start_date) }} - {{ $this->formatDate($project->end_date) }}
                                    </span>
                                    <span>
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        {{ $project->manager_name ?? 'No Manager' }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: {{ $this->calculateProgress($project) }}%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Progress</span>
                                    <span>{{ number_format($this->calculateProgress($project), 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="mt-2">No projects found</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">
                    <a href="{{ route('projects.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        View all projects
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Recent Orders</h2>
                <p class="text-sm text-gray-600">Latest customer orders and transactions</p>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3">Order #</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Amount</th>
                                <th class="pb-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3">
                                        <a href="#" class="text-green-600 hover:text-green-800 font-medium">
                                            #{{ $order->order_number ?? 'N/A' }}
                                        </a>
                                    </td>
                                    <td class="py-3 text-sm">
                                        {{ $order->first_name ?? 'Unknown' }} {{ $order->last_name ?? '' }}
                                    </td>
                                    <td class="py-3 text-sm text-gray-600">
                                        {{ $this->formatDate($order->order_date) }}
                                    </td>
                                    <td class="py-3 font-medium">
                                        {{ $this->formatCurrency($order->grand_total ?? 0) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getStatusColor($order->status ?? '') }}">
                                            {{ ucfirst($order->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-500">
                                        No orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <a href="{{ route('sales.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        View all orders
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Low Stock Items -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Low Stock Items</h2>
                <p class="text-sm text-gray-600">Items that need restocking</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($lowStockItems as $item)
                        <div class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg transition-colors">
                            <div>
                                <h4 class="font-medium text-gray-800">{{ $item->product_name ?? 'Unknown Item' }}</h4>
                                <p class="text-sm text-gray-600">{{ $item->sku ?? 'No SKU' }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-red-600 font-bold">{{ $item->quantity ?? 0 }}</span>
                                <p class="text-xs text-gray-500">in stock</p>
                                <p class="text-xs text-gray-500">Min: {{ $item->min_quantity ?? 10 }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>All items are sufficiently stocked</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">
                    <a href="{{ route('inventory.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        Manage inventory
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Active Tickets -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Active Tickets</h2>
                <p class="text-sm text-gray-600">Support tickets needing attention</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($activeTickets as $ticket)
                        <div class="p-3 hover:bg-gray-50 rounded-lg border-l-4 
                            {{ ($ticket->priority ?? 'medium') == 'critical' ? 'border-red-500' : 
                               (($ticket->priority ?? 'medium') == 'high' ? 'border-orange-500' : 
                               (($ticket->priority ?? 'medium') == 'medium' ? 'border-yellow-500' : 
                               'border-green-500')) }}">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-medium text-gray-800">{{ $ticket->subject ?? 'No Subject' }}</h4>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getPriorityColor($ticket->priority ?? 'medium') }}">
                                    {{ ucfirst($ticket->priority ?? 'medium') }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 truncate">
                                {{ Str::limit($ticket->issue_description ?? 'No description', 80) }}
                            </p>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>#{{ $ticket->ticket_number ?? 'N/A' }}</span>
                                <span>{{ $this->getTimeAgo($ticket->created_at) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No active tickets</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">
                    <a href="{{ route('customerservice.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        View all tickets
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Top Employees -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Top Employees</h2>
                <p class="text-sm text-gray-600">High-performing team members</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topEmployees as $employee)
                        <div class="flex items-center p-3 hover:bg-gray-50 rounded-lg transition-colors">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-green-600 font-medium">
                                    {{ substr($employee->full_name ?? '?', 0, 1) }}
                                </span>
                            </div>
                            <div class="ml-4 flex-1">
                                <h4 class="font-medium text-gray-800">{{ $employee->full_name ?? 'Unknown Employee' }}</h4>
                                <p class="text-sm text-gray-600">{{ $employee->job_title ?? 'No Title' }}</p>
                                <p class="text-xs text-gray-500">{{ $employee->department_name ?? 'No Department' }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-green-600 font-medium">{{ $this->formatCurrency($employee->salary ?? 0) }}</span>
                                <p class="text-xs text-gray-500">Salary</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-gray-500">
                            <p>No employee data</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">
                    <a href="{{ route('hr.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                        Manage employees
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('projects.home') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="font-medium text-gray-800">New Project</span>
            </a>
            
            <a href="{{ route('sales.home') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">New Order</span>
            </a>
            
            <a href="{{ route('inventory.home') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <span class="font-medium text-gray-800">Add Inventory</span>
            </a>
            
            <a href="{{ route('customerservice.home') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <span class="font-medium text-gray-800">New Ticket</span>
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="mt-8 bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-md p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold mb-2">System Status</h2>
                <p class="opacity-90">All systems operational</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ $stats['total_employees'] ?? 0 }}</div>
                    <div class="text-sm opacity-90">Employees</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ count($activeTickets) }}</div>
                    <div class="text-sm opacity-90">Active Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ $stats['pending_orders'] ?? 0 }}</div>
                    <div class="text-sm opacity-90">Pending Orders</div>
                </div>
            </div>
        </div>
    </div>
</div>