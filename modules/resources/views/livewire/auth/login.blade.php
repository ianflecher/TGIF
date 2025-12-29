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

        // Debug: Log input
        logger()->info('Login attempt started', [
            'username' => $this->username,
            'remember' => $this->remember,
            'ip' => request()->ip()
        ]);

        // Prepare credentials for authentication
        $credentials = ['password' => $this->password];
        
        // Determine if input is email or username
        if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $this->username;
            logger()->info('Using email for authentication');
        } else {
            $credentials['username'] = $this->username;
            logger()->info('Using username for authentication');
        }

        // Debug: Log credentials (except password)
        logger()->info('Auth credentials prepared', [
            'auth_field' => isset($credentials['email']) ? 'email' : 'username',
            'auth_value' => $this->username
        ]);

        // First, check if user exists in users table with customer role
        $userExists = DB::table('users')
            ->where(function($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } else {
                    $query->where('username', $credentials['username']);
                }
            })
            ->where('role', 'customer')
            ->whereNull('deleted_at')
            ->exists();

        logger()->info('User exists check', ['user_exists' => $userExists]);

        if (!$userExists) {
            logger()->warning('User not found or not a customer', ['username' => $this->username]);
            throw ValidationException::withMessages([
                'username' => __('Invalid credentials or account not found. Please check your username/email and try again.'),
            ]);
        }

        // Then check if customer record exists in customers table
        if (isset($credentials['email'])) {
            $customerExists = DB::table('customers')
                ->join('users', 'customers.user_id', '=', 'users.user_id')
                ->where('users.email', $credentials['email'])
                ->where('users.role', 'customer')
                ->exists();
        } else {
            $customerExists = DB::table('customers')
                ->join('users', 'customers.user_id', '=', 'users.user_id')
                ->where('users.username', $credentials['username'])
                ->where('users.role', 'customer')
                ->exists();
        }

        logger()->info('Customer record check', ['customer_exists' => $customerExists]);

        if (!$customerExists) {
            logger()->error('Customer record missing', ['username' => $this->username]);
            throw ValidationException::withMessages([
                'username' => __('Customer account not properly configured. Please contact support.'),
            ]);
        }

        // Debug: Check the actual user record
        $userRecord = DB::table('users')
            ->where(function($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } else {
                    $query->where('username', $credentials['username']);
                }
            })
            ->where('role', 'customer')
            ->whereNull('deleted_at')
            ->first(['user_id', 'email', 'username', 'password']);

        logger()->info('User record found', [
            'user_id' => $userRecord?->user_id,
            'email' => $userRecord?->email,
            'username' => $userRecord?->username,
            'has_password' => !empty($userRecord?->password)
        ]);

        // Now attempt authentication
        logger()->info('Attempting Auth::attempt', ['credentials_keys' => array_keys($credentials)]);
        
        if (!Auth::attempt($credentials, $this->remember)) {
            logger()->warning('Auth::attempt failed', ['username' => $this->username]);
            
            // Additional debug: Check password manually
            if ($userRecord) {
                $isPasswordValid = password_verify($this->password, $userRecord->password);
                logger()->info('Manual password check', [
                    'password_match' => $isPasswordValid,
                    'password_provided_length' => strlen($this->password),
                    'hashed_password_exists' => !empty($userRecord->password)
                ]);
            }
            
            throw ValidationException::withMessages([
                'username' => __('These credentials do not match our records.'),
            ]);
        }

        logger()->info('Auth::attempt successful', ['user_id' => Auth::id()]);

        session()->regenerate();
        
        logger()->info('Session regenerated', ['session_id' => session()->getId()]);

        // Get authenticated user
        $user = Auth::user();
        logger()->info('User authenticated', [
            'user_id' => $user->id ?? null,
            'name' => $user->name ?? null,
            'email' => $user->email ?? null
        ]);

        // Debug: Check if we're actually authenticated
        logger()->info('Auth check after login', ['is_authenticated' => Auth::check()]);

        // Use Livewire's redirect method with more debugging
        logger()->info('Attempting redirect to /dashboard');
        
        try {
            $this->redirect('/dashboard', navigate: true);
            logger()->info('Redirect method called successfully');
        } catch (\Exception $e) {
            logger()->error('Redirect failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model="remember" 
                            id="remember"
                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Remember me
                        </label>
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