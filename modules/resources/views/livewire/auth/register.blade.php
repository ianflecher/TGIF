<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

new #[Layout('components.layouts.landing')] class extends Component
{
    public string $full_name = '';
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function register()
    {
        $this->validate();

        // Start database transaction
        DB::beginTransaction();

        try {
            // Create user in users table
            $userId = DB::table('users')->insertGetId([
                'full_name' => $this->full_name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => 'customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create customer in customers table
            $customerId = DB::table('customers')->insertGetId([
                'first_name' => explode(' ', $this->full_name)[0] ?? '',
                'last_name' => explode(' ', $this->full_name)[1] ?? '',
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'user_id' => $userId,
                'date_registered' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // Automatically log in the user
            $credentials = [
                'email' => $this->email,
                'password' => $this->password,
            ];

            if (Auth::attempt($credentials)) {
                session()->regenerate();
                return redirect()->route('customer.dashboard');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'email' => __('Registration failed. Please try again.'),
            ]);
        }
    }

    public function mount()
    {
        // If user is already logged in, redirect to dashboard
        if (Auth::check() && Auth::user()->role === 'customer') {
            return redirect()->route('customer.dashboard');
        }
    }
}
?>

<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-green-100 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-green-200">
                <img 
                    src="{{ asset('TGIF.png') }}" 
                    alt="TGIF Fries Logo" 
                    class="w-16 h-16 object-contain"
                >
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Account</h1>
            <p class="text-gray-600">Join our community of fry lovers</p>
        </div>

        <!-- Registration Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="p-8">
                <form wire:submit="register" class="space-y-6">
                    @csrf
                    
                    <!-- Full Name -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name *
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="full_name" 
                                id="full_name"
                                required
                                autofocus
                                placeholder="Enter your full name"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        @error('full_name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username *
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="username" 
                                id="username"
                                required
                                placeholder="Choose a username"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-at"></i>
                            </div>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address *
                        </label>
                        <div class="relative">
                            <input 
                                type="email" 
                                wire:model="email" 
                                id="email"
                                required
                                placeholder="email@example.com"
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

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <div class="relative">
                            <input 
                                type="tel" 
                                wire:model="phone" 
                                id="phone"
                                placeholder="Enter phone number"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>
                        @error('phone')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Address -->
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                            Address
                        </label>
                        <div class="relative">
                            <textarea 
                                wire:model="address" 
                                id="address"
                                rows="2"
                                placeholder="Enter your address"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition resize-none"
                            ></textarea>
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-home"></i>
                            </div>
                        </div>
                        @error('address')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password *
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                wire:model="password" 
                                id="password"
                                required
                                placeholder="Enter password (min. 8 characters)"
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password *
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                wire:model="password_confirmation" 
                                id="password_confirmation"
                                required
                                placeholder="Confirm your password"
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600" onclick="togglePassword('password_confirmation')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Terms Agreement -->
                    <div class="flex items-start">
                        <input 
                            type="checkbox" 
                            id="terms"
                            required
                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-1"
                        >
                        <label for="terms" class="ml-2 block text-sm text-gray-700">
                            I agree to the 
                            <a href="#" class="text-green-600 hover:text-green-700 font-medium">Terms of Service</a> 
                            and 
                            <a href="#" class="text-green-600 hover:text-green-700 font-medium">Privacy Policy</a>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="register">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </span>
                            <span wire:loading wire:target="register">
                                <i class="fas fa-spinner fa-spin"></i>
                                Creating Account...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Already have an account?</span>
                        </div>
                    </div>
                </div>

                <!-- Login Link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('login') }}" 
                       class="inline-flex items-center gap-2 bg-white text-green-600 border-2 border-green-600 py-3 px-6 rounded-lg hover:bg-green-50 transition font-medium shadow-sm hover:shadow">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In Instead
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-green-50 px-8 py-4 border-t border-green-100">
                <p class="text-xs text-center text-green-700">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Your information is secure and will never be shared.
                </p>
            </div>
        </div>

        <!-- Registration Benefits -->
        <div class="mt-8 bg-green-50 rounded-xl p-6 border border-green-100">
            <h3 class="text-lg font-semibold text-green-800 mb-4">Why Register?</h3>
            <ul class="space-y-3">
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                    <span class="text-sm text-gray-700">Track your orders and delivery status</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                    <span class="text-sm text-gray-700">Save favorite products for quick reordering</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                    <span class="text-sm text-gray-700">Get exclusive deals and promotions</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                    <span class="text-sm text-gray-700">Fast checkout with saved addresses</span>
                </li>
            </ul>
        </div>
    </div>
</div>

@script
<script>
    // Toggle password visibility
    function togglePassword(inputId) {
        const passwordInput = document.getElementById(inputId);
        const eyeIcon = event.currentTarget.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }

    // Auto-focus name field
    document.addEventListener('DOMContentLoaded', function() {
        const nameField = document.getElementById('full_name');
        if (nameField) {
            nameField.focus();
        }
    });
</script>
@endscript