<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

new #[Layout('components.layouts.helpdesk')] class extends Component
{
    public $customers = [];
    public $stats = [];
    public $recentRecoveryTickets = [];
    
    public $selectedCustomerId = null;
    public $customerTickets = [];
    public $customerInfo = [];
    public $customerOrders = [];
    
    public $recoveryForm = [
        'ticket_count' => 3,
        'timeframe' => '7',
        'discount_percentage' => 10,
        'discount_days' => 30,
        'escalate_to_manager' => true,
        'create_followup_task' => true
    ];
    
    public $selectedTicketId = null;
    public $selectedTicketDetails = null;
    public $replyText = '';
    public $replyIsInternal = false;
    public $replies = [];
    public $showConversationModal = false;
    
    public function mount()
    {
        $this->loadStats();
        $this->loadCustomers();
        $this->loadRecentRecoveryTickets();
    }
    
    public function loadStats()
    {
        // Count customers with 3+ tickets in last 7 days
        $atRiskCustomers = DB::select("
            SELECT COUNT(DISTINCT c.customer_id) as count
            FROM customers c
            INNER JOIN tickets t ON c.customer_id = t.customer_id
            WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY c.customer_id
            HAVING COUNT(t.ticket_id) >= 3
        ");
        
        $this->stats = [
            'at_risk_customers' => $atRiskCustomers[0]->count ?? 0,
            'high_value_customers' => DB::table('customers')->count(),
            'total_tickets_week' => DB::table('tickets')->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'recovery_tickets' => DB::table('tickets')->where('subject', 'LIKE', '%Service Recovery%')->count(),
        ];
    }
    
    public function loadCustomers()
    {
        $this->customers = DB::select("
            SELECT 
                c.customer_id,
                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                COALESCE(SUM(so.grand_total), 0) as total_spent,
                COUNT(t.ticket_id) as total_tickets,
                SUM(CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_tickets
            FROM customers c
            LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
            LEFT JOIN tickets t ON c.customer_id = t.customer_id
            GROUP BY c.customer_id, c.first_name, c.last_name, c.email, c.phone
            HAVING recent_tickets >= 3
            ORDER BY recent_tickets DESC, total_tickets DESC
            LIMIT 50
        ");
    }
    
    public function loadRecentRecoveryTickets()
    {
        $this->recentRecoveryTickets = DB::table('tickets as t')
            ->join('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->where('t.subject', 'LIKE', '%Service Recovery%')
            ->orWhere('t.subject', 'LIKE', '%Goodwill%')
            ->orWhere('t.subject', 'LIKE', '%Discount%')
            ->select('t.*', 'c.first_name', 'c.last_name', 'c.email', 'u.full_name as agent_name')
            ->orderBy('t.created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    public function reviewCustomer($customerId)
    {
        $this->selectCustomer($customerId);
    }
    
    public function selectCustomer($customerId)
    {
        $this->selectedCustomerId = $customerId;
        $this->selectedTicketId = null;
        $this->selectedTicketDetails = null;
        $this->replyText = '';
        $this->replies = [];
        $this->showConversationModal = false;
        
        // Load customer info
        $this->customerInfo = DB::table('customers')
            ->where('customer_id', $customerId)
            ->first() ?? [];
        
        // Load customer tickets from last 30 days
        $this->customerTickets = DB::table('tickets as t')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->where('t.customer_id', $customerId)
            ->where('t.created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('t.created_at', 'desc')
            ->select('t.*', 'u.full_name as agent_name')
            ->get()
            ->toArray();
        
        // Load customer orders
        $this->customerOrders = DB::table('sales_orders')
            ->where('customer_id', $customerId)
            ->orderBy('order_date', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
    
    public function viewTicket($ticketId)
    {
        $this->selectedTicketId = $ticketId;
        $this->loadTicketReplies($ticketId);
        $this->showConversationModal = true;
        
        // Also load the customer info if we have it
        $ticket = DB::table('tickets')
            ->where('ticket_id', $ticketId)
            ->first();
        
        if ($ticket && $ticket->customer_id) {
            $this->selectedCustomerId = $ticket->customer_id;
            $this->loadCustomerInfo($ticket->customer_id);
        }
    }
    
    private function loadCustomerInfo($customerId)
    {
        $this->customerInfo = DB::table('customers')
            ->where('customer_id', $customerId)
            ->first() ?? [];
        
        $this->customerTickets = DB::table('tickets as t')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->where('t.customer_id', $customerId)
            ->where('t.created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('t.created_at', 'desc')
            ->select('t.*', 'u.full_name as agent_name')
            ->get()
            ->toArray();
    }
    
    public function loadTicketReplies($ticketId)
    {
        $this->selectedTicketId = $ticketId;
        
        // Load ticket details
        $this->selectedTicketDetails = DB::table('tickets as t')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->leftJoin('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->where('t.ticket_id', $ticketId)
            ->select('t.*', 'u.full_name as agent_name', 'c.first_name', 'c.last_name', 'c.email as customer_email')
            ->first();
        
        // Ensure ticket_notes table exists
        $this->ensureTicketNotesTable();
        
        // Get the ticket first to show as initial message
        $ticket = $this->selectedTicketDetails;
        
        $this->replies = [];
        
        // Add the ticket itself as the first message in the conversation
        if ($ticket) {
            $this->replies[] = (object) [
                'note_id' => 0,
                'ticket_id' => $ticketId,
                'note_text' => "**Ticket Created**\n\n" . 
                              "**Subject:** {$ticket->subject}\n" .
                              "**Description:**\n{$ticket->issue_description}\n\n" .
                              "**Priority:** " . ucfirst($ticket->priority) . "\n" .
                              "**Status:** " . ucfirst($ticket->status) . "\n" .
                              ($ticket->agent_name ? "**Assigned To:** {$ticket->agent_name}\n" : ""),
                'created_by' => null,
                'author_name' => $ticket->first_name . ' ' . $ticket->last_name . ' (Customer)',
                'internal' => false,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
                'is_ticket' => true
            ];
        }
        
        // Load all notes for this ticket
        $notes = DB::table('ticket_notes')
            ->leftJoin('users', 'ticket_notes.created_by', '=', 'users.user_id')
            ->where('ticket_notes.ticket_id', $ticketId)
            ->orderBy('ticket_notes.created_at', 'asc')
            ->select('ticket_notes.*', 'users.full_name as author_name')
            ->get();
        
        foreach ($notes as $note) {
            $this->replies[] = (object) array_merge((array) $note, ['is_ticket' => false]);
        }
    }
    
    private function ensureTicketNotesTable()
    {
        if (!Schema::hasTable('ticket_notes')) {
            Schema::create('ticket_notes', function ($table) {
                $table->bigIncrements('note_id');
                $table->unsignedBigInteger('ticket_id');
                $table->text('note_text');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('internal')->default(false);
                $table->text('attachments')->nullable();
                $table->timestamps();
                
                $table->foreign('ticket_id')->references('ticket_id')->on('tickets')->onDelete('cascade');
                if (Schema::hasTable('users')) {
                    $table->foreign('created_by')->references('user_id')->on('users')->onDelete('set null');
                }
            });
        }
    }
    
    public function addReply()
{
    if (!$this->selectedTicketId || empty(trim($this->replyText))) {
        session()->flash('error', 'Please select a ticket and enter reply text');
        return;
    }
    
    try {
        $this->ensureTicketNotesTable();
        
        $currentUserId = auth()->id();
        $currentUserName = auth()->user()->full_name ?? auth()->user()->name ?? 'System';
        
        // Debug: Check if we have auth user
        if (!$currentUserId) {
            session()->flash('error', 'No authenticated user found. Please login.');
            return;
        }
        
        // Debug: Check if ticket exists
        $ticketExists = DB::table('tickets')
            ->where('ticket_id', $this->selectedTicketId)
            ->exists();
            
        if (!$ticketExists) {
            session()->flash('error', 'Ticket not found in database');
            return;
        }
        
        // Insert the note
        $noteId = DB::table('ticket_notes')->insertGetId([
            'ticket_id' => $this->selectedTicketId,
            'note_text' => $this->replyText,
            'created_by' => $currentUserId,
            'internal' => $this->replyIsInternal,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Update ticket's updated_at timestamp
        DB::table('tickets')
            ->where('ticket_id', $this->selectedTicketId)
            ->update(['updated_at' => now()]);
        
        // Clear reply form
        $this->replyText = '';
        $this->replyIsInternal = false;
        
        // IMPORTANT: Reload replies WITHOUT closing the modal
        $this->loadTicketReplies($this->selectedTicketId);
        
        session()->flash('success', 'Reply added successfully (Note ID: ' . $noteId . ')');
        
        // Refresh recent tickets list if needed
        $this->loadRecentRecoveryTickets();
        
    } catch (\Exception $e) {
        session()->flash('error', 'Failed to add reply: ' . $e->getMessage());
        
        // Add more detailed error info
        if (str_contains($e->getMessage(), 'SQLSTATE')) {
            session()->flash('error', 'Database error: ' . $e->getMessage());
        }
    }
}
    
    public function closeConversationModal()
    {
        $this->showConversationModal = false;
        $this->selectedTicketId = null;
        $this->selectedTicketDetails = null;
        $this->replies = [];
        $this->replyText = '';
        $this->replyIsInternal = false;
    }
    
    public function triggerServiceRecovery()
    {
        if (!$this->selectedCustomerId) {
            session()->flash('error', 'Please select a customer first');
            return;
        }
        
        try {
            DB::beginTransaction();
            
            // Count recent tickets
            $recentTicketCount = DB::table('tickets')
                ->where('customer_id', $this->selectedCustomerId)
                ->where('created_at', '>=', Carbon::now()->subDays($this->recoveryForm['timeframe']))
                ->count();
            
            if ($recentTicketCount < $this->recoveryForm['ticket_count']) {
                session()->flash('warning', "Customer only has {$recentTicketCount} tickets in the last {$this->recoveryForm['timeframe']} days (requires {$this->recoveryForm['ticket_count']})");
                return;
            }
            
            // Generate ticket number
            $ticketNumber = 'SR-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            // Get current logged-in user (agent)
            $currentUserId = auth()->id() ?? 1;
            $currentUserName = auth()->user()->full_name ?? auth()->user()->name ?? 'System';
            
            // Create service recovery ticket
            $ticketId = DB::table('tickets')->insertGetId([
                'ticket_number' => $ticketNumber,
                'customer_id' => $this->selectedCustomerId,
                'assigned_agent' => $currentUserId,
                'subject' => 'Service Recovery - Multiple Issues Detected',
                'issue_description' => "**AUTOMATED SERVICE RECOVERY TICKET**\n\n" .
                                      "Customer has submitted {$recentTicketCount} tickets in the last {$this->recoveryForm['timeframe']} days.\n\n" .
                                      "**Recommended Actions:**\n" .
                                      "1. Personal follow-up call\n" .
                                      "2. Offer goodwill discount\n" .
                                      "3. Escalate to account manager if high-value customer\n" .
                                      "4. Document recovery attempt in ticket notes",
                'priority' => 'high',
                'status' => 'open',
                'category' => 'complaint',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->ensureTicketNotesTable();
            
            // Generate discount code
            $discountCode = 'RECOVERY-' . strtoupper(substr(uniqid(), -8));
            
            // Add initial system note
            DB::table('ticket_notes')->insert([
                'ticket_id' => $ticketId,
                'note_text' => "**SERVICE RECOVERY INITIATED**\n\n" .
                              "Triggered: " . now()->format('Y-m-d H:i:s') . "\n" .
                              "Reason: {$recentTicketCount} tickets in {$this->recoveryForm['timeframe']} days\n" .
                              "Discount Setting: {$this->recoveryForm['discount_percentage']}% for {$this->recoveryForm['discount_days']} days\n" .
                              "Manager Escalation: " . ($this->recoveryForm['escalate_to_manager'] ? 'Yes' : 'No') . "\n" .
                              "Follow-up Task: " . ($this->recoveryForm['create_followup_task'] ? 'Yes' : 'No'),
                'created_by' => $currentUserId,
                'internal' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Add customer-facing goodwill message
            DB::table('ticket_notes')->insert([
                'ticket_id' => $ticketId,
                'note_text' => "**GOODWILL DISCOUNT OFFERED**\n\n" .
                              "Dear {$this->customerInfo->first_name},\n\n" .
                              "We sincerely apologize for the recent issues you've experienced. As a gesture of goodwill, " .
                              "we're offering you a {$this->recoveryForm['discount_percentage']}% discount on your next purchase.\n\n" .
                              "**Discount Code:** {$discountCode}\n" .
                              "**Discount Amount:** {$this->recoveryForm['discount_percentage']}% off\n" .
                              "**Valid Until:** " . now()->addDays($this->recoveryForm['discount_days'])->format('F d, Y') . "\n\n" .
                              "To use this discount, simply enter the code at checkout.\n\n" .
                              "We value your business and are committed to providing you with better service moving forward.\n\n" .
                              "Best regards,\n" .
                              "The Customer Service Team",
                'created_by' => $currentUserId,
                'internal' => false,
                'attachments' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            // Refresh data
            $this->loadStats();
            $this->loadRecentRecoveryTickets();
            $this->selectCustomer($this->selectedCustomerId);
            
            // Open the new ticket conversation in modal
            $this->viewTicket($ticketId);
            
            session()->flash('success', "Service recovery initiated! Ticket #{$ticketNumber} created and assigned to you. You can now continue the conversation in the replies section below.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to trigger service recovery: ' . $e->getMessage());
        }
    }
    
    public function runAutoRecoveryCheck()
    {
        try {
            DB::beginTransaction();
            
            // Find customers with 3+ tickets in last 7 days
            $atRiskCustomers = DB::select("
                SELECT c.customer_id, c.first_name, c.last_name, c.email, COUNT(t.ticket_id) as ticket_count
                FROM customers c
                INNER JOIN tickets t ON c.customer_id = t.customer_id
                WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY c.customer_id, c.first_name, c.last_name, c.email
                HAVING COUNT(t.ticket_id) >= 3
            ");
            
            $currentUserId = auth()->id() ?? 1;
            $recoveryCount = 0;
            
            foreach ($atRiskCustomers as $customer) {
                // Check if already has a recent recovery ticket
                $hasRecentRecovery = DB::table('tickets')
                    ->where('customer_id', $customer->customer_id)
                    ->where('subject', 'LIKE', '%Service Recovery%')
                    ->where('created_at', '>=', Carbon::now()->subDays(14))
                    ->exists();
                
                if (!$hasRecentRecovery) {
                    // Create auto-recovery ticket
                    $ticketNumber = 'AUTO-SR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    $ticketId = DB::table('tickets')->insertGetId([
                        'ticket_number' => $ticketNumber,
                        'customer_id' => $customer->customer_id,
                        'assigned_agent' => $currentUserId,
                        'subject' => 'Automated Service Recovery - Multiple Issues',
                        'issue_description' => "**AUTOMATED SERVICE RECOVERY**\n\n" .
                                              "System detected {$customer->ticket_count} tickets from this customer in the last 7 days.\n\n" .
                                              "Customer: {$customer->first_name} {$customer->last_name}\n" .
                                              "Email: {$customer->email}\n\n" .
                                              "**Action Required:**\n" .
                                              "1. Review recent tickets\n" .
                                              "2. Consider goodwill gesture\n" .
                                              "3. Personal follow-up recommended\n" .
                                              "4. Add goodwill messages to ticket notes",
                        'priority' => 'medium',
                        'status' => 'open',
                        'category' => 'complaint',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $this->ensureTicketNotesTable();
                    
                    // Add initial note
                    DB::table('ticket_notes')->insert([
                        'ticket_id' => $ticketId,
                        'note_text' => "Auto-generated by Service Recovery System\n" .
                                      "Trigger: {$customer->ticket_count} tickets in 7 days\n" .
                                      "Time: " . now()->format('Y-m-d H:i:s'),
                        'created_by' => $currentUserId,
                        'internal' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $recoveryCount++;
                }
            }
            
            DB::commit();
            
            $this->loadStats();
            $this->loadRecentRecoveryTickets();
            
            session()->flash('success', "Auto-recovery check completed! Created {$recoveryCount} new recovery tickets assigned to you.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Auto-recovery check failed: ' . $e->getMessage());
        }
    }
    
    public function reviewAndRecover($customerId)
    {
        $this->selectCustomer($customerId);
        $this->triggerServiceRecovery();
    }
    
    public function sendDiscountToCustomer()
    {
        if (!$this->selectedCustomerId) {
            session()->flash('error', 'Please select a customer first');
            return;
        }
        
        $currentUserId = auth()->id() ?? 1;
        $discountCode = 'GOODWILL-' . strtoupper(substr(uniqid(), -8));
        
        // Create a special ticket for goodwill discount
        $ticketId = DB::table('tickets')->insertGetId([
            'ticket_number' => 'GW-' . date('Ymd') . '-' . str_pad(DB::table('tickets')->count() + 1, 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->selectedCustomerId,
            'assigned_agent' => $currentUserId,
            'subject' => 'Goodwill Gesture - Discount Offer',
            'issue_description' => "Goodwill discount offer for exceptional customer service recovery.",
            'priority' => 'low',
            'status' => 'open',
            'category' => 'general',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->ensureTicketNotesTable();
        
        // Add goodwill discount note (customer-facing)
        DB::table('ticket_notes')->insert([
            'ticket_id' => $ticketId,
            'note_text' => "**GOODWILL DISCOUNT OFFER**\n\n" .
                          "Dear {$this->customerInfo->first_name},\n\n" .
                          "Thank you for your patience with the recent issues. As a token of our appreciation, " .
                          "we'd like to offer you a special discount on your next purchase.\n\n" .
                          "**Discount Code:** {$discountCode}\n" .
                          "**Discount Amount:** {$this->recoveryForm['discount_percentage']}% off\n" .
                          "**Valid Until:** " . now()->addDays($this->recoveryForm['discount_days'])->format('F d, Y') . "\n\n" .
                          "Simply enter this code at checkout to redeem your discount.\n\n" .
                          "We value your business and look forward to serving you better.\n\n" .
                          "Best regards,\nThe Customer Service Team",
            'created_by' => $currentUserId,
            'internal' => false,
            'attachments' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->loadRecentRecoveryTickets();
        $this->selectCustomer($this->selectedCustomerId);
        session()->flash('success', "Goodwill discount created! Discount code: {$discountCode}");
    }
}
?>

<div class="helpdesk-content">
    <div class="page-header">
        <h1>Service Recovery System</h1>
        <p>Automated customer retention using existing database tables</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['at_risk_customers'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">Customers at Risk</div>
            <div class="text-xs text-red-500 mt-1">3+ tickets in 7 days</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['high_value_customers'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">Total Customers</div>
            <div class="text-xs text-green-500 mt-1">In database</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['total_tickets_week'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">Weekly Tickets</div>
            <div class="text-xs text-blue-500 mt-1">Last 7 days</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['recovery_tickets'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">Recovery Tickets</div>
            <div class="text-xs text-purple-500 mt-1">Created</div>
        </div>
    </div>

    <div class="helpdesk-grid">
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- At-Risk Customers -->
            <div class="page-card">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Customers Needing Recovery (3+ tickets in 7 days)</h3>
                    <div class="flex space-x-2">
                        <button wire:click="runAutoRecoveryCheck" class="btn btn-primary">
                            <i class="fas fa-robot mr-2"></i>Auto-Recover All
                        </button>
                    </div>
                </div>
                
                @if(!empty($customers))
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                            <span class="font-semibold text-red-700">{{ count($customers) }} customers need immediate attention</span>
                        </div>
                        <p class="text-sm text-red-600 mt-1">Each customer below has submitted 3 or more tickets in the past week.</p>
                    </div>
                @endif
                
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Total Spent</th>
                                <th>Total Tickets</th>
                                <th>Recent (7d)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                <tr class="bg-red-50 hover:bg-red-100">
                                    <td class="font-medium">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                                <i class="fas fa-exclamation text-red-600 text-sm"></i>
                                            </div>
                                            <div>
                                                {{ $customer->first_name }} {{ $customer->last_name }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm">{{ $customer->email }}</div>
                                        @if($customer->phone)
                                            <div class="text-xs text-gray-500">{{ $customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="font-semibold">${{ number_format($customer->total_spent, 2) }}</td>
                                    <td>{{ $customer->total_tickets }}</td>
                                    <td>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-ticket-alt mr-1"></i>
                                            {{ $customer->recent_tickets }}
                                        </span>
                                    </td>
                                    <td>
                                        <button wire:click="reviewCustomer({{ $customer->customer_id }})" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye mr-1"></i> Review
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            @if(empty($customers))
                                <tr>
                                    <td colspan="6" class="text-center py-8">
                                        <div class="text-gray-500">
                                            <i class="fas fa-check-circle fa-2x mb-3 opacity-50"></i>
                                            <p class="text-lg font-medium">No critical customers found</p>
                                            <p class="text-sm mt-1">No customers have 3+ tickets in the past week</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                
                @if(!empty($customers))
                    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                            <span class="font-medium text-blue-700">Quick Recovery Options:</span>
                        </div>
                        <div class="flex space-x-3 mt-2">
                            <button wire:click="runAutoRecoveryCheck" class="btn btn-primary btn-sm">
                                <i class="fas fa-bolt mr-1"></i> Auto-Recover All {{ count($customers) }} Customers
                            </button>
                            <div class="text-xs text-blue-600 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Creates recovery tickets for all customers above
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Recent Recovery Tickets -->
            <div class="page-card">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Recent Recovery Tickets</h3>
                
                <div class="space-y-4">
                    @foreach($recentRecoveryTickets as $ticket)
                        <div class="border rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer 
                                    {{ $selectedTicketId == $ticket->ticket_id ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : '' }}"
                             wire:click="viewTicket({{ $ticket->ticket_id }})">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-semibold">{{ $ticket->subject }}</h4>
                                    <p class="text-sm text-gray-600">{{ $ticket->first_name }} {{ $ticket->last_name }}</p>
                                    @if($ticket->agent_name)
                                        <p class="text-xs text-blue-600">
                                            <i class="fas fa-user-circle mr-1"></i>
                                            Assigned to: {{ $ticket->agent_name }}
                                        </p>
                                    @endif
                                </div>
                                <span class="text-xs {{ $ticket->priority === 'high' ? 'text-red-600' : 'text-yellow-600' }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 mb-3 line-clamp-2">{{ Str::limit($ticket->issue_description, 150) }}</p>
                            <div class="flex justify-between items-center text-xs text-gray-500">
                                <span>{{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, H:i') }}</span>
                                <span class="{{ $ticket->status === 'closed' ? 'text-green-600' : 'text-blue-600' }}">
                                    {{ ucfirst($ticket->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                    @if(empty($recentRecoveryTickets))
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                            <p>No recovery tickets yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="helpdesk-sidebar">
            <h3>
                <i class="fas fa-user-circle"></i>
                Customer Details
            </h3>
            
            @if(!empty($customerInfo))
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-lg">
                                {{ $customerInfo->first_name }} {{ $customerInfo->last_name }}
                            </h4>
                            <p class="text-gray-600 text-sm">{{ $customerInfo->email }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="text-center p-3 bg-white rounded border">
                            <div class="text-2xl font-bold text-red-600">
                                {{ count($customerTickets) }}
                            </div>
                            <div class="text-sm text-gray-500">Recent Tickets (30d)</div>
                        </div>
                        <div class="text-center p-3 bg-white rounded border">
                            <div class="text-2xl font-bold text-green-600">
                                ₱{{ number_format(collect($customerOrders)->sum('grand_total'), 2) }}
                            </div>
                            <div class="text-sm text-gray-500">Total Orders</div>
                        </div>
                    </div>
                    
                    <!-- Recovery Actions -->
                    <div class="border-t pt-4 mt-4">
                        <button wire:click="triggerServiceRecovery" 
                                class="btn btn-primary w-full mb-4">
                            <i class="fas fa-bullhorn mr-2"></i>
                            Trigger Service Recovery
                        </button>
                    </div>
                    
                    <!-- Recovery Form -->
                    <div class="border-t pt-4 mt-4">
                        <h5 class="font-semibold mb-3">Service Recovery Settings</h5>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Ticket Threshold</label>
                                <input type="range" wire:model.live="recoveryForm.ticket_count" 
                                       min="1" max="10" class="w-full"
                                       oninput="this.nextElementSibling.value = this.value + ' tickets'">
                                <output class="text-sm text-gray-600">{{ $recoveryForm['ticket_count'] }} tickets</output>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-700">Timeframe (days)</label>
                                <select wire:model="recoveryForm.timeframe" class="form-input text-sm">
                                    <option value="3">3 days</option>
                                    <option value="7">7 days</option>
                                    <option value="14">14 days</option>
                                    <option value="30">30 days</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Discount %</label>
                                    <input type="number" wire:model="recoveryForm.discount_percentage" 
                                           class="form-input text-sm" min="1" max="50">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Valid Days</label>
                                    <input type="number" wire:model="recoveryForm.discount_days" 
                                           class="form-input text-sm" min="1" max="365">
                                </div>
                            </div>
                        
                        </div>
                        
                        <!-- Send Goodwill Discount Button at the bottom of the form -->
                        <div class="mt-6 pt-4 border-t">
                            <button wire:click="sendDiscountToCustomer" 
                                    class="btn btn-success w-full">
                                <i class="fas fa-gift mr-2"></i>
                                Send Goodwill Discount Only
                            </button>
                            <p class="text-xs text-gray-500 mt-2 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Sends discount without creating a full recovery ticket
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Recent Tickets -->
            @if(!empty($customerInfo))
                <h4 class="font-semibold mb-3">Recent Tickets (30 days)</h4>
                <div class="space-y-2 mb-6">
                    @foreach($customerTickets as $ticket)
                        <div class="p-3 border rounded bg-white hover:bg-gray-50 cursor-pointer 
                                    {{ $selectedTicketId == $ticket->ticket_id ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : '' }}"
                             wire:click="viewTicket({{ $ticket->ticket_id }})">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">{{ $ticket->subject }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, H:i') }}
                                        • {{ ucfirst($ticket->status) }}
                                    </div>
                                    @if($ticket->agent_name)
                                        <div class="text-xs text-blue-600 mt-1">
                                            <i class="fas fa-user mr-1"></i>
                                            {{ $ticket->agent_name }}
                                        </div>
                                    @endif
                                </div>
                                <span class="text-xs px-2 py-1 rounded 
                                    {{ $ticket->priority === 'critical' ? 'bg-red-100 text-red-800' : 
                                       ($ticket->priority === 'high' ? 'bg-orange-100 text-orange-800' : 
                                       ($ticket->priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                    @if(empty($customerTickets))
                        <p class="text-gray-500 text-center py-4">No recent tickets</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

   <!-- Ticket Conversation Modal -->
@if($showConversationModal && $selectedTicketDetails)
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" wire:key="ticket-modal-{{ $selectedTicketId }}">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold">Ticket Conversation</h3>
                    <div class="text-sm text-blue-100 mt-1">
                        <span class="font-medium">{{ $selectedTicketDetails->first_name }} {{ $selectedTicketDetails->last_name }}</span>
                        • {{ $selectedTicketDetails->customer_email }}
                    </div>
                </div>
                <button wire:click="closeConversationModal" 
                        type="button"
                        class="text-white hover:text-blue-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="mt-4 flex flex-wrap gap-3">
                <div class="bg-blue-700 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-ticket-alt mr-2"></i>
                    {{ $selectedTicketDetails->ticket_number }}
                </div>
                <div class="bg-blue-700 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-tag mr-2"></i>
                    {{ $selectedTicketDetails->subject }}
                </div>
                <div class="{{ $selectedTicketDetails->priority === 'high' || $selectedTicketDetails->priority === 'critical' ? 'bg-red-500' : 'bg-yellow-500' }} px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-flag mr-2"></i>
                    {{ ucfirst($selectedTicketDetails->priority) }}
                </div>
                <div class="{{ $selectedTicketDetails->status === 'open' ? 'bg-yellow-500' : ($selectedTicketDetails->status === 'closed' ? 'bg-gray-500' : 'bg-green-500') }} px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-circle mr-2"></i>
                    {{ ucfirst($selectedTicketDetails->status) }}
                </div>
                @if($selectedTicketDetails->agent_name)
                    <div class="bg-blue-700 px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-user-circle mr-2"></i>
                        {{ $selectedTicketDetails->agent_name }}
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Flash Messages -->
        @if(session()->has('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mx-6 my-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if(session()->has('error'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mx-6 my-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
        @endif

        @if(session()->has('warning'))
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mx-6 my-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">{{ session('warning') }}</p>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Modal Body -->
        <div class="flex flex-col h-[70vh]">
            <!-- Conversation Thread -->
            <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <div class="space-y-4">
                    @foreach($replies as $reply)
                        <div class="{{ $reply->internal ? 'bg-yellow-50 border-yellow-200' : ($reply->is_ticket ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200') }} border rounded-lg p-4 shadow-sm">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full {{ $reply->internal ? 'bg-yellow-100' : ($reply->is_ticket ? 'bg-blue-100' : 'bg-gray-100') }} flex items-center justify-center mr-3">
                                        @if($reply->is_ticket)
                                            <i class="fas fa-ticket-alt {{ $reply->internal ? 'text-yellow-600' : 'text-blue-600' }} text-sm"></i>
                                        @else
                                            <i class="fas fa-user {{ $reply->internal ? 'text-yellow-600' : 'text-gray-600' }} text-sm"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            @if($reply->author_name)
                                                {{ $reply->author_name }}
                                            @elseif($reply->is_ticket)
                                                <span class="text-blue-700">
                                                    <i class="fas fa-ticket-alt mr-1"></i>
                                                    Ticket Created
                                                </span>
                                            @else
                                                {{ auth()->user()->full_name ?? 'System' }}
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($reply->created_at)->format('F j, Y g:i A') }}
                                        </div>
                                    </div>
                                </div>
                                @if($reply->internal)
                                    <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium">
                                        <i class="fas fa-lock mr-1"></i>
                                        Internal
                                    </span>
                                @endif
                            </div>
                            <div class="text-gray-700 whitespace-pre-line text-sm leading-relaxed">{{ $reply->note_text }}</div>
                        </div>
                    @endforeach
                    @if(empty($replies))
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-comments fa-3x"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No conversation yet</h4>
                            <p class="text-gray-600">Start the conversation by sending the first message below.</p>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Reply Form -->
            <div class="border-t border-gray-200 p-6 bg-white">
                <h5 class="font-semibold text-gray-900 mb-4">Add Reply</h5>
                <textarea wire:model.live="replyText" 
          rows="4"
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-4"
          placeholder="Type your message here..."
          wire:keydown.enter.prevent></textarea>

                
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="replyIsInternal" class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-lock mr-1"></i>
                            Internal note (not visible to customer)
                        </span>
                    </label>
                    
                    <div class="flex space-x-3">
                        <button wire:click="closeConversationModal" 
                                type="button"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button wire:click="addReply" 
                                type="button"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                                {{ empty(trim($this->replyText)) ? 'disabled' : '' }}>
                            <span wire:loading.remove>
                                <i class="fas fa-paper-plane mr-2"></i>
                                Send Message
                            </span>
                            <span wire:loading>
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Sending...
                            </span>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Messages are stored in ticket_notes table. Internal notes are only visible to support agents.
                </p>
            </div>
        </div>
    </div>
</div>
@endif
</div>
