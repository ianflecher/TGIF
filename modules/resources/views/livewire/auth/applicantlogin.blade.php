<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use App\Models\User;

new #[Layout('components.layouts.employee')] class extends Component
{
    public $showLogin = true; // Toggle between login and register
    public $email = '';
    public $password = '';
    public $remember = false;
    
    // Registration fields
    public $full_name = '';
    public $username = '';
    public $password_confirmation = '';
    public $phone = '';
    public $address = '';
    public $position = '';
    public $experience = '';
    public $resume = null;
    
    public function toggleForm()
    {
        $this->showLogin = !$this->showLogin;
        $this->reset(['email', 'password', 'full_name', 'username', 'password_confirmation', 
                      'phone', 'address', 'position', 'experience']);
        $this->resetErrorBag();
    }
    
    public function login()
{
    $this->validate([
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);
    
    if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        $user = Auth::user();
        
        // Check if user is an employee (not customer anymore)
        if ($user->role !== 'employee') { // Changed from 'customer' to 'employee'
            Auth::logout();
            $this->addError('email', 'This account is not authorized as an employee.');
            return;
        }
               
        session()->regenerate();
        return redirect()->route('applicant.index');
    }
    
    $this->addError('email', 'The provided credentials are incorrect.');
}
    
    public function register()
{
    $this->validate([
        'full_name' => ['required', 'string', 'max:150'],
        'username' => ['required', 'string', 'max:100', 'unique:users'],
        'email' => ['required', 'string', 'email', 'max:150', 'unique:users'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
        'phone' => ['required', 'string', 'max:50'],
        'address' => ['required', 'string', 'max:500'],
        'position' => ['required', 'string', 'max:100'],
        'experience' => ['required', 'string', 'max:50'],
    ]);
    
    // Create user - role defaults to 'employee' according to your schema
    $user = User::create([
        'full_name' => $this->full_name,
        'username' => $this->username,
        'email' => $this->email,
        'password' => Hash::make($this->password),
        // role will default to 'employee' as per your DB schema
    ]);
    
    // Create employee record
    DB::table('employees')->insert([
        'user_id' => $user->user_id,
        'job_title' => $this->position,
        'hire_date' => now(),
        'salary' => 0.00, // Default salary
        'status' => 'inactive', // Default status
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create job application record (optional - for tracking)
    DB::table('job_applications')->insert([
        'user_id' => $user->user_id,
        'position_applied' => $this->position,
        'years_experience' => $this->experience,
        'status' => 'pending', // Since we're creating employee immediately
        'application_date' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Auto login after registration
    Auth::login($user);
    
    session()->flash('success', 'Registration successful! You are now registered as an employee.');
    return redirect()->route('applicant.index');
}
}
?>

<div class="min-h-screen bg-gradient-to-br from-green-50 to-amber-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                Join Our Team at <span class="text-green-600">TGIF</span>
            </h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Looking for an exciting career opportunity? Apply now to become part of our growing team!
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Left Column: Info Cards -->
            <div class="space-y-6">
                <!-- Current Openings -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Current Openings</h2>
                    <div class="space-y-4">
                        @php
                            $positions = [
                                ['title' => 'Restaurant Manager', 'department' => 'Management', 'type' => 'Full-time'],
                                ['title' => 'Head Chef', 'department' => 'Kitchen', 'type' => 'Full-time'],
                                ['title' => 'Service Crew', 'department' => 'Operations', 'type' => 'Part-time'],
                                ['title' => 'Cashier', 'department' => 'Finance', 'type' => 'Full-time'],
                                ['title' => 'Delivery Driver', 'department' => 'Logistics', 'type' => 'Contract'],
                                ['title' => 'Marketing Executive', 'department' => 'Marketing', 'type' => 'Full-time'],
                            ];
                        @endphp
                        @foreach($positions as $position)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-green-300 transition-colors">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">{{ $position['title'] }}</h3>
                                        <p class="text-sm text-gray-600">{{ $position['department'] }}</p>
                                    </div>
                                    <span class="px-3 py-1 text-xs font-medium bg-amber-100 text-amber-800 rounded-full">
                                        {{ $position['type'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Benefits -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Why Join TGIF?</h2>
                    <div class="grid grid-cols-2 gap-4">
                        @php
                            $benefits = [
                                ['icon' => 'fa-money-bill-wave', 'title' => 'Competitive Salary', 'color' => 'text-green-600'],
                                ['icon' => 'fa-utensils', 'title' => 'Free Meals', 'color' => 'text-amber-600'],
                                ['icon' => 'fa-heartbeat', 'title' => 'Health Insurance', 'color' => 'text-red-600'],
                                ['icon' => 'fa-graduation-cap', 'title' => 'Training Programs', 'color' => 'text-blue-600'],
                                ['icon' => 'fa-calendar-alt', 'title' => 'Flexible Schedule', 'color' => 'text-purple-600'],
                                ['icon' => 'fa-trophy', 'title' => 'Career Growth', 'color' => 'text-indigo-600'],
                            ];
                        @endphp
                        @foreach($benefits as $benefit)
                            <div class="text-center p-4 rounded-lg bg-gray-50">
                                <i class="fas {{ $benefit['icon'] }} {{ $benefit['color'] }} text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">{{ $benefit['title'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Column: Auth Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Form Toggle -->
                <div class="flex mb-8">
                    <button wire:click="toggleForm" 
                            class="flex-1 py-3 text-center font-medium text-lg {{ $showLogin ? 'bg-green-600 text-white rounded-l-lg' : 'bg-gray-100 text-gray-600 rounded-l-lg' }}">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login
                    </button>
                    <button wire:click="toggleForm" 
                            class="flex-1 py-3 text-center font-medium text-lg {{ !$showLogin ? 'bg-amber-600 text-white rounded-r-lg' : 'bg-gray-100 text-gray-600 rounded-r-lg' }}">
                        <i class="fas fa-user-plus mr-2"></i>
                        Register
                    </button>
                </div>

                @if($showLogin)
                    <!-- Login Form -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Applicant Login</h2>
                        
                        @if(session('success'))
                            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                {{ session('success') }}
                            </div>
                        @endif

                        <form wire:submit="login" class="space-y-6">
                            <!-- Email -->
                            <div>
                                <label for="login-email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input wire:model="email" 
                                           id="login-email"
                                           type="email" 
                                           required
                                           class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                           placeholder="you@example.com">
                                </div>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Password -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label for="login-password" class="block text-sm font-medium text-gray-700">
                                        Password
                                    </label>
                                    <a href="#" class="text-sm text-green-600 hover:text-green-700">
                                        Forgot password?
                                    </a>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input wire:model="password" 
                                           id="login-password"
                                           type="password" 
                                           required
                                           class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                           placeholder="••••••••">
                                </div>
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" 
                                    class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Sign In
                            </button>
                        </form>

                        <!-- Demo Credentials -->
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Demo Credentials:</h4>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p>Email: applicant@example.com</p>
                                <p>Password: password</p>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Registration Form -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Create Applicant Account</h2>
                        
                        <form wire:submit="register" class="space-y-6">
                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Full Name -->
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Full Name *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input wire:model="full_name" 
                                               id="full_name"
                                               type="text" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="John Doe">
                                    </div>
                                    @error('full_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Username -->
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                        Username *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-at text-gray-400"></i>
                                        </div>
                                        <input wire:model="username" 
                                               id="username"
                                               type="text" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="johndoe">
                                    </div>
                                    @error('username')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Email -->
                                <div>
                                    <label for="register-email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-envelope text-gray-400"></i>
                                        </div>
                                        <input wire:model="email" 
                                               id="register-email"
                                               type="email" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="you@example.com">
                                    </div>
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Phone Number *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone text-gray-400"></i>
                                        </div>
                                        <input wire:model="phone" 
                                               id="phone"
                                               type="tel" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="+1234567890">
                                    </div>
                                    @error('phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address *
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 pt-3 pointer-events-none">
                                        <i class="fas fa-home text-gray-400"></i>
                                    </div>
                                    <textarea wire:model="address" 
                                              id="address"
                                              rows="2"
                                              required
                                              class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                              placeholder="123 Main St, City, Country"></textarea>
                                </div>
                                @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Application Details -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Position Applied -->
                                <div>
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-2">
                                        Position Applied For *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-briefcase text-gray-400"></i>
                                        </div>
                                        <select wire:model="position" 
                                                id="position"
                                                required
                                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent appearance-none">
                                            <option value="">Select Position</option>
                                            <option value="Restaurant Manager">Restaurant Manager</option>
                                            <option value="Head Chef">Head Chef</option>
                                            <option value="Service Crew">Service Crew</option>
                                            <option value="Cashier">Cashier</option>
                                            <option value="Delivery Driver">Delivery Driver</option>
                                            <option value="Marketing Executive">Marketing Executive</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    @error('position')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Experience -->
                                <div>
                                    <label for="experience" class="block text-sm font-medium text-gray-700 mb-2">
                                        Years of Experience *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-chart-line text-gray-400"></i>
                                        </div>
                                        <select wire:model="experience" 
                                                id="experience"
                                                required
                                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent appearance-none">
                                            <option value="">Select Experience</option>
                                            <option value="0-1 years">0-1 years</option>
                                            <option value="1-3 years">1-3 years</option>
                                            <option value="3-5 years">3-5 years</option>
                                            <option value="5-10 years">5-10 years</option>
                                            <option value="10+ years">10+ years</option>
                                        </select>
                                    </div>
                                    @error('experience')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Password -->
                                <div>
                                    <label for="register-password" class="block text-sm font-medium text-gray-700 mb-2">
                                        Password *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input wire:model="password" 
                                               id="register-password"
                                               type="password" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="••••••••">
                                    </div>
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm Password *
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input wire:model="password_confirmation" 
                                               id="password_confirmation"
                                               type="password" 
                                               required
                                               class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                               placeholder="••••••••">
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="terms" 
                                           name="terms" 
                                           type="checkbox" 
                                           required
                                           class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="terms" class="text-gray-700">
                                        I agree to the 
                                        <a href="#" class="text-amber-600 hover:text-amber-700">Terms of Service</a> 
                                        and 
                                        <a href="#" class="text-amber-600 hover:text-amber-700">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" 
                                    class="w-full bg-amber-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-colors">
                                <i class="fas fa-user-plus mr-2"></i>
                                Create Account & Apply
                            </button>
                        </form>

                        <div class="mt-6 text-center text-sm text-gray-600">
                            <p>Already have an account? 
                                <button wire:click="toggleForm" class="text-amber-600 hover:text-amber-700 font-medium">
                                    Sign in here
                                </button>
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bottom Info -->
        <div class="mt-12 text-center">
            <div class="inline-flex items-center space-x-6 text-gray-600">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                    <span>Secure Application Process</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-clock text-green-600 mr-2"></i>
                    <span>24/7 Application Support</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-headset text-green-600 mr-2"></i>
                    <span>HR Support: hr@tgif.com</span>
                </div>
            </div>
        </div>
    </div>
</div>