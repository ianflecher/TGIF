<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

new #[Layout('components.layouts.landing')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;

    public function mount()
    {
        // Debug: Check if already logged in
        if (Auth::check()) {
            logger()->info('User already logged in', ['user_id' => Auth::id()]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function login()
{
    $this->validate();

    $credentials = ['password' => $this->password];

    if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
        $credentials['email'] = $this->username;
    } else {
        $credentials['username'] = $this->username;
    }

    // Check if user exists with employee OR manager role
    $userExists = DB::table('users')
        ->where(function($query) use ($credentials) {
            if (isset($credentials['email'])) {
                $query->where('email', $credentials['email']);
            } else {
                $query->where('username', $credentials['username']);
            }
        })
        ->whereIn('role', ['employee', 'manager']) // allow both roles
        ->whereNull('deleted_at')
        ->exists();

    if (!$userExists) {
        throw ValidationException::withMessages([
            'username' => __('Access denied. Employee or Manager credentials required.'),
        ]);
    }

    // Attempt authentication
    if (!Auth::attempt($credentials, $this->remember)) {
        throw ValidationException::withMessages([
            'username' => __('Invalid credentials.'),
        ]);
    }

    session()->regenerate();

    $authUser = Auth::user();

    // Optional: Check employee-specific table only if role is employee
    if ($authUser->role === 'employee') {
        $employeeExists = DB::table('employees')
            ->where('user_id', $authUser->user_id)
            ->where('status', 'active')
            ->exists();

        if (!$employeeExists) {
            Auth::logout();
            throw ValidationException::withMessages([
                'username' => __('Employee account is inactive or not properly configured.'),
            ]);
        }
    }

    // Redirect based on role
    if ($authUser->role === 'employee') {
        return redirect()->route('employee.dashboard');
    } elseif ($authUser->role === 'manager') {
        return redirect()->route('manager.dashboard'); // make sure this route exists
    }

    // Default fallback
    Auth::logout();
    throw ValidationException::withMessages([
        'username' => __('Unauthorized access.'),
    ]);
}

}
?>
<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Customer Login</h1>
            <p class="text-gray-600">Sign in to access your orders and account</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300">
            <div class="p-8">
                <form wire:submit.prevent="login" class="space-y-6">
                    @csrf
                    
                    <!-- Username/Email -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username or Email
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="username" 
                                id="username"
                                x-ref="username"
                                required
                                autofocus
                                placeholder="Enter username or email@example.com"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-green-600 hover:text-green-700 font-medium">
                                    Forgot password?
                                </a>
                            @endif
                        </div>
                        <div class="relative">
                            <input 
                                :type="showPassword ? 'text' : 'password'"
                                wire:model="password" 
                                id="password"
                                required
                                placeholder="Enter your password"
                                class="w-full pl-10 pr-10 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-lock"></i>
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
                            class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Signing in...
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
                            <span class="px-2 bg-white text-gray-500">New to TGIF?</span>
                        </div>
                    </div>
                </div>

                <!-- Register Link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('register') }}" 
                       class="inline-flex items-center gap-2 bg-white text-green-600 border-2 border-green-600 py-3 px-6 rounded-lg hover:bg-green-50 transition font-medium shadow-sm hover:shadow">
                        <i class="fas fa-user-plus"></i>
                        Create New Account
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-green-50 px-8 py-4 border-t border-green-100">
                <p class="text-xs text-center text-green-700">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Secure customer portal. Your data is protected.
                </p>
            </div>
        </div>
    </div>
</div>