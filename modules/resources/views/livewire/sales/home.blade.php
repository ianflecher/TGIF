<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $ticketStats = [];
    public array $recentTickets = [];
    public array $topAgents = [];
    public array $priorityStats = [];
    public array $categoryStats = [];
    public array $slaCompliance = [];
    
    public function mount()
    {
        $this->loadCustomerServiceData();
    }
    
    public function loadCustomerServiceData()
    {
        // Ticket Statistics
        $this->ticketStats = [
            'total' => DB::table('tickets')->count(),
            'open' => DB::table('tickets')->where('status', 'open')->count(),
            'in_progress' => DB::table('tickets')->where('status', 'in_progress')->count(),
            'resolved' => DB::table('tickets')->where('status', 'resolved')->count(),
            'closed' => DB::table('tickets')->where('status', 'closed')->count(),
            'critical' => DB::table('tickets')->where('priority', 'critical')->count(),
            'avg_response_time' => $this->calculateAvgResponseTime(),
        ];
        
        // Recent Tickets
        $this->recentTickets = DB::table('tickets')
            ->select(
                'tickets.*',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                'agent_users.full_name as agent_name'
            )
            ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->leftJoin('employees as agents', 'tickets.assigned_agent', '=', 'agents.employee_id')
            ->leftJoin('users as agent_users', 'agents.user_id', '=', 'agent_users.user_id')
            ->orderBy('tickets.created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Top Agents
        $this->topAgents = DB::table('tickets')
            ->select(
                'agent_users.full_name',
                'agents.employee_id',
                DB::raw('COUNT(tickets.ticket_id) as ticket_count'),
                DB::raw('SUM(CASE WHEN tickets.status = "resolved" OR tickets.status = "closed" THEN 1 ELSE 0 END) as resolved_count'),
                DB::raw('AVG(CASE WHEN tickets.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, tickets.created_at, tickets.resolved_at) ELSE NULL END) as avg_resolution_time')
            )
            ->leftJoin('employees as agents', 'tickets.assigned_agent', '=', 'agents.employee_id')
            ->leftJoin('users as agent_users', 'agents.user_id', '=', 'agent_users.user_id')
            ->whereNotNull('tickets.assigned_agent')
            ->groupBy('agents.employee_id', 'agent_users.full_name')
            ->orderBy('ticket_count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // Priority Statistics
        $this->priorityStats = DB::table('tickets')
            ->select(
                'priority',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resolved_at, NOW()))) as avg_time')
            )
            ->groupBy('priority')
            ->get()
            ->toArray();
        
        // Category Statistics
        $this->categoryStats = DB::table('tickets')
            ->select(
                'category',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "resolved" OR status = "closed" THEN 1 ELSE 0 END) as resolved')
            )
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
        
        // SLA Compliance
        $this->slaCompliance = [
            'within_sla' => DB::table('tickets')
                ->where('first_response_at', '<=', DB::raw('DATE_ADD(created_at, INTERVAL 24 HOUR)'))
                ->count(),
            'breached_sla' => DB::table('tickets')
                ->where('first_response_at', '>', DB::raw('DATE_ADD(created_at, INTERVAL 24 HOUR)'))
                ->orWhereNull('first_response_at')
                ->count(),
            'total_with_sla' => DB::table('tickets')->whereNotNull('first_response_at')->count(),
        ];
    }
    
    private function calculateAvgResponseTime()
    {
        $result = DB::table('tickets')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(first_response_at, NOW()))) as avg_response_hours'))
            ->whereNotNull('first_response_at')
            ->first();
        
        return $result->avg_response_hours ?? 0;
    }
    
    public function formatNumber($number)
    {
        return number_format($number);
    }
    
    public function formatTime($hours)
    {
        if ($hours >= 24) {
            return floor($hours / 24) . ' days';
        }
        return round($hours, 1) . ' hours';
    }
    
    public function getPriorityColor($priority)
    {
        return match(strtolower($priority)) {
            'critical' => 'bg-red-100 text-red-800',
            'high' => 'bg-orange-100 text-orange-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getStatusColor($status)
    {
        return match(strtolower($status)) {
            'open' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-100 text-gray-800',
            'pending' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getTimeAgo($datetime)
    {
        if (!$datetime) return 'N/A';
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M d, Y', $time);
    }
    
    public function calculateResolutionRate($resolved, $total)
    {
        if ($total > 0) {
            return round(($resolved / $total) * 100, 1);
        }
        return 0;
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Customer Service Dashboard</h1>
        <p class="text-gray-600 mt-2">Monitor support tickets, agent performance, and customer satisfaction</p>
    </div>

    <!-- Ticket Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Tickets -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Tickets</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ticketStats['total'] ?? 0) }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $ticketStats['critical'] ?? 0 }} critical</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Urgent issues</span>
                </span>
            </div>
        </div>

        <!-- Open Tickets -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Open Tickets</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ticketStats['open'] ?? 0) }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $ticketStats['in_progress'] ?? 0 }} in progress</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Being worked on</span>
                </span>
            </div>
        </div>

        <!-- Resolved Tickets -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Resolved Tickets</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatNumber($ticketStats['resolved'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $ticketStats['closed'] ?? 0 }} closed</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Completed tickets</span>
                </span>
            </div>
        </div>

        <!-- Avg Response Time -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Avg Response Time</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatTime($ticketStats['avg_response_time'] ?? 0) }}</h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-orange-600 font-medium">SLA Compliance</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">
                        @if(($slaCompliance['total_with_sla'] ?? 0) > 0)
                            {{ round((($slaCompliance['within_sla'] ?? 0) / ($slaCompliance['total_with_sla'] ?? 1)) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Tickets -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Tickets</h2>
                    <p class="text-sm text-gray-600">Latest customer support requests</p>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentTickets as $ticket)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">{{ $ticket->subject ?? 'No Subject' }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $ticket->first_name ?? 'Customer' }} {{ $ticket->last_name ?? '' }}
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getPriorityColor($ticket->priority ?? 'medium') }}">
                                        {{ ucfirst($ticket->priority ?? 'medium') }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getStatusColor($ticket->status ?? '') }}">
                                        {{ ucfirst($ticket->status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3 truncate">
                                {{ Str::limit($ticket->issue_description ?? 'No description', 80) }}
                            </p>
                            
                            <div class="flex justify-between items-center">
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    {{ $ticket->agent_name ?? 'Unassigned' }}
                                </div>
                                
                                <div class="text-xs text-gray-500">
                                    <span>#{{ $ticket->ticket_number ?? 'N/A' }}</span>
                                    <span class="mx-2">•</span>
                                    <span>{{ $this->getTimeAgo($ticket->created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <p class="mt-2">No tickets found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Top Agents -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Top Agents</h2>
                    <p class="text-sm text-gray-600">Best performing support agents</p>
                </div>

            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($topAgents as $agent)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 font-medium">
                                        {{ substr($agent->full_name ?? 'A', 0, 1) }}
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <h4 class="font-medium text-gray-800">{{ $agent->full_name ?? 'Unknown Agent' }}</h4>
                                    <p class="text-sm text-gray-600">{{ $agent->ticket_count ?? 0 }} tickets handled</p>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="text-lg font-bold text-green-600">
                                    {{ $this->calculateResolutionRate($agent->resolved_count ?? 0, $agent->ticket_count ?? 1) }}%
                                </div>
                                <div class="text-xs text-gray-500">Resolution Rate</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Avg: {{ $this->formatTime($agent->avg_resolution_time ?? 0) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p class="mt-2">No agent data available</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Priority Distribution -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Ticket Priority Distribution</h2>
                <p class="text-sm text-gray-600">Breakdown of tickets by priority level</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @foreach($priorityStats as $priority)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getPriorityColor($priority->priority) }}">
                                    {{ ucfirst($priority->priority ?? 'unknown') }}
                                </span>
                                <span class="ml-2 text-sm text-gray-600">{{ $priority->count ?? 0 }} tickets</span>
                            </div>
                            <div class="text-sm text-gray-500">
                                Avg: {{ $this->formatTime($priority->avg_time ?? 0) }}
                            </div>
                        </div>
                    @endforeach
                    @if(count($priorityStats) === 0)
                        <div class="text-center py-6 text-gray-500">
                            <p>No priority data available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Category Distribution -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Ticket Categories</h2>
                <p class="text-sm text-gray-600">Most common support categories</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @foreach($categoryStats as $category)
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700">{{ ucfirst($category->category ?? 'Unknown') }}</span>
                                <span class="text-sm text-gray-500">{{ $category->count ?? 0 }} tickets</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                @php
                                    $totalTickets = $ticketStats['total'] ?? 1;
                                    $percentage = min(($category->count / $totalTickets) * 100, 100);
                                @endphp
                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ $category->resolved ?? 0 }} resolved</span>
                                <span>{{ round($percentage, 1) }}% of total</span>
                            </div>
                        </div>
                    @endforeach
                    @if(count($categoryStats) === 0)
                        <div class="text-center py-6 text-gray-500">
                            <p>No category data available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('sales.ticket') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">View Tickets</span>
            </a>
            
        </div>
    </div>
</div>