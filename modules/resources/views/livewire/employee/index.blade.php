<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.employeeland')] class extends Component
{
    public $employee;
    public $attendanceStats = [];
    public $recentTasks = [];
    public $upcomingDeadlines = [];
    public $leaveBalance = 0;
    public $payrollInfo = [];
    public $departmentAnnouncements = [];
    
    public function mount()
    {
        // Get current logged in employee
        $user = Auth::user();
        $this->employee = DB::table('employees')
            ->join('users', 'employees.user_id', '=', 'users.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.department_id')
            ->where('employees.user_id', $user->user_id)
            ->select(
                'employees.*',
                'users.full_name',
                'users.email',
                'users.role',
                'departments.department_name'
            )
            ->first();
        
        $this->loadDashboardData();
    }
    
    public function loadDashboardData()
    {
        $this->loadAttendanceStats();
        $this->loadRecentTasks();
        $this->loadUpcomingDeadlines();
        $this->loadPayrollInfo();
        $this->loadDepartmentAnnouncements();
    }
    
    private function loadAttendanceStats()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        
        $this->attendanceStats = [
            'today' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->whereDate('date', $today)
                ->select('status', 'time_in', 'time_out')
                ->first(),
            
            'month_stats' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->whereBetween('date', [$startOfMonth, $today])
                ->selectRaw('
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as leave_days
                ')
                ->first(),
            
            'late_count' => DB::table('hr_attendance')
                ->where('employee_id', $this->employee->employee_id)
                ->where('status', 'late')
                ->whereMonth('date', $today->month)
                ->count()
        ];
    }
    
    private function loadRecentTasks()
    {
        $this->recentTasks = DB::table('tasks')
            ->leftJoin('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->leftJoin('projects', 'project_phases.project_id', '=', 'projects.project_id')
            ->where('tasks.assigned_to', $this->employee->employee_id)
            ->whereIn('tasks.status', ['not_started', 'in_progress'])
            ->select(
                'tasks.task_id',
                'tasks.task_name',
                'tasks.description',
                'tasks.status',
                'tasks.end_date',
                'tasks.progress_percentage',
                'projects.project_name'
            )
            ->orderBy('tasks.end_date', 'asc')
            ->limit(5)
            ->get();
    }
    
    private function loadUpcomingDeadlines()
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addWeek();
        
        $this->upcomingDeadlines = DB::table('tasks')
            ->leftJoin('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->leftJoin('projects', 'project_phases.project_id', '=', 'projects.project_id')
            ->where('tasks.assigned_to', $this->employee->employee_id)
            ->whereBetween('tasks.end_date', [$today, $nextWeek])
            ->whereIn('tasks.status', ['not_started', 'in_progress'])
            ->select(
                'tasks.task_id',
                'tasks.task_name',
                'tasks.end_date',
                'projects.project_name'
            )
            ->orderBy('tasks.end_date', 'asc')
            ->get();
    }
    
    private function loadPayrollInfo()
    {
        $this->payrollInfo = DB::table('hr_payroll')
            ->where('employee_id', $this->employee->employee_id)
            ->where('status', 'paid')
            ->orderBy('period_end', 'desc')
            ->select('period_start', 'period_end', 'gross_pay', 'deductions', 'net_pay')
            ->first();
    }
    
    private function loadDepartmentAnnouncements()
    {
        // This would come from an announcements table
        // For now, we'll create some dummy data
        $this->departmentAnnouncements = [
            [
                'title' => 'Department Meeting',
                'content' => 'Monthly department meeting scheduled for Friday at 2 PM.',
                'date' => Carbon::tomorrow()->format('Y-m-d')
            ],
            [
                'title' => 'Training Session',
                'content' => 'Mandatory security training on Wednesday next week.',
                'date' => Carbon::now()->addDays(5)->format('Y-m-d')
            ]
        ];
    }
    
    public function clockIn()
    {
        $today = Carbon::today();
        
        // Check if already clocked in
        $existing = DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $today)
            ->first();
        
        if ($existing && $existing->time_in) {
            session()->flash('error', 'You have already clocked in today!');
            return;
        }
        
        DB::table('hr_attendance')->updateOrInsert(
            [
                'employee_id' => $this->employee->employee_id,
                'date' => $today
            ],
            [
                'time_in' => now(),
                'status' => now()->hour > 9 ? 'late' : 'present',
                'updated_at' => now()
            ]
        );
        
        session()->flash('success', 'Clocked in successfully!');
        $this->loadAttendanceStats();
    }
    
    public function clockOut()
    {
        $today = Carbon::today();
        
        $attendance = DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $today)
            ->first();
        
        if (!$attendance || !$attendance->time_in) {
            session()->flash('error', 'You need to clock in first!');
            return;
        }
        
        if ($attendance->time_out) {
            session()->flash('error', 'You have already clocked out today!');
            return;
        }
        
        DB::table('hr_attendance')
            ->where('employee_id', $this->employee->employee_id)
            ->whereDate('date', $today)
            ->update([
                'time_out' => now(),
                'updated_at' => now()
            ]);
        
        session()->flash('success', 'Clocked out successfully!');
        $this->loadAttendanceStats();
    }
    
    public function updateTaskProgress($taskId, $progress)
    {
        DB::table('tasks')
            ->where('task_id', $taskId)
            ->update([
                'progress_percentage' => $progress,
                'status' => $progress == 100 ? 'completed' : 'in_progress',
                'updated_at' => now()
            ]);
        
        session()->flash('success', 'Task progress updated!');
        $this->loadRecentTasks();
    }
}
?>

<div class="py-6">
    <!-- Welcome Section -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Welcome back, {{ $employee->full_name ?? 'Employee' }}!
        </h1>
        <p class="text-gray-600 dark:text-gray-400">
            {{ $employee->department_name ? "Department: {$employee->department_name}" : '' }}
            • {{ $employee->job_title ?? '' }}
        </p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Attendance Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Status</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $attendanceStats['today']->status ?? 'Not Recorded' }}
                    </p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex space-x-2">
                @if(!$attendanceStats['today'] || !$attendanceStats['today']->time_in)
                    <button wire:click="clockIn" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                        Clock In
                    </button>
                @endif
                @if($attendanceStats['today'] && $attendanceStats['today']->time_in && !$attendanceStats['today']->time_out)
                    <button wire:click="clockOut" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700">
                        Clock Out
                    </button>
                @endif
            </div>
        </div>

        <!-- Monthly Attendance -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly Attendance</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->present_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Present</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->late_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Late</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->absent_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Absent</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $attendanceStats['month_stats']->leave_days ?? 0 }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">On Leave</p>
                </div>
            </div>
        </div>

        <!-- Tasks -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Tasks</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ count($recentTasks) }}
                    </p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Latest Pay -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Last Payment</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ₱{{ number_format($payrollInfo->net_pay ?? 0, 2) }}
                    </p>
                    @if($payrollInfo)
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ \Carbon\Carbon::parse($payrollInfo->period_end)->format('M d, Y') }}
                        </p>
                    @endif
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Tasks Section -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Tasks</h2>
                </div>
                <div class="p-6">
                    @if(count($recentTasks) > 0)
                        <div class="space-y-4">
                            @foreach($recentTasks as $task)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-gray-900 dark:text-white">{{ $task->task_name }}</h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $task->project_name }}</p>
                                            <div class="flex items-center mt-2 space-x-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    {{ $task->status == 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                       ($task->status == 'in_progress' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                                       'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200') }}">
                                                    {{ str_replace('_', ' ', ucfirst($task->status)) }}
                                                </span>
                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                    Due: {{ \Carbon\Carbon::parse($task->end_date)->format('M d') }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $task->progress_percentage }}%</p>
                                            <input type="range" min="0" max="100" value="{{ $task->progress_percentage }}" 
                                                   wire:change="updateTaskProgress({{ $task->task_id }}, $event.target.value)"
                                                   class="w-full mt-2">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">No active tasks assigned</p>
                    @endif
                </div>
            </div>

            <!-- Upcoming Deadlines -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-6">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upcoming Deadlines</h2>
                </div>
                <div class="p-6">
                    @if(count($upcomingDeadlines) > 0)
                        <div class="space-y-3">
                            @foreach($upcomingDeadlines as $deadline)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" 
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $deadline->task_name }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $deadline->project_name }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($deadline->end_date)->format('M d') }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($deadline->end_date)->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">No upcoming deadlines</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Announcements -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Announcements</h2>
                </div>
                <div class="p-6">
                    @if(count($departmentAnnouncements) > 0)
                        <div class="space-y-4">
                            @foreach($departmentAnnouncements as $announcement)
                                <div class="border-l-4 border-blue-500 pl-4">
                                    <h3 class="font-medium text-gray-900 dark:text-white">{{ $announcement['title'] }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $announcement['content'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        {{ \Carbon\Carbon::parse($announcement['date'])->format('M d, Y') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-4">No announcements</p>
                    @endif
                </div>
            </div>

            <!-- Quick Links -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Links</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('employee.attendance') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Attendance</span>
                        </a>
                        <a href="{{ route('employee.tasks') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tasks</span>
                        </a>
                        <a href="{{ route('employee.payroll') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Payroll</span>
                        </a>
                        <a href="{{ route('employee.leave') }}" 
                           class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-6 h-6 text-gray-600 dark:text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Leave</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Time Tracking -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Time</h2>
                </div>
                <div class="p-6">
                    @if($attendanceStats['today'] && $attendanceStats['today']->time_in)
                        <div class="text-center">
                            <div class="flex justify-center items-center space-x-4 mb-4">
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Clock In</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($attendanceStats['today']->time_in)->format('h:i A') }}
                                    </p>
                                </div>
                                @if($attendanceStats['today']->time_out)
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Clock Out</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ \Carbon\Carbon::parse($attendanceStats['today']->time_out)->format('h:i A') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                            @if($attendanceStats['today']->time_in && $attendanceStats['today']->time_out)
                                @php
                                    $totalMinutes = \Carbon\Carbon::parse($attendanceStats['today']->time_out)
                                        ->diffInMinutes(\Carbon\Carbon::parse($attendanceStats['today']->time_in));
                                    $hours = floor($totalMinutes / 60);
                                    $minutes = $totalMinutes % 60;
                                @endphp
                                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Hours Today</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $hours }}h {{ $minutes }}m</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-4">Not clocked in yet</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(session()->has('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('success') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#10B981",
            }).showToast();
        });
    </script>
@endif

@if(session()->has('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Toastify({
                text: "{{ session('error') }}",
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: "#EF4444",
            }).showToast();
        });
    </script>
@endif