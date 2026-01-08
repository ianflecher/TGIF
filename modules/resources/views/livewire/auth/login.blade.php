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

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
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
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <!-- Logo -->
        <div class="flex justify-center">
            <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
        </div>
        
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Customer Portal
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            Sign in to access your account
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl rounded-lg sm:px-10 border border-gray-100">
            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 text-sm font-medium text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <form wire:submit="login" class="space-y-6">
                <!-- Username/Email -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">
                        Username or Email
                    </label>
                    <div class="mt-1 relative">
                        <input 
                            wire:model="username"
                            id="username"
                            name="username"
                            type="text"
                            autocomplete="username"
                            required
                            x-ref="username"
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm @error('username') border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500 @enderror"
                            placeholder="Enter your username or email"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <div class="mt-1 relative">
                        <input 
                            wire:model="password"
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm @error('password') border-red-300 text-red-900 focus:ring-red-500 focus:border-red-500 @enderror"
                            placeholder="Enter your password"
                        >
                        <button 
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-3 text-gray-400 hover:text-gray-600"
                            :class="{ 'text-green-600': showPassword }"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <template x-if="showPassword">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </template>
                                <template x-if="!showPassword">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </template>
                            </svg>
                        </button>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
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
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="login">
                            Sign in
                        </span>
                        <span wire:loading wire:target="login">
                            <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Help Links -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="text-sm text-center">
                    <a href="#" class="font-medium text-green-600 hover:text-green-500">
                        Forgot your password?
                    </a>
                </div>
                <div class="text-sm text-center mt-2">
                    <span class="text-gray-600">Don't have an account?</span>
                    <a href="#" class="font-medium text-green-600 hover:text-green-500 ml-1">
                        Contact support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>