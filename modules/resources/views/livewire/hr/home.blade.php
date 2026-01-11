<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] 
#[Title('HR Dashboard')]
class extends Component
{
    // Filters
    #[Url]
    public $dateRange = 'this_month';
    #[Url]
    public $department_id = null;
    #[Url]
    public $employee_status = 'active';
    #[Url]
    public $application_status = 'all';
    
    // Statistics
    public $totalEmployees = 0;
    public $activeEmployees = 0;
    public $onLeave = 0;
    public $departments = [];
    public $attendanceStats = [];
    public $payrollStats = [];
    public $departmentStats = [];
    public $recentHires = [];
    public $upcomingLeave = [];
    public $attendanceToday = [];
    public $monthlyAttendance = [];
    public $salaryDistribution = [];
    public $applicationStats = [];
    public $recentApplications = [];
    public $positionStats = [];
    public $statusDistribution = [];
    
    public function mount()
    {
        $this->loadStatistics();
    }
    
    public function updatedDateRange()
    {
        $this->loadStatistics();
    }
    
    public function updatedDepartmentId()
    {
        $this->loadStatistics();
    }
    
    public function updatedEmployeeStatus()
    {
        $this->loadStatistics();
    }
    
    public function updatedApplicationStatus()
    {
        $this->loadStatistics();
    }
    
    private function loadStatistics()
    {
        $this->loadEmployeeStats();
        $this->loadAttendanceStats();
        $this->loadPayrollStats();
        $this->loadDepartmentStats();
        $this->loadRecentHires();
        $this->loadUpcomingLeave();
        $this->loadAttendanceToday();
        $this->loadMonthlyAttendance();
        $this->loadSalaryDistribution();
        $this->loadApplicationStats();
        $this->loadRecentApplications();
        $this->loadPositionStats();
        $this->loadStatusDistribution();
    }
    
    private function loadEmployeeStats()
    {
        // Total employees
        $employeeQuery = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->whereNull('u.deleted_at');
        
        if ($this->department_id) {
            $employeeQuery->where('e.department_id', $this->department_id);
        }
        
        $this->totalEmployees = $employeeQuery->count();
        
        // Active employees
        $this->activeEmployees = $employeeQuery->clone()
            ->where('e.status', 'active')
            ->count();
        
        // On leave
        $this->onLeave = $employeeQuery->clone()
            ->where('e.status', 'on_leave')
            ->count();
        
        // Departments
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->get();
    }
    
    private function loadAttendanceStats()
    {
        $startDate = $this->getDateRangeStart();
        $endDate = $this->getDateRangeEnd();
        
        $query = DB::table('hr_attendance as a')
            ->join('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->whereBetween('a.date', [$startDate, $endDate]);
        
        if ($this->department_id) {
            $query->where('e.department_id', $this->department_id);
        }
        
        $stats = $query->select(
            DB::raw('COUNT(*) as total_attendance'),
            DB::raw('SUM(CASE WHEN a.status = "present" THEN 1 ELSE 0 END) as present'),
            DB::raw('SUM(CASE WHEN a.status = "absent" THEN 1 ELSE 0 END) as absent'),
            DB::raw('SUM(CASE WHEN a.status = "late" THEN 1 ELSE 0 END) as late'),
            DB::raw('SUM(CASE WHEN a.status = "on_leave" THEN 1 ELSE 0 END) as on_leave')
        )->first();
        
        $this->attendanceStats = [
            'present' => $stats->present ?? 0,
            'absent' => $stats->absent ?? 0,
            'late' => $stats->late ?? 0,
            'on_leave' => $stats->on_leave ?? 0,
            'total' => $stats->total_attendance ?? 0
        ];
    }
    
    private function loadPayrollStats()
    {
        $startDate = $this->getDateRangeStart();
        $endDate = $this->getDateRangeEnd();
        
        $query = DB::table('hr_payroll as p')
            ->join('employees as e', 'p.employee_id', '=', 'e.employee_id')
            ->where('p.status', 'paid')
            ->whereBetween('p.period_end', [$startDate, $endDate]);
        
        if ($this->department_id) {
            $query->where('e.department_id', $this->department_id);
        }
        
        $stats = $query->select(
            DB::raw('SUM(p.gross_pay) as total_gross_pay'),
            DB::raw('SUM(p.net_pay) as total_net_pay'),
            DB::raw('SUM(p.deductions) as total_deductions'),
            DB::raw('COUNT(*) as payroll_count')
        )->first();
        
        $this->payrollStats = [
            'total_gross' => $stats->total_gross_pay ?? 0,
            'total_net' => $stats->total_net_pay ?? 0,
            'total_deductions' => $stats->total_deductions ?? 0,
            'payroll_count' => $stats->payroll_count ?? 0
        ];
    }
    
    private function loadDepartmentStats()
    {
        $query = DB::table('departments as d')
            ->leftJoin('employees as e', function($join) {
                $join->on('d.department_id', '=', 'e.department_id');
                
                if ($this->employee_status && $this->employee_status !== 'all') {
                    $join->where('e.status', $this->employee_status);
                }
            })
            ->groupBy('d.department_id', 'd.department_name')
            ->select(
                'd.department_id',
                'd.department_name',
                DB::raw('COUNT(e.employee_id) as employee_count')
            );
        
        $departments = $query->get();
        
        $this->departmentStats = $departments->map(function($dept) {
            return [
                'id' => $dept->department_id,
                'name' => $dept->department_name,
                'employee_count' => $dept->employee_count,
                'percentage' => $this->totalEmployees > 0 ? ($dept->employee_count / $this->totalEmployees * 100) : 0
            ];
        });
    }
    
    private function loadRecentHires()
    {
        $this->recentHires = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('e.hire_date', '>=', Carbon::now()->subMonths(3))
            ->select(
                'e.employee_id',
                'e.job_title',
                'e.hire_date',
                'e.status',
                'u.full_name',
                'u.email',
                'd.department_name'
            )
            ->orderBy('e.hire_date', 'desc')
            ->limit(5)
            ->get();
    }
    
    private function loadUpcomingLeave()
    {
        $today = Carbon::today();
        $nextWeek = $today->copy()->addWeek();
        
        $this->upcomingLeave = DB::table('hr_attendance as a')
            ->join('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->where('a.status', 'on_leave')
            ->whereBetween('a.date', [$today, $nextWeek])
            ->select(
                'a.attendance_id',
                'a.date',
                'e.employee_id',
                'u.full_name',
                'd.department_name'
            )
            ->orderBy('a.date', 'asc')
            ->limit(5)
            ->get();
    }
    
    private function loadAttendanceToday()
    {
        $today = Carbon::today();
        
        $this->attendanceToday = DB::table('hr_attendance as a')
            ->join('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->join('users as u', 'e.user_id', '=', 'u.user_id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.department_id')
            ->whereDate('a.date', $today)
            ->select(
                'a.attendance_id',
                'a.status',
                'a.time_in',
                'e.employee_id',
                'u.full_name',
                'd.department_name'
            )
            ->orderBy('a.time_in', 'desc')
            ->limit(8)
            ->get();
    }
    
    private function loadMonthlyAttendance()
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        
        $this->monthlyAttendance = DB::table('hr_attendance')
            ->select(
                DB::raw('DATE(date) as attendance_date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late')
            )
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('attendance_date')
            ->orderBy('attendance_date', 'asc')
            ->get();
    }
    
    private function loadSalaryDistribution()
    {
        $this->salaryDistribution = DB::table('employees')
            ->select(
                DB::raw('CASE 
                    WHEN salary < 30000 THEN "Under $30k"
                    WHEN salary BETWEEN 30000 AND 50000 THEN "$30k - $50k"
                    WHEN salary BETWEEN 50001 AND 80000 THEN "$50k - $80k"
                    WHEN salary BETWEEN 80001 AND 120000 THEN "$80k - $120k"
                    ELSE "Above $120k"
                END as salary_range'),
                DB::raw('COUNT(*) as employee_count'),
                DB::raw('AVG(salary) as avg_salary')
            )
            ->where('status', 'active')
            ->groupBy('salary_range')
            ->orderByRaw('MIN(salary)')
            ->get();
    }
    
    private function loadApplicationStats()
    {
        $query = DB::table('job_applications');
        
        if ($this->application_status !== 'all') {
            $query->where('status', $this->application_status);
        }
        
        if ($this->dateRange !== 'this_month') {
            $startDate = $this->getDateRangeStart();
            $endDate = $this->getDateRangeEnd();
            $query->whereBetween('application_date', [$startDate, $endDate]);
        } else {
            $query->where('application_date', '>=', Carbon::now()->startOfMonth());
        }
        
        $stats = $query->select(
            DB::raw('COUNT(*) as total_applications'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN status = "reviewed" THEN 1 ELSE 0 END) as reviewed'),
            DB::raw('SUM(CASE WHEN status = "shortlisted" THEN 1 ELSE 0 END) as shortlisted'),
            DB::raw('SUM(CASE WHEN status = "hired" THEN 1 ELSE 0 END) as hired'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
        )->first();
        
        $this->applicationStats = [
            'total' => $stats->total_applications ?? 0,
            'pending' => $stats->pending ?? 0,
            'reviewed' => $stats->reviewed ?? 0,
            'shortlisted' => $stats->shortlisted ?? 0,
            'hired' => $stats->hired ?? 0,
            'rejected' => $stats->rejected ?? 0
        ];
    }
    
    private function loadRecentApplications()
    {
        $query = DB::table('job_applications as ja')
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->orderBy('ja.application_date', 'desc')
            ->limit(5);
        
        if ($this->application_status !== 'all') {
            $query->where('ja.status', $this->application_status);
        }
        
        $this->recentApplications = $query->select(
            'ja.application_id',
            'ja.position_applied',
            'ja.years_experience',
            'ja.status',
            'ja.application_date',
            'u.full_name',
            'u.email'
        )->get();
    }
    
    private function loadPositionStats()
    {
        $this->positionStats = DB::table('job_applications')
            ->select(
                'position_applied',
                DB::raw('COUNT(*) as application_count'),
                DB::raw('SUM(CASE WHEN status = "hired" THEN 1 ELSE 0 END) as hired_count'),
                DB::raw('AVG(LENGTH(years_experience)) as avg_experience_length')
            )
            ->where('application_date', '>=', Carbon::now()->subMonth())
            ->groupBy('position_applied')
            ->orderBy('application_count', 'desc')
            ->limit(5)
            ->get();
    }
    
    private function loadStatusDistribution()
    {
        $this->statusDistribution = DB::table('job_applications')
            ->select(
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM job_applications WHERE application_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 1) as percentage')
            )
            ->where('application_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('status')
            ->get();
    }
    
    private function getDateRangeStart()
    {
        return match($this->dateRange) {
            'today' => Carbon::today(),
            'this_week' => Carbon::now()->startOfWeek(),
            'last_week' => Carbon::now()->subWeek()->startOfWeek(),
            'this_month' => Carbon::now()->startOfMonth(),
            'last_month' => Carbon::now()->subMonth()->startOfMonth(),
            'this_year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
    }
    
    private function getDateRangeEnd()
    {
        return match($this->dateRange) {
            'today' => Carbon::today(),
            'this_week' => Carbon::now()->endOfWeek(),
            'last_week' => Carbon::now()->subWeek()->endOfWeek(),
            'this_month' => Carbon::now()->endOfMonth(),
            'last_month' => Carbon::now()->subMonth()->endOfMonth(),
            'this_year' => Carbon::now()->endOfYear(),
            default => Carbon::now()->endOfMonth(),
        };
    }
    
    public function updateApplicationStatus($applicationId, $status)
    {
        DB::table('job_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]);
        
        $this->loadStatistics();
    }
}

?>
<div class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">HR Dashboard</h1>
                <p class="text-gray-700 mt-2 font-medium">Manage your human resources with eco-friendly efficiency</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-sm font-medium shadow-sm">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Recruitment Active
                </span>

            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6 border border-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">📅 Date Range</label>
                    <div class="relative">
                        <select wire:model.live="dateRange" class="pl-10 pr-4 py-2.5 border-2 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500/20 rounded-xl shadow-sm bg-white text-gray-900 font-medium">
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                        </select>
                        <svg class="w-5 h-5 absolute left-3 top-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">🏢 Department</label>
                    <div class="relative">
                        <select wire:model.live="department_id" class="pl-10 pr-4 py-2.5 border-2 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500/20 rounded-xl shadow-sm bg-white text-gray-900 font-medium">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->department_id }}">{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                        <svg class="w-5 h-5 absolute left-3 top-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">👥 Employee Status</label>
                    <div class="relative">
                        <select wire:model.live="employee_status" class="pl-10 pr-4 py-2.5 border-2 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500/20 rounded-xl shadow-sm bg-white text-gray-900 font-medium">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                            <option value="terminated">Terminated</option>
                        </select>
                        <svg class="w-5 h-5 absolute left-3 top-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">📋 Application Status</label>
                    <div class="relative">
                        <select wire:model.live="application_status" class="pl-10 pr-4 py-2.5 border-2 border-gray-300 focus:border-emerald-500 focus:ring-emerald-500/20 rounded-xl shadow-sm bg-white text-gray-900 font-medium">
                            <option value="all">All Applications</option>
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="shortlisted">Shortlisted</option>
                            <option value="hired">Hired</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <svg class="w-5 h-5 absolute left-3 top-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button wire:click="$refresh" class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-colors shadow-md hover:shadow-lg font-medium">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh Data
                </button>
            </div>
        </div>
    </div>

        <!-- Key Metrics - FIXED READABILITY -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Employees -->
    <div class="bg-white border border-emerald-200 rounded-2xl shadow-xl p-6 text-gray-900 transform hover:scale-[1.02] transition-transform duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 font-medium">Total Employees</p>
                <p class="text-4xl font-bold text-emerald-700">{{ $totalEmployees }}</p>
                <div class="flex items-center mt-3">
                    <svg class="w-4 h-4 mr-2 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-600">+12% from last month</span>
                </div>
            </div>
            <div class="p-3 rounded-full bg-emerald-100">
                <svg class="w-8 h-8 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Active Employees -->
    <div class="bg-white border border-teal-200 rounded-2xl shadow-xl p-6 text-gray-900 transform hover:scale-[1.02] transition-transform duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 font-medium">Active Employees</p>
                <p class="text-4xl font-bold text-teal-700">{{ $activeEmployees }}</p>
                <div class="flex items-center mt-3">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-teal-700 h-2 rounded-full" style="width: {{ $totalEmployees > 0 ? ($activeEmployees / $totalEmployees * 100) : 0 }}%"></div>
                    </div>
                    <span class="text-sm text-gray-600 font-medium ml-3">{{ $totalEmployees > 0 ? round($activeEmployees / $totalEmployees * 100, 1) : 0 }}%</span>
                </div>
            </div>
            <div class="p-3 rounded-full bg-teal-100">
                <svg class="w-8 h-8 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Job Applications -->
    <div class="bg-white border border-blue-200 rounded-2xl shadow-xl p-6 text-gray-900 transform hover:scale-[1.02] transition-transform duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 font-medium">Job Applications</p>
                <p class="text-4xl font-bold text-blue-700">{{ $applicationStats['total'] }}</p>
                <div class="flex items-center mt-3">
                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-600">{{ $applicationStats['pending'] }} pending review</span>
                </div>
            </div>
            <div class="p-3 rounded-full bg-blue-100">
                <svg class="w-8 h-8 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Hiring Success Rate -->
    <div class="bg-white border border-green-200 rounded-2xl shadow-xl p-6 text-gray-900 transform hover:scale-[1.02] transition-transform duration-300">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 mb-1 font-medium">Hiring Success</p>
                <p class="text-4xl font-bold text-green-700">
                    @if($applicationStats['total'] > 0)
                        {{ number_format(($applicationStats['hired'] / $applicationStats['total']) * 100, 1) }}%
                    @else
                        0%
                    @endif
                </p>
                <div class="flex items-center mt-3">
                    <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm text-gray-600">{{ $applicationStats['hired'] }} hired this month</span>
                </div>
            </div>
            <div class="p-3 rounded-full bg-green-100">
                <svg class="w-8 h-8 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Box -->
<div class="mb-8 bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
    <div class="p-6 border-b border-emerald-200 bg-emerald-50">
        <div class="flex items-center">
            <div class="p-2 bg-emerald-100 rounded-lg mr-3">
                <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-emerald-900">⚡ Quick Actions</h2>
                <p class="text-emerald-700 mt-1">Frequently used HR operations</p>
            </div>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
            <!-- Attendance Summary -->
            <a href="{{ route('hr.attendance') }}" class="group">
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6 text-center hover:border-emerald-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-16 h-16 mx-auto mb-4 bg-emerald-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-md">
                        <svg class="w-8 h-8 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Attendance</h3>
                    <p class="text-sm text-gray-700 font-medium">Today's summary</p>
                    <div class="mt-2 text-emerald-600 text-xs font-bold">
                        {{ $attendanceToday->where('status', 'present')->count() }} present
                    </div>
                </div>
            </a>

            <!-- Applications -->
            <a href="{{ route('hr.applications') }}" class="group">
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6 text-center hover:border-blue-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-16 h-16 mx-auto mb-4 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-md">
                        <svg class="w-8 h-8 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Applications</h3>
                    <p class="text-sm text-gray-700 font-medium">Review candidates</p>
                    <div class="mt-2 text-blue-600 text-xs font-bold">
                        {{ $applicationStats['pending'] }} pending
                    </div>
                </div>
            </a>

            <!-- Process Payroll -->
            <a href="{{ route('hr.payroll') }}" class="group">
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6 text-center hover:border-teal-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-16 h-16 mx-auto mb-4 bg-teal-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-md">
                        <svg class="w-8 h-8 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Payroll</h3>
                    <p class="text-sm text-gray-700 font-medium">Process salaries</p>
                    <div class="mt-2 text-teal-600 text-xs font-bold">
                        ₱{{ number_format($payrollStats['total_net'] ?? 0, 0) }} total
                    </div>
                </div>
            </a>

            <!-- Manage Leave -->
            <a href="{{ route('hr.leave') }}" class="group">
                <div class="bg-white border-2 border-gray-200 rounded-xl p-6 text-center hover:border-purple-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="w-16 h-16 mx-auto mb-4 bg-purple-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-md">
                        <svg class="w-8 h-8 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">Leave</h3>
                    <p class="text-sm text-gray-700 font-medium">Manage requests</p>
                    <div class="mt-2 text-purple-600 text-xs font-bold">
                        {{ $upcomingLeave->count() }} upcoming
                    </div>
                </div>
            </a>

            </a>
        </div>
    </div>
</div>


    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Recruitment Overview -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-emerald-600 bg-gradient-to-r from-emerald-600 to-green-600">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white">📊 Recruitment Overview</h2>
                        <div class="flex space-x-2">
                            <span class="px-3 py-1 bg-white/20 text-white rounded-full text-sm font-medium">
                                {{ $applicationStats['total'] }} Total
                            </span>
                            <span class="px-3 py-1 bg-white/20 text-white rounded-full text-sm font-medium">
                                {{ $applicationStats['hired'] }} Hired
                            </span>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                        <div class="text-center p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                            <p class="text-2xl font-bold text-yellow-900">{{ $applicationStats['pending'] }}</p>
                            <p class="text-sm text-yellow-800 font-medium">Pending</p>
                        </div>
                        <div class="text-center p-4 bg-blue-50 rounded-xl border border-blue-200">
                            <p class="text-2xl font-bold text-blue-900">{{ $applicationStats['reviewed'] }}</p>
                            <p class="text-sm text-blue-800 font-medium">Reviewed</p>
                        </div>
                        <div class="text-center p-4 bg-purple-50 rounded-xl border border-purple-200">
                            <p class="text-2xl font-bold text-purple-900">{{ $applicationStats['shortlisted'] }}</p>
                            <p class="text-sm text-purple-800 font-medium">Shortlisted</p>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-xl border border-green-200">
                            <p class="text-2xl font-bold text-green-900">{{ $applicationStats['hired'] }}</p>
                            <p class="text-sm text-green-800 font-medium">Hired</p>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-xl border border-red-200">
                            <p class="text-2xl font-bold text-red-900">{{ $applicationStats['rejected'] }}</p>
                            <p class="text-sm text-red-800 font-medium">Rejected</p>
                        </div>
                    </div>
                    
                    <!-- Application Status Chart -->
                    <div class="h-64">
                        <canvas id="applicationChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-emerald-600 bg-gradient-to-r from-emerald-600 to-green-600">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white">📝 Recent Job Applications</h2>
                        <a href="#" class="text-white hover:text-emerald-100 text-sm font-medium flex items-center">
                            View All 
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Candidate</th>
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Position</th>
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Experience</th>
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Date</th>
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="pb-3 pt-4 px-6 text-left text-sm font-semibold text-gray-900">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentApplications as $application)
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center mr-3 border border-emerald-200">
                                                    <span class="text-emerald-900 font-bold">
                                                        {{ substr($application->full_name, 0, 2) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-900">{{ $application->full_name }}</p>
                                                    <p class="text-sm text-gray-700">{{ $application->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="font-bold text-gray-900">{{ $application->position_applied }}</span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="px-3 py-1 bg-emerald-100 text-emerald-900 rounded-full text-sm font-medium border border-emerald-200">
                                                {{ $application->years_experience }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-gray-900 font-medium">
                                                {{ \Carbon\Carbon::parse($application->application_date)->format('M d, Y') }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="px-3 py-1.5 rounded-full text-sm font-bold
                                                @if($application->status == 'pending') bg-yellow-100 text-yellow-900 border border-yellow-300
                                                @elseif($application->status == 'reviewed') bg-blue-100 text-blue-900 border border-blue-300
                                                @elseif($application->status == 'shortlisted') bg-purple-100 text-purple-900 border border-purple-300
                                                @elseif($application->status == 'hired') bg-green-100 text-green-900 border border-green-300
                                                @else bg-red-100 text-red-900 border border-red-300 @endif">
                                                {{ ucfirst($application->status) }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="relative group">
                                                <button class="p-2 hover:bg-gray-100 rounded-lg transition-colors border border-gray-300">
                                                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                    </svg>
                                                </button>
                                                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-300 hidden group-hover:block z-10">
                                                    <div class="py-1">
                                                        <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'reviewed')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-blue-50 text-blue-900 font-medium flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            Mark as Reviewed
                                                        </button>
                                                        <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'shortlisted')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-purple-50 text-purple-900 font-medium flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Shortlist
                                                        </button>
                                                        <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'hired')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-green-50 text-green-900 font-medium flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Hire
                                                        </button>
                                                        <button wire:click="updateApplicationStatus('{{ $application->application_id }}', 'rejected')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-red-50 text-red-900 font-medium flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                            Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p class="text-gray-700 font-medium">No applications found</p>
                                                <p class="text-gray-600 text-sm mt-1">Start by creating a new job position</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-8">
            <!-- Popular Positions -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-emerald-600 bg-gradient-to-r from-emerald-600 to-green-600">
                    <h2 class="text-xl font-bold text-white">🎯 Popular Positions</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @forelse($positionStats as $position)
                            <div class="p-4 bg-emerald-50 rounded-xl hover:bg-emerald-100 transition-colors border border-emerald-200">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="font-bold text-gray-900">{{ $position->position_applied }}</h3>
                                    <span class="px-3 py-1 bg-emerald-600 text-white text-xs rounded-full font-bold">
                                        {{ $position->application_count }} apps
                                    </span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">Hired:</span>
                                        <span class="font-bold text-gray-900">{{ $position->hired_count }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">Avg. Experience:</span>
                                        <span class="font-bold text-gray-900">
                                            {{ round($position->avg_experience_length / 2) }}+ years
                                        </span>
                                    </div>
                                    <div class="pt-2">
                                        <div class="w-full bg-emerald-200 rounded-full h-2.5">
                                            <div class="bg-emerald-600 h-2.5 rounded-full" style="width: {{ min(100, ($position->hired_count / max(1, $position->application_count)) * 100) }}%"></div>
                                        </div>
                                        <div class="text-xs text-gray-700 mt-1 font-medium text-right">
                                            {{ round(min(100, ($position->hired_count / max(1, $position->application_count)) * 100)) }}% hire rate
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <div class="text-gray-400 mb-3">
                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-700 font-medium">No position data available</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Status Distribution -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-emerald-600 bg-gradient-to-r from-emerald-600 to-green-600">
                    <h2 class="text-xl font-bold text-white">📈 Status Distribution</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($statusDistribution as $status)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded-full mr-3 shadow-sm
                                        @if($status->status == 'pending') bg-yellow-500
                                        @elseif($status->status == 'reviewed') bg-blue-500
                                        @elseif($status->status == 'shortlisted') bg-purple-500
                                        @elseif($status->status == 'hired') bg-green-500
                                        @else bg-red-500 @endif">
                                    </div>
                                    <span class="font-medium text-gray-900">{{ ucfirst($status->status) }}</span>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <span class="text-gray-900 font-bold text-sm">{{ $status->count }}</span>
                                    <div class="w-32 bg-gray-300 rounded-full h-2.5">
                                        <div class="h-2.5 rounded-full shadow-sm
                                            @if($status->status == 'pending') bg-yellow-600
                                            @elseif($status->status == 'reviewed') bg-blue-600
                                            @elseif($status->status == 'shortlisted') bg-purple-600
                                            @elseif($status->status == 'hired') bg-green-600
                                            @else bg-red-600 @endif" 
                                            style="width: {{ $status->percentage }}%">
                                        </div>
                                    </div>
                                    <span class="text-gray-700 font-bold text-sm w-12 text-right">{{ $status->percentage }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Today's Attendance Summary - COMPLETELY FIXED -->
            <div class="bg-gradient-to-br from-emerald-700 to-emerald-800 rounded-2xl shadow-xl p-6 border border-emerald-600">
    <div class="flex items-center mb-6">
        <div class="p-2 bg-emerald-500 rounded-lg mr-3">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-green">📅 Today's Attendance</h2>
    </div>
    <div class="space-y-4">
        <div class="flex justify-between items-center p-3 bg-emerald-600 rounded-lg">
            <span class="font-medium text-white">Present</span>
            <span class="text-2xl font-bold text-white">{{ $attendanceToday->where('status', 'present')->count() }}</span>
        </div>
        <div class="flex justify-between items-center p-3 bg-emerald-500 rounded-lg">
            <span class="font-medium text-white">Absent</span>
            <span class="text-2xl font-bold text-white">{{ $attendanceToday->where('status', 'absent')->count() }}</span>
        </div>
        <div class="flex justify-between items-center p-3 bg-emerald-500 rounded-lg">
            <span class="font-medium text-white">Late</span>
            <span class="text-2xl font-bold text-white">{{ $attendanceToday->where('status', 'late')->count() }}</span>
        </div>
        <div class="flex justify-between items-center p-3 bg-emerald-600 rounded-lg">
            <span class="font-medium text-white">On Leave</span>
            <span class="text-2xl font-bold text-white">{{ $attendanceToday->where('status', 'on_leave')->count() }}</span>
        </div>
        <div class="pt-4 border-t border-emerald-600">
            <div class="flex justify-between items-center p-3 bg-emerald-700 rounded-lg">
                <span class="font-bold text-white">Total Check-ins</span>
                <span class="text-2xl font-bold text-white">{{ $attendanceToday->count() }}</span>
            </div>
        </div>
    </div>
</div>

        </div>
    </div>
</div>