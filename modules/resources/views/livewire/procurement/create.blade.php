<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public int $step = 1; // current step
    public int $supplierId = 0;
    public array $suppliers = [];
    public array $products = [];
    public array $selectedProducts = []; // product_id => quantity
    public array $productPrices = []; // product_id => price
    public string $status = 'pending';
    public string $date = '';
    public string $message = '';
    public float $estimatedCost = 0.00;
    public int $userId = 0;
    
    // New properties for status tracking
    public array $requisitionHistory = [];
    public bool $showHistory = false;
    public int $selectedRequisitionId = 0;
    public array $requisitionDetails = [];
    public array $requisitionItems = [];

    public function mount(): void
    {
        $this->userId = Auth::id();
        
        // Load suppliers
        $this->suppliers = DB::table('suppliers')
            ->select('supplier_id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->supplierId = $this->suppliers[0]->supplier_id ?? 0;
        $this->loadProducts();
        $this->date = Carbon::now()->toDateString();
        
        // Load user's requisition history
        $this->loadRequisitionHistory();
    }

    // Load user's requisition history
    public function loadRequisitionHistory(): void
{
    $this->requisitionHistory = DB::table('purchase_requisitions as pr')
        ->leftJoin('suppliers as s', function($join) {
            $join->whereRaw('EXISTS (
                SELECT 1 FROM requisition_items ri 
                JOIN products p ON ri.product_id = p.product_id 
                WHERE ri.requisition_id = pr.requisition_id 
                AND p.supplier_id = s.supplier_id
                LIMIT 1
            )');
        })
        ->select(
            'pr.requisition_id',
            'pr.status', // Make sure this is selected
            'pr.date_requested',
            'pr.estimated_cost',
            DB::raw('COALESCE(GROUP_CONCAT(DISTINCT s.name), "Multiple Suppliers") as supplier_names'),
            DB::raw('(SELECT COUNT(*) FROM requisition_items WHERE requisition_id = pr.requisition_id) as item_count')
        )
        ->where('pr.requested_by', $this->userId)
        ->groupBy('pr.requisition_id', 'pr.status', 'pr.date_requested', 'pr.estimated_cost')
        ->orderBy('pr.requisition_id', 'desc')
        ->limit(20)
        ->get()
        ->toArray();
}

    // View requisition details
    public function viewRequisition($requisitionId): void
{
    $this->selectedRequisitionId = $requisitionId;
    
    // Load requisition details
    $requisition = DB::table('purchase_requisitions as pr')
        ->leftJoin('users as u', 'pr.requested_by', '=', 'u.user_id')
        ->leftJoin('departments as d', 'pr.department_id', '=', 'd.department_id')
        ->select(
            'pr.*',
            'u.full_name as requester_name',
            'u.email as requester_email',
            'd.department_name as department_name'
        )
        ->where('pr.requisition_id', $requisitionId)
        ->first();
    
    $this->requisitionDetails = $requisition ? (array) $requisition : [];
    
    // Load requisition items with product details
    $this->requisitionItems = DB::table('requisition_items as ri')
        ->join('products as p', 'ri.product_id', '=', 'p.product_id')
        ->join('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
        ->select(
            'ri.*',
            'p.product_name',
            'p.category',
            'p.price as unit_price',
            's.name as supplier_name'
        )
        ->where('ri.requisition_id', $requisitionId)
        ->orderBy('ri.product_id')
        ->get()
        ->toArray();
    
    $this->showHistory = true;
}

    public function nextStep()
    {
        if ($this->step === 1 && $this->supplierId === 0) {
            $this->message = '⚠ Please select a supplier first.';
            return;
        }
        
        if ($this->step === 2) {
            $hasSelectedProducts = false;
            foreach ($this->selectedProducts as $quantity) {
                if ($quantity > 0) {
                    $hasSelectedProducts = true;
                    break;
                }
            }
            
            if (!$hasSelectedProducts) {
                $this->message = '⚠ Please select at least one product with quantity.';
                return;
            }
            
            $this->calculateEstimatedCost();
        }
        
        $this->message = '';
        $this->step++;
    }

    public function incrementQuantity($productId)
    {
        if (!isset($this->selectedProducts[$productId])) {
            $this->selectedProducts[$productId] = 1;
        } else {
            $this->selectedProducts[$productId]++;
        }
        
        $this->calculateEstimatedCost();
    }

    public function decrementQuantity($productId)
    {
        if (isset($this->selectedProducts[$productId]) && $this->selectedProducts[$productId] > 0) {
            $this->selectedProducts[$productId]--;
            
            if ($this->selectedProducts[$productId] == 0) {
                unset($this->selectedProducts[$productId]);
            }
            
            $this->calculateEstimatedCost();
        }
    }

    public function updatedSelectedProducts($value, $key)
    {
        if (isset($this->selectedProducts[$key]) && $this->selectedProducts[$key] <= 0) {
            unset($this->selectedProducts[$key]);
        }
        
        $this->calculateEstimatedCost();
    }

    public function previousStep()
    {
        $this->message = '';
        $this->step--;
    }

    public function updatedSupplierId(): void
    {
        $this->loadProducts();
        $this->selectedProducts = [];
        $this->estimatedCost = 0.00;
    }

    protected function loadProducts(): void
    {
        if ($this->supplierId === 0) {
            $this->products = [];
            return;
        }

        $this->products = DB::table('products')
            ->where('supplier_id', $this->supplierId)
            ->select('product_id', 'product_name', 'category', 'price', 'stock_quantity')
            ->orderBy('product_name')
            ->get()
            ->toArray();
        
        foreach ($this->products as $product) {
            $this->productPrices[$product->product_id] = $product->price;
        }
    }

    protected function calculateEstimatedCost(): void
    {
        $this->estimatedCost = 0.00;
        
        foreach ($this->selectedProducts as $productId => $quantity) {
            if ($quantity > 0 && isset($this->productPrices[$productId])) {
                $this->estimatedCost += $quantity * $this->productPrices[$productId];
            }
        }
    }

    public function submit()
{
    if ($this->userId === 0 || $this->supplierId === 0 || empty($this->selectedProducts)) {
        $this->message = '⚠ Please complete all steps.';
        return;
    }

    $this->calculateEstimatedCost();

    // Get user's department from users table
    $user = DB::table('users')->where('user_id', $this->userId)->first();
    $departmentId = $user->department_id ?? 1;

    // Insert requisition - use 'submitted' instead of 'pending'
    $requisitionId = DB::table('purchase_requisitions')->insertGetId([
        'requested_by' => $this->userId,
        'status' => 'draft', // Changed from 'pending' to 'submitted'
        'date_requested' => $this->date,
        'department_id' => $departmentId,
        'estimated_cost' => $this->estimatedCost,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    // Insert items
    foreach ($this->selectedProducts as $productId => $quantity) {
        if ($quantity > 0) {
            DB::table('requisition_items')->insert([
                'requisition_id' => $requisitionId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }


    $this->message = '✅ Requisition #' . $requisitionId . ' created successfully! Estimated Cost: ₱' . number_format($this->estimatedCost, 2);
    
    // Refresh history
    $this->loadRequisitionHistory();
    
    // Reset form
    $this->resetForm();
}

    protected function resetForm()
    {
        $this->step = 1;
        $this->supplierId = $this->suppliers[0]->supplier_id ?? 0;
        $this->selectedProducts = [];
        $this->estimatedCost = 0.00;
        $this->loadProducts();
    }

    // Close requisition details view
    public function closeHistory()
    {
        $this->showHistory = false;
        $this->selectedRequisitionId = 0;
        $this->requisitionDetails = [];
        $this->requisitionItems = [];
    }
};
?>

<div class="p-8 bg-gray-50 min-h-screen max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-green-800">Purchase Requisition System</h1>
            <p class="text-gray-600 mt-1">Create and track your purchase requests</p>
        </div>
        <div>
            <button wire:click="$set('showHistory', false)"
                    class="px-6 py-2 rounded-lg {{ !$showHistory ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700' }} hover:bg-green-700 transition duration-200">
                <i class="fas fa-plus-circle mr-2"></i> New Requisition
            </button>
            <button wire:click="$set('showHistory', true)"
                    class="ml-2 px-6 py-2 rounded-lg {{ $showHistory ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }} hover:bg-blue-700 transition duration-200">
                <i class="fas fa-history mr-2"></i> View History
            </button>
        </div>
    </div>

    @if($message)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($message, '✅') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }} font-semibold text-center">
            {{ $message }}
        </div>
    @endif

    <!-- Main Content -->
    @if(!$showHistory)
        <!-- Create New Requisition -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Create New Purchase Requisition</h2>
                <span class="text-sm text-gray-500">Step {{ $step }} of 3</span>
            </div>

            <!-- Stepper -->
            <div class="flex justify-between mb-8 relative">
                @foreach(['Select Supplier', 'Select Products', 'Review & Submit'] as $index => $label)
                    @php $stepNumber = $index + 1; @endphp
                    <div class="flex flex-col items-center z-10">
                        <div class="w-10 h-10 rounded-full {{ $step >= $stepNumber ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }} flex items-center justify-center font-bold mb-2 transition-all duration-300">
                            {{ $stepNumber }}
                        </div>
                        <span class="text-sm font-medium {{ $step >= $stepNumber ? 'text-green-700' : 'text-gray-500' }}">{{ $label }}</span>
                    </div>
                    @if($stepNumber < 3)
                        <div class="absolute top-5 left-{{ $stepNumber * 25 }}% right-{{ (3 - $stepNumber) * 25 }}% h-0.5 bg-gray-300 {{ $step > $stepNumber ? 'bg-green-600' : '' }}"></div>
                    @endif
                @endforeach
            </div>

            <!-- Step 1: Supplier -->
            @if($step === 1)
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Step 1: Select Supplier</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        @foreach($suppliers as $supplier)
                            <div wire:click="$set('supplierId', {{ $supplier->supplier_id }})"
                                class="cursor-pointer border-2 rounded-xl p-5 hover:border-green-500 hover:shadow-md transition-all duration-300 {{ $supplier->supplier_id === $supplierId ? 'border-green-600 bg-green-50 shadow-sm' : 'border-gray-300' }}">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-building text-green-600 text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <span class="font-semibold text-gray-800 block">{{ $supplier->name }}</span>
                                        <span class="text-sm text-gray-500">Click to select</span>
                                    </div>
                                    @if($supplier->supplier_id === $supplierId)
                                        <div class="text-green-600 text-xl">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end">
                        <button wire:click="nextStep" 
                                class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200 shadow-md hover:shadow-lg">
                            Next: Select Products <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Step 2: Products -->
            @if($step === 2)
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Step 2: Select Products</h3>
                    
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex flex-wrap items-center justify-between">
                            <div class="flex items-center mb-2 md:mb-0">
                                <i class="fas fa-truck text-blue-600 mr-2"></i>
                                <span class="font-medium text-gray-700">Supplier:</span>
                                <span class="ml-2 font-semibold text-blue-800">{{ collect($suppliers)->firstWhere('supplier_id', $supplierId)->name ?? '-' }}</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-box mr-1"></i>
                                {{ count(array_filter($selectedProducts, fn($qty) => $qty > 0)) }} product(s) selected
                            </div>
                        </div>
                    </div>
                    
                    @if(count($products) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-h-[500px] overflow-y-auto p-3">
                            @foreach($products as $product)
                                <div class="border rounded-xl p-5 hover:shadow-lg transition-shadow duration-300 {{ isset($selectedProducts[$product->product_id]) && $selectedProducts[$product->product_id] > 0 ? 'border-green-500 bg-green-50' : 'border-gray-300' }}">
                                    <div class="mb-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-bold text-gray-800 block">{{ $product->product_name }}</span>
                                                <span class="text-sm text-gray-600">{{ $product->category }}</span>
                                            </div>
                                            @if($product->stock_quantity > 0)
                                                <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">
                                                    In Stock: {{ $product->stock_quantity }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($product->price > 0)
                                            <span class="text-lg font-bold text-green-700 block mt-2">
                                                ₱{{ number_format($product->price, 2) }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity:</label>
                                        <div class="flex items-center space-x-2">
                                            <button type="button"
                                                    wire:click="decrementQuantity({{ $product->product_id }})"
                                                    class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center hover:bg-gray-300 transition duration-200">
                                                <i class="fas fa-minus text-gray-700"></i>
                                            </button>
                                            <input type="number" 
                                                   min="0"
                                                   max="{{ $product->stock_quantity }}"
                                                   wire:model.live="selectedProducts.{{ $product->product_id }}"
                                                   class="w-20 h-10 border border-gray-300 rounded-lg text-center font-semibold focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <button type="button"
                                                    wire:click="incrementQuantity({{ $product->product_id }})"
                                                    class="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center hover:bg-gray-300 transition duration-200">
                                                <i class="fas fa-plus text-gray-700"></i>
                                            </button>
                                        </div>
                                        @if(isset($selectedProducts[$product->product_id]) && $selectedProducts[$product->product_id] > 0)
                                            <p class="text-sm font-medium text-green-700 mt-2">
                                                Subtotal: ₱{{ number_format(($selectedProducts[$product->product_id] ?? 0) * ($product->price ?? 0), 2) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Estimated Cost Summary -->
                        @if($estimatedCost > 0)
                            <div class="mt-6 p-5 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-300 shadow-sm">
                                <div class="flex flex-col md:flex-row justify-between items-center">
                                    <div class="mb-4 md:mb-0">
                                        <h4 class="font-bold text-green-800 text-lg flex items-center">
                                            <i class="fas fa-calculator mr-2"></i>
                                            Estimated Cost Summary
                                        </h4>
                                        <p class="text-sm text-green-600 mt-1">Total for all selected items</p>
                                    </div>
                                    <div class="text-center md:text-right">
                                        <p class="text-3xl font-bold text-green-700">₱{{ number_format($estimatedCost, 2) }}</p>
                                        <p class="text-sm text-green-600">
                                            {{ count(array_filter($selectedProducts, fn($qty) => $qty > 0)) }} item(s) selected
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-box-open text-5xl mb-4"></i>
                            <p class="text-lg">No products available for this supplier.</p>
                            <p class="text-sm mt-2">Please select a different supplier or contact procurement.</p>
                        </div>
                    @endif
                    
                    <div class="flex flex-col md:flex-row justify-between mt-8 pt-6 border-t border-gray-200">
                        <button wire:click="previousStep" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-8 py-3 rounded-lg font-medium transition duration-200 mb-3 md:mb-0">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Supplier
                        </button>
                        
                        @if(count(array_filter($selectedProducts, fn($qty) => $qty > 0)) > 0)
                            <button wire:click="nextStep" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200 shadow-md hover:shadow-lg">
                                Next: Review & Submit <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Step 3: Confirm -->
            @if($step === 3)
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Step 3: Review & Submit</h3>

                    <!-- Receipt Preview -->
                    <div class="border-2 border-gray-300 rounded-xl p-8 mb-8 bg-gradient-to-br from-white to-gray-50 shadow-inner">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-green-800">PURCHASE REQUISITION</h3>
                            <p class="text-gray-600 mt-1">Request Summary</p>
                        </div>
                        
                        <!-- Requisition Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-truck text-green-600 mr-3"></i>
                                        Supplier Information
                                    </h4>
                                    <div class="pl-10">
                                        <p class="text-gray-900 font-bold text-lg">{{ collect($suppliers)->firstWhere('supplier_id', $supplierId)->name ?? '-' }}</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-user text-green-600 mr-3"></i>
                                        Requester Information
                                    </h4>
                                    <div class="pl-10">
                                        <p class="text-gray-900 font-bold">{{ Auth::user()->name ?? 'N/A' }}</p>
                                        <p class="text-sm text-gray-600">{{ Auth::user()->email ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-calendar text-green-600 mr-3"></i>
                                        Request Details
                                    </h4>
                                    <div class="pl-10">
                                        <p class="text-gray-900 font-bold">{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</p>
                                        <p class="text-sm text-gray-600">Date Requested</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-clipboard-check text-green-600 mr-3"></i>
                                        Status
                                    </h4>
                                    <div class="pl-10">
        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800 border border-yellow-300">
            <i class="fas fa-clock mr-2"></i>
            Submitted for Approval <!-- Changed from "Pending Approval" -->
        </span>
    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estimated Cost Display -->
                        <div class="mb-8 p-6 bg-gradient-to-r from-blue-100 to-indigo-100 rounded-xl border-2 border-blue-300">
                            <div class="flex flex-col md:flex-row justify-between items-center">
                                <div>
                                    <h4 class="font-bold text-blue-900 text-xl flex items-center">
                                        <i class="fas fa-money-bill-wave mr-3"></i>
                                        TOTAL ESTIMATED COST
                                    </h4>
                                    <p class="text-blue-700 mt-1">Inclusive of all selected items</p>
                                </div>
                                <div class="mt-4 md:mt-0 text-center">
                                    <p class="text-4xl font-black text-blue-900">₱{{ number_format($estimatedCost, 2) }}</p>
                                    <p class="text-sm text-blue-700">Amount in Philippine Peso</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Items Table -->
                        <div class="mb-8">
                            <h4 class="font-bold text-gray-800 mb-4 text-lg border-b pb-2">
                                <i class="fas fa-boxes text-green-600 mr-2"></i>
                                Requested Items
                            </h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-300">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit Price</th>
                                            <th class="px6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach($selectedProducts as $productId => $qty)
                                            @if($qty > 0)
                                                @php
                                                    $prod = collect($products)->firstWhere('product_id', $productId);
                                                    $price = $prod->price ?? 0;
                                                    $subtotal = $qty * $price;
                                                @endphp
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $prod->product_name ?? 'Unknown' }}</td>
                                                    <td class="px-6 py-4 text-gray-700">{{ $prod->category ?? '-' }}</td>
                                                    <td class="px-6 py-4 text-gray-700">₱{{ number_format($price, 2) }}</td>
                                                    <td class="px-6 py-4">
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-100 text-blue-800">
                                                            {{ $qty }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 font-bold text-green-700">₱{{ number_format($subtotal, 2) }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-right font-bold text-gray-900">TOTAL:</td>
                                            <td class="px-6 py-4 font-bold text-blue-900">
                                                {{ count(array_filter($selectedProducts, fn($qty) => $qty > 0)) }} items
                                            </td>
                                            <td class="px-6 py-4 font-bold text-green-900 text-xl">
                                                ₱{{ number_format($estimatedCost, 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between mt-8 pt-6 border-t border-gray-300">
                        <button wire:click="previousStep" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-8 py-3 rounded-lg font-medium transition duration-200 mb-3 md:mb-0">
                            <i class="fas fa-edit mr-2"></i> Edit Products
                        </button>
                        
                        <div class="space-x-4">
                            <button wire:click="$set('showHistory', true)"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-history mr-2"></i> View History
                            </button>
                            <button wire:click="submit" 
                                    class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-black px-10 py-3 rounded-lg font-bold transition duration-200 shadow-lg hover:shadow-xl">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Requisition
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <!-- Requisition History -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">My Requisition History</h2>
                <button wire:click="closeHistory" 
                        class="text-gray-600 hover:text-gray-800 transition duration-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            @if($selectedRequisitionId === 0)
                <!-- History List -->
                <div class="overflow-x-auto rounded-lg border border-gray-300">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Requisition #</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier(s)</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                               
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($requisitionHistory as $requisition)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-mono font-medium text-gray-900">
                                        {{ $requisition->requisition_id ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ \Carbon\Carbon::parse($requisition->date_requested)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ $requisition->supplier_names ?? 'Multiple' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-box mr-1"></i> {{ $requisition->item_count ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-green-700">
                                        ₱{{ number_format($requisition->estimated_cost ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $status = $requisition->status ?? 'pending';
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                                'approved' => 'bg-green-100 text-green-800 border-green-300',
                                                'rejected' => 'bg-red-100 text-red-800 border-red-300',
                                                'processing' => 'bg-blue-100 text-blue-800 border-blue-300',
                                                'completed' => 'bg-purple-100 text-purple-800 border-purple-300',
                                                'cancelled' => 'bg-gray-100 text-gray-800 border-gray-300',
                                            ];
                                            $statusIcons = [
                                                'pending' => 'fa-clock',
                                                'approved' => 'fa-check-circle',
                                                'rejected' => 'fa-times-circle',
                                                'processing' => 'fa-cog',
                                                'completed' => 'fa-check-double',
                                                'cancelled' => 'fa-ban',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold border {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                            <i class="fas {{ $statusIcons[$status] ?? 'fa-question-circle' }} mr-2"></i>
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(empty($requisitionHistory))
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-history text-5xl mb-4"></i>
                        <p class="text-lg">No requisition history found.</p>
                        <p class="text-sm mt-2">Create your first purchase requisition to get started.</p>
                    </div>
                @endif
            @else
                <!-- Requisition Details View -->
                <div class="bg-gray-50 rounded-xl p-6 border-2 border-gray-300">
                    <div class="flex justify-between items-center mb-8 pb-4 border-b border-gray-300">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Requisition Details</h3>
                            <p class="text-gray-600">Requisition #{{ $selectedRequisitionId }}</p>
                        </div>
                        <div>
                            @php
                                $status = $requisitionDetails['status'] ?? 'pending';
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'approved' => 'bg-green-100 text-green-800 border-green-300',
                                    'rejected' => 'bg-red-100 text-red-800 border-red-300',
                                    'processing' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    'completed' => 'bg-purple-100 text-purple-800 border-purple-300',
                                    'cancelled' => 'bg-gray-100 text-gray-800 border-gray-300',
                                ];
                                $statusIcons = [
                                    'pending' => 'fa-clock',
                                    'approved' => 'fa-check-circle',
                                    'rejected' => 'fa-times-circle',
                                    'processing' => 'fa-cog',
                                    'completed' => 'fa-check-double',
                                    'cancelled' => 'fa-ban',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold border {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                <i class="fas {{ $statusIcons[$status] ?? 'fa-question-circle' }} mr-2"></i>
                                {{ ucfirst($status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <div class="mb-8 p-6 bg-white rounded-lg border border-gray-300">
                        <h4 class="font-bold text-gray-700 mb-6 text-lg">Status Timeline</h4>
                        <div class="relative">
                            <!-- Timeline Line -->
                            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-300"></div>
                            
                            @php
                                $timelineSteps = [
                                    ['status' => 'pending', 'label' => 'Request Submitted', 'icon' => 'fa-paper-plane'],
                                    ['status' => 'processing', 'label' => 'Under Review', 'icon' => 'fa-search'],
                                    ['status' => 'approved', 'label' => 'Approved', 'icon' => 'fa-check'],
                                    ['status' => 'completed', 'label' => 'Completed', 'icon' => 'fa-check-double'],
                                ];
                                
                                $currentStatusIndex = array_search($status, array_column($timelineSteps, 'status'));
                                $currentStatusIndex = $currentStatusIndex !== false ? $currentStatusIndex : 0;
                            @endphp
                            
                            <div class="space-y-8">
                                @foreach($timelineSteps as $index => $step)
                                    @php
                                        $isActive = $index <= $currentStatusIndex;
                                        $isCurrent = $index === $currentStatusIndex;
                                    @endphp
                                    <div class="relative flex items-start">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center z-10 {{ $isActive ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }}">
                                                <i class="fas {{ $step['icon'] }}"></i>
                                            </div>
                                        </div>
                                        <div class="ml-6">
                                            <h5 class="font-medium {{ $isActive ? 'text-green-700' : 'text-gray-500' }}">
                                                {{ $step['label'] }}
                                            </h5>
                                            @if($isCurrent)
                                                <p class="text-sm text-gray-600 mt-1">Current Status</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Requisition Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="p-5 bg-white rounded-lg border border-gray-300">
                            <h4 class="font-bold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                Requisition Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date Requested:</span>
                                    <span class="font-medium">{{ \Carbon\Carbon::parse($requisitionDetails['date_requested'] ?? now())->format('F d, Y') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Requested By:</span>
                                    <span class="font-medium">{{ $requisitionDetails['requester_name'] ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Department:</span>
                                    <span class="font-medium">{{ $requisitionDetails['department_name'] ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Estimated Cost:</span>
                                    <span class="font-bold text-green-700">₱{{ number_format($requisitionDetails['estimated_cost'] ?? 0, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 bg-white rounded-lg border border-gray-300">
                            <h4 class="font-bold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-file-invoice-dollar text-green-600 mr-2"></i>
                                Cost Summary
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Items:</span>
                                    <span class="font-medium">{{ count($requisitionItems) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Quantity:</span>
                                    <span class="font-medium">
                                        {{ array_sum(array_column($requisitionItems, 'quantity')) }}
                                    </span>
                                </div>
                                <div class="pt-3 border-t border-gray-300">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                                        <span class="text-2xl font-bold text-green-700">
                                            ₱{{ number_format($requisitionDetails['estimated_cost'] ?? 0, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="mb-6">
                        <h4 class="font-bold text-gray-700 mb-4 text-lg">Requested Items</h4>
                        <div class="overflow-x-auto rounded-lg border border-gray-300">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Quantity</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Subtotal</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($requisitionItems as $item)
                                        <tr>
                                            <td class="px-6 py-4 font-medium text-gray-900">{{ $item->product_name ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 text-gray-700">{{ $item->supplier_name ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 text-gray-700">₱{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                    {{ $item->quantity ?? 0 }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-green-700">
                                                ₱{{ number_format(($item->quantity ?? 0) * ($item->unit_price ?? 0), 2) }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $item->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                    {{ ucfirst($item->status ?? 'pending') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between pt-6 border-t border-gray-300">
                        <button wire:click="$set('selectedRequisitionId', 0)"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back to List
                        </button>
                        
                        @if($status === 'pending')
                            <div class="space-x-4">
                                <button class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                                    <i class="fas fa-times mr-2"></i> Cancel Request
                                </button>
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                                    <i class="fas fa-edit mr-2"></i> Edit Request
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
