<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] #[Title('My Attendance')] class extends Component
{
    public $attendance = null;
    public $attendanceHistory = [];
    public $monthlySummary = [];
    public $employee;
    public $currentMonth;
    public $clockStatus = [];
    public $isClockedIn = false;
    
    public function mount()
    {
        // Get current employee based on logged-in user
        $user = Auth::user();
        $this->employee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'departments.department_name',
                'departments.department_id'
            )
            ->where('users.user_id', $user->user_id)
            ->first();
        
        $this->currentMonth = now()->format('F Y');
        $this->loadData();
    }
    
    public function loadData()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Load today's attendance
        $this->attendance = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();
            
        // Load attendance history for current month
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d');
        
        $this->attendanceHistory = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
            
        // Load monthly summary
        $this->monthlySummary = DB::table('hr_attendance')
            ->select(
                DB::raw('COUNT(*) as total_days'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days'),
                DB::raw('SUM(CASE WHEN status = "half_day" THEN 1 ELSE 0 END) as half_days'),
                DB::raw('SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as leave_days')
            )
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->first();
            
        // Check clock status
        $this->checkClockStatus();
    }
    
    public function checkClockStatus()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Check if clock_logs table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        
        if (!empty($tableExists)) {
            // Get latest clock log
            $clockLog = DB::table('clock_logs')
                ->where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $this->isClockedIn = $clockLog ? !$clockLog->clock_out : false;
            $this->clockStatus = [
                'is_clocked_in' => $this->isClockedIn,
                'last_clock_in' => $clockLog ? $clockLog->clock_in : null,
                'last_clock_out' => $clockLog ? $clockLog->clock_out : null
            ];
        } else {
            // If clock_logs table doesn't exist, check hr_attendance
            $todayAttendance = DB::table('hr_attendance')
                ->where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->first();
            
            $this->isClockedIn = $todayAttendance && $todayAttendance->time_in && !$todayAttendance->time_out;
            $this->clockStatus = [
                'is_clocked_in' => $this->isClockedIn,
                'last_clock_in' => $todayAttendance ? $todayAttendance->time_in : null,
                'last_clock_out' => $todayAttendance ? $todayAttendance->time_out : null
            ];
        }
    }
    
    // Manual Time In (for backup)
    public function manualTimeIn()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Check if already timed in today
        if ($this->attendance && $this->attendance->time_in) {
            session()->flash('error', 'You have already timed in today!');
            return;
        }
        
        $currentTime = now();
        $status = 'present';
        
        // Check if late (after 9:00 AM)
        $lateThreshold = Carbon::createFromTime(9, 0, 0);
        if ($currentTime->gt($lateThreshold)) {
            $status = 'late';
        }
        
        // Create attendance record
        DB::table('hr_attendance')->insert([
            'employee_id' => $employeeId,
            'date' => $today,
            'time_in' => $currentTime,
            'status' => $status,
            'notes' => 'Manual time in from attendance page',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Also create clock log if table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        if (!empty($tableExists)) {
            DB::table('clock_logs')->insert([
                'employee_id' => $employeeId,
                'date' => $today,
                'clock_in' => $currentTime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->loadData();
        session()->flash('success', 'Time In recorded successfully!');
    }
    
    // Manual Time Out (for backup)
    public function manualTimeOut()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        if (!$this->attendance || !$this->attendance->time_in) {
            session()->flash('error', 'Please time in first!');
            return;
        }
        
        if ($this->attendance->time_out) {
            session()->flash('error', 'You have already timed out today!');
            return;
        }
        
        $currentTime = now();
        
        // Update attendance record
        DB::table('hr_attendance')
            ->where('attendance_id', $this->attendance->attendance_id)
            ->update([
                'time_out' => $currentTime,
                'updated_at' => now(),
            ]);
        
        // Update clock log if table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        if (!empty($tableExists)) {
            DB::table('clock_logs')
                ->where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->whereNull('clock_out')
                ->update([
                    'clock_out' => $currentTime,
                    'updated_at' => now(),
                ]);
        }
        
        $this->loadData();
        session()->flash('success', 'Time Out recorded successfully!');
    }
    
    // Sync clock with attendance
    public function syncClockWithAttendance()
    {
        $today = now()->format('Y-m-d');
        $employeeId = $this->employee->employee_id;
        
        // Check if clock_logs table exists
        $tableExists = DB::select("SHOW TABLES LIKE 'clock_logs'");
        
        if (empty($tableExists)) {
            session()->flash('error', 'Clock system not available. Please use manual time in/out.');
            return;
        }
        
        // Get latest clock log
        $clockLog = DB::table('clock_logs')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$clockLog) {
            session()->flash('error', 'No clock records found for today.');
            return;
        }
        
        // Check attendance record
        $attendance = DB::table('hr_attendance')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();
        
        if (!$attendance && $clockLog->clock_in) {
            // Create attendance record from clock log
            $status = 'present';
            $clockInTime = Carbon::parse($clockLog->clock_in);
            
            // Check if late (after 9:00 AM)
            $lateThreshold = Carbon::createFromTime(9, 0, 0);
            if ($clockInTime->gt($lateThreshold)) {
                $status = 'late';
            }
            
            DB::table('hr_attendance')->insert([
                'employee_id' => $employeeId,
                'date' => $today,
                'time_in' => $clockLog->clock_in,
                'time_out' => $clockLog->clock_out,
                'status' => $status,
                'notes' => 'Synced from clock system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            session()->flash('success', 'Attendance synced from clock system!');
        } else if ($attendance && $clockLog->clock_out && !$attendance->time_out) {
            // Update time out
            DB::table('hr_attendance')
                ->where('attendance_id', $attendance->attendance_id)
                ->update([
                    'time_out' => $clockLog->clock_out,
                    'updated_at' => now(),
                ]);
            
            session()->flash('success', 'Time out updated from clock system!');
        } else if ($attendance && $clockLog->clock_in && !$attendance->time_in) {
            // Update time in
            $status = 'present';
            $clockInTime = Carbon::parse($clockLog->clock_in);
            
            // Check if late (after 9:00 AM)
            $lateThreshold = Carbon::createFromTime(9, 0, 0);
            if ($clockInTime->gt($lateThreshold)) {
                $status = 'late';
            }
            
            DB::table('hr_attendance')
                ->where('attendance_id', $attendance->attendance_id)
                ->update([
                    'time_in' => $clockLog->clock_in,
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            
            session()->flash('success', 'Time in updated from clock system!');
        } else {
            session()->flash('info', 'Attendance is already up to date.');
        }
        
        $this->loadData();
    }
    
    public function refreshData()
    {
        $this->loadData();
    }
}

?>

<div class="p-6">
    <!-- Simplified Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-emerald-800 mb-2">My Attendance Overview</h1>
        <p class="text-emerald-600">Track your daily attendance and work hours</p>
    </div>
    
    <!-- Today's Status Card -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border border-emerald-100">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-emerald-800">Today's Status</h2>
            <div class="flex items-center space-x-2">
                <span class="text-emerald-600">{{ now()->format('l, F j, Y') }}</span>
                <button 
                    wire:click="refreshData"
                    class="p-2 text-emerald-600 hover:text-emerald-800 transition rounded-lg hover:bg-emerald-50"
                    title="Refresh data"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Time In -->
            <div class="bg-emerald-50 rounded-lg p-5 border border-emerald-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-emerald-800">Time In</h3>
                </div>
                @if($attendance && $attendance->time_in)
                    <p class="text-3xl font-bold text-emerald-700 mb-1">
                        {{ \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') }}
                    </p>
                    <p class="text-emerald-600 text-sm">
                        {{ \Carbon\Carbon::parse($attendance->time_in)->format('M j, Y') }}
                    </p>
                @else
                    <p class="text-xl text-emerald-500 italic mb-3">Not recorded</p>
                    <button 
                        wire:click="manualTimeIn"
                        class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center justify-center space-x-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Manual Time In</span>
                    </button>
                @endif
            </div>
            
            <!-- Time Out -->
            <div class="bg-emerald-50 rounded-lg p-5 border border-emerald-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-emerald-800">Time Out</h3>
                </div>
                @if($attendance && $attendance->time_out)
                    <p class="text-3xl font-bold text-emerald-700 mb-1">
                        {{ \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') }}
                    </p>
                    <p class="text-emerald-600 text-sm">
                        {{ \Carbon\Carbon::parse($attendance->time_out)->format('M j, Y') }}
                    </p>
                @elseif($attendance && $attendance->time_in)
                    <p class="text-xl text-emerald-500 italic mb-3">Not recorded</p>
                    <button 
                        wire:click="manualTimeOut"
                        class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-2 px-4 rounded-lg transition flex items-center justify-center space-x-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Manual Time Out</span>
                    </button>
                @else
                    <p class="text-xl text-emerald-500 italic">Time in required first</p>
                @endif
            </div>
            
            <!-- Status & Hours -->
            <div class="bg-emerald-50 rounded-lg p-5 border border-emerald-200">
                <div class="flex items-center mb-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-emerald-800">Status</h3>
                </div>
                @if($attendance)
                    <div class="mb-3">
                        <span class="px-4 py-2 rounded-full text-sm font-medium
                            {{ $attendance->status === 'present' ? 'bg-emerald-100 text-emerald-800' : 
                               ($attendance->status === 'late' ? 'bg-amber-100 text-amber-800' : 
                               ($attendance->status === 'on_leave' ? 'bg-blue-100 text-blue-800' : 
                               ($attendance->status === 'half_day' ? 'bg-yellow-100 text-yellow-800' : 
                               'bg-gray-100 text-gray-800'))) 
                            }}">
                            {{ ucfirst(str_replace('_', ' ', $attendance->status)) }}
                        </span>
                    </div>
                    @if($attendance->time_in && $attendance->time_out)
                        @php
                            $timeIn = \Carbon\Carbon::parse($attendance->time_in);
                            $timeOut = \Carbon\Carbon::parse($attendance->time_out);
                            $hours = $timeIn->diffInHours($timeOut);
                            $minutes = $timeIn->diffInMinutes($timeOut) % 60;
                        @endphp
                        <p class="text-2xl font-bold text-emerald-800">{{ $hours }}h {{ $minutes }}m</p>
                        <p class="text-emerald-600 text-sm">Total hours worked</p>
                    @endif
                @else
                    <p class="text-xl text-emerald-500 italic">No attendance today</p>
                @endif
                
                <!-- Clock System Status -->
                @if($isClockedIn)
                    <div class="mt-4 p-3 bg-green-50 rounded-lg border border-green-200">
                        <p class="text-sm text-green-700 font-medium">✓ Clocked In via System</p>
                        <p class="text-xs text-green-600">
                            @if($clockStatus['last_clock_in'])
                                Since {{ \Carbon\Carbon::parse($clockStatus['last_clock_in'])->format('h:i A') }}
                            @endif
                        </p>
                    </div>
                @elseif($clockStatus['last_clock_in'])
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-700 font-medium">Last Clocked</p>
                        <p class="text-xs text-blue-600">
                            {{ \Carbon\Carbon::parse($clockStatus['last_clock_in'])->format('h:i A') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Sync Button -->
        <div class="mt-6 flex justify-center">
            <button 
                wire:click="syncClockWithAttendance"
                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium flex items-center space-x-2 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Sync with Clock System</span>
            </button>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Monthly Summary -->
        <div class="lg:col-span-2">
            <!-- Monthly Statistics -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100 mb-8">
                <h2 class="text-xl font-bold text-emerald-800 mb-6">Monthly Overview - {{ $currentMonth }}</h2>
                
                @if($monthlySummary)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-emerald-50 rounded-lg p-5 text-center border border-emerald-200">
                            <div class="text-3xl font-bold text-emerald-700 mb-2">{{ $monthlySummary->present_days ?? 0 }}</div>
                            <p class="text-emerald-600 text-sm">Present Days</p>
                        </div>
                        
                        <div class="bg-amber-50 rounded-lg p-5 text-center border border-amber-200">
                            <div class="text-3xl font-bold text-amber-700 mb-2">{{ $monthlySummary->late_days ?? 0 }}</div>
                            <p class="text-amber-600 text-sm">Late Days</p>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-5 text-center border border-blue-200">
                            <div class="text-3xl font-bold text-blue-700 mb-2">{{ $monthlySummary->leave_days ?? 0 }}</div>
                            <p class="text-blue-600 text-sm">Leave Days</p>
                        </div>
                        
                        <div class="bg-red-50 rounded-lg p-5 text-center border border-red-200">
                            <div class="text-3xl font-bold text-red-700 mb-2">{{ $monthlySummary->absent_days ?? 0 }}</div>
                            <p class="text-red-600 text-sm">Absent Days</p>
                        </div>
                    </div>
                    
                    <!-- Monthly Total -->
                    <div class="mt-6 p-5 bg-gradient-to-r from-emerald-500 to-green-600 rounded-lg text-white">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm opacity-90">Total Working Days This Month</p>
                                <p class="text-3xl font-bold mt-1">{{ $monthlySummary->total_days ?? 0 }}</p>
                            </div>
                            <div class="text-right">
                                @php
                                    $presentRate = $monthlySummary->total_days > 0 ? 
                                        round(($monthlySummary->present_days / $monthlySummary->total_days) * 100, 1) : 0;
                                @endphp
                                <p class="text-2xl font-bold">{{ $presentRate }}%</p>
                                <p class="text-sm opacity-90">Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-emerald-600">No attendance data available for this month.</p>
                    </div>
                @endif
            </div>
            
            <!-- Recent Attendance -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100">
                <h2 class="text-xl font-bold text-emerald-800 mb-6">Recent Attendance</h2>
                
                @if(count($attendanceHistory) > 0)
                    <div class="space-y-4">
                        @foreach(array_slice($attendanceHistory, 0, 7) as $record)
                            <div class="flex items-center justify-between p-4 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4 border border-emerald-200">
                                        <span class="text-emerald-700 font-bold">{{ \Carbon\Carbon::parse($record->date)->format('d') }}</span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-emerald-800">
                                            {{ \Carbon\Carbon::parse($record->date)->format('l, M j') }}
                                        </p>
                                        <div class="flex items-center space-x-4 mt-1">
                                            @if($record->time_in)
                                                <span class="text-sm text-emerald-600">
                                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ \Carbon\Carbon::parse($record->time_in)->format('h:i A') }}
                                                </span>
                                            @endif
                                            @if($record->time_out)
                                                <span class="text-sm text-emerald-600">
                                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                                                    </svg>
                                                    {{ \Carbon\Carbon::parse($record->time_out)->format('h:i A') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    {{ $record->status === 'present' ? 'bg-emerald-100 text-emerald-800' : 
                                       ($record->status === 'late' ? 'bg-amber-100 text-amber-800' : 
                                       ($record->status === 'on_leave' ? 'bg-blue-100 text-blue-800' : 
                                       ($record->status === 'half_day' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($record->status === 'absent' ? 'bg-red-100 text-red-800' : 
                                       'bg-gray-100 text-gray-800')))) 
                                    }}">
                                    {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    
                    @if(count($attendanceHistory) > 7)
                        <div class="mt-6 text-center">
                            <button class="px-4 py-2 text-emerald-600 hover:text-emerald-800 font-medium hover:bg-emerald-50 rounded-lg transition">
                                View All Records →
                            </button>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="text-emerald-600">No attendance records found for this month.</p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Right Column - Employee Info & Legend -->
        <div class="space-y-8">
            <!-- Employee Info Card -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100">
                <h2 class="text-xl font-bold text-emerald-800 mb-4">Employee Information</h2>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Name</p>
                        <p class="font-medium text-emerald-800">{{ $employee->full_name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Department</p>
                        <p class="font-medium text-emerald-800">{{ $employee->department_name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Employee ID</p>
                        <p class="font-medium text-emerald-800">{{ str_pad($employee->employee_id, 6, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Status</p>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ $employee->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 
                               ($employee->status === 'on_leave' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst(str_replace('_', ' ', $employee->status)) }}
                        </span>
                    </div>
                    
                    <div>
                        <p class="text-sm text-emerald-600 mb-1">Hire Date</p>
                        <p class="font-medium text-emerald-800">
                            {{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Status Legend -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100">
                <h2 class="text-xl font-bold text-emerald-800 mb-4">Status Guide</h2>
                
                <div class="space-y-3">
                    <div class="flex items-center p-3 bg-emerald-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-emerald-800">Present</p>
                            <p class="text-sm text-emerald-600">On time attendance</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-amber-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-amber-800">Late</p>
                            <p class="text-sm text-amber-600">Arrived after 9:00 AM</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-blue-800">On Leave</p>
                            <p class="text-sm text-blue-600">Approved leave day</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center p-3 bg-red-50 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-red-800">Absent</p>
                            <p class="text-sm text-red-600">No attendance recorded</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-emerald-100">
                <h2 class="text-xl font-bold text-emerald-800 mb-4">Quick Stats</h2>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
                        <span class="text-emerald-700">This Week</span>
                        @php
                            $weekDays = collect($attendanceHistory)->filter(function($record) {
                                return in_array($record->status, ['present', 'late', 'half_day']) && 
                                       Carbon::parse($record->date)->between(
                                           now()->startOfWeek(), 
                                           now()->endOfWeek()
                                       );
                            })->count();
                        @endphp
                        <span class="text-xl font-bold text-emerald-800">{{ $weekDays }} days</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="text-blue-700">Average Hours/Day</span>
                        @php
                            $avgHours = 0;
                            $completedDays = collect($attendanceHistory)->filter(function($record) {
                                return $record->time_in && $record->time_out;
                            });
                            
                            if($completedDays->count() > 0) {
                                $totalHours = $completedDays->sum(function($record) {
                                    $timeIn = Carbon::parse($record->time_in);
                                    $timeOut = Carbon::parse($record->time_out);
                                    return $timeIn->diffInHours($timeOut);
                                });
                                $avgHours = round($totalHours / $completedDays->count(), 1);
                            }
                        @endphp
                        <span class="text-xl font-bold text-blue-800">{{ $avgHours }}h</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <span class="text-purple-700">Current Time</span>
                        <span class="text-xl font-bold text-purple-800" id="current-time">
                            {{ now()->format('h:i A') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif
    
    @if(session()->has('info'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
             class="fixed bottom-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('info') }}
        </div>
    @endif
</div>

<!-- JavaScript for Live Clock -->
<script>
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit'
        });
        
        const currentTimeElement = document.getElementById('current-time');
        if (currentTimeElement) {
            currentTimeElement.textContent = timeString;
        }
    }
    
    // Update clock every minute
    setInterval(updateClock, 60000);
    updateClock(); // Initial call
    
    // Listen for clock system events
    document.addEventListener('clockIn', function() {
        // Force refresh page after clock in
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
    
    document.addEventListener('clockOut', function() {
        // Force refresh page after clock out
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
    
    // If using Livewire, listen for refresh events
    if (typeof Livewire !== 'undefined') {
        Livewire.on('refresh-attendance', () => {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }
</script>