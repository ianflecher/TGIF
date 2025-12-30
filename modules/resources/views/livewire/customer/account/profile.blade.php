<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

new #[Layout('components.layouts.customerapp')] 
#[Title('My Account')]
class extends Component
{
    public $customer = null;
    public $user = null;
    
    // Stats
    public $totalOrders = 0;
    public $totalSpent = 0;
    public $activeTickets = 0;
    
    // Recent data
    public $recentOrders = [];
    public $recentTickets = [];
    
    public function mount()
    {
        $this->user = Auth::user();
        
        if ($this->user) {
            // Get customer profile using DB query
            $this->customer = DB::table('customers')
                ->where('user_id', $this->user->user_id)
                ->first();
            
            if ($this->customer) {
                // Load customer stats
                $this->loadCustomerStats();
                
                // Load recent orders
                $this->recentOrders = DB::table('sales_orders')
                    ->where('customer_id', $this->customer->customer_id)
                    ->orderBy('order_date', 'desc')
                    ->limit(5)
                    ->get()
                    ->toArray();
                
                // Load recent tickets
                $this->recentTickets = DB::table('tickets')
                    ->where('customer_id', $this->customer->customer_id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->toArray();
            }
        }
    }
    
    private function loadCustomerStats()
    {
        // Total orders count
        $this->totalOrders = DB::table('sales_orders')
            ->where('customer_id', $this->customer->customer_id)
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->count();
        
        // Total amount spent
        $result = DB::table('sales_orders')
            ->where('customer_id', $this->customer->customer_id)
            ->whereIn('status', ['delivered'])
            ->selectRaw('SUM(grand_total) as total_spent')
            ->first();
        
        $this->totalSpent = $result->total_spent ?? 0;
        
        // Active tickets
        $this->activeTickets = DB::table('tickets')
            ->where('customer_id', $this->customer->customer_id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
    }
    
    // Form fields for editing
    public $form = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
    ];
    
    // Toggle edit mode
    public $editing = false;
    
    public function editProfile()
    {
        if ($this->customer) {
            $this->form = [
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'address' => $this->customer->address,
            ];
            $this->editing = true;
        }
    }
    
    public function saveProfile()
    {
        if ($this->customer) {
            $validator = Validator::make($this->form, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:100|unique:customers,email,' . $this->customer->customer_id . ',customer_id',
                'phone' => 'nullable|string|max:50',
                'address' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                $this->addError('form.first_name', $validator->errors()->first('first_name'));
                $this->addError('form.last_name', $validator->errors()->first('last_name'));
                $this->addError('form.email', $validator->errors()->first('email'));
                $this->addError('form.phone', $validator->errors()->first('phone'));
                $this->addError('form.address', $validator->errors()->first('address'));
                return;
            }
            
            DB::table('customers')
                ->where('customer_id', $this->customer->customer_id)
                ->update($this->form);
            
            // Refresh customer data
            $this->customer = DB::table('customers')
                ->where('customer_id', $this->customer->customer_id)
                ->first();
            
            $this->editing = false;
            session()->flash('message', 'Profile updated successfully!');
        }
    }
    
    public function cancelEdit()
    {
        $this->editing = false;
        $this->reset('form');
        $this->resetErrorBag();
    }
}

?>

<div>
    @if(!$customer)
        <div class="text-center py-12">
            <div class="text-gray-500 text-lg mb-4">Customer profile not found</div>
            <a href="{{ route('customer.dashboard') }}" class="text-blue-600 hover:text-blue-800">Return to Dashboard</a>
        </div>
    @else
        <!-- Header Section -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">My Account</h1>
                        <p class="text-gray-600 mt-1">Manage your profile and view account information</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Member since</p>
                        <p class="font-medium">{{ \Carbon\Carbon::parse($customer->date_registered)->format('F d, Y') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Profile Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Profile Card -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
                            @if(!$editing)
                                <button wire:click="editProfile" class="text-blue-600 hover:text-blue-900 font-medium">
                                    Edit Profile
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <div class="px-6 py-5">
                        @if($editing)
                            <!-- Edit Form -->
                            <form wire:submit.prevent="saveProfile">
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                            <input type="text" wire:model="form.first_name" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            @error('form.first_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                            <input type="text" wire:model="form.last_name" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            @error('form.last_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                        <input type="email" wire:model="form.email" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        @error('form.email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                        <input type="text" wire:model="form.phone" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        @error('form.phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea wire:model="form.address" rows="3" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                        @error('form.address') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div class="flex justify-end space-x-3 pt-4">
                                        <button type="button" wire:click="cancelEdit" 
                                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Cancel
                                        </button>
                                        <button type="submit" 
                                                class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <!-- View Mode -->
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Name</p>
                                        <p class="font-medium">{{ $customer->first_name }} {{ $customer->last_name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="font-medium">{{ $customer->email }}</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Phone</p>
                                    <p class="font-medium">{{ $customer->phone ?? 'Not provided' }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Address</p>
                                    <p class="font-medium">{{ $customer->address ?? 'Not provided' }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Account ID</p>
                                    <p class="font-medium">{{ $customer->customer_id }}</p>
                                </div>
                            </div>
                        @endif
                        
                        @if(session('message'))
                            <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-green-700">{{ session('message') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">Recent Orders</h2>
                            <a href="{{ route('customer.orders.index') }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                View All
                            </a>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        @if(count($recentOrders) > 0)
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentOrders as $order)
                                        @php
                                            $orderDate = \Carbon\Carbon::parse($order->order_date);
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $order->order_number }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $orderDate->format('M d, Y') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $statusColors = [
                                                        'draft' => 'bg-gray-100 text-gray-800',
                                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                                        'processing' => 'bg-yellow-100 text-yellow-800',
                                                        'shipped' => 'bg-purple-100 text-purple-800',
                                                        'delivered' => 'bg-green-100 text-green-800',
                                                        'cancelled' => 'bg-red-100 text-red-800',
                                                    ];
                                                @endphp
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($order->grand_total, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('customer.order.details', $order->order_id) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="px-6 py-8 text-center">
                                <p class="text-gray-500">No orders found.</p>
                                <a href="{{ route('customer.orders.index') }}" class="mt-2 inline-block text-blue-600 hover:text-blue-800">Start Shopping</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column - Stats & Quick Actions -->
            <div class="space-y-6">
                <!-- Stats Summary -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Account Summary</h2>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-500">Total Orders</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $totalOrders }}</p>
                            </div>
                            <div class="text-blue-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-500">Total Spent</p>
                                <p class="text-2xl font-bold text-gray-900">₱{{ number_format($totalSpent, 2) }}</p>
                            </div>
                            <div class="text-green-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-500">Active Tickets</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $activeTickets }}</p>
                            </div>
                            <div class="text-yellow-600">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <a href="{{ route('customer.orders.index') }}" 
                           class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-150">
                            <div class="flex-shrink-0 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">View Orders</p>
                                <p class="text-xs text-gray-500">Track your orders</p>
                            </div>
                        </a>
                        
                        
                        <a href="{{ route('customer.orders.index') }}" 
                           class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-150">
                            <div class="flex-shrink-0 text-green-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Shop Now</p>
                                <p class="text-xs text-gray-500">Browse products</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Account Security -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Account Security</h2>
                    </div>
                    <div class="px-6 py-5">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Password</p>
                                    <p class="text-xs text-gray-500">Last changed: {{ \Carbon\Carbon::parse($user->updated_at)->diffForHumans() }}</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Two-Factor Authentication</p>
                                    <p class="text-xs text-gray-500">Add an extra layer of security</p>
                                </div>
                                <button class="text-sm text-blue-600 hover:text-blue-900 font-medium">
                                    Enable
                                </button>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <button onclick="confirm('Are you sure?')" 
                                        class="w-full px-4 py-2 border border-red-300 rounded-md text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Sign Out All Devices
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>