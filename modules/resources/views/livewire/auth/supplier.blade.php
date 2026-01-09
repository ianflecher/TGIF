<?php

namespace App\Livewire\Auth;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.supplier')] class extends Component
{
    #[Validate('required|string')]
    public $email = '';
    
    #[Validate('required|string')]
    public $password = 'supplier123'; // Default password
    
    public $remember = false;
    
    public function mount()
    {
        // Set default password on mount
        $this->password = 'supplier123';
    }
    
    public function login()
    {
        $this->validate();
        
        // Check if supplier exists with this email
        $supplier = DB::table('suppliers')
            ->where('email', $this->email)
            ->where('status', 'active')
            ->first();
        
        if (!$supplier) {
            $this->addError('email', 'Invalid supplier email or account not active.');
            return;
        }
        
        // Since password is always 'supplier123', we can just check it directly
        if ($this->password !== 'supplier123') {
            $this->addError('password', 'Invalid password. Default password is supplier123');
            return;
        }
        
        // Store supplier in session
        session(['supplier' => $supplier]);
        
        return redirect()->route('supplier.dashboard')
            ->with('success', 'Welcome back, ' . $supplier->name . '!');
    }
}

?>

<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.email.focus()">
    <div class="max-w-md mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-emerald-200 shadow-lg">
                <i class="fas fa-truck text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Supplier Portal</h1>
            <p class="text-gray-600">Partner Portal Access</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="p-8">
                @if (session('success'))
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="text-green-800">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif
                
                <form wire:submit.prevent="login" class="space-y-6">
                    @csrf
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Supplier Email
                        </label>
                        <div class="relative">
                            <input 
                                type="email" 
                                wire:model="email" 
                                id="email"
                                x-ref="email"
                                required
                                autofocus
                                placeholder="deekutchiki123@gmail.com"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <span class="text-xs text-green-600 font-medium">
                                Default: supplier123
                            </span>
                        </div>
                        <div class="relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                wire:model="password" 
                                id="password"
                                required
                                value="supplier123"
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-key"></i>
                            </div>
                            <button 
                                type="button" 
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" 
                                @click="showPassword = !showPassword"
                            >
                                <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Default password for all supplier accounts
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Quick Login Helper -->
                    <div class="p-3 bg-blue-50 border border-blue-100 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-blue-800">Test Supplier Account</p>
                                <p class="text-xs text-blue-700 mt-1">Email: deekutchiki123@gmail.com</p>
                            </div>
                            <button 
                                type="button" 
                                wire:click="$set('email', 'deekutchiki123@gmail.com')"
                                class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded-lg transition"
                            >
                                <i class="fas fa-bolt mr-1"></i>
                                Fill
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 px-4 rounded-lg hover:from-green-700 hover:to-emerald-700 transition font-medium flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Access Supplier Portal
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Signing in...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Supplier Access Info -->
                <div class="mt-6 p-4 bg-green-50 border border-green-100 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-green-600 mt-0.5 mr-2"></i>
                        <div>
                            <p class="text-xs font-medium text-green-800 mb-1">📋 Supplier Portal Access</p>
                            <p class="text-xs text-green-700">
                                Use the email registered in our system. Default password is <span class="font-bold">supplier123</span>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-4 border-t border-green-100">
                <div class="flex items-center justify-center">
                    <i class="fas fa-handshake text-green-600 mr-2"></i>
                    <p class="text-xs text-center text-green-800 font-medium">
                        TGIF Supplier Partnership Portal
                    </p>
                </div>
            </div>
        </div>

        <!-- Default Supplier Info -->
        <div class="mt-8 text-center">
            <details class="inline-block">
                <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                    <i class="fas fa-question-circle mr-1"></i>
                    Need Help?
                </summary>
                <div class="mt-2 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs text-gray-600 mb-2">
                        Supplier login credentials:
                    </p>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li><i class="fas fa-envelope mr-2"></i> Use your registered email</li>
                        <li><i class="fas fa-key mr-2"></i> Default password: <code class="bg-gray-200 px-1 rounded">supplier123</code></li>
                        <li><i class="fas fa-exclamation-triangle mr-2 text-amber-600"></i> Contact support if login fails</li>
                    </ul>
                </div>
            </details>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('login-error', (error) => {
            alert(error.message);
        });
        
        // Auto-fill for testing
        @this.$refs.email?.focus();
    });
</script>
@endscript