<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new #[Layout('components.layouts.procurement')] class extends Component
{
    public int $step = 1; // current step
    public int $supplierId = 0;
    public array $suppliers = [];
    public array $products = [];
    public array $selectedProducts = []; // product_id => quantity
    public string $status = 'Pending';
    public string $date = '';
    public string $message = '';

    public ?int $employeeId = null; // Can be null for admin users

    public function mount(): void
    {
        // For admin users, we might not have employee_id
        $user = Auth::user();
        $this->employeeId = $user->employee_id ?? null;

        // Load suppliers - CORRECTED: Using 'suppliers' (plural)
        $this->suppliers = DB::table('suppliers')
            ->select('supplier_id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->supplierId = $this->suppliers[0]->supplier_id ?? 0;

        // Load products for the first supplier
        $this->loadProducts();

        $this->date = Carbon::now()->toDateString();
    }

    public function nextStep()
    {
        $this->step++;
    }

    public function previousStep()
    {
        $this->step--;
    }

    // Load products when supplier changes
    public function updatedSupplierId(): void
    {
        $this->loadProducts();
        $this->selectedProducts = []; // clear previous selection
    }

    protected function loadProducts(): void
    {
        if ($this->supplierId === 0) {
            $this->products = [];
            return;
        }

        $this->products = DB::table('products')
            ->where('supplier_id', $this->supplierId)
            ->select('product_id', 'product_name', 'category')
            ->orderBy('product_name')
            ->get()
            ->toArray();
    }

    public function submit()
    {
        // Check if user is authorized (admin or purchasing officer)
        $user = Auth::user();
        
        if (!$user) {
            $this->message = '⚠ Please log in to continue.';
            return;
        }

        // Check if user has access to procurement
        if (!in_array($user->role, ['admin', 'manager', 'employee', 'purchasing'])) {
            $this->message = '⚠ You do not have permission to create purchase requisitions.';
            return;
        }

        if ($this->supplierId === 0 || empty($this->selectedProducts)) {
            $this->message = '⚠ Please complete all steps.';
            return;
        }

        // For admin users, we can use user_id instead of employee_id
        // or create a default employee record for admin
        $requisitionData = [
            'status' => $this->status,
            'date' => $this->date,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Use employee_id if available, otherwise use user_id
        if ($this->employeeId) {
            $requisitionData['employee_id'] = $this->employeeId;
            $requisitionData['requested_by'] = $user->full_name ?? $user->name;
        } else {
            // For admin users, store the user_id or create a system entry
            $requisitionData['user_id'] = $user->user_id;
            $requisitionData['requested_by'] = $user->full_name ?? $user->name;
        }

        // Insert requisition - Assuming your table is 'requisitions' (plural)
        $requisitionId = DB::table('requisitions')->insertGetId($requisitionData);

        // Insert requisition items - Assuming your table is 'requisition_items' (plural)
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

        $this->message = '✅ Requisition created successfully!';
        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->step = 1;
        $this->supplierId = $this->suppliers[0]->supplier_id ?? 0;
        $this->selectedProducts = [];
        $this->loadProducts();
    }
};
?>

<div class="p-8 bg-gray-100 min-h-screen max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold text-green-800 mb-6 text-center">Create Purchase Requisition</h1>

    @if($message)
        <div class="mb-6 p-4 rounded-lg {{ str_contains($message, '✅') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }} font-semibold text-center">
            {{ $message }}
        </div>
    @endif

    <!-- Debug: Show current step and supplierId -->
    <div class="hidden">Debug: Step {{ $step }}, Supplier ID: {{ $supplierId }}</div>

    <!-- Stepper Navigation -->
    <div class="flex justify-between mb-6 relative">
        <div class="flex flex-col items-center">
            <div class="w-8 h-8 rounded-full {{ $step >= 1 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }} flex items-center justify-center font-bold mb-1">
                1
            </div>
            <span class="text-sm {{ $step >= 1 ? 'text-green-700 font-semibold' : 'text-gray-500' }}">Supplier</span>
        </div>
        <div class="absolute top-4 left-1/4 right-1/4 h-0.5 bg-gray-300 -translate-y-3 {{ $step >= 2 ? 'bg-green-600' : '' }}"></div>
        
        <div class="flex flex-col items-center">
            <div class="w-8 h-8 rounded-full {{ $step >= 2 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }} flex items-center justify-center font-bold mb-1">
                2
            </div>
            <span class="text-sm {{ $step >= 2 ? 'text-green-700 font-semibold' : 'text-gray-500' }}">Products</span>
        </div>
        <div class="absolute top-4 left-1/2 right-1/4 h-0.5 bg-gray-300 -translate-y-3 {{ $step >= 3 ? 'bg-green-600' : '' }}"></div>
        
        <div class="flex flex-col items-center">
            <div class="w-8 h-8 rounded-full {{ $step >= 3 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }} flex items-center justify-center font-bold mb-1">
                3
            </div>
            <span class="text-sm {{ $step >= 3 ? 'text-green-700 font-semibold' : 'text-gray-500' }}">Confirm</span>
        </div>
    </div>

    <!-- Step 1: Supplier -->
    @if($step === 1)
        <div class="bg-white p-6 rounded-lg shadow max-w-lg mx-auto mb-6">
            <div class="flex items-center mb-4">
                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-truck"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Step 1: Select Supplier</h2>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Choose a supplier:</label>
                <div class="grid grid-cols-1 gap-3">
                    @foreach($suppliers as $supplier)
                        <div wire:click="$set('supplierId', {{ $supplier->supplier_id }})"
                            wire:key="supplier-{{ $supplier->supplier_id }}"
                            class="cursor-pointer border rounded-lg p-4 hover:border-green-500 hover:bg-green-50 transition-colors duration-200 {{ $supplier->supplier_id === $supplierId ? 'border-green-600 bg-green-50' : 'border-gray-300' }}">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-building text-green-600"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-800">{{ $supplier->name }}</span>
                                </div>
                                @if($supplier->supplier_id === $supplierId)
                                    <div class="ml-auto text-green-600">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <div class="flex justify-end">
                <button wire:click="nextStep" 
                        class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 shadow hover:shadow-lg">
                    <span>Next: Select Products</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    @endif

    <!-- Step 2: Products -->
    @if($step === 2)
        <div class="bg-white p-6 rounded-lg shadow max-w-4xl mx-auto mb-6">
            <div class="flex items-center mb-4">
                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-box"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Step 2: Select Products & Quantities</h2>
            </div>
            
            <div class="mb-4">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-gray-700">
                        <i class="fas fa-truck text-green-600 mr-1"></i>
                        Supplier: <strong>{{ collect($suppliers)->firstWhere('supplier_id', $supplierId)->name ?? '-' }}</strong>
                    </span>
                    <span class="text-sm text-gray-500">
                        {{ count(array_filter($selectedProducts, fn($qty) => $qty > 0)) }} product(s) selected
                    </span>
                </div>
                
                @if(count($products) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto p-2">
                        @foreach($products as $product)
                            <div class="border rounded-lg p-4 hover:shadow-md transition-shadow {{ isset($selectedProducts[$product->product_id]) && $selectedProducts[$product->product_id] > 0 ? 'border-green-500 bg-green-50' : 'border-gray-300' }}">
                                <label class="flex items-start mb-2 w-full cursor-pointer">
                                    <input type="checkbox" 
                                           wire:model="selectedProducts.{{ $product->product_id }}" 
                                           value="1" 
                                           class="mr-2 mt-1 w-5 h-5 text-green-600">
                                    <div class="flex-1">
                                        <span class="font-medium text-gray-800 block">{{ $product->product_name }}</span>
                                        <span class="text-sm text-gray-600">{{ $product->category }}</span>
                                    </div>
                                </label>
                                
                                @if(isset($selectedProducts[$product->product_id]) && $selectedProducts[$product->product_id])
                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity:</label>
                                        <div class="flex items-center">
                                            <button wire:click="$set('selectedProducts.{{ $product->product_id }}', {{ max(1, ($selectedProducts[$product->product_id] ?? 1) - 1) }})"
                                                    class="w-8 h-8 bg-gray-200 rounded-l-lg flex items-center justify-center hover:bg-gray-300">
                                                <i class="fas fa-minus text-gray-700"></i>
                                            </button>
                                            <input type="number" 
                                                   min="1" 
                                                   wire:model="selectedProducts.{{ $product->product_id }}"
                                                   class="w-16 h-8 border-y border-gray-300 text-center outline-none">
                                            <button wire:click="$set('selectedProducts.{{ $product->product_id }}', {{ ($selectedProducts[$product->product_id] ?? 0) + 1 }})"
                                                    class="w-8 h-8 bg-gray-200 rounded-r-lg flex items-center justify-center hover:bg-gray-300">
                                                <i class="fas fa-plus text-gray-700"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-box-open text-4xl mb-3"></i>
                        <p>No products available for this supplier.</p>
                    </div>
                @endif
            </div>
            
            <div class="flex justify-between mt-6">
                <button wire:click="previousStep" 
                        class="flex items-center gap-2 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </button>
                
                @if(count(array_filter($selectedProducts, fn($qty) => $qty > 0)) > 0)
                    <button wire:click="nextStep" 
                            class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 shadow hover:shadow-lg">
                        <span>Next: Review & Submit</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Step 3: Confirm -->
    @if($step === 3)
        <div class="bg-white p-6 rounded-lg shadow max-w-4xl mx-auto mb-6">
            <div class="flex items-center mb-4">
                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Step 3: Review & Submit</h2>
            </div>

            <!-- Receipt Preview -->
            <div class="border border-gray-300 rounded-lg p-6 mb-6 bg-gradient-to-r from-green-50 to-emerald-50">
                <h3 class="text-lg font-semibold text-green-800 mb-4 pb-2 border-b border-green-200">Purchase Requisition Summary</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-truck text-green-600 mr-2"></i>
                                Supplier
                            </h4>
                            <p class="text-gray-900 font-medium pl-6">{{ collect($suppliers)->firstWhere('supplier_id', $supplierId)->name ?? '-' }}</p>
                        </div>
                        
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-user text-green-600 mr-2"></i>
                                Requested By
                            </h4>
                            <p class="text-gray-900 font-medium pl-6">{{ Auth::user()->full_name ?? Auth::user()->name }}</p>
                            <p class="text-sm text-gray-500 pl-6">{{ Auth::user()->role ?? 'User' }}</p>
                        </div>
                    </div>
                    
                    <div>
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-calendar text-green-600 mr-2"></i>
                                Request Date
                            </h4>
                            <p class="text-gray-900 font-medium pl-6">{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</p>
                        </div>
                        
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-clipboard-check text-green-600 mr-2"></i>
                                Status
                            </h4>
                            <p class="text-gray-900 font-medium pl-6">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $status }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="font-medium text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-boxes text-green-600 mr-2"></i>
                        Products Requested
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($selectedProducts as $productId => $qty)
                                    @if($qty > 0)
                                        @php
                                            $prod = collect($products)->firstWhere('product_id', $productId);
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $prod->product_name ?? 'Unknown Product' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $prod->category ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-green-700">{{ $qty }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Total Items:</td>
                                    <td class="px-4 py-3 text-sm font-bold text-green-800">
                                        {{ count(array_filter($selectedProducts, fn($qty) => $qty > 0)) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <button wire:click="previousStep" 
                        class="flex items-center gap-2 bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Products</span>
                </button>
                
                <button wire:click="submit" 
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200 shadow-lg hover:shadow-xl">
                    <span wire:loading.remove wire:target="submit">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Requisition
                    </span>
                    <span wire:loading wire:target="submit">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Submitting...
                    </span>
                </button>
            </div>
        </div>
    @endif
</div>

@script
<script>
    // Add some interactive features
    document.addEventListener('livewire:initialized', () => {
        // Auto-scroll to top when changing steps
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            // Check if this is a step change
            if (component.get('step') && document.documentElement) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
        
        // Debug logging
        console.log('Livewire component initialized');
        
        // Make sure buttons are working
        document.addEventListener('click', function(e) {
            if (e.target.closest('button[wire\\:click]')) {
                console.log('Button clicked:', e.target.closest('button[wire\\:click]').getAttribute('wire:click'));
            }
        });
    });
</script>
@endscript