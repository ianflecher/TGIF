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
    public $slaMetrics = [];
    public $slaViolations = [];
    
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
    
    // SLA Settings
    public $slaSettings = [
        'first_response_time' => 4, // hours
        'resolution_time_critical' => 8, // hours
        'resolution_time_high' => 24, // hours
        'resolution_time_medium' => 72, // hours
        'resolution_time_low' => 168, // hours (7 days)
    ];
    
    public $showSLAModal = false;
    public $slaUpdateMessage = '';
    
    public function mount()
    {
        $this->initializeSLATables();
        $this->loadStats();
        $this->loadCustomers();
        $this->loadRecentRecoveryTickets();
        $this->loadSLAMetrics();
        $this->loadSLAViolations();
    }
    
    private function initializeSLATables()
    {
        // Create SLA policies table if not exists
        if (!Schema::hasTable('sla_policies')) {
            Schema::create('sla_policies', function ($table) {
                $table->bigIncrements('sla_id');
                $table->string('policy_name');
                $table->string('ticket_category')->nullable();
                $table->string('ticket_priority');
                $table->integer('first_response_time')->nullable()->comment('Hours for first response');
                $table->integer('resolution_time')->comment('Hours for resolution');
                $table->integer('warning_threshold')->default(80)->comment('Percentage for warning');
                $table->string('escalation_contact')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
            });
            
            // Insert default SLA policies
            DB::table('sla_policies')->insert([
                [
                    'policy_name' => 'Critical Priority SLA',
                    'ticket_priority' => 'critical',
                    'first_response_time' => 1,
                    'resolution_time' => 4,
                    'warning_threshold' => 80,
                    'escalation_contact' => 'manager@example.com',
                    'is_active' => true,
                    'description' => 'Critical issues must be resolved within 4 hours',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'policy_name' => 'High Priority SLA',
                    'ticket_priority' => 'high',
                    'first_response_time' => 2,
                    'resolution_time' => 8,
                    'warning_threshold' => 80,
                    'escalation_contact' => 'supervisor@example.com',
                    'is_active' => true,
                    'description' => 'High priority issues must be resolved within 8 hours',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'policy_name' => 'Medium Priority SLA',
                    'ticket_priority' => 'medium',
                    'first_response_time' => 4,
                    'resolution_time' => 24,
                    'warning_threshold' => 80,
                    'escalation_contact' => null,
                    'is_active' => true,
                    'description' => 'Medium priority issues must be resolved within 24 hours',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'policy_name' => 'Low Priority SLA',
                    'ticket_priority' => 'low',
                    'first_response_time' => 8,
                    'resolution_time' => 72,
                    'warning_threshold' => 80,
                    'escalation_contact' => null,
                    'is_active' => true,
                    'description' => 'Low priority issues must be resolved within 72 hours',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
        
        // Create SLA compliance tracking table if not exists
        if (!Schema::hasTable('sla_compliance')) {
            Schema::create('sla_compliance', function ($table) {
                $table->bigIncrements('compliance_id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('sla_id');
                $table->timestamp('first_response_due')->nullable();
                $table->timestamp('resolution_due')->nullable();
                $table->timestamp('first_response_actual')->nullable();
                $table->timestamp('resolution_actual')->nullable();
                $table->boolean('first_response_met')->default(false);
                $table->boolean('resolution_met')->default(false);
                $table->integer('first_response_minutes_late')->default(0);
                $table->integer('resolution_minutes_late')->default(0);
                $table->boolean('warning_sent')->default(false);
                $table->boolean('escalation_sent')->default(false);
                $table->text('compliance_notes')->nullable();
                $table->timestamps();
                
                $table->foreign('ticket_id')->references('ticket_id')->on('tickets')->onDelete('cascade');
                $table->foreign('sla_id')->references('sla_id')->on('sla_policies')->onDelete('cascade');
            });
        }
        
        // Create SLA violations table if not exists
        if (!Schema::hasTable('sla_violations')) {
            Schema::create('sla_violations', function ($table) {
                $table->bigIncrements('violation_id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('sla_id');
                $table->string('violation_type'); // first_response, resolution
                $table->integer('minutes_late');
                $table->string('assigned_agent')->nullable();
                $table->timestamp('violation_time');
                $table->boolean('acknowledged')->default(false);
                $table->timestamp('acknowledged_at')->nullable();
                $table->string('acknowledged_by')->nullable();
                $table->text('violation_notes')->nullable();
                $table->timestamps();
                
                $table->foreign('ticket_id')->references('ticket_id')->on('tickets')->onDelete('cascade');
                $table->foreign('sla_id')->references('sla_id')->on('sla_policies')->onDelete('cascade');
            });
        }
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
        
        // SLA compliance stats
        $slaStats = DB::select("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN sc.first_response_met = 1 THEN 1 ELSE 0 END) as fr_met,
                SUM(CASE WHEN sc.resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met
            FROM sla_compliance sc
            WHERE sc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")[0] ?? (object)['total_tickets' => 0, 'fr_met' => 0, 'resolution_met' => 0];
        
        $frCompliance = $slaStats->total_tickets > 0 ? 
            round(($slaStats->fr_met / $slaStats->total_tickets) * 100, 1) : 0;
        $resolutionCompliance = $slaStats->total_tickets > 0 ? 
            round(($slaStats->resolution_met / $slaStats->total_tickets) * 100, 1) : 0;
        
        $this->stats = [
            'at_risk_customers' => $atRiskCustomers[0]->count ?? 0,
            'high_value_customers' => DB::table('customers')->count(),
            'total_tickets_week' => DB::table('tickets')->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
            'recovery_tickets' => DB::table('tickets')->where('subject', 'LIKE', '%Service Recovery%')->count(),
            'sla_compliance_fr' => $frCompliance,
            'sla_compliance_resolution' => $resolutionCompliance,
            'active_violations' => DB::table('sla_violations')->where('acknowledged', false)->count(),
        ];
    }
    
    public function loadSLAMetrics()
    {
        // Calculate SLA metrics for different priorities
        $this->slaMetrics = DB::select("
            SELECT 
                tp.priority_name,
                COALESCE(sc.total_tickets, 0) as total_tickets,
                COALESCE(sc.fr_met, 0) as fr_met,
                COALESCE(sc.resolution_met, 0) as resolution_met,
                COALESCE(sc.avg_fr_minutes_late, 0) as avg_fr_minutes_late,
                COALESCE(sc.avg_resolution_minutes_late, 0) as avg_resolution_minutes_late
            FROM (
                SELECT 'critical' as priority_name
                UNION SELECT 'high'
                UNION SELECT 'medium'
                UNION SELECT 'low'
            ) tp
            LEFT JOIN (
                SELECT 
                    t.priority,
                    COUNT(DISTINCT t.ticket_id) as total_tickets,
                    SUM(CASE WHEN sc.first_response_met = 1 THEN 1 ELSE 0 END) as fr_met,
                    SUM(CASE WHEN sc.resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met,
                    AVG(CASE WHEN sc.first_response_met = 0 THEN sc.first_response_minutes_late ELSE NULL END) as avg_fr_minutes_late,
                    AVG(CASE WHEN sc.resolution_met = 0 THEN sc.resolution_minutes_late ELSE NULL END) as avg_resolution_minutes_late
                FROM tickets t
                LEFT JOIN sla_compliance sc ON t.ticket_id = sc.ticket_id
                WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY t.priority
            ) sc ON tp.priority_name = sc.priority
        ");
    }
    
    public function loadSLAViolations()
    {
        $this->slaViolations = DB::table('sla_violations as sv')
            ->join('tickets as t', 'sv.ticket_id', '=', 't.ticket_id')
            ->join('sla_policies as sp', 'sv.sla_id', '=', 'sp.sla_id')
            ->leftJoin('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->select(
                'sv.*',
                't.ticket_number',
                't.subject',
                't.priority',
                't.status',
                'c.first_name',
                'c.last_name',
                'c.email',
                'sp.policy_name',
                'sp.resolution_time'
            )
            ->where('sv.acknowledged', false)
            ->orderBy('sv.violation_time', 'desc')
            ->limit(20)
            ->get();
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
            ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
            ->where(function($query) {
                $query->where('t.subject', 'LIKE', '%Service Recovery%')
                      ->orWhere('t.subject', 'LIKE', '%Goodwill%')
                      ->orWhere('t.subject', 'LIKE', '%Discount%');
            })
            ->select(
                't.*', 
                'c.first_name', 
                'c.last_name', 
                'c.email', 
                'u.full_name as agent_name',
                'sc.first_response_met',
                'sc.resolution_met',
                'sc.first_response_minutes_late',
                'sc.resolution_minutes_late'
            )
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
        
        // Load customer tickets from last 30 days with SLA compliance
        $this->customerTickets = DB::table('tickets as t')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
            ->where('t.customer_id', $customerId)
            ->where('t.created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('t.created_at', 'desc')
            ->select('t.*', 'u.full_name as agent_name', 'sc.first_response_met', 'sc.resolution_met')
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
    
    public function checkSLACompliance($ticketId = null)
    {
        try {
            DB::beginTransaction();
            
            $ticketsToCheck = $ticketId ? 
                DB::table('tickets')->where('ticket_id', $ticketId)->get() :
                DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->get();
            
            $processedCount = 0;
            $violationCount = 0;
            
            foreach ($ticketsToCheck as $ticket) {
                // Get SLA policy for this ticket's priority
                $slaPolicy = DB::table('sla_policies')
                    ->where('ticket_priority', $ticket->priority)
                    ->where('is_active', true)
                    ->first();
                
                if (!$slaPolicy) continue;
                
                // Calculate due dates
                $firstResponseDue = Carbon::parse($ticket->created_at)->addHours($slaPolicy->first_response_time);
                $resolutionDue = Carbon::parse($ticket->created_at)->addHours($slaPolicy->resolution_time);
                
                // Check if SLA compliance record exists
                $slaCompliance = DB::table('sla_compliance')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->first();
                
                if (!$slaCompliance) {
                    // Create new SLA compliance record
                    DB::table('sla_compliance')->insert([
                        'ticket_id' => $ticket->ticket_id,
                        'sla_id' => $slaPolicy->sla_id,
                        'first_response_due' => $firstResponseDue,
                        'resolution_due' => $resolutionDue,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    continue;
                }
                
                // Update first response compliance
                $firstResponseNotes = DB::table('ticket_notes')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->where('internal', false)
                    ->where('created_at', '>', $ticket->created_at)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                $firstResponseMet = false;
                $firstResponseMinutesLate = 0;
                $firstResponseActual = null;
                
                if ($firstResponseNotes) {
                    $firstResponseActual = Carbon::parse($firstResponseNotes->created_at);
                    if ($firstResponseActual->lte($firstResponseDue)) {
                        $firstResponseMet = true;
                    } else {
                        $firstResponseMinutesLate = $firstResponseActual->diffInMinutes($firstResponseDue);
                        $this->logSLAViolation($ticket, $slaPolicy, 'first_response', $firstResponseMinutesLate);
                        $violationCount++;
                    }
                } else if (now()->gt($firstResponseDue)) {
                    $firstResponseMinutesLate = now()->diffInMinutes($firstResponseDue);
                    $this->logSLAViolation($ticket, $slaPolicy, 'first_response', $firstResponseMinutesLate);
                    $violationCount++;
                }
                
                // Update resolution compliance
                $resolutionMet = $ticket->status === 'closed' && 
                    Carbon::parse($ticket->updated_at)->lte($resolutionDue);
                $resolutionMinutesLate = 0;
                
                if ($ticket->status === 'closed' && !$resolutionMet) {
                    $resolutionMinutesLate = Carbon::parse($ticket->updated_at)->diffInMinutes($resolutionDue);
                    $this->logSLAViolation($ticket, $slaPolicy, 'resolution', $resolutionMinutesLate);
                    $violationCount++;
                } else if ($ticket->status !== 'closed' && now()->gt($resolutionDue)) {
                    $resolutionMinutesLate = now()->diffInMinutes($resolutionDue);
                    $this->logSLAViolation($ticket, $slaPolicy, 'resolution', $resolutionMinutesLate);
                    $violationCount++;
                }
                
                // Update SLA compliance record
                DB::table('sla_compliance')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->update([
                        'first_response_actual' => $firstResponseActual,
                        'resolution_actual' => $ticket->status === 'closed' ? $ticket->updated_at : null,
                        'first_response_met' => $firstResponseMet,
                        'resolution_met' => $resolutionMet,
                        'first_response_minutes_late' => $firstResponseMinutesLate,
                        'resolution_minutes_late' => $resolutionMinutesLate,
                        'updated_at' => now()
                    ]);
                
                $processedCount++;
            }
            
            DB::commit();
            
            $this->loadStats();
            $this->loadSLAMetrics();
            $this->loadSLAViolations();
            
            $this->slaUpdateMessage = "SLA check completed: {$processedCount} tickets processed, {$violationCount} violations found.";
            
            if ($ticketId) {
                $this->loadTicketReplies($ticketId);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->slaUpdateMessage = "SLA check failed: " . $e->getMessage();
        }
    }
    
    private function logSLAViolation($ticket, $slaPolicy, $type, $minutesLate)
    {
        $existingViolation = DB::table('sla_violations')
            ->where('ticket_id', $ticket->ticket_id)
            ->where('violation_type', $type)
            ->where('acknowledged', false)
            ->exists();
        
        if (!$existingViolation) {
            DB::table('sla_violations')->insert([
                'ticket_id' => $ticket->ticket_id,
                'sla_id' => $slaPolicy->sla_id,
                'violation_type' => $type,
                'minutes_late' => $minutesLate,
                'assigned_agent' => $ticket->assigned_agent,
                'violation_time' => now(),
                'violation_notes' => "SLA violation for {$type}. Due: " . 
                    ($type === 'first_response' ? 
                        Carbon::parse($ticket->created_at)->addHours($slaPolicy->first_response_time)->format('Y-m-d H:i:s') :
                        Carbon::parse($ticket->created_at)->addHours($slaPolicy->resolution_time)->format('Y-m-d H:i:s')) .
                    " | Actual: " . now()->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    public function acknowledgeViolation($violationId)
    {
        $currentUserId = auth()->id() ?? 1;
        $currentUserName = auth()->user()->full_name ?? auth()->user()->name ?? 'System';
        
        DB::table('sla_violations')
            ->where('violation_id', $violationId)
            ->update([
                'acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => $currentUserName,
                'updated_at' => now()
            ]);
        
        $this->loadSLAViolations();
        $this->loadStats();
    }
    
    public function openSLAModal()
    {
        $this->showSLAModal = true;
    }
    
    public function closeSLAModal()
    {
        $this->showSLAModal = false;
        $this->slaUpdateMessage = '';
    }
    
    public function viewTicket($ticketId)
    {
        $this->selectedTicketId = $ticketId;
        $this->loadTicketReplies($ticketId);
        $this->showConversationModal = true;
        
        // Check SLA compliance for this ticket
        $this->checkSLACompliance($ticketId);
        
        // Load the customer info if we have it
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
            ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
            ->where('t.customer_id', $customerId)
            ->where('t.created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('t.created_at', 'desc')
            ->select('t.*', 'u.full_name as agent_name', 'sc.first_response_met', 'sc.resolution_met')
            ->get()
            ->toArray();
    }
    
    public function loadTicketReplies($ticketId)
    {
        $this->selectedTicketId = $ticketId;
        
        // Load ticket details with SLA info
        $this->selectedTicketDetails = DB::table('tickets as t')
            ->leftJoin('users as u', 't.assigned_agent', '=', 'u.user_id')
            ->leftJoin('customers as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
            ->leftJoin('sla_policies as sp', 'sc.sla_id', '=', 'sp.sla_id')
            ->where('t.ticket_id', $ticketId)
            ->select(
                't.*', 
                'u.full_name as agent_name', 
                'c.first_name', 
                'c.last_name', 
                'c.email as customer_email',
                'sc.first_response_due',
                'sc.resolution_due',
                'sc.first_response_met',
                'sc.resolution_met',
                'sc.first_response_minutes_late',
                'sc.resolution_minutes_late',
                'sp.policy_name',
                'sp.first_response_time',
                'sp.resolution_time'
            )
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
                              "**SLA Policy:** {$ticket->policy_name}\n" .
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
            
            if (!$currentUserId) {
                session()->flash('error', 'No authenticated user found. Please login.');
                return;
            }
            
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
            
            // Check SLA compliance after adding reply
            $this->checkSLACompliance($this->selectedTicketId);
            
            // Clear reply form
            $this->replyText = '';
            $this->replyIsInternal = false;
            
            // Reload replies WITHOUT closing the modal
            $this->loadTicketReplies($this->selectedTicketId);
            
            session()->flash('success', 'Reply added successfully');
            
            // Refresh recent tickets list if needed
            $this->loadRecentRecoveryTickets();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add reply: ' . $e->getMessage());
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
            
            // Create SLA compliance record for this recovery ticket
            $this->checkSLACompliance($ticketId);
            
            DB::commit();
            
            // Refresh data
            $this->loadStats();
            $this->loadRecentRecoveryTickets();
            $this->selectCustomer($this->selectedCustomerId);
            
            // Open the new ticket conversation in modal
            $this->viewTicket($ticketId);
            
            session()->flash('success', "Service recovery initiated! Ticket #{$ticketNumber} created and assigned to you.");
            
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
                    
                    // Create SLA compliance record
                    $this->checkSLACompliance($ticketId);
                    
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
        
        // Create SLA compliance record
        $this->checkSLACompliance($ticketId);
        
        $this->loadRecentRecoveryTickets();
        $this->selectCustomer($this->selectedCustomerId);
        session()->flash('success', "Goodwill discount created! Discount code: {$discountCode}");
    }
    
    public function runSLACheck()
    {
        $this->checkSLACompliance();
        $this->openSLAModal();
    }
}
?>
<div class="helpdesk-content">
    <div class="page-header">
        <h1>Service Recovery & SLA Compliance System</h1>
        <p>Automated customer retention with SLA monitoring and compliance tracking</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-7 gap-4 mb-6">
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
        
        <!-- SLA Compliance Stats -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['sla_compliance_fr'] ?? 0 }}%</div>
            <div class="text-sm text-gray-600">First Response Compliance</div>
            <div class="text-xs {{ $stats['sla_compliance_fr'] >= 90 ? 'text-green-500' : ($stats['sla_compliance_fr'] >= 80 ? 'text-yellow-500' : 'text-red-500') }} mt-1">
                @if($stats['sla_compliance_fr'] >= 90) Excellent @elseif($stats['sla_compliance_fr'] >= 80) Good @else Needs Improvement @endif
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-teal-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['sla_compliance_resolution'] ?? 0 }}%</div>
            <div class="text-sm text-gray-600">Resolution Compliance</div>
            <div class="text-xs {{ $stats['sla_compliance_resolution'] >= 90 ? 'text-green-500' : ($stats['sla_compliance_resolution'] >= 80 ? 'text-yellow-500' : 'text-red-500') }} mt-1">
                @if($stats['sla_compliance_resolution'] >= 90) Excellent @elseif($stats['sla_compliance_resolution'] >= 80) Good @else Needs Improvement @endif
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-2xl font-bold text-gray-900">{{ $stats['active_violations'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">SLA Violations</div>
            <div class="text-xs {{ $stats['active_violations'] == 0 ? 'text-green-500' : 'text-red-500' }} mt-1">
                {{ $stats['active_violations'] == 0 ? 'No violations' : 'Requires attention' }}
            </div>
        </div>
    </div>

    <!-- SLA Compliance Section -->
    <div class="page-card mb-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-900">SLA Compliance Dashboard</h3>
            <div class="flex space-x-2">
                <button wire:click="runSLACheck" class="btn btn-primary">
                    <i class="fas fa-sync-alt mr-2"></i>Run SLA Check Now
                </button>
                <button wire:click="openSLAModal" class="btn btn-secondary">
                    <i class="fas fa-chart-bar mr-2"></i>View Details
                </button>
            </div>
        </div>
        
        @if($slaUpdateMessage)
        <div class="mb-4 p-3 {{ str_contains($slaUpdateMessage, 'failed') ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' }} border rounded">
            <div class="flex items-center">
                <i class="fas {{ str_contains($slaUpdateMessage, 'failed') ? 'fa-exclamation-circle' : 'fa-check-circle' }} mr-2"></i>
                <span>{{ $slaUpdateMessage }}</span>
            </div>
        </div>
        @endif
        
        <!-- SLA Metrics Table -->
        <div class="overflow-x-auto mb-6">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Priority Level</th>
                        <th>Total Tickets</th>
                        <th>First Response Compliance</th>
                        <th>Resolution Compliance</th>
                        <th>Avg. FR Delay (min)</th>
                        <th>Avg. Resolution Delay (min)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($slaMetrics as $metric)
                        @php
                            $frCompliance = $metric->total_tickets > 0 ? round(($metric->fr_met / $metric->total_tickets) * 100, 1) : 0;
                            $resolutionCompliance = $metric->total_tickets > 0 ? round(($metric->resolution_met / $metric->total_tickets) * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td class="font-medium">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    {{ $metric->priority_name == 'critical' ? 'bg-red-100 text-red-800' : 
                                       ($metric->priority_name == 'high' ? 'bg-orange-100 text-orange-800' : 
                                       ($metric->priority_name == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) }}">
                                    <i class="fas fa-flag mr-1"></i>
                                    {{ ucfirst($metric->priority_name) }}
                                </span>
                            </td>
                            <td class="font-semibold">{{ $metric->total_tickets }}</td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                        <div class="h-2.5 rounded-full {{ $frCompliance >= 90 ? 'bg-green-500' : ($frCompliance >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                             style="width: {{ min($frCompliance, 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium {{ $frCompliance >= 90 ? 'text-green-700' : ($frCompliance >= 80 ? 'text-yellow-700' : 'text-red-700') }}">
                                        {{ $frCompliance }}%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                        <div class="h-2.5 rounded-full {{ $resolutionCompliance >= 90 ? 'bg-green-500' : ($resolutionCompliance >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                             style="width: {{ min($resolutionCompliance, 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium {{ $resolutionCompliance >= 90 ? 'text-green-700' : ($resolutionCompliance >= 80 ? 'text-yellow-700' : 'text-red-700') }}">
                                        {{ $resolutionCompliance }}%
                                    </span>
                                </div>
                            </td>
                            <td class="{{ $metric->avg_fr_minutes_late > 0 ? 'text-red-600 font-semibold' : 'text-green-600' }}">
                                {{ $metric->avg_fr_minutes_late > 0 ? $metric->avg_fr_minutes_late : 'On Time' }}
                            </td>
                            <td class="{{ $metric->avg_resolution_minutes_late > 0 ? 'text-red-600 font-semibold' : 'text-green-600' }}">
                                {{ $metric->avg_resolution_minutes_late > 0 ? $metric->avg_resolution_minutes_late : 'On Time' }}
                            </td>
                            <td>
                                @if($frCompliance >= 90 && $resolutionCompliance >= 90)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Excellent
                                    </span>
                                @elseif($frCompliance >= 80 && $resolutionCompliance >= 80)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Good
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Needs Improvement
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Active SLA Violations -->
        <div class="border-t pt-6">
            <h4 class="font-semibold mb-4">Active SLA Violations ({{ count($slaViolations) }})</h4>
            @if(count($slaViolations) > 0)
            <div class="space-y-3">
                @foreach($slaViolations as $violation)
                <div class="border border-red-200 bg-red-50 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h5 class="font-semibold text-red-800">{{ $violation->subject }}</h5>
                            <p class="text-sm text-red-700">
                                <span class="font-medium">Ticket:</span> {{ $violation->ticket_number }} • 
                                <span class="font-medium">Customer:</span> {{ $violation->first_name }} {{ $violation->last_name }} • 
                                <span class="font-medium">Type:</span> {{ ucfirst(str_replace('_', ' ', $violation->violation_type)) }}
                            </p>
                        </div>
                        <button wire:click="acknowledgeViolation({{ $violation->violation_id }})" 
                                class="btn btn-secondary btn-sm">
                            <i class="fas fa-check mr-1"></i>
                            Acknowledge
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Policy:</span>
                            <span class="font-medium">{{ $violation->policy_name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Minutes Late:</span>
                            <span class="font-semibold text-red-600">{{ $violation->minutes_late }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Violation Time:</span>
                            <span>{{ \Carbon\Carbon::parse($violation->violation_time)->format('M d, H:i') }}</span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">{{ $violation->violation_notes }}</p>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-6 bg-green-50 border border-green-200 rounded-lg">
                <i class="fas fa-check-circle text-3xl text-green-500 mb-3"></i>
                <p class="text-green-700 font-medium">No active SLA violations!</p>
                <p class="text-green-600 text-sm mt-1">All tickets are within SLA targets.</p>
            </div>
            @endif
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
                                <th>SLA Compliance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                @php
                                    $slaCompliance = DB::table('tickets as t')
                                        ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
                                        ->where('t.customer_id', $customer->customer_id)
                                        ->where('t.created_at', '>=', Carbon::now()->subDays(30))
                                        ->select(
                                            DB::raw('COUNT(DISTINCT t.ticket_id) as total_tickets'),
                                            DB::raw('SUM(CASE WHEN sc.first_response_met = 1 THEN 1 ELSE 0 END) as fr_met'),
                                            DB::raw('SUM(CASE WHEN sc.resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met')
                                        )
                                        ->first();
                                    
                                    $frCompliance = $slaCompliance && $slaCompliance->total_tickets > 0 ? 
                                        round(($slaCompliance->fr_met / $slaCompliance->total_tickets) * 100, 0) : 0;
                                @endphp
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
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 mr-2">
                                                <div class="h-1.5 rounded-full {{ $frCompliance >= 90 ? 'bg-green-500' : ($frCompliance >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                                     style="width: {{ min($frCompliance, 100) }}%"></div>
                                            </div>
                                            <span class="text-xs {{ $frCompliance >= 90 ? 'text-green-700' : ($frCompliance >= 80 ? 'text-yellow-700' : 'text-red-700') }}">
                                                {{ $frCompliance }}%
                                            </span>
                                        </div>
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
                                    <td colspan="7" class="text-center py-8">
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
            </div>

            <!-- Recent Recovery Tickets with SLA Status -->
            <div class="page-card">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Recent Recovery Tickets with SLA Status</h3>
                
                <div class="space-y-4">
                    @foreach($recentRecoveryTickets as $ticket)
                        @php
                            $slaStatus = '';
                            $slaColor = '';
                            if ($ticket->first_response_met && $ticket->resolution_met) {
                                $slaStatus = 'SLA Met';
                                $slaColor = 'text-green-600 bg-green-100';
                            } elseif (!$ticket->first_response_met && $ticket->first_response_minutes_late > 0) {
                                $slaStatus = 'FR Violation: ' . $ticket->first_response_minutes_late . ' min';
                                $slaColor = 'text-red-600 bg-red-100';
                            } elseif (!$ticket->resolution_met && $ticket->resolution_minutes_late > 0) {
                                $slaStatus = 'Resolution Violation: ' . $ticket->resolution_minutes_late . ' min';
                                $slaColor = 'text-orange-600 bg-orange-100';
                            } else {
                                $slaStatus = 'SLA Pending';
                                $slaColor = 'text-blue-600 bg-blue-100';
                            }
                        @endphp
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
                                <div class="text-right">
                                    <span class="text-xs {{ $ticket->priority === 'critical' ? 'text-red-600' : ($ticket->priority === 'high' ? 'text-orange-600' : 'text-yellow-600') }}">
                                        {{ ucfirst($ticket->priority) }}
                                    </span>
                                    <div class="mt-1">
                                        <span class="text-xs px-2 py-1 rounded-full {{ $slaColor }}">
                                            {{ $slaStatus }}
                                        </span>
                                    </div>
                                </div>
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

        <!-- Customer Details Sidebar -->
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
                    
                    <!-- SLA Status for Customer -->
                    @php
                        $customerSLA = DB::table('tickets as t')
                            ->leftJoin('sla_compliance as sc', 't.ticket_id', '=', 'sc.ticket_id')
                            ->where('t.customer_id', $selectedCustomerId)
                            ->where('t.created_at', '>=', Carbon::now()->subDays(30))
                            ->select(
                                DB::raw('COUNT(DISTINCT t.ticket_id) as total_tickets'),
                                DB::raw('SUM(CASE WHEN sc.first_response_met = 1 THEN 1 ELSE 0 END) as fr_met'),
                                DB::raw('SUM(CASE WHEN sc.resolution_met = 1 THEN 1 ELSE 0 END) as resolution_met')
                            )
                            ->first();
                        
                        $customerFRCompliance = $customerSLA && $customerSLA->total_tickets > 0 ? 
                            round(($customerSLA->fr_met / $customerSLA->total_tickets) * 100, 0) : 0;
                        $customerResolutionCompliance = $customerSLA && $customerSLA->total_tickets > 0 ? 
                            round(($customerSLA->resolution_met / $customerSLA->total_tickets) * 100, 0) : 0;
                    @endphp
                    
                    <div class="border-t pt-4 mt-4">
                        <h5 class="font-semibold mb-3">Customer SLA Compliance (30d)</h5>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">First Response</span>
                                    <span class="font-medium {{ $customerFRCompliance >= 90 ? 'text-green-600' : ($customerFRCompliance >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $customerFRCompliance }}%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $customerFRCompliance >= 90 ? 'bg-green-500' : ($customerFRCompliance >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                         style="width: {{ min($customerFRCompliance, 100) }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Resolution</span>
                                    <span class="font-medium {{ $customerResolutionCompliance >= 90 ? 'text-green-600' : ($customerResolutionCompliance >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $customerResolutionCompliance }}%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $customerResolutionCompliance >= 90 ? 'bg-green-500' : ($customerResolutionCompliance >= 80 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                         style="width: {{ min($customerResolutionCompliance, 100) }}%"></div>
                                </div>
                            </div>
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
                        
                        <!-- Send Goodwill Discount Button -->
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

            <!-- Recent Tickets with SLA Status -->
            @if(!empty($customerInfo))
                <h4 class="font-semibold mb-3">Recent Tickets with SLA Status (30 days)</h4>
                <div class="space-y-2 mb-6">
                    @foreach($customerTickets as $ticket)
                        @php
                            $slaStatus = '';
                            $slaColor = '';
                            if ($ticket->first_response_met && $ticket->resolution_met) {
                                $slaStatus = '✓';
                                $slaColor = 'text-green-500';
                            } elseif (!$ticket->first_response_met) {
                                $slaStatus = 'FR ✗';
                                $slaColor = 'text-red-500';
                            } elseif (!$ticket->resolution_met) {
                                $slaStatus = 'Res ✗';
                                $slaColor = 'text-orange-500';
                            } else {
                                $slaStatus = '...';
                                $slaColor = 'text-gray-500';
                            }
                        @endphp
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
                                <div class="flex flex-col items-end">
                                    <span class="text-xs px-2 py-1 rounded 
                                        {{ $ticket->priority === 'critical' ? 'bg-red-100 text-red-800' : 
                                           ($ticket->priority === 'high' ? 'bg-orange-100 text-orange-800' : 
                                           ($ticket->priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) }}">
                                        {{ ucfirst($ticket->priority) }}
                                    </span>
                                    <span class="text-xs mt-1 {{ $slaColor }}">
                                        {{ $slaStatus }}
                                    </span>
                                </div>
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
                        <h3 class="text-xl font-bold">Ticket Conversation with SLA Status</h3>
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
                    
                    <!-- SLA Status Badges -->
                    @if($selectedTicketDetails->policy_name)
                    <div class="bg-indigo-600 px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-clock mr-2"></i>
                        {{ $selectedTicketDetails->policy_name }}
                    </div>
                    @endif
                    
                    <div class="{{ $selectedTicketDetails->first_response_met ? 'bg-green-500' : 'bg-red-500' }} px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-reply mr-2"></i>
                        FR: {{ $selectedTicketDetails->first_response_met ? 'Met' : 'Pending' }}
                        @if($selectedTicketDetails->first_response_minutes_late > 0)
                            ({{ $selectedTicketDetails->first_response_minutes_late }}min)
                        @endif
                    </div>
                    
                    <div class="{{ $selectedTicketDetails->resolution_met ? 'bg-green-500' : ($selectedTicketDetails->status === 'closed' ? 'bg-red-500' : 'bg-yellow-500') }} px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        Resolution: {{ $selectedTicketDetails->resolution_met ? 'Met' : ($selectedTicketDetails->status === 'closed' ? 'Missed' : 'Pending') }}
                        @if($selectedTicketDetails->resolution_minutes_late > 0)
                            ({{ $selectedTicketDetails->resolution_minutes_late }}min)
                        @endif
                    </div>
                    
                    <!-- SLA Due Times -->
                    @if($selectedTicketDetails->first_response_due)
                    <div class="bg-blue-500 px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-hourglass-end mr-2"></i>
                        FR Due: {{ \Carbon\Carbon::parse($selectedTicketDetails->first_response_due)->format('M d, H:i') }}
                    </div>
                    @endif
                    
                    @if($selectedTicketDetails->resolution_due)
                    <div class="bg-purple-500 px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-calendar-check mr-2"></i>
                        Resolve By: {{ \Carbon\Carbon::parse($selectedTicketDetails->resolution_due)->format('M d, H:i') }}
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
                        <span class="block mt-1">
                            <i class="fas fa-clock mr-1"></i>
                            This reply will update the ticket's SLA compliance status.
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- SLA Details Modal -->
@if($showSLAModal)
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-6 py-4 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold">SLA Compliance Details & Policies</h3>
                    <div class="text-sm text-indigo-100 mt-1">
                        Service Level Agreement tracking and compliance monitoring
                    </div>
                </div>
                <button wire:click="closeSLAModal" 
                        type="button"
                        class="text-white hover:text-indigo-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[80vh]">
            @if($slaUpdateMessage)
            <div class="mb-6 p-4 {{ str_contains($slaUpdateMessage, 'failed') ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' }} border rounded">
                <div class="flex items-center">
                    <i class="fas {{ str_contains($slaUpdateMessage, 'failed') ? 'fa-exclamation-circle' : 'fa-check-circle' }} mr-3"></i>
                    <div>
                        <p class="font-medium">{{ $slaUpdateMessage }}</p>
                        <p class="text-sm mt-1 opacity-90">SLA compliance check completed at {{ now()->format('H:i:s') }}</p>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Current SLA Policies -->
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Active SLA Policies</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @php
                        $slaPolicies = DB::table('sla_policies')->where('is_active', true)->get();
                    @endphp
                    @foreach($slaPolicies as $policy)
                    <div class="border rounded-lg p-4 hover:shadow-md transition-shadow 
                                {{ $policy->ticket_priority === 'critical' ? 'border-red-200 bg-red-50' : 
                                   ($policy->ticket_priority === 'high' ? 'border-orange-200 bg-orange-50' : 
                                   ($policy->ticket_priority === 'medium' ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50')) }}">
                        <div class="flex justify-between items-start mb-3">
                            <h5 class="font-bold text-gray-900">{{ $policy->policy_name }}</h5>
                            <span class="text-xs px-2 py-1 rounded font-medium
                                {{ $policy->ticket_priority === 'critical' ? 'bg-red-100 text-red-800' : 
                                   ($policy->ticket_priority === 'high' ? 'bg-orange-100 text-orange-800' : 
                                   ($policy->ticket_priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) }}">
                                {{ ucfirst($policy->ticket_priority) }} Priority
                            </span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">First Response Time:</span>
                                <span class="font-semibold">{{ $policy->first_response_time }} hours</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Resolution Time:</span>
                                <span class="font-semibold">{{ $policy->resolution_time }} hours</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Warning Threshold:</span>
                                <span class="font-semibold">{{ $policy->warning_threshold }}%</span>
                            </div>
                            @if($policy->escalation_contact)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Escalation Contact:</span>
                                <span class="font-semibold text-blue-600">{{ $policy->escalation_contact }}</span>
                            </div>
                            @endif
                        </div>
                        @if($policy->description)
                        <p class="text-xs text-gray-500 mt-3">{{ $policy->description }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Compliance Summary -->
            <div class="mb-8">
                <h4 class="text-lg font-semibold text-gray-900 mb-4">Compliance Summary (Last 30 Days)</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-white rounded-lg border">
                            <div class="text-3xl font-bold text-indigo-600">
                                {{ $stats['total_tickets_week'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600">Tickets Created</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg border">
                            <div class="text-3xl font-bold {{ $stats['sla_compliance_fr'] >= 90 ? 'text-green-600' : ($stats['sla_compliance_fr'] >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $stats['sla_compliance_fr'] ?? 0 }}%
                            </div>
                            <div class="text-sm text-gray-600">First Response Compliance</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg border">
                            <div class="text-3xl font-bold {{ $stats['sla_compliance_resolution'] >= 90 ? 'text-green-600' : ($stats['sla_compliance_resolution'] >= 80 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $stats['sla_compliance_resolution'] ?? 0 }}%
                            </div>
                            <div class="text-sm text-gray-600">Resolution Compliance</div>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg border">
                            <div class="text-3xl font-bold {{ $stats['active_violations'] == 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $stats['active_violations'] ?? 0 }}
                            </div>
                            <div class="text-sm text-gray-600">Active Violations</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Database Tables Info -->
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-4">SLA Database Structure</h4>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border rounded p-3 bg-white">
                            <h6 class="font-semibold text-gray-900 mb-2">sla_policies</h6>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><code class="text-xs">sla_id</code> - Primary Key</li>
                                <li><code class="text-xs">policy_name</code> - Policy Name</li>
                                <li><code class="text-xs">ticket_priority</code> - Priority Level</li>
                                <li><code class="text-xs">first_response_time</code> - Hours</li>
                                <li><code class="text-xs">resolution_time</code> - Hours</li>
                                <li><code class="text-xs">is_active</code> - Status</li>
                            </ul>
                        </div>
                        <div class="border rounded p-3 bg-white">
                            <h6 class="font-semibold text-gray-900 mb-2">sla_compliance</h6>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><code class="text-xs">ticket_id</code> - Foreign Key</li>
                                <li><code class="text-xs">sla_id</code> - Policy Reference</li>
                                <li><code class="text-xs">first_response_due</code> - Due Date</li>
                                <li><code class="text-xs">resolution_due</code> - Due Date</li>
                                <li><code class="text-xs">first_response_met</code> - Boolean</li>
                                <li><code class="text-xs">resolution_met</code> - Boolean</li>
                            </ul>
                        </div>
                        <div class="border rounded p-3 bg-white">
                            <h6 class="font-semibold text-gray-900 mb-2">sla_violations</h6>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li><code class="text-xs">ticket_id</code> - Foreign Key</li>
                                <li><code class="text-xs">violation_type</code> - Type</li>
                                <li><code class="text-xs">minutes_late</code> - Delay</li>
                                <li><code class="text-xs">acknowledged</code> - Status</li>
                                <li><code class="text-xs">violation_time</code> - Timestamp</li>
                                <li><code class="text-xs">violation_notes</code> - Details</li>
                            </ul>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">
                        <i class="fas fa-info-circle mr-1"></i>
                        These tables are automatically created and maintained by the system. Tables are checked on page load and created if they don't exist.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="border-t px-6 py-4 bg-gray-50">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    3 SLA-related tables in database
                </div>
                <div class="flex space-x-3">
                    <button wire:click="closeSLAModal" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Close
                    </button>
                    <button wire:click="runSLACheck" 
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Run Compliance Check
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>
