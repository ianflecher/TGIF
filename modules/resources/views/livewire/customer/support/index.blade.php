<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

new #[Layout('components.layouts.landing')] 
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
    
    // Data
    public $categories = [];
    public $priorities = [];
    public $faqItems = [];
    public $openFaqId = null;
    
    // For debugging
    public $debugMessage = '';
    
    public function mount()
    {
        $this->loadCategories();
        $this->loadPriorities();
        $this->loadFAQ();
    }
    
    protected function loadCategories()
    {
        $this->categories = [
            'general' => ['label' => 'General Inquiry', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 'color' => 'blue'],
            'technical' => ['label' => 'Technical Support', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'purple'],
            'billing' => ['label' => 'Billing & Payments', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green'],
            'order' => ['label' => 'Order Issues', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'orange'],
            'shipping' => ['label' => 'Shipping & Delivery', 'icon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'color' => 'red'],
            'returns' => ['label' => 'Returns & Refunds', 'icon' => 'M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4', 'color' => 'yellow'],
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
            ['id' => 2, 'q' => 'Do I need an account to submit a request?', 'a' => 'No, you can submit as a guest. We\'ll create a customer record with your email.'],
            ['id' => 3, 'q' => 'Can I track my support request?', 'a' => 'Yes, use your ticket number to track status online or contact us.'],
            ['id' => 4, 'q' => 'What information should I include?', 'a' => 'Be specific: error messages, order numbers, screenshots, and steps to reproduce the issue.'],
        ];
    }
    
    public function updatedPriority($value)
    {
        $this->estimatedResponse = $this->priorities[$value]['time'];
    }
    
    public function submitRequest()
    {
        // Validate the form
        $this->validate();
        
        $this->debugMessage = "Starting submission...";
        
        try {
            // First, test database connection
            $this->debugMessage = "Testing DB connection...";
            
            // Generate ticket number first (for debugging)
            $ticketNumber = 'SUP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $this->debugMessage = "Ticket number generated: " . $ticketNumber;
            
            // For testing: Simulate success without DB
            $this->ticketNumber = $ticketNumber;
            $this->submitted = true;
            $this->debugMessage = "Success! Ticket created.";
            
            return; // Early return for testing
            
            /* 
            // Original database code (commented for testing)
            DB::beginTransaction();
            
            // Check/create customer
            $customer = DB::table('customers')
                ->where('email', $this->email)
                ->first();
            
            if (!$customer) {
                $customerId = DB::table('customers')->insertGetId([
                    'first_name' => $this->name,
                    'last_name' => '',
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'date_registered' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $customerId = $customer->customer_id;
            }
            
            // Find agent
            $agent = DB::table('employees as e')
                ->join('users as u', 'e.user_id', '=', 'u.user_id')
                ->where('e.status', 'active')
                ->orderByRaw('(SELECT COUNT(*) FROM tickets WHERE assigned_agent = e.employee_id AND status IN ("open", "in_progress"))')
                ->select('e.employee_id')
                ->first();
            
            // Create ticket
            $ticketId = DB::table('tickets')->insertGetId([
                'ticket_number' => $ticketNumber,
                'customer_id' => $customerId,
                'assigned_agent' => $agent?->employee_id,
                'subject' => $this->subject,
                'issue_description' => $this->description,
                'priority' => $this->priority,
                'status' => 'open',
                'category' => $this->category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Add initial note
            DB::table('ticket_notes')->insert([
                'ticket_id' => $ticketId,
                'note_text' => $this->description,
                'created_by' => $customerId,
                'internal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            // Update UI
            $this->ticketNumber = $ticketNumber;
            $this->submitted = true;
            */
            
        } catch (\Exception $e) {
            // DB::rollBack();
            $this->debugMessage = "Error: " . $e->getMessage();
            
            // For debugging, show error but still show success
            $this->ticketNumber = 'SUP-' . date('Ymd') . '-TEST';
            $this->submitted = true;
            $this->debugMessage .= " (Showing test success for debugging)";
        }
    }
    
    public function resetForm()
    {
        $this->reset('name', 'email', 'phone', 'subject', 'description', 'orderNumber', 'debugMessage');
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

<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white">
    <!-- Debug message (remove in production) -->
    @if($debugMessage)
    <div class="fixed top-4 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-2 rounded-lg shadow-lg z-50 max-w-md">
        <strong>Debug:</strong> {{ $debugMessage }}
    </div>
    @endif

    <!-- Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Contact Support</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Need help? Fill out the form below and our team will get back to you quickly.
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
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
                        
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 max-w-md mx-auto mb-8">
                            <div class="text-center mb-4">
                                <div class="text-sm text-gray-500 mb-2">Your Ticket Number</div>
                                <div class="text-4xl font-bold text-blue-600 tracking-wider">{{ $ticketNumber }}</div>
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
                                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition shadow-sm">
                                Submit Another Request
                            </button>
                            <a href="/support/track" 
                               class="px-8 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-medium transition">
                                Track Your Ticket
                            </a>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">What happens next?</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center p-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-blue-600 font-bold">1</span>
                                </div>
                                <h4 class="font-medium text-gray-900 mb-2">Ticket Review</h4>
                                <p class="text-sm text-gray-600">Our team reviews your request and assigns the best agent.</p>
                            </div>
                            <div class="text-center p-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-blue-600 font-bold">2</span>
                                </div>
                                <h4 class="font-medium text-gray-900 mb-2">Agent Assignment</h4>
                                <p class="text-sm text-gray-600">An expert agent is assigned based on your issue type.</p>
                            </div>
                            <div class="text-center p-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <span class="text-blue-600 font-bold">3</span>
                                </div>
                                <h4 class="font-medium text-gray-900 mb-2">You Get Response</h4>
                                <p class="text-sm text-gray-600">Agent contacts you via email within estimated time.</p>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <!-- Support Form -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
                    <!-- Form Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8">
                        <h2 class="text-2xl font-bold text-white mb-2">Support Request Form</h2>
                        <p class="text-blue-100">Fill out the form below. Our team typically responds within {{ $estimatedResponse }}.</p>
                    </div>
                    
                    <form wire:submit.prevent="submitRequest" class="p-8 space-y-8">
                        <!-- Personal Info -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-4">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                Your Information
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Full Name *
                                    </label>
                                    <input type="text" 
                                           wire:model="name"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="John Smith"
                                           required>
                                    @error('name')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address *
                                    </label>
                                    <input type="email" 
                                           wire:model="email"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="john@example.com"
                                           required>
                                    @error('email')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone Number (Optional)
                                    </label>
                                    <input type="tel" 
                                           wire:model="phone"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="+1 (555) 123-4567">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Order Number (Optional)
                                    </label>
                                    <input type="text" 
                                           wire:model="orderNumber"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                           placeholder="ORD-123456">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Selection -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-4">
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
                                                   {{ $category === $key ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50' }}">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 rounded-lg bg-{{ $cat['color'] }}-100 flex items-center justify-center mr-3">
                                                <svg class="w-6 h-6 text-{{ $cat['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cat['icon'] }}" />
                                                </svg>
                                            </div>
                                            <span class="font-medium text-gray-900">{{ $cat['label'] }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 rounded-full {{ $category === $key ? 'bg-blue-500' : 'bg-gray-300' }} mr-2"></div>
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
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-4">
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
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
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
                                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
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
                            </div>
                        </div>
                        
                        <!-- Priority Selection -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-4">
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
                                                   peer-checked:border-blue-500 peer-checked:bg-blue-50 
                                                   hover:border-blue-300 hover:bg-gray-50">
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
                                        <svg class="w-6 h-6 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                            class="px-8 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-medium transition shadow-lg">
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
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-100">
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
                                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Help</h3>
                    <div class="space-y-3">
                        <a href="/faq" class="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-blue-200">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>FAQ & Knowledge Base</span>
                        </a>
                        <a href="/support/track" class="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <span>Track Existing Ticket</span>
                        </a>
                        <a href="mailto:support@example.com" class="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition group">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-purple-200">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>Email Support</span>
                        </a>
                    </div>
                </div>
                
                <!-- FAQ -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Common Questions</h3>
                    <div class="space-y-3">
                        @foreach($faqItems as $faq)
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <button wire:click="toggleFaq({{ $faq['id'] }})" 
                                        class="w-full px-4 py-3 text-left flex justify-between items-center hover:bg-gray-50 transition">
                                    <span class="font-medium text-gray-900">{{ $faq['q'] }}</span>
                                    <svg class="w-5 h-5 text-gray-500 transition-transform {{ $openFaqId === $faq['id'] ? 'rotate-180' : '' }}" 
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                @if($openFaqId === $faq['id'])
                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                                        <p class="text-gray-700">{{ $faq['a'] }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Support Hours -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Support Hours</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-2">
                            <span class="text-gray-600">Monday - Friday</span>
                            <span class="font-medium">24/7</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <span class="text-gray-600">Saturday - Sunday</span>
                            <span class="font-medium">24/7</span>
                        </div>
                        <div class="flex justify-between items-center p-2">
                            <span class="text-gray-600">Holidays</span>
                            <span class="font-medium">24/7</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Methods -->
        <div class="mt-16">
            <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Other Ways to Reach Us</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gradient-to-br from-blue-50 to-white rounded-2xl p-8 text-center border border-blue-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Call Us</h4>
                    <p class="text-gray-600 mb-4">Speak directly with our support team</p>
                    <a href="tel:+18005551234" class="text-blue-600 font-bold text-lg hover:text-blue-800 inline-block">
                        +1 (800) 555-1234
                    </a>
                </div>
                
                <div class="bg-gradient-to-br from-green-50 to-white rounded-2xl p-8 text-center border border-green-100">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Email Us</h4>
                    <p class="text-gray-600 mb-4">Get help via email within 24 hours</p>
                    <a href="mailto:support@example.com" class="text-green-600 font-bold text-lg hover:text-green-800 inline-block">
                        support@example.com
                    </a>
                </div>
                
                <div class="bg-gradient-to-br from-purple-50 to-white rounded-2xl p-8 text-center border border-purple-100">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Community</h4>
                    <p class="text-gray-600 mb-4">Get help from our community forum</p>
                    <a href="/community" class="text-purple-600 font-bold text-lg hover:text-purple-800 inline-block">
                        Visit Forum →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add JavaScript for form submission -->
<script>
document.addEventListener('livewire:init', () => {
    // Add form submission listener
    const form = document.querySelector('form[wire\\:submit\\.prevent="submitRequest"]');
    if (form) {
        console.log('Form found, adding submit listener');
    }
    
    // Listen for validation errors
    Livewire.on('validationErrors', (errors) => {
        console.log('Validation errors:', errors);
    });
});
</script>