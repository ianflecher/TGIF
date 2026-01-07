<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Check if user exists with employee or manager role
        $userExists = DB::table('users')
            ->where(function($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } else {
                    $query->where('username', $credentials['username']);
                }
            })
            ->whereIn('role', ['employee', 'manager']) // Allow both employee and manager roles
            ->whereNull('deleted_at')
            ->exists();

        if (!$userExists) {
            throw ValidationException::withMessages([
                'username' => __('Access denied. Employee or Manager credentials required.'),
            ]);
        }

        // Get the user
        $user = DB::table('users')
            ->where(function($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } else {
                    $query->where('username', $credentials['username']);
                }
            })
            ->whereIn('role', ['employee', 'manager'])
            ->whereNull('deleted_at')
            ->first(['user_id', 'role']);

        if (!$user) {
            throw ValidationException::withMessages([
                'username' => __('Account not found.'),
            ]);
        }

        // For employees, check if they have an active employee record
        if ($user->role === 'employee') {
            $employeeExists = DB::table('employees')
                ->where('user_id', $user->user_id)
                ->where('status', 'active')
                ->exists();

            if (!$employeeExists) {
                throw ValidationException::withMessages([
                    'username' => __('Employee account is inactive or not properly configured.'),
                ]);
            }
        }

        // For managers, check if they have an active status (you might want to add a managers table)
        // For now, we'll just verify they exist in users table with manager role

        // Attempt authentication
        if (!Auth::attempt($credentials, $this->remember)) {
            throw ValidationException::withMessages([
                'username' => __('Invalid credentials.'),
            ]);
        }

        // Verify the user has employee or manager role
        $authUser = Auth::user();
        if (!in_array($authUser->role, ['employee', 'manager'])) {
            Auth::logout();
            throw ValidationException::withMessages([
                'username' => __('Employee or Manager access required.'),
            ]);
        }

        session()->regenerate();
        
        // Default to employee dashboard
        return redirect()->route('employee.dashboard');
    }
}
?>
<div class="min-h-screen bg-gradient-to-b from-white to-green-50 py-12 px-4 sm:px-6 lg:px-8" x-data="{ showPassword: false }" x-init="$refs.username.focus()">
    <div class="max-w-md mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="mx-auto h-20 w-20 bg-gradient-to-r from-green-500 to-indigo-600 rounded-full flex items-center justify-center mb-4 overflow-hidden border-2 border-green-200 shadow-lg">
                <i class="fas fa-users text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Staff Portal</h1>
            <p class="text-gray-600">Employee & Manager Access</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 hover:shadow-2xl transition-all duration-300">
            <div class="p-8">
                <form wire:submit.prevent="login" class="space-y-6">
                    @csrf
                    
                    <!-- Username/Email -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Staff ID / Email
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                wire:model="username" 
                                id="username"
                                x-ref="username"
                                required
                                autofocus
                                placeholder="staff@tgif.local"
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                            >
                            <div class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-id-badge"></i>
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
                            <a href="#" class="text-sm text-green-600 hover:text-green-700 font-medium">
                                Forgot password?
                            </a>
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
                            class="w-full bg-gradient-to-r from-green-600 to-indigo-600 text-white py-3 px-4 rounded-lg hover:from-green-700 hover:to-indigo-700 transition font-medium flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <span wire:loading.remove wire:target="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </span>
                            <span wire:loading wire:target="login">
                                <i class="fas fa-spinner fa-spin"></i>
                                Verifying...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Role Information -->
                <div class="mt-6 grid grid-cols-2 gap-4">
                    <!-- Employee Features -->
                    <div class="p-4 bg-green-50 border border-green-100 rounded-lg">
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-2">
                                <i class="fas fa-user-tie text-green-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-green-800 mb-1">Employee</p>
                                <ul class="text-xs text-green-700 space-y-0.5">
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Attendance</span>
                                    </li>
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Tasks</span>
                                    </li>
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Orders</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Manager Features -->
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <div class="flex items-start">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center mr-2">
                                <i class="fas fa-user-cog text-purple-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-purple-800 mb-1">Manager</p>
                                <ul class="text-xs text-purple-700 space-y-0.5">
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Reports</span>
                                    </li>
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Approvals</span>
                                    </li>
                                    <li class="flex items-center gap-1">
                                        <i class="fas fa-check text-xs"></i>
                                        <span>Team</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access Badges -->
                <div class="mt-6 flex justify-center gap-3">
                    <div class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                        <i class="fas fa-user-tie mr-1"></i>
                        <span class="font-medium">Employee</span>
                    </div>
                    <div class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                        <i class="fas fa-user-cog mr-1"></i>
                        <span class="font-medium">Manager</span>
                    </div>
                </div>

                <!-- Note -->
                <div class="mt-6 p-4 bg-green-50 border border-green-100 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-green-500 mt-0.5 mr-2"></i>
                        <p class="text-xs text-green-700">
                            <span class="font-medium">Note:</span> Login grants access based on your role permissions.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gradient-to-r from-green-50 to-indigo-50 px-8 py-4 border-t border-green-100">
                <div class="flex items-center justify-center">
                    <i class="fas fa-building text-green-600 mr-2"></i>
                    <p class="text-xs text-center text-green-800 font-medium">
                        TGIF Staff Portal v2.0
                    </p>
                </div>
            </div>
        </div>

        <!-- Support Information -->
        <div class="mt-8 text-center">
            <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                <i class="fas fa-headset"></i>
                <span>Support: <span class="font-medium">hr@tgif.local</span> | IT: <span class="font-medium">it@tgif.local</span></span>
            </div>
        </div>
    </div>
</div>