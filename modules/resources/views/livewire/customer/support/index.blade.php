<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new #[Layout('components.layouts.customerapp')] 
#[Title('Contact Support - Get Help Now')]
class extends Component
{
    // Form fields
    #[Validate('required|min:2|max:100')]
    public $name = '';
    
    #[Validate('required|email|max:150')]
    public $email = '';
    
    public $phone = '';
    
    #[Validate('required')]
    public $category = 'general';
    
    #[Validate('required|min:10|max:255')]
    public $subject = '';
    
    #[Validate('required|min:20|max:2000')]
    public $description = '';
    
    public $priority = 'medium';
    public $orderNumber = '';
    
    // Status
    public $submitted = false;
    public $ticketNumber = '';
    public $estimatedResponse = '24 hours';
    
    // Ticket tracking
    public $customerTickets = [];
    public $selectedTicket = null;
    public $newMessage = '';
    public $updatingTicket = false;
    
    // Data
    public $categories = [];
    public $priorities = [];
    public $faqItems = [];
    public $openFaqId = null;
    
    // Customer info
    public $customer = null;
    public $userId = null; // Add this to store user_id
    
    public function mount()
    {
        $this->loadCustomerInfo();
        $this->loadCategories();
        $this->loadPriorities();
        $this->loadFAQ();
        $this->loadCustomerTickets();
    }
    
    protected function loadCustomerInfo()
    {
        $userId = Auth::id();
        $this->userId = $userId; // Store the user_id
        
        if ($userId) {
            $this->customer = DB::table('customers')
                ->join('users', 'customers.user_id', '=', 'users.user_id')
                ->where('customers.user_id', $userId)
                ->select(
                    'customers.customer_id',
                    'customers.first_name',
                    'customers.last_name',
                    'customers.email',
                    'customers.phone',
                    'customers.address',
                    'customers.user_id as customer_user_id', // Get user_id from customers table
                    'users.full_name'
                )
                ->first();
            
            if ($this->customer) {
                $this->name = $this->customer->full_name ?? $this->customer->first_name . ' ' . $this->customer->last_name;
                $this->email = $this->customer->email;
                $this->phone = $this->customer->phone ?? '';
                $this->userId = $this->customer->customer_user_id; // Use the user_id from customers
            }
        }
    }
    
    protected function loadCustomerTickets()
    {
        if ($this->customer) {
            $this->customerTickets = DB::table('tickets')
                ->select(
                    'tickets.ticket_id',
                    'tickets.ticket_number',
                    'tickets.subject',
                    'tickets.priority',
                    'tickets.status',
                    'tickets.category',
                    'tickets.created_at',
                    'tickets.updated_at',
                    'tickets.issue_description',
                    'tickets.assigned_agent',
                    'users.full_name as agent_name'
                )
                ->leftJoin('employees', 'tickets.assigned_agent', '=', 'employees.employee_id')
                ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
                ->where('tickets.customer_id', $this->customer->customer_id)
                ->orderBy('tickets.created_at', 'desc')
                ->get();
        }
    }
    
    protected function loadCategories()
    {
        // Match your database enum values: ('billing','technical','sales','general','complaint')
        $this->categories = [
            'general' => ['label' => 'General Inquiry', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'color' => 'blue'],
            'technical' => ['label' => 'Technical Support', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'purple'],
            'billing' => ['label' => 'Billing & Payments', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green'],
            'sales' => ['label' => 'Sales Inquiry', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'orange'],
            'complaint' => ['label' => 'Complaint', 'icon' => 'M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4', 'color' => 'red'],
        ];
    }
    
    protected function loadPriorities()
    {
        $this->priorities = [
            'low' => [
                'label' => 'Low',
                'desc' => 'General question',
                'color' => 'bg-green-100 text-green-800',
                'time' => '48 hours',
                'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'
            ],
            'medium' => [
                'label' => 'Medium',
                'desc' => 'Need assistance',
                'color' => 'bg-yellow-100 text-yellow-800',
                'time' => '24 hours',
                'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
            ],
            'high' => [
                'label' => 'High',
                'desc' => 'Important issue',
                'color' => 'bg-orange-100 text-orange-800',
                'time' => '12 hours',
                'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            ],
            'critical' => [
                'label' => 'Critical',
                'desc' => 'System down',
                'color' => 'bg-red-100 text-red-800',
                'time' => '4 hours',
                'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z'
            ],
        ];
        
        $this->estimatedResponse = $this->priorities[$this->priority]['time'];
    }
    
    protected function loadFAQ()
    {
        $this->faqItems = [
            ['id' => 1, 'q' => 'How long does it take to get a response?', 'a' => 'Response time depends on priority: Low (48h), Medium (24h), High (12h), Critical (4h).'],
            ['id' => 2, 'q' => 'Can I track my support request?', 'a' => 'Yes, your tickets are automatically displayed when you\'re logged in.'],
            ['id' => 3, 'q' => 'What information should I include?', 'a' => 'Be specific: error messages, order numbers, screenshots, and steps to reproduce the issue.'],
            ['id' => 4, 'q' => 'How do I update an existing ticket?', 'a' => 'Click "Update Ticket" on any of your tickets and add a new message.'],
        ];
    }
    
    public function updatedPriority($value)
    {
        $this->estimatedResponse = $this->priorities[$value]['time'];
    }
    
    public function submitRequest()
    {
        $this->validate();
        
        if (!$this->customer || !$this->userId) {
            session()->flash('error', 'You must be logged in to submit a ticket.');
            return;
        }
        
        try {
            DB::beginTransaction();
            
            // Generate ticket number
            $ticketNumber = 'SUP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            
            // Find available agent
            $agent = DB::table('employees as e')
                ->join('users as u', 'e.user_id', '=', 'u.user_id')
                ->where('e.status', 'active')
                ->orderByRaw('(SELECT COUNT(*) FROM tickets WHERE assigned_agent = e.employee_id AND status IN ("open", "in_progress"))')
                ->select('e.employee_id')
                ->first();
            
            // Create ticket
            $ticketId = DB::table('tickets')->insertGetId([
                'ticket_number' => $ticketNumber,
                'customer_id' => $this->customer->customer_id,
                'assigned_agent' => $agent?->employee_id,
                'subject' => $this->subject,
                'issue_description' => $this->description,
                'priority' => $this->priority,
                'status' => 'open',
                'category' => $this->category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Add initial note - use user_id
            DB::table('ticket_notes')->insert([
                'ticket_id' => $ticketId,
                'note_text' => $this->description,
                'created_by' => $this->userId, // Use user_id
                'internal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            // Update UI
            $this->ticketNumber = $ticketNumber;
            $this->submitted = true;
            
            // Refresh tickets list
            $this->loadCustomerTickets();
            
            // Clear form
            $this->reset('subject', 'description', 'orderNumber');
            $this->category = 'general';
            $this->priority = 'medium';
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error creating ticket: ' . $e->getMessage());
        }
    }
    
    public function selectTicketForUpdate($ticketId)
{
    $ticket = DB::table('tickets')
        ->select(
            'tickets.*',
            'users.full_name as agent_name'
        )
        ->leftJoin('employees', 'tickets.assigned_agent', '=', 'employees.employee_id')
        ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
        ->where('tickets.ticket_id', $ticketId)
        ->where('tickets.customer_id', $this->customer->customer_id)
        ->first();
    
    // Check if ticket is closed
    if ($ticket && in_array($ticket->status, ['closed', 'resolved'])) {
        session()->flash('error', 'This ticket is closed and cannot be updated.');
        return;
    }
    
    $this->selectedTicket = $ticket;
    
    if ($this->selectedTicket && $this->userId) {
        // Load ticket notes with correct user_id comparison
        $this->selectedTicket->notes = DB::table('ticket_notes')
            ->select(
                'ticket_notes.*',
                DB::raw('CASE WHEN ticket_notes.created_by = ' . $this->userId . ' THEN "customer" ELSE "agent" END as sender')
            )
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    $this->newMessage = '';
    $this->updatingTicket = true;
}
    
   public function addMessageToTicket()
{
    if ($this->selectedTicket && $this->newMessage && $this->userId) {
        // Check if ticket is closed
        if (in_array($this->selectedTicket->status, ['closed', 'resolved'])) {
            session()->flash('error', 'This ticket is closed and cannot be updated.');
            $this->closeTicketUpdate();
            return;
        }
        
        DB::table('ticket_notes')->insert([
            'ticket_id' => $this->selectedTicket->ticket_id,
            'note_text' => $this->newMessage,
            'created_by' => $this->userId, // Use user_id
            'internal' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Update ticket status to in_progress if it was open
        if ($this->selectedTicket->status === 'open') {
            DB::table('tickets')
                ->where('ticket_id', $this->selectedTicket->ticket_id)
                ->update(['status' => 'in_progress', 'updated_at' => now()]);
        }
        
        session()->flash('message', 'Message added successfully!');
        
        // Refresh data
        $this->loadCustomerTickets();
        $this->selectTicketForUpdate($this->selectedTicket->ticket_id);
        
        $this->newMessage = '';
    }
}
    
    public function closeTicketUpdate()
    {
        $this->selectedTicket = null;
        $this->updatingTicket = false;
        $this->newMessage = '';
    }
    
    public function resetForm()
    {
        $this->reset('subject', 'description', 'orderNumber');
        $this->category = 'general';
        $this->priority = 'medium';
        $this->submitted = false;
        $this->resetErrorBag();
    }
    
    public function toggleFaq($id)
    {
        $this->openFaqId = $this->openFaqId === $id ? null : $id;
    }
    
    public function selectCategory($cat)
    {
        $this->category = $cat;
    }
}
?>

<!-- The HTML/Blade template remains exactly the same as you have it -->
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white">
    <!-- Header -->
    <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-4">Customer Support Center</h1>
                <p class="text-xl max-w-3xl mx-auto">
                    Welcome back, {{ $customer ? $customer->first_name : 'Customer' }}! Get help and manage your support requests.
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Ticket Tracking Section -->
        @if($customer)
        <div class="bg-white rounded-2xl shadow-lg p-8 border border-green-100 mb-12">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Your Support Tickets</h2>
                        <p class="text-gray-600">View and manage your existing support requests</p>
                    </div>
                </div>
                <div class="text-sm text-green-600 font-medium">
                    {{ count($customerTickets) }} ticket(s)
                </div>
            </div>
            
            @if(count($customerTickets) > 0)
            <div class="space-y-4">
                @foreach($customerTickets as $ticket)
                <div class="border border-green-200 rounded-lg p-6 hover:bg-green-50 transition-colors">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-xl font-bold text-green-700">{{ $ticket->ticket_number }}</span>
                                <span class="px-3 py-1 rounded-full text-sm font-medium 
                                    @if($ticket->status === 'open') bg-yellow-100 text-yellow-800
                                    @elseif($ticket->status === 'in_progress') bg-blue-100 text-blue-800
                                    @elseif($ticket->status === 'resolved') bg-green-100 text-green-800
                                    @elseif($ticket->status === 'closed') bg-gray-100 text-gray-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </div>
                            <h4 class="font-bold text-gray-900 text-lg">{{ $ticket->subject }}</h4>
                            <p class="text-gray-600 text-sm mt-1">
                                Created: {{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, Y h:i A') }}
                            </p>
                        </div>
                        @if(!in_array($ticket->status, ['closed', 'resolved']))
    <button wire:click="selectTicketForUpdate({{ $ticket->ticket_id }})"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
        Update Ticket
    </button>
@else
    <span class="px-4 py-2 bg-gray-200 text-gray-600 rounded-lg cursor-not-allowed">
        Closed
    </span>
@endif
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Category:</span>
                            <span class="font-medium ml-2">{{ $categories[$ticket->category]['label'] ?? 'General' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Priority:</span>
                            <span class="font-medium ml-2">{{ ucfirst($ticket->priority) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Assigned Agent:</span>
                            <span class="font-medium ml-2">{{ $ticket->agent_name ?? 'Unassigned' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Last Update:</span>
                            <span class="font-medium ml-2">
                                {{ \Carbon\Carbon::parse($ticket->updated_at)->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-12 bg-green-50 rounded-xl">
                <svg class="mx-auto h-16 w-16 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No tickets found</h3>
                <p class="mt-2 text-sm text-gray-600">You haven't submitted any support tickets yet.</p>
            </div>
            @endif
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-12">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div>
                    <h3 class="font-bold text-gray-900">Please log in</h3>
                    <p class="text-gray-600">You need to be logged in to submit and track support tickets.</p>
                </div>
            </div>
        </div>
        @endif

        <!-- New Ticket Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form Area -->
            <div class="lg:col-span-2">
                @if($submitted)
                <!-- Success State -->
                <div class="bg-white rounded-2xl shadow-lg p-8 border border-green-200">
                    <div class="text-center mb-8">
                        <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-3">Request Submitted!</h2>
                        <p class="text-gray-600 mb-6">
                            We've received your support request and will respond within the estimated time.
                        </p>
                        
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 max-w-md mx-auto mb-8">
                            <div class="text-center mb-4">
                                <div class="text-sm text-gray-500 mb-2">Your Ticket Number</div>
                                <div class="text-4xl font-bold text-green-600 tracking-wider">{{ $ticketNumber }}</div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Priority:</span>
                                    <span class="font-medium {{ $priorities[$priority]['color'] }} px-3 py-1 rounded-full text-sm">
                                        {{ $priorities[$priority]['label'] }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Estimated Response:</span>
                                    <span class="font-medium">{{ $estimatedResponse }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Category:</span>
                                    <span class="font-medium">{{ $categories[$category]['label'] }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <button wire:click="resetForm" 
                                    class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition shadow-sm">
                                Submit Another Request
                            </button>
                        </div>
                    </div>
                </div>
                @else
                <!-- Support Form -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-green-200">
                    <!-- Form Header -->
                    <div class="bg-gradient-to-r from-green-600 to-emerald-700 p-8">
                        <h2 class="text-2xl font-bold text-white mb-2">Submit New Support Request</h2>
                        <p class="text-green-100">Fill out the form below. Our team typically responds within {{ $estimatedResponse }}.</p>
                    </div>
                    
                    <form wire:submit.prevent="submitRequest" class="p-8 space-y-8">
                        <!-- Validation Errors -->
                        @if($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
                            <div class="flex items-center mb-3">
                                <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <h3 class="font-bold text-gray-900">Please fix the following errors:</h3>
                            </div>
                            <ul class="list-disc list-inside space-y-1 text-red-600">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        
                        <!-- Customer Info Display -->
                        <div class="bg-green-50 rounded-xl p-6 mb-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Your Information (from your account)
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Name</label>
                                    <p class="font-medium text-gray-900">{{ $name }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Email</label>
                                    <p class="font-medium text-gray-900">{{ $email }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Phone</label>
                                    <p class="font-medium text-gray-900">{{ $phone ?: 'Not provided' }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Customer ID</label>
                                    <p class="font-medium text-gray-900">{{ $customer ? $customer->customer_id : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Selection -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                What do you need help with? *
                            </h3>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                @foreach($categories as $key => $cat)
                                    <button type="button"
                                            wire:click="selectCategory('{{ $key }}')"
                                            class="p-4 border-2 rounded-xl text-left transition-all duration-200
                                                   {{ $category === $key ? 'border-green-500 bg-green-50 shadow-sm' : 'border-gray-200 hover:border-green-300 hover:bg-gray-50' }}">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 rounded-lg bg-{{ $cat['color'] }}-100 flex items-center justify-center mr-3">
                                                <svg class="w-6 h-6 text-{{ $cat['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cat['icon'] }}" />
                                                </svg>
                                            </div>
                                            <span class="font-medium text-gray-900">{{ $cat['label'] }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 rounded-full {{ $category === $key ? 'bg-green-500' : 'bg-gray-300' }} mr-2"></div>
                                            <span class="text-sm text-gray-500">Select</span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                            @error('category')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Issue Details -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                Describe Your Issue
                            </h3>
                            
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Subject *
                                    </label>
                                    <input type="text" 
                                           wire:model="subject"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                                           placeholder="Brief description of your issue"
                                           required>
                                    @error('subject')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Detailed Description *
                                    </label>
                                    <textarea wire:model="description" 
                                              rows="5"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                                              placeholder="Please provide as much detail as possible. Include error messages, steps to reproduce, screenshots, etc."
                                              required></textarea>
                                    <div class="flex justify-between mt-2">
                                        <div>
                                            @error('description')
                                                <p class="text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <p class="text-sm text-gray-500">
                                            {{ strlen($description) }}/2000 characters
                                        </p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Order Number (Optional)
                                    </label>
                                    <input type="text" 
                                           wire:model="orderNumber"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition"
                                           placeholder="ORD-123456">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Priority Selection -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $priorities[$priority]['icon'] }}" />
                                    </svg>
                                </div>
                                How urgent is this?
                            </h3>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                @foreach($priorities as $key => $priorityOpt)
                                    <label class="relative">
                                        <input type="radio" 
                                               wire:model="priority" 
                                               value="{{ $key }}"
                                               class="sr-only peer">
                                        <div class="p-4 border-2 rounded-xl cursor-pointer transition-all duration-200
                                                   peer-checked:border-green-500 peer-checked:bg-green-50 
                                                   hover:border-green-300 hover:bg-gray-50">
                                            <div class="flex items-center mb-2">
                                                <div class="w-8 h-8 rounded-lg {{ $priorityOpt['color'] }} flex items-center justify-center mr-3">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $priorityOpt['icon'] }}" />
                                                    </svg>
                                                </div>
                                                <span class="font-bold text-gray-900">{{ $priorityOpt['label'] }}</span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">{{ $priorityOpt['desc'] }}</p>
                                            <p class="text-xs text-gray-500">Response: {{ $priorityOpt['time'] }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- Submit Section -->
                        <div class="pt-8 border-t border-gray-200">
                            <div class="flex flex-col md:flex-row justify-between items-center">
                                <div class="mb-6 md:mb-0">
                                    <div class="flex items-center text-lg text-gray-700">
                                        <svg class="w-6 h-6 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                        </svg>
                                        Estimated Response: 
                                        <span class="font-bold ml-2">{{ $estimatedResponse }}</span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-4">
                                    <button type="button" 
                                            wire:click="resetForm"
                                            class="px-8 py-3 border-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-medium transition">
                                        Clear Form
                                    </button>
                                    <button type="submit" 
                                            class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-medium transition shadow-lg">
                                        Submit Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-8">
                <!-- Support Info -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-6 border border-green-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Support Information</h3>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center mr-4 shadow-sm">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">24/7 Support</p>
                                <p class="text-gray-600">Available round the clock</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center mr-4 shadow-sm">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900">{{ $estimatedResponse }}</p>
                                <p class="text-gray-600">Avg. response time</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Help -->
                <div class="bg-white rounded-2xl shadow-sm border border-green-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Help</h3>
                    <div class="space-y-3">
                        <a href="#" class="flex items-center p-3 text-gray-700 hover:bg-green-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>FAQ & Knowledge Base</span>
                        </a>
                        <button wire:click="loadCustomerTickets" 
                                class="flex items-center p-3 text-gray-700 hover:bg-green-50 rounded-lg transition group w-full text-left">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <span>Refresh Tickets</span>
                        </button>
                        <a href="mailto:support@example.com" class="flex items-center p-3 text-gray-700 hover:bg-green-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>Email Support</span>
                        </a>
                    </div>
                </div>
                
                <!-- FAQ -->
                <div class="bg-white rounded-2xl shadow-sm border border-green-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Common Questions</h3>
                    <div class="space-y-3">
                        @foreach($faqItems as $faq)
                            <div class="border border-green-200 rounded-lg overflow-hidden">
                                <button wire:click="toggleFaq({{ $faq['id'] }})" 
                                        class="w-full px-4 py-3 text-left flex justify-between items-center hover:bg-green-50 transition">
                                    <span class="font-medium text-gray-900">{{ $faq['q'] }}</span>
                                    <svg class="w-5 h-5 text-gray-500 transition-transform {{ $openFaqId === $faq['id'] ? 'rotate-180' : '' }}" 
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                @if($openFaqId === $faq['id'])
                                    <div class="px-4 py-3 bg-green-50 border-t border-green-200">
                                        <p class="text-gray-700">{{ $faq['a'] }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Update Modal -->
        @if($updatingTicket && $selectedTicket)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold">Update Ticket: {{ $selectedTicket->ticket_number }}</h3>
                            <p class="text-green-100">Add additional information or ask follow-up questions</p>
                        </div>
                        <button wire:click="closeTicketUpdate" class="text-white hover:text-green-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="p-6 overflow-y-auto max-h-[70vh]">
                    <!-- Ticket Info -->
                    <div class="mb-8">
                        <h4 class="font-bold text-gray-900 mb-4 text-lg">{{ $selectedTicket->subject }}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-green-50 p-3 rounded-lg">
                                <div class="text-sm text-gray-600">Status</div>
                                <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $selectedTicket->status)) }}</div>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <div class="text-sm text-gray-600">Priority</div>
                                <div class="font-medium">{{ ucfirst($selectedTicket->priority) }}</div>
                            </div>
                            <div class="bg-purple-50 p-3 rounded-lg">
                                <div class="text-sm text-gray-600">Assigned Agent</div>
                                <div class="font-medium">{{ $selectedTicket->agent_name ?? 'Unassigned' }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversation Thread -->
                    <div class="mb-8">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                            </svg>
                            Conversation
                        </h4>
                        
                        <div class="space-y-4">
                            @foreach($selectedTicket->notes ?? [] as $note)
                            <div class="flex {{ $note->sender === 'customer' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-3/4">
                                    <div class="{{ $note->sender === 'customer' ? 'bg-green-100 text-gray-800' : 'bg-gray-100 text-gray-800' }} rounded-2xl p-4">
                                        <p class="whitespace-pre-line">{{ $note->note_text }}</p>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1 {{ $note->sender === 'customer' ? 'text-right' : 'text-left' }}">
                                        {{ \Carbon\Carbon::parse($note->created_at)->format('M d, Y h:i A') }}
                                        <span class="ml-2 font-medium">
                                            {{ $note->sender === 'customer' ? 'You' : 'Support Agent' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Add Message -->
                    <div>
                        <h4 class="font-bold text-gray-900 mb-4">Add Update</h4>
                        <div class="space-y-4">
                            <textarea wire:model="newMessage" 
                                      rows="4"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                      placeholder="Type your message here... Add any additional details, questions, or updates about your issue."></textarea>
                            
                            <div class="flex justify-between">
                                <div class="text-sm text-gray-500">
                                    @if($selectedTicket->status === 'open')
                                    Your update will notify the support team and change ticket status to "In Progress"
                                    @else
                                    Your update will be added to the conversation
                                    @endif
                                </div>
                                <div class="space-x-3">
                                    <button wire:click="closeTicketUpdate" 
                                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button wire:click="addMessageToTicket" 
                                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                                        Send Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

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

@if(session()->has('error'))
<div class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
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