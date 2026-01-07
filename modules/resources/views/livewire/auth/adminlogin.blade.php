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
        $this->validate();

        // Prepare credentials for authentication
        $credentials = ['password' => $this->password];
        
        // Determine if input is email or username
        if (filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $this->username;
        } else {
            $credentials['username'] = $this->username;
        }

        // Check if user exists with admin role ONLY
        $userExists = DB::table('users')
            ->where(function($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } else {
                    $query->where('username', $credentials['username']);
                }
            })
            ->where('role', 'admin') // Only admin role
            ->whereNull('deleted_at')
            ->exists();

        if (!$userExists) {
            throw ValidationException::withMessages([
                'username' => __('Access denied. Administrator credentials required.'),
            ]);
        }

        // Attempt authentication
        if (!Auth::attempt($credentials, $this->remember)) {
            throw ValidationException::withMessages([
                'username' => __('Invalid credentials.'),
            ]);
        }

        // Verify the user is an admin
        $user = Auth::user();
        if ($user->role !== 'admin') {
            Auth::logout();
            throw ValidationException::withMessages([
                'username' => __('Insufficient permissions. Administrator access required.'),
            ]);
        }

        session()->regenerate();

        // Redirect to admin dashboard using the named route
        return redirect()->route('admin.dashboard');
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
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Administrator Portal</h1>
            <p class="text-gray-600">System Administrator Access Only</p>
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
                                placeholder="admin@tgif.local"
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
                                Admin Password
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
                                Access Control Panel
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Verifying Credentials...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Admin Only Warning -->
                <div class="mt-6 p-4 bg-red-50 border border-red-100 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-shield-alt text-red-500 mt-0.5 mr-2"></i>
                        <div>
                            <p class="text-xs font-medium text-red-800 mb-1">🔐 Restricted Access</p>
                            <p class="text-xs text-red-700">
                                This portal is for <span class="font-bold">System Administrators only</span>. 
                                All access attempts are logged and monitored.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Admin Information -->
                <div class="mt-6 text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-emerald-100 text-emerald-800 rounded-full">
                        <i class="fas fa-crown mr-2"></i>
                        <span class="text-sm font-medium">System Administrator Role</span>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-4 border-t border-green-100">
                <div class="flex items-center justify-center">
                    <i class="fas fa-server text-green-600 mr-2"></i>
                    <p class="text-xs text-center text-green-800 font-medium">
                        TGIF Admin Control System
                    </p>
                </div>
            </div>
        </div>

        <!-- Default Admin Credentials (Optional - remove in production) -->
        <div class="mt-8 text-center">
            <details class="inline-block">
                <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Default Admin Credentials
                </summary>
                <div class="mt-2 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs text-gray-600 mb-1"><strong>Username:</strong> admin</p>
                    <p class="text-xs text-gray-600 mb-1"><strong>Email:</strong> admin@tgif.local</p>
                    <p class="text-xs text-gray-600"><strong>Password:</strong> password</p>
                    <p class="text-xs text-gray-500 mt-2 italic">Change these credentials after first login</p>
                </div>
            </details>
        </div>
    </div>
</div>