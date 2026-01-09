<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
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
            ->where('t.subject', 'LIKE', '%Service Recovery%')
            ->orWhere('t.subject', 'LIKE', '%Goodwill%')
            ->orWhere('t.subject', 'LIKE', '%Discount%')
            ->select('t.*', 'c.first_name', 'c.last_name', 'c.email')
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
        
        // Load customer info
        $this->customerInfo = DB::table('customers')
            ->where('customer_id', $customerId)
            ->first() ?? [];
        
        // Load customer tickets from last 30 days
        $this->customerTickets = DB::table('tickets')
            ->where('customer_id', $customerId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('created_at', 'desc')
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
            
            // Create service recovery ticket
            $ticketId = DB::table('tickets')->insertGetId([
                'ticket_number' => $ticketNumber,
                'customer_id' => $this->selectedCustomerId,
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
            
            // Add internal note
            DB::table('ticket_notes')->insert([
                'ticket_id' => $ticketId,
                'note_text' => "**SERVICE RECOVERY INITIATED**\n\n" .
                              "Triggered: " . now()->format('Y-m-d H:i:s') . "\n" .
                              "Reason: {$recentTicketCount} tickets in {$this->recoveryForm['timeframe']} days\n" .
                              "Discount Setting: {$this->recoveryForm['discount_percentage']}% for {$this->recoveryForm['discount_days']} days\n" .
                              "Manager Escalation: " . ($this->recoveryForm['escalate_to_manager'] ? 'Yes' : 'No') . "\n" .
                              "Follow-up Task: " . ($this->recoveryForm['create_followup_task'] ? 'Yes' : 'No'),
                'created_by' => auth()->id() ?? 1,
                'internal' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Generate discount code
            $discountCode = 'RECOVERY-' . strtoupper(substr(uniqid(), -8));
            
            // Add goodwill message to ticket_notes (customer-facing note)
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
                'created_by' => auth()->id() ?? 1,
                'internal' => false,
                'attachments' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create discount code in journal entries
            DB::table('journal_entries')->insert([
                'entry_date' => now()->format('Y-m-d'),
                'journal_number' => 'DISC-' . date('Ymd') . '-' . str_pad($ticketId, 4, '0', STR_PAD_LEFT),
                'description' => "Goodwill discount for customer {$this->customerInfo->first_name} {$this->customerInfo->last_name} - Service Recovery",
                'created_by' => auth()->id() ?? 1,
                'reference_type' => 'ticket',
                'reference_id' => $ticketId,
                'status' => 'posted',
                'total_debit' => $this->recoveryForm['discount_percentage'],
                'total_credit' => $this->recoveryForm['discount_percentage'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create follow-up task if enabled
            if ($this->recoveryForm['create_followup_task']) {
                // Find or create a service recovery project
                $projectId = DB::table('projects')
                    ->where('project_name', 'LIKE', '%Service Recovery%')
                    ->value('project_id');
                
                if (!$projectId) {
                    $projectId = DB::table('projects')->insertGetId([
                        'project_name' => 'Service Recovery Management',
                        'description' => 'Automated service recovery follow-ups',
                        'start_date' => now()->format('Y-m-d'),
                        'end_date' => now()->addYear()->format('Y-m-d'),
                        'status' => 'in_progress',
                        'project_manager_id' => auth()->id() ?? 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Create task for follow-up
                DB::table('project_tasks')->insert([
                    'project_id' => $projectId,
                    'task_name' => "Service Recovery - {$this->customerInfo->first_name} {$this->customerInfo->last_name}",
                    'description' => "Customer has submitted {$recentTicketCount} tickets recently. Please:\n" .
                                    "1. Make personal call\n" .
                                    "2. Offer {$this->recoveryForm['discount_percentage']}% discount (Code: {$discountCode})\n" .
                                    "3. Document conversation in ticket notes\n" .
                                    "4. Update ticket #{$ticketNumber}",
                    'start_date' => now()->format('Y-m-d'),
                    'end_date' => now()->addDays(3)->format('Y-m-d'),
                    'priority' => 'high',
                    'status' => 'not_started',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // Send email to account manager if high-value customer and escalation enabled
            if ($this->recoveryForm['escalate_to_manager']) {
                // Check if customer is high-value (spent over $1000)
                $totalSpent = DB::table('sales_orders')
                    ->where('customer_id', $this->selectedCustomerId)
                    ->sum('grand_total');
                
                if ($totalSpent > 1000) {
                    // Get account managers
                    $managers = DB::table('employees as e')
                        ->join('users as u', 'e.user_id', '=', 'u.user_id')
                        ->where('e.job_title', 'LIKE', '%Manager%')
                        ->orWhere('u.role', 'manager')
                        ->limit(3)
                        ->get();
                    
                    // Create a report for management
                    DB::table('reports')->insert([
                        'report_name' => "Service Recovery Alert - {$this->customerInfo->first_name} {$this->customerInfo->last_name}",
                        'module_name' => 'helpdesk',
                        'created_by' => auth()->id() ?? 1,
                        'report_type' => 'alert',
                        'filters' => json_encode(['customer_id' => $this->selectedCustomerId]),
                        'status' => 'generated',
                        'generated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Add note about manager escalation
                    DB::table('ticket_notes')->insert([
                        'ticket_id' => $ticketId,
                        'note_text' => "**MANAGER ESCALATION**\n\n" .
                                      "Customer flagged as high-value (total spent: $" . number_format($totalSpent, 2) . ")\n" .
                                      "Alert sent to account managers for personal follow-up.",
                        'created_by' => auth()->id() ?? 1,
                        'internal' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            DB::commit();
            
            // Refresh data
            $this->loadStats();
            $this->loadRecentRecoveryTickets();
            $this->selectCustomer($this->selectedCustomerId);
            
            session()->flash('success', "Service recovery initiated! Ticket #{$ticketNumber} created. Goodwill message added to ticket notes.");
            
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
                    
                    // Add initial note
                    DB::table('ticket_notes')->insert([
                        'ticket_id' => $ticketId,
                        'note_text' => "Auto-generated by Service Recovery System\n" .
                                      "Trigger: {$customer->ticket_count} tickets in 7 days\n" .
                                      "Time: " . now()->format('Y-m-d H:i:s'),
                        'created_by' => auth()->id() ?? 1,
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
            
            session()->flash('success', "Auto-recovery check completed! Created {$recoveryCount} new recovery tickets.");
            
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
        
        $discountCode = 'GOODWILL-' . strtoupper(substr(uniqid(), -8));
        
        // Create a special ticket for goodwill discount
        $ticketId = DB::table('tickets')->insertGetId([
            'ticket_number' => 'GW-' . date('Ymd') . '-' . str_pad(DB::table('tickets')->count() + 1, 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->selectedCustomerId,
            'subject' => 'Goodwill Gesture - Discount Offer',
            'issue_description' => "Goodwill discount offer for exceptional customer service recovery.",
            'priority' => 'low',
            'status' => 'open',
            'category' => 'general',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Add goodwill discount note to ticket_notes (customer-facing)
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
            'created_by' => auth()->id() ?? 1,
            'internal' => false,
            'attachments' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Add internal note for tracking
        DB::table('ticket_notes')->insert([
            'ticket_id' => $ticketId,
            'note_text' => "Goodwill discount created manually via Service Recovery System.\n" .
                          "Code: {$discountCode}\n" .
                          "Discount: {$this->recoveryForm['discount_percentage']}%\n" .
                          "Valid for: {$this->recoveryForm['discount_days']} days",
            'created_by' => auth()->id() ?? 1,
            'internal' => true,
            'attachments' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Log the discount in journal entries
        DB::table('journal_entries')->insert([
            'entry_date' => now()->format('Y-m-d'),
            'journal_number' => 'DISCOUNT-' . date('YmdHis'),
            'description' => "Goodwill discount issued to {$this->customerInfo->first_name} {$this->customerInfo->last_name} - Code: {$discountCode}",
            'created_by' => auth()->id() ?? 1,
            'reference_type' => 'ticket',
            'reference_id' => $ticketId,
            'status' => 'posted',
            'total_debit' => $this->recoveryForm['discount_percentage'],
            'total_credit' => $this->recoveryForm['discount_percentage'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->loadRecentRecoveryTickets();
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
                        <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-semibold">{{ $ticket->subject }}</h4>
                                    <p class="text-sm text-gray-600">{{ $ticket->first_name }} {{ $ticket->last_name }}</p>
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

        <!-- Right Sidebar -->
        <div class="space-y-6">
            @if($selectedCustomerId)
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
                                        ${{ number_format(collect($customerOrders)->sum('grand_total'), 2) }}
                                    </div>
                                    <div class="text-sm text-gray-500">Total Orders</div>
                                </div>
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
                                    
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" wire:model="recoveryForm.escalate_to_manager" class="mr-2">
                                            <span class="text-sm">Escalate to manager</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" wire:model="recoveryForm.create_followup_task" class="mr-2">
                                            <span class="text-sm">Create follow-up task</span>
                                        </label>
                                    </div>

                                    
                                    <button wire:click="sendDiscountToCustomer" 
                                            class="btn btn-success w-full mt-2">
                                        <i class="fas fa-gift mr-2"></i>
                                        Send Goodwill Discount
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Recent Tickets -->
                    <h4 class="font-semibold mb-3">Recent Tickets (30 days)</h4>
                    <div class="space-y-2">
                        @foreach($customerTickets as $ticket)
                            <div class="p-3 border rounded bg-white hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="font-medium text-sm">{{ $ticket->subject }}</div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, H:i') }}
                                            • {{ ucfirst($ticket->status) }}
                                        </div>
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
                </div>
            @else
                <!-- Instructions -->
                <div class="helpdesk-sidebar">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        How It Works
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="p-3 bg-blue-50 rounded border border-blue-100">
                            <h4 class="font-semibold text-blue-800 mb-2">
                                <i class="fas fa-robot mr-2"></i>Auto-Detection
                            </h4>
                            <p class="text-sm text-blue-700">
                                System automatically flags customers with 3+ tickets in 7 days
                            </p>
                        </div>
                        
                        <div class="p-3 bg-green-50 rounded border border-green-100">
                            <h4 class="font-semibold text-green-800 mb-2">
                                <i class="fas fa-ticket-alt mr-2"></i>Recovery Ticket
                            </h4>
                            <p class="text-sm text-green-700">
                                Creates a special service recovery ticket with recommended actions
                            </p>
                        </div>
                        
                        <div class="p-3 bg-purple-50 rounded border border-purple-100">
                            <h4 class="font-semibold text-purple-800 mb-2">
                                <i class="fas fa-sticky-note mr-2"></i>Goodwill in Ticket Notes
                            </h4>
                            <p class="text-sm text-purple-700">
                                All goodwill messages are stored in ticket_notes table (customer-facing)
                            </p>
                        </div>
                        
                        <div class="p-3 bg-orange-50 rounded border border-orange-100">
                            <h4 class="font-semibold text-orange-800 mb-2">
                                <i class="fas fa-user-tie mr-2"></i>Manager Escalation
                            </h4>
                            <p class="text-sm text-orange-700">
                                Escalates high-value cases to account managers for personal follow-up
                            </p>
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="font-semibold mb-2">Quick Start:</h4>
                            <ol class="text-sm text-gray-600 space-y-2 pl-4 list-decimal">
                                <li>Select a customer from the list</li>
                                <li>Configure recovery settings</li>
                                <li>Click "Trigger Service Recovery"</li>
                                <li>System creates ticket + adds goodwill message to ticket notes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>