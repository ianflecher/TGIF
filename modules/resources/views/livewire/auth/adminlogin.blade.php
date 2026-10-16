<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

new #[Layout('components.layouts.employee')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;

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
        \App\Support\PortalLoginLimiter::check($this->username, 'username');
    $this->validate();

    // Prepare credentials for authentication
    $credentials = ['password' => $this->password, 'role' => 'admin'];
    
    // Determine if input is email or username
    if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
        $credentials['email'] = $this->username;
    } else {
        $credentials['username'] = $this->username;
    }

    // DEBUG: Log what we're looking for

    
    // Check if user exists
    $user = DB::table('users')
        ->where(function($query) use ($credentials) {
            if (isset($credentials['email'])) {
                $query->where('email', $credentials['email']);
            } else {
                $query->where('username', $credentials['username']);
            }
        })
        ->whereNull('deleted_at')
        ->first(); // Use first() instead of exists() to see the actual user

    
    if (!$user) {
        throw ValidationException::withMessages([
            'username' => __('User not found.'),
        ]);
    }

    // Check if user has admin role
    if (!app(\App\Services\AccessControl::class)->isSuperAdmin(\App\Models\User::find($user->user_id))) {
        \Log::info('User role is not admin:', ['role' => $user->role]);
        throw ValidationException::withMessages([
            'username' => __('Access denied. Administrator credentials required.'),
        ]);
    }

    // Attempt authentication
    if (\App\Support\PortalTwoFactor::challenge($credentials, $this->remember)) return redirect()->route('two-factor.login');
    if (!Auth::attempt($credentials, $this->remember)) {
        throw ValidationException::withMessages([
            'username' => __('Invalid credentials.'),
        ]);
    }

    // Get the authenticated user
    $user = Auth::user();
    
    if (!app(\App\Services\AccessControl::class)->isSuperAdmin(\App\Models\User::find($user->user_id))) {
        Auth::logout();
        throw ValidationException::withMessages([
            'username' => __('Insufficient permissions. Administrator access required.'),
        ]);
    }

    session()->regenerate(); session()->forget('supplier');

    // Redirect based on username
    return $this->redirectBasedOnUsername($user->username);
}

    private function redirectBasedOnUsername(string $username)
    {
        return redirect()->route(app(\App\Services\AccessControl::class)->home(Auth::user()));
    }
}
?>
<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <div class="max-w-md mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-emerald-200 shadow-lg">
                <i class="fas fa-crown text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Super admin portal</h1>
            <p class="text-gray-600">Administration and access management</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="p-8">
                <form wire:submit.prevent="login" class="space-y-6">
                    @csrf
                    
                    <!-- Username/Email -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Username or Email
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="username" 
                                id="username"
                                x-ref="username"
                                required
                                autofocus
                                placeholder="Your super administrator account"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-user-tie"></i>
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
                                Sign in as super admin
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Verifying Credentials...
                            </span>
                        </button>
                    </div>
                </form>              
            </div>
            
            <!-- Footer -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-4 border-t border-green-100">
                <div class="flex items-center justify-center">
                    <i class="fas fa-server text-green-600 mr-2"></i>
                    <p class="text-xs text-center text-green-800 font-medium">
                        TGIF ERP · Super administrator access
                    </p>
                </div>
            </div>
        </div>

        <p class="mt-8 text-center"><a class="text-sm text-green-700 underline" href="{{ route('portals.index') }}">Looking for your department? Open department portals →</a></p>
        @if(app()->environment('local', 'testing'))<p class="mt-4 text-center text-xs text-gray-500">Local demo super admin: admin / password</p>@endif
    </div>
</div>
