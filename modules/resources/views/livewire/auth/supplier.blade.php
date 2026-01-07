<?php

namespace App\Livewire\Auth;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.supplier')] class extends Component
{
    #[Validate('required|string')]
    public $login = '';
    
    #[Validate('required|string')]
    public $password = '';
    
    public $remember = false;
    
    public function login()
    {
        $this->validate();
        
        $fieldType = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        if (Auth::attempt([
            $fieldType => $this->login,
            'password' => $this->password,
            'role' => 'supplier'
        ], $this->remember)) {
            session()->regenerate();
            return redirect()->route('supplier.dashboard')
                ->with('success', 'Welcome back!');
        }
        
        $this->addError('login', 'Invalid credentials or not authorized as supplier.');
    }
}

?>

<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.login.focus()">
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
                    
                    <!-- Username/Email -->
                    <div>
                        <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                            Email or Username
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="login" 
                                id="login"
                                x-ref="login"
                                required
                                autofocus
                                placeholder="supplier@example.com"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                        @error('login')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                        </div>
                        <div class="relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                wire:model="password" 
                                id="password"
                                required
                                placeholder="••••••••"
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
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                                This portal is for <span class="font-bold">TGIF suppliers only</span>. 
                                Contact our support team if you need supplier account access.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Supplier Information -->
                <div class="mt-6 text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-emerald-100 text-emerald-800 rounded-full">
                        <i class="fas fa-truck mr-2"></i>
                        <span class="text-sm font-medium">Supplier Partner Account</span>
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

        <!-- Default Supplier Info (Optional - remove in production) -->
        <div class="mt-8 text-center">
            <details class="inline-block">
                <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Need Supplier Access?
                </summary>
                <div class="mt-2 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs text-gray-600 mb-2">
                        To apply for supplier access, please contact our procurement team:
                    </p>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li><i class="fas fa-envelope mr-2"></i> procurement@tgif.com</li>
                        <li><i class="fas fa-phone mr-2"></i> (555) 123-4567</li>
                        <li><i class="fas fa-building mr-2"></i> Business Hours: Mon-Fri, 9AM-5PM</li>
                    </ul>
                </div>
            </details>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('livewire:initialized', () => {
        @this.$refs.login?.focus();
    });
</script>
@endscript