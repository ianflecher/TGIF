<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] 
#[Title('Support Dashboard - Manage Tickets')]
class extends Component
{
    // Agent/Employee info
    public $employee = null;
    public $agentId = null;
    
    // Ticket lists
    public $assignedTickets = [];
    public $selectedTicket = null;
    
    // Filters
    public $statusFilter = 'all';
    public $priorityFilter = 'all';
    public $searchQuery = '';
    
    // Ticket actions
    public $newNote = '';
    public $internalNote = false;
    public $updatingTicket = false;
    public $showResolveModal = false;
    public $showCloseModal = false;
    public $resolutionNote = '';
    public $closeReason = '';
    
    // Stats
    public $stats = [
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'total' => 0,
        'avg_response_time' => 'N/A'
    ];
    
    // Statuses and priorities
    public $statuses = [];
    public $priorities = [];
    public $categories = [];
    
    public function mount()
    {
        $this->loadEmployeeInfo();
        $this->loadStatuses();
        $this->loadPriorities();
        $this->loadCategories();
        $this->loadAssignedTickets();
        $this->loadStats();
    }
    
    protected function loadEmployeeInfo()
    {
        $userId = Auth::id();
        
        if ($userId) {
            $this->employee = DB::table('employees')
                ->join('users', 'employees.user_id', '=', 'users.user_id')
                ->where('employees.user_id', $userId)
                ->select(
                    'employees.employee_id',
                    'employees.job_title',
                    'employees.status as emp_status',
                    'users.full_name',
                    'users.email'
                )
                ->first();
            
            if ($this->employee) {
                $this->agentId = $this->employee->employee_id;
            }
        }
    }
    
    protected function loadStatuses()
    {
        $this->statuses = [
            'open' => ['label' => 'Open', 'color' => 'bg-yellow-100 text-yellow-800', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            'in_progress' => ['label' => 'In Progress', 'color' => 'bg-blue-100 text-blue-800', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            'resolved' => ['label' => 'Resolved', 'color' => 'bg-green-100 text-green-800', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            'closed' => ['label' => 'Closed', 'color' => 'bg-gray-100 text-gray-800', 'icon' => 'M6 18L18 6M6 6l12 12'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800', 'icon' => 'M6 18L18 6M6 6l12 12'],
        ];
    }
    
    protected function loadPriorities()
    {
        $this->priorities = [
            'low' => ['label' => 'Low', 'color' => 'bg-green-100 text-green-800', 'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
            'medium' => ['label' => 'Medium', 'color' => 'bg-yellow-100 text-yellow-800', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            'high' => ['label' => 'High', 'color' => 'bg-orange-100 text-orange-800', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            'critical' => ['label' => 'Critical', 'color' => 'bg-red-100 text-red-800', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z'],
        ];
    }
    
    protected function loadCategories()
    {
        $this->categories = [
            'general' => ['label' => 'General Inquiry', 'color' => 'blue'],
            'technical' => ['label' => 'Technical Support', 'color' => 'purple'],
            'billing' => ['label' => 'Billing & Payments', 'color' => 'green'],
            'sales' => ['label' => 'Sales Inquiry', 'color' => 'orange'],
            'complaint' => ['label' => 'Complaint', 'color' => 'red'],
        ];
    }
    
    protected function loadAssignedTickets()
    {
        if (!$this->agentId) return;
        
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
                'tickets.resolved_at',
                'customers.first_name',
                'customers.last_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone',
                DB::raw('(SELECT COUNT(*) FROM ticket_notes WHERE ticket_notes.ticket_id = tickets.ticket_id) as note_count'),
                DB::raw('(SELECT MAX(created_at) FROM ticket_notes WHERE ticket_notes.ticket_id = tickets.ticket_id) as last_note_at')
            )
            ->join('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->where('tickets.assigned_agent', $this->agentId);
        
        // Apply filters
        if ($this->statusFilter !== 'all') {
            $query->where('tickets.status', $this->statusFilter);
        }
        
        if ($this->priorityFilter !== 'all') {
            $query->where('tickets.priority', $this->priorityFilter);
        }
        
        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->where('tickets.ticket_number', 'like', "%{$this->searchQuery}%")
                ->orWhere('tickets.subject', 'like', "%{$this->searchQuery}%")
                ->orWhere('customers.first_name', 'like', "%{$this->searchQuery}%")
                ->orWhere('customers.last_name', 'like', "%{$this->searchQuery}%");
            });
        }
        
        $this->assignedTickets = $query
            ->orderByRaw("FIELD(tickets.priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('tickets.created_at', 'desc')
            ->get();
    }
    
    protected function loadStats()
    {
        if (!$this->agentId) return;
        
        // Get basic counts
        $stats = DB::table('tickets')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as open'),
                DB::raw('SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress'),
                DB::raw('SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved')
            )
            ->where('assigned_agent', $this->agentId)
            ->first();
        
        // Get average resolution time for resolved tickets
        $avgTime = DB::table('tickets')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours'))
            ->where('assigned_agent', $this->agentId)
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->value('avg_hours');
        
        $this->stats = [
            'open' => $stats->open ?? 0,
            'in_progress' => $stats->in_progress ?? 0,
            'resolved' => $stats->resolved ?? 0,
            'total' => $stats->total ?? 0,
            'avg_response_time' => $avgTime ? round($avgTime) . ' hours' : 'N/A'
        ];
    }
    
    public function updated($property)
    {
        if (in_array($property, ['statusFilter', 'priorityFilter', 'searchQuery'])) {
            $this->loadAssignedTickets();
        }
    }
    
    public function selectTicket($ticketId)
    {
        $this->selectedTicket = DB::table('tickets')
            ->select(
                'tickets.*',
                'customers.first_name',
                'customers.last_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone',
                'customers.address as customer_address',
                'users.full_name as assigned_agent_name'
            )
            ->join('customers', 'tickets.customer_id', '=', 'customers.customer_id')
            ->leftJoin('employees', 'tickets.assigned_agent', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->where('tickets.ticket_id', $ticketId)
            ->where('tickets.assigned_agent', $this->agentId)
            ->first();
        
        if ($this->selectedTicket) {
            // Load ticket notes with sender info
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
        
        $this->newNote = '';
        $this->internalNote = false;
        $this->updatingTicket = true;
    }
    
    public function addNote()
    {
        if (!$this->selectedTicket || !$this->newNote || !$this->employee) {
            return;
        }
        
        try {
            DB::beginTransaction();
            
            // Get user_id for the agent
            $userId = DB::table('employees')
                ->where('employee_id', $this->agentId)
                ->value('user_id');
            
            if (!$userId) {
                throw new \Exception('Could not find user account for agent.');
            }
            
            DB::table('ticket_notes')->insert([
                'ticket_id' => $this->selectedTicket->ticket_id,
                'note_text' => $this->newNote,
                'created_by' => $userId,
                'internal' => $this->internalNote,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Update ticket status to in_progress if it was open
            if ($this->selectedTicket->status === 'open') {
                DB::table('tickets')
                    ->where('ticket_id', $this->selectedTicket->ticket_id)
                    ->update([
                        'status' => 'in_progress',
                        'updated_at' => now(),
                    ]);
            } else {
                // Just update the timestamp
                DB::table('tickets')
                    ->where('ticket_id', $this->selectedTicket->ticket_id)
                    ->update(['updated_at' => now()]);
            }
            
            DB::commit();
            
            session()->flash('message', 'Note added successfully!');
            
            // Refresh data
            $this->loadAssignedTickets();
            $this->loadStats();
            $this->selectTicket($this->selectedTicket->ticket_id);
            
            $this->newNote = '';
            $this->internalNote = false;
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error adding note: ' . $e->getMessage());
        }
    }
    
    public function markAsInProgress()
    {
        if (!$this->selectedTicket) return;
        
        DB::table('tickets')
            ->where('ticket_id', $this->selectedTicket->ticket_id)
            ->update([
                'status' => 'in_progress',
                'updated_at' => now(),
            ]);
        
        // Add system note
        $userId = DB::table('employees')
            ->where('employee_id', $this->agentId)
            ->value('user_id');
        
        if ($userId) {
            DB::table('ticket_notes')->insert([
                'ticket_id' => $this->selectedTicket->ticket_id,
                'note_text' => "Ticket marked as In Progress by support agent.",
                'created_by' => $userId,
                'internal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        session()->flash('message', 'Ticket marked as In Progress');
        
        // Refresh data
        $this->loadAssignedTickets();
        $this->loadStats();
        $this->selectTicket($this->selectedTicket->ticket_id);
    }
    
    public function showResolveModal()
    {
        $this->resolutionNote = '';
        $this->showResolveModal = true;
    }
    
    public function resolveTicket()
    {
        if (!$this->selectedTicket) {
            session()->flash('error', 'No ticket selected.');
            return;
        }
        
        if (empty(trim($this->resolutionNote))) {
            session()->flash('error', 'Please provide a resolution note.');
            return;
        }
        
        try {
            DB::beginTransaction();
            
            \Log::info('Resolving ticket ID: ' . $this->selectedTicket->ticket_id);
            
            $userId = DB::table('employees')
                ->where('employee_id', $this->agentId)
                ->value('user_id');
            
            if ($userId) {
                // Add resolution note
                DB::table('ticket_notes')->insert([
                    'ticket_id' => $this->selectedTicket->ticket_id,
                    'note_text' => "Issue resolved: " . trim($this->resolutionNote),
                    'created_by' => $userId,
                    'internal' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \Log::info('Added resolution note for ticket: ' . $this->selectedTicket->ticket_id);
            }
            
            // Update ticket status to resolved
            $updateData = [
                'status' => 'resolved',
                'updated_at' => now(),
                'resolved_at' => now()
            ];
            
            $updated = DB::table('tickets')
                ->where('ticket_id', $this->selectedTicket->ticket_id)
                ->update($updateData);
            
            if ($updated) {
                \Log::info('Successfully updated ticket status to resolved');
            } else {
                \Log::warning('No rows were updated for ticket: ' . $this->selectedTicket->ticket_id);
            }
            
            DB::commit();
            
            session()->flash('message', 'Ticket resolved successfully!');
            
            // Refresh data and close modal
            $this->loadAssignedTickets();
            $this->loadStats();
            $this->resolutionNote = '';
            $this->showResolveModal = false;
            $this->updatingTicket = false;
            $this->selectedTicket = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error resolving ticket: ' . $e->getMessage());
            session()->flash('error', 'Error resolving ticket: ' . $e->getMessage());
        }
    }
    
    public function closeTicket()
    {
        if (!$this->selectedTicket) {
            session()->flash('error', 'No ticket selected.');
            return;
        }
        
        if (empty(trim($this->closeReason))) {
            session()->flash('error', 'Please provide a reason for closing.');
            return;
        }
        
        try {
            DB::beginTransaction();
            
            $userId = DB::table('employees')
                ->where('employee_id', $this->agentId)
                ->value('user_id');
            
            if ($userId) {
                // Add closing note
                DB::table('ticket_notes')->insert([
                    'ticket_id' => $this->selectedTicket->ticket_id,
                    'note_text' => "Ticket closed. Reason: " . trim($this->closeReason),
                    'created_by' => $userId,
                    'internal' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Update ticket status to closed
            DB::table('tickets')
                ->where('ticket_id', $this->selectedTicket->ticket_id)
                ->update([
                    'status' => 'closed',
                    'updated_at' => now(),
                    'resolved_at' => now()
                ]);
            
            DB::commit();
            
            session()->flash('message', 'Ticket closed successfully.');
            
            // Refresh data and close modal
            $this->loadAssignedTickets();
            $this->loadStats();
            $this->closeReason = '';
            $this->showCloseModal = false;
            $this->updatingTicket = false;
            $this->selectedTicket = null;
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error closing ticket: ' . $e->getMessage());
        }
    }
    
    public function closeTicketView()
    {
        $this->selectedTicket = null;
        $this->updatingTicket = false;
        $this->newNote = '';
        $this->internalNote = false;
        $this->showResolveModal = false;
        $this->showCloseModal = false;
        $this->resolutionNote = '';
        $this->closeReason = '';
    }
    
    public function refreshTickets()
    {
        $this->loadAssignedTickets();
        $this->loadStats();
        session()->flash('message', 'Tickets refreshed!');
    }
}
?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-black">Support Agent Dashboard</h1>
                    <p class="text-blue-500 mt-2">
                        Welcome back, {{ $employee?->full_name ?? 'Agent' }}! 
                        @if($employee)
                            <span class="font-medium">({{ $employee->job_title }})</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <button wire:click="refreshTickets" 
                            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug Info -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
        <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-sm">
            <p class="font-semibold">Debug Info:</p>
            <p>Agent ID: {{ $agentId ?? 'Not found' }}</p>
            <p>Employee: {{ $employee?->full_name ?? 'Not found' }}</p>
            <p>Tickets Count: {{ count($assignedTickets) }}</p>
            <p>Stats: Open={{ $stats['open'] }}, In Progress={{ $stats['in_progress'] }}, Resolved={{ $stats['resolved'] }}, Total={{ $stats['total'] }}</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Open Tickets -->
            <div class="bg-white rounded-xl shadow p-6 border-l-4 border-yellow-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500">Open Tickets</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['open'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- In Progress -->
            <div class="bg-white rounded-xl shadow p-6 border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500">In Progress</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['in_progress'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Resolved -->
            <div class="bg-white rounded-xl shadow p-6 border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500">Resolved</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['resolved'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Avg Resolution Time -->
            <div class="bg-white rounded-xl shadow p-6 border-l-4 border-purple-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm text-gray-500">Avg Resolution Time</p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['avg_response_time'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Tickets List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow">
                    <!-- Header with Filters -->
                    <div class="border-b border-gray-200 px-6 py-4">
                        <div class="flex flex-col md:flex-row md:items-center justify-between space-y-4 md:space-y-0">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Assigned Tickets ({{ count($assignedTickets) }})</h2>
                                <p class="text-sm text-gray-600">Tickets assigned to you</p>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                                <!-- Search -->
                                <div class="relative">
                                    <input type="text" 
                                        wire:model.live.debounce.300ms="searchQuery"
                                        placeholder="Search tickets..."
                                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                
                                <!-- Status Filter -->
                                <select wire:model.live="statusFilter" 
                                        class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="all">All Status</option>
                                    @foreach($statuses as $key => $status)
                                        <option value="{{ $key }}">{{ $status['label'] }}</option>
                                    @endforeach
                                </select>
                                
                                <!-- Priority Filter -->
                                <select wire:model.live="priorityFilter" 
                                        class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="all">All Priority</option>
                                    @foreach($priorities as $key => $priority)
                                        <option value="{{ $key }}">{{ $priority['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tickets Table -->
                    <div class="overflow-x-auto">
                        @if(count($assignedTickets) > 0)
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Update</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($assignedTickets as $ticket)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-bold text-blue-600">{{ $ticket->ticket_number }}</div>
                                        <div class="text-xs text-gray-500">{{ Carbon::parse($ticket->created_at)->format('M d') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900">{{ Str::limit($ticket->subject, 40) }}</div>
                                        <div class="text-sm text-gray-500">{{ $categories[$ticket->category]['label'] ?? $ticket->category }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium">{{ $ticket->first_name }} {{ $ticket->last_name }}</div>
                                        <div class="text-sm text-gray-500">{{ $ticket->customer_email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $priorities[$ticket->priority]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $priorities[$ticket->priority]['label'] ?? ucfirst($ticket->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statuses[$ticket->status]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statuses[$ticket->status]['label'] ?? ucfirst($ticket->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ Carbon::parse($ticket->updated_at)->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if($ticket->status === 'resolved' || $ticket->status === 'closed')
                                            <button wire:click="selectTicket({{ $ticket->ticket_id }})"
                                                    class="text-gray-600 hover:text-gray-900 px-3 py-1 rounded hover:bg-gray-50">
                                                View
                                            </button>
                                        @else
                                            <button wire:click="selectTicket({{ $ticket->ticket_id }})"
                                                    class="text-blue-600 hover:text-blue-900 px-3 py-1 rounded hover:bg-blue-50">
                                                Manage
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">No tickets assigned</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                @if($statusFilter !== 'all' || $priorityFilter !== 'all' || $searchQuery)
                                    Try changing your filters or search query
                                @else
                                    You don't have any tickets assigned to you yet.
                                @endif
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats & Actions -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button wire:click="refreshTickets" 
                                class="w-full flex items-center p-3 text-gray-700 hover:bg-blue-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-blue-200">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <span>Refresh Tickets</span>
                        </button>
                        
                        @if($selectedTicket && $selectedTicket->status !== 'resolved' && $selectedTicket->status !== 'closed')
                        <button wire:click="markAsInProgress"
                                class="w-full flex items-center p-3 text-gray-700 hover:bg-yellow-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-yellow-200">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <span>Mark as In Progress</span>
                        </button>
                        
                        <!-- <button wire:click="showResolveModal"
                                class="w-full flex items-center p-3 text-gray-700 hover:bg-green-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span> Ticket</span>
                        </button> -->
                        
                        <button wire:click="$set('showCloseModal', true)"
                                class="w-full flex items-center p-3 text-gray-700 hover:bg-red-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-red-200">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <span>Resolve Close Ticket</span>
                        </button>
                        @endif
                    </div>
                </div>
                
                <!-- Performance -->
                <div class="bg-white rounded-xl shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Your Performance</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700">Open Rate</span>
                                <span class="text-sm font-medium text-gray-700">
                                    @if($stats['total'] > 0)
                                        {{ round(($stats['open'] / $stats['total']) * 100) }}%
                                    @else
                                        0%
                                    @endif
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" 
                                    style="width: {{ $stats['total'] > 0 ? ($stats['open'] / $stats['total']) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700">Resolution Rate</span>
                                <span class="text-sm font-medium text-gray-700">
                                    @if($stats['total'] > 0)
                                        {{ round(($stats['resolved'] / $stats['total']) * 100) }}%
                                    @else
                                        0%
                                    @endif
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                    style="width: {{ $stats['total'] > 0 ? ($stats['resolved'] / $stats['total']) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</div>
                                <div class="text-sm text-gray-600">Total Tickets Assigned</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ticket Detail Modal -->
    @if($updatingTicket && $selectedTicket)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4 text-white relative">
                <div>
                    <h3 class="text-xl font-bold">Ticket: {{ $selectedTicket->ticket_number }}</h3>
                    <p class="text-blue-100">{{ $selectedTicket->subject }}</p>
                </div>
                <button wire:click="closeTicketView" 
                        class="absolute top-4 right-4 text-white hover:text-blue-200 bg-blue-700 hover:bg-blue-800 w-8 h-8 rounded-full flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Content -->
            <div class="p-6 overflow-y-auto max-h-[70vh]">
                <!-- Ticket Info -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 mb-4">Customer Information</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Name</p>
                                        <p class="font-medium">{{ $selectedTicket->first_name }} {{ $selectedTicket->last_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="font-medium">{{ $selectedTicket->customer_email }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Phone</p>
                                        <p class="font-medium">{{ $selectedTicket->customer_phone ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Customer Since</p>
                                        <p class="font-medium">{{ Carbon::parse($selectedTicket->created_at)->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 mb-4">Ticket Details</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Status</p>
                                        <span class="font-medium px-3 py-1 rounded-full text-sm {{ $statuses[$selectedTicket->status]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statuses[$selectedTicket->status]['label'] ?? ucfirst($selectedTicket->status) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Priority</p>
                                        <span class="font-medium px-3 py-1 rounded-full text-sm {{ $priorities[$selectedTicket->priority]['color'] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $priorities[$selectedTicket->priority]['label'] ?? ucfirst($selectedTicket->priority) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Category</p>
                                        <p class="font-medium">{{ $categories[$selectedTicket->category]['label'] ?? $selectedTicket->category }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Created</p>
                                        <p class="font-medium">{{ Carbon::parse($selectedTicket->created_at)->format('M d, Y h:i A') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Issue Description -->
                    <div class="mb-6">
                        <h4 class="text-lg font-bold text-gray-900 mb-3">Issue Description</h4>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <p class="whitespace-pre-line">{{ $selectedTicket->issue_description }}</p>
                        </div>
                    </div>
                    
                    <!-- Conversation -->
                    @if(isset($selectedTicket->notes) && count($selectedTicket->notes) > 0)
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-900 mb-4">Conversation</h4>
                        
                        <div class="space-y-4 max-h-80 overflow-y-auto p-4 border border-gray-200 rounded-lg">
                            @foreach($selectedTicket->notes as $note)
                            <div class="flex {{ $note->sender_type === 'agent' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-3/4">
                                    <div class="{{ $note->sender_type === 'agent' ? 'bg-blue-100 text-gray-800' : 'bg-gray-100 text-gray-800' }} rounded-2xl p-4">
                                        @if($note->internal)
                                        <div class="flex items-center mb-2">
                                            <svg class="w-4 h-4 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <span class="text-xs font-medium text-gray-600">Internal Note</span>
                                        </div>
                                        @endif
                                        <p class="whitespace-pre-line">{{ $note->note_text }}</p>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 {{ $note->sender_type === 'agent' ? 'text-right' : 'text-left' }}">
                                        {{ Carbon::parse($note->created_at)->format('M d, Y h:i A') }}
                                        <span class="ml-2 font-medium">
                                            {{ $note->author_name ?? ($note->sender_type === 'agent' ? 'You' : 'Customer') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    <!-- Add Note -->
                    @if($selectedTicket->status !== 'resolved' && $selectedTicket->status !== 'closed')
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-900 mb-4">Add Note</h4>
                        <div class="space-y-4">
                            <div>
                                <textarea wire:model="newNote" 
                                        rows="4"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Type your response here..."></textarea>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="internalNote" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-600">Internal Note (not visible to customer)</span>
                                </label>
                                
                                <button wire:click="addNote" 
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                                    Add Note
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Action Buttons -->
                    @if($selectedTicket->status !== 'resolved' && $selectedTicket->status !== 'closed')
                    <div class="pt-6 border-t border-gray-200">
                        <h4 class="text-lg font-bold text-gray-900 mb-4">Ticket Actions</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @if($selectedTicket->status !== 'in_progress')
                            <button wire:click="markAsInProgress"
                                    wire:loading.attr="disabled"
                                    class="flex items-center justify-center p-4 bg-yellow-50 border-2 border-yellow-200 text-yellow-700 rounded-lg hover:bg-yellow-100 transition">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Mark as In Progress
                            </button>
                            @endif
                            
                                <!-- <button wire:click="showResolveModal"
                                        wire:loading.attr="disabled"
                                        class="flex items-center justify-center p-4 bg-green-50 border-2 border-green-200 text-green-700 rounded-lg hover:bg-green-100 transition">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Resolve Ticket
                                </button> -->
                            
                            <button wire:click="$set('showCloseModal', true)"
                                    wire:loading.attr="disabled"
                                    class="flex items-center justify-center p-4 bg-red-50 border-2 border-red-200 text-red-700 rounded-lg hover:bg-red-100 transition">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Resolve and Close Ticket
                            </button>
                        </div>
                    </div>
                    @else
                    <div class="pt-6 border-t border-gray-200">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <h4 class="font-bold text-green-700">Ticket {{ ucfirst($selectedTicket->status) }}</h4>
                                    <p class="text-sm text-green-600">
                                        This ticket has been {{ $selectedTicket->status }} on 
                                        {{ Carbon::parse($selectedTicket->resolved_at ?? $selectedTicket->updated_at)->format('F d, Y') }}.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Resolve Ticket Modal -->
    @if($showResolveModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-70" wire:key="resolve-modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md z-80">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Resolve Ticket</h3>
                    <button wire:click="$set('showResolveModal', false)" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <p class="text-gray-600 mb-6">Please provide details about how you resolved this issue.</p>
                
                <textarea wire:model="resolutionNote" 
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-6"
                        placeholder="Describe the solution you provided, steps taken, or any other relevant information..."></textarea>
                
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showResolveModal', false)" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="resolveTicket" 
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                        <span wire:loading.remove>Mark as Resolved</span>
                        <span wire:loading>Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Close Ticket Modal -->
    @if($showCloseModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-70" wire:key="close-modal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md z-80">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Close Ticket</h3>
                    <button wire:click="$set('showCloseModal', false)" 
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <p class="text-gray-600 mb-6">Please provide a reason for closing this ticket.</p>
                
                <textarea wire:model="closeReason" 
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-6"
                        placeholder="e.g., Issue resolved, customer satisfied, duplicate ticket, customer unresponsive..."></textarea>
                
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showCloseModal', false)" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button wire:click="closeTicket" 
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                        <span wire:loading.remove>Close Ticket</span>
                        <span wire:loading>Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Flash Messages -->
    @if(session()->has('message'))
    <div class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-90">
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
    
    @if(session()->has('error'))
    <div class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-90">
        <div class="max-w-sm w-full bg-red-100 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden border border-red-200">
            <div class="p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>