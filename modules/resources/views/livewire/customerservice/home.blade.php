<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public $stats = [];
    public $tickets = [];
    public $agents = [];
    public $selectedTicket = null;
    public $showTicketModal = false;
    public $assignAgentId = '';
    public $statusFilter = 'all';
    
    public function mount()
    {
        $this->loadStats();
        $this->loadTickets();
        $this->loadAgents();
    }
    
    public function loadStats()
    {
        $this->stats = [
            'total_tickets' => DB::table('tickets')->count(),
            'open_tickets' => DB::table('tickets')->where('status', 'open')->count(),
            'in_progress_tickets' => DB::table('tickets')->where('status', 'in_progress')->count(),
            'resolved_tickets' => DB::table('tickets')->where('status', 'resolved')->count(),
            'high_priority' => DB::table('tickets')->whereIn('priority', ['high', 'critical'])->count(),
        ];
    }
    
    public function loadTickets()
    {
        $query = DB::table('tickets')
            ->select(
                'tickets.ticket_id',
                'tickets.ticket_number',
                'tickets.subject',
                'tickets.priority',
                'tickets.status',
                'tickets.category',
                'tickets.created_at',
                'tickets.updated_at',
                'customers.first_name',
                'customers.last_name',
                'users.full_name as agent_name'
            )
            ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->leftJoin('employees', 'tickets.assigned_agent', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->orderBy('tickets.created_at', 'desc');
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('tickets.status', $this->statusFilter);
        }
        
        $this->tickets = $query->limit(20)->get();
    }
    
    public function loadAgents()
    {
        $agents = DB::table('employees')
            ->select(
                'employees.employee_id',
                'users.full_name',
                'departments.department_name',
                DB::raw('(SELECT COUNT(*) FROM tickets WHERE assigned_agent = employees.employee_id AND status IN ("open", "in_progress")) as active_tickets')
            )
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('employees.status', 'active')
            ->orderBy('users.full_name', 'asc')
            ->get();
        
        $this->agents = $agents->map(function($agent) {
            return [
                'id' => $agent->employee_id,
                'name' => $agent->full_name,
                'department' => $agent->department_name ?? 'N/A',
                'active_tickets' => $agent->active_tickets ?? 0,
            ];
        });
    }
    
    public function showTicketDetails($ticketId)
    {
        $this->selectedTicket = DB::table('tickets')
            ->select(
                'tickets.*',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                'customers.phone',
                'users.full_name as agent_name',
                'agent_users.full_name as assigned_agent_name'
            )
            ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->leftJoin('employees', 'tickets.assigned_agent', '=', 'employees.employee_id')
            ->leftJoin('users as agent_users', 'employees.user_id', '=', 'agent_users.user_id')
            ->where('tickets.ticket_id', $ticketId)
            ->first();
        
        if ($this->selectedTicket) {
            // Load ticket notes
            $this->selectedTicket->notes = DB::table('ticket_notes')
                ->select(
                    'ticket_notes.*',
                    'users.full_name as author_name',
                    DB::raw('CASE WHEN employees.employee_id IS NOT NULL THEN "agent" ELSE "customer" END as sender_type')
                )
                ->leftJoin('users', 'ticket_notes.created_by', '=', 'users.user_id')
                ->leftJoin('employees', 'users.user_id', '=', 'employees.user_id')
                ->where('ticket_id', $ticketId)
                ->orderBy('created_at', 'asc')
                ->get();
        }
        
        $this->assignAgentId = $this->selectedTicket->assigned_agent ?? '';
        $this->showTicketModal = true;
    }
    
    public function assignAgent()
    {
        if ($this->selectedTicket && $this->assignAgentId) {
            DB::table('tickets')
                ->where('ticket_id', $this->selectedTicket->ticket_id)
                ->update([
                    'assigned_agent' => $this->assignAgentId,
                    'updated_at' => now()
                ]);
            
            session()->flash('message', 'Agent assigned successfully!');
            $this->loadTickets();
            $this->showTicketModal = false;
        }
    }
    
    public function updatedStatusFilter()
    {
        $this->loadTickets();
    }
}
?>
<div>
    <!-- Header -->
    <div class="py-6 bg-gradient-to-r from-green-50 to-emerald-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Support Management Dashboard</h1>
                <p class="mt-2 text-sm text-gray-600">Monitor and assign customer support tickets</p>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total Tickets -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-green-100">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 p-3 rounded-lg">
                            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Tickets</dt>
                                <dd class="text-2xl font-bold text-green-600">{{ $stats['total_tickets'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open Tickets -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-yellow-100">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 p-3 rounded-lg">
                            <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Open</dt>
                                <dd class="text-2xl font-bold text-yellow-600">{{ $stats['open_tickets'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- In Progress -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-blue-100">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 p-3 rounded-lg">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">In Progress</dt>
                                <dd class="text-2xl font-bold text-blue-600">{{ $stats['in_progress_tickets'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resolved -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-green-200">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-50 p-3 rounded-lg">
                            <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Resolved</dt>
                                <dd class="text-2xl font-bold text-green-500">{{ $stats['resolved_tickets'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High Priority -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-red-100">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 p-3 rounded-lg">
                            <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">High Priority</dt>
                                <dd class="text-2xl font-bold text-red-600">{{ $stats['high_priority'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl rounded-lg overflow-hidden border border-green-100 mb-8">
            <!-- Header with Filter -->
            <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Recent Tickets</h3>
                        <p class="text-sm text-gray-600">Monitor and assign support tickets</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Filter by status:</span>
                        <select wire:model.live="statusFilter" 
                                class="border border-green-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="all">All Status</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                @if(count($tickets) > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-green-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Ticket #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Assigned To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Last Update</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-green-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tickets as $ticket)
                        <tr class="hover:bg-green-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-green-600">{{ $ticket->ticket_number }}</div>
                                <div class="text-xs text-gray-500">{{ Carbon::parse($ticket->created_at)->format('M d') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $ticket->first_name }} {{ $ticket->last_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 truncate max-w-xs">{{ $ticket->subject }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $categoryLabels = [
                                        'general' => 'General',
                                        'technical' => 'Technical',
                                        'billing' => 'Billing',
                                        'sales' => 'Sales',
                                        'complaint' => 'Complaint'
                                    ];
                                @endphp
                                <span class="text-sm text-gray-700">
                                    {{ $categoryLabels[$ticket->category] ?? $ticket->category }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'high' => 'bg-orange-100 text-orange-800',
                                        'critical' => 'bg-red-100 text-red-800'
                                    ];
                                @endphp
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $priorityColors[$ticket->priority] ?? 'bg-gray-100' }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusColors = [
                                        'open' => 'bg-yellow-100 text-yellow-800',
                                        'in_progress' => 'bg-blue-100 text-blue-800',
                                        'resolved' => 'bg-green-100 text-green-800',
                                        'closed' => 'bg-gray-100 text-gray-800'
                                    ];
                                @endphp
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$ticket->status] ?? 'bg-gray-100' }}">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $ticket->agent_name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ Carbon::parse($ticket->updated_at)->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                @if($ticket->status === 'resolved' || $ticket->status === 'closed')
                                    <button wire:click="showTicketDetails({{ $ticket->ticket_id }})" 
                                            class="px-4 py-1.5 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-150">
                                        View Only
                                    </button>
                                @else
                                    <button wire:click="showTicketDetails({{ $ticket->ticket_id }})" 
                                            class="px-4 py-1.5 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-150">
                                        View & Assign
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No tickets found</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        @if($statusFilter !== 'all')
                            No tickets with status "{{ ucfirst(str_replace('_', ' ', $statusFilter)) }}"
                        @else
                            All tickets have been processed
                        @endif
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Ticket Details Modal -->
    @if($showTicketModal && $selectedTicket)
    <div class="fixed inset-0 overflow-y-auto z-50">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showTicketModal', false)"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-green-200">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Ticket #{{ $selectedTicket->ticket_number }}</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Created: {{ Carbon::parse($selectedTicket->created_at)->format('F d, Y h:i A') }}
                                @if($selectedTicket->status === 'resolved' || $selectedTicket->status === 'closed')
                                    • Resolved: {{ Carbon::parse($selectedTicket->resolved_at ?? $selectedTicket->updated_at)->format('F d, Y') }}
                                @endif
                            </p>
                        </div>
                        <button wire:click="$set('showTicketModal', false)" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                    <div class="space-y-6">
                        <!-- Ticket Information Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column: Ticket Details -->
                            <div>
                                <h4 class="font-medium text-green-700 mb-3">Ticket Details</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                                    <div>
                                        <span class="text-sm text-gray-500 block">Subject:</span>
                                        <p class="font-medium text-gray-900">{{ $selectedTicket->subject }}</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <span class="text-sm text-gray-500 block">Status:</span>
                                            @php
                                                $statusColors = [
                                                    'open' => 'bg-yellow-100 text-yellow-800',
                                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                                    'resolved' => 'bg-green-100 text-green-800',
                                                    'closed' => 'bg-gray-100 text-gray-800'
                                                ];
                                            @endphp
                                            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$selectedTicket->status] ?? 'bg-gray-100' }}">
                                                {{ ucfirst(str_replace('_', ' ', $selectedTicket->status)) }}
                                            </span>
                                        </div>
                                        
                                        <div>
                                            <span class="text-sm text-gray-500 block">Priority:</span>
                                            @php
                                                $priorityColors = [
                                                    'low' => 'bg-gray-100 text-gray-800',
                                                    'medium' => 'bg-yellow-100 text-yellow-800',
                                                    'high' => 'bg-orange-100 text-orange-800',
                                                    'critical' => 'bg-red-100 text-red-800'
                                                ];
                                            @endphp
                                            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $priorityColors[$selectedTicket->priority] ?? 'bg-gray-100' }}">
                                                {{ ucfirst($selectedTicket->priority) }}
                                            </span>
                                        </div>
                                        
                                        <div>
                                            <span class="text-sm text-gray-500 block">Category:</span>
                                            <p class="font-medium text-gray-900">
                                                @php
                                                    $categoryLabels = [
                                                        'general' => 'General Inquiry',
                                                        'technical' => 'Technical Support',
                                                        'billing' => 'Billing & Payments',
                                                        'sales' => 'Sales Inquiry',
                                                        'complaint' => 'Complaint'
                                                    ];
                                                @endphp
                                                {{ $categoryLabels[$selectedTicket->category] ?? $selectedTicket->category }}
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <span class="text-sm text-gray-500 block">Last Updated:</span>
                                            <p class="font-medium text-gray-900">{{ Carbon::parse($selectedTicket->updated_at)->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column: Customer Information -->
                            <div>
                                <h4 class="font-medium text-green-700 mb-3">Customer Information</h4>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 space-y-3">
                                    <div>
                                        <span class="text-sm text-gray-500 block">Name:</span>
                                        <p class="font-medium text-gray-900">{{ $selectedTicket->first_name }} {{ $selectedTicket->last_name }}</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <span class="text-sm text-gray-500 block">Email:</span>
                                            <p class="font-medium text-gray-900">{{ $selectedTicket->email }}</p>
                                        </div>
                                        
                                        <div>
                                            <span class="text-sm text-gray-500 block">Phone:</span>
                                            <p class="font-medium text-gray-900">{{ $selectedTicket->phone ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <span class="text-sm text-gray-500 block">Currently Assigned To:</span>
                                        <p class="font-medium text-gray-900">
                                            {{ $selectedTicket->assigned_agent_name ?? 'Unassigned' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Issue Description -->
                        <div>
                            <h4 class="font-medium text-green-700 mb-3">Issue Description</h4>
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <p class="text-gray-700 whitespace-pre-line">{{ $selectedTicket->issue_description }}</p>
                            </div>
                        </div>

                        <!-- Conversation History (if any) -->
                        @if(isset($selectedTicket->notes) && count($selectedTicket->notes) > 0)
                        <div>
                            <h4 class="font-medium text-green-700 mb-3">Conversation History</h4>
                            <div class="space-y-3 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-4">
                                @foreach($selectedTicket->notes as $note)
                                <div class="{{ $note->sender_type === 'agent' ? 'bg-blue-50' : 'bg-gray-50' }} p-3 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $note->author_name ?? ($note->sender_type === 'agent' ? 'Support Agent' : 'Customer') }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ Carbon::parse($note->created_at)->format('M d, Y h:i A') }}
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $note->note_text }}</p>
                                    @if($note->internal)
                                    <div class="mt-2 flex items-center">
                                        <svg class="w-4 h-4 text-gray-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <span class="text-xs text-gray-500">Internal Note</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Assign Agent Section (only for non-resolved/closed tickets) -->
                        @if($selectedTicket->status !== 'resolved' && $selectedTicket->status !== 'closed')
                        <div>
                            <h4 class="font-medium text-green-700 mb-3">Assign to Agent</h4>
                            <div class="flex items-center space-x-3">
                                <select wire:model="assignAgentId" 
                                        class="flex-1 border border-green-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select an agent...</option>
                                    @foreach($agents as $agent)
                                    <option value="{{ $agent['id'] }}">
                                        {{ $agent['name'] }} ({{ $agent['department'] }}) - {{ $agent['active_tickets'] }} active tickets
                                    </option>
                                    @endforeach
                                </select>
                                <button wire:click="assignAgent" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-150">
                                    Assign
                                </button>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">
                                Note: Agents can only mark tickets as resolved from their dashboard
                            </p>
                        </div>
                        @else
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <h4 class="font-medium text-green-700">Ticket {{ ucfirst($selectedTicket->status) }}</h4>
                                    <p class="text-sm text-gray-600">
                                        This ticket has been {{ $selectedTicket->status }}. No further actions can be taken.
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Flash Message -->
    @if(session()->has('message'))
    <div class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
        <div class="max-w-sm w-full bg-green-100 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden border border-green-200">
            <div class="p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>