<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public array $recentTasks = [];
    public array $projectStats = [];
    public array $budgetStats = [];
    
    public function mount()
    {
        $this->loadProjectData();
    }
    
    public function loadProjectData()
    {
        // Load all projects
        $this->projects = DB::table('projects')
            ->select(
                'projects.*',
                'users.full_name as project_manager_name',
                DB::raw('(SELECT COUNT(*) FROM project_tasks WHERE project_tasks.project_id = projects.project_id) as total_tasks'),
                DB::raw('(SELECT COUNT(*) FROM project_tasks WHERE project_tasks.project_id = projects.project_id AND project_tasks.status = "completed") as completed_tasks'),
                DB::raw('(SELECT AVG(progress) FROM project_tasks WHERE project_tasks.project_id = projects.project_id) as avg_progress')
            )
            ->leftJoin('employees', 'projects.project_manager_id', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->orderBy('projects.created_at', 'desc')
            ->get()
            ->toArray();
        
        // Load recent tasks
        $this->recentTasks = DB::table('project_tasks')
            ->select(
                'project_tasks.*',
                'projects.project_name',
                'users.full_name as assigned_to_name'
            )
            ->leftJoin('projects', 'project_tasks.project_id', '=', 'projects.project_id')
            ->leftJoin('employees', 'project_tasks.assigned_to', '=', 'employees.employee_id')
            ->leftJoin('users', 'employees.user_id', '=', 'users.user_id')
            ->orderBy('project_tasks.created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
        
        // Calculate project statistics
        $this->projectStats = [
            'total' => DB::table('projects')->count(),
            'active' => DB::table('projects')->where('status', 'in_progress')->count(),
            'completed' => DB::table('projects')->where('status', 'completed')->count(),
            'delayed' => DB::table('projects')
                ->where('status', 'in_progress')
                ->where('end_date', '<', now())
                ->count(),
        ];
        
        // Calculate budget statistics
        $totalBudget = DB::table('projects')->sum('budget_total') ?? 0;
        $totalSpent = DB::table('projects')->sum('actual_cost') ?? 0;
        $budgetUtilization = ($totalBudget > 0) ? round(($totalSpent / $totalBudget) * 100, 2) : 0;
        
        $this->budgetStats = [
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'budget_utilization' => $budgetUtilization,
            'remaining_budget' => $totalBudget - $totalSpent,
        ];
    }
    
    public function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
    
    public function formatDate($date)
    {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }
    
    public function getStatusColor($status)
    {
        return match(strtolower($status)) {
            'completed' => 'bg-green-100 text-green-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'on_hold' => 'bg-yellow-100 text-yellow-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'planning' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getPriorityColor($priority)
    {
        return match(strtolower($priority)) {
            'high', 'critical' => 'bg-red-100 text-red-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
    
    public function getTimeAgo($datetime)
    {
        if (!$datetime) return 'N/A';
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M d, Y', $time);
    }
    
    public function calculateProgress($project)
    {
        return $project->avg_progress ?? 0;
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Project Management Dashboard</h1>
        <p class="text-gray-600 mt-2">Monitor and manage all your projects in one place</p>
    </div>

    <!-- Project Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Projects -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Projects</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $projectStats['total'] ?? 0 }}</h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-green-600 font-medium">{{ $projectStats['active'] ?? 0 }} active</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $projectStats['completed'] ?? 0 }} completed</span>
                </span>
            </div>
        </div>

        <!-- Active Projects -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Active Projects</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $projectStats['active'] ?? 0 }}</h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-blue-600 font-medium">{{ $projectStats['delayed'] ?? 0 }} delayed</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">Need attention</span>
                </span>
            </div>
        </div>

        <!-- Total Budget -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Budget</p>
                    <h3 class="text-2xl font-bold text-gray-800">{{ $this->formatCurrency($budgetStats['total_budget'] ?? 0) }}</h3>
                </div>
                <div class="bg-purple-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ $budgetStats['budget_utilization'] ?? 0 }}% utilized</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">{{ $this->formatCurrency($budgetStats['total_spent'] ?? 0) }} spent</span>
                </span>
            </div>
        </div>

        <!-- Completion Rate -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Completion Rate</p>
                    <h3 class="text-2xl font-bold text-gray-800">
                        @if(($projectStats['total'] ?? 0) > 0)
                            {{ round((($projectStats['completed'] ?? 0) / ($projectStats['total'] ?? 1)) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </h3>
                </div>
                <div class="bg-orange-100 p-3 rounded-full">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center text-sm">
                    <span class="text-orange-600 font-medium">{{ $projectStats['completed'] ?? 0 }} completed</span>
                    <span class="mx-2">•</span>
                    <span class="text-gray-500">of {{ $projectStats['total'] ?? 0 }} total</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Projects List -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">All Projects</h2>
                    <p class="text-sm text-gray-600">Overview of all active and completed projects</p>
                </div>
                <a href="{{ route('projects.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($projects as $project)
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="font-medium text-gray-800">{{ $project->project_name ?? 'Untitled Project' }}</h4>
                                        <p class="text-sm text-gray-600">Manager: {{ $project->project_manager_name ?? 'Unassigned' }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getStatusColor($project->status ?? '') }}">
                                        {{ ucfirst(str_replace('_', ' ', $project->status ?? 'unknown')) }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-600 mb-3">
                                    <span class="mr-4">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ $this->formatDate($project->start_date) }} - {{ $this->formatDate($project->end_date) }}
                                    </span>
                                    <span>
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        {{ $project->total_tasks ?? 0 }} tasks
                                    </span>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mb-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $this->calculateProgress($project) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>Progress</span>
                                        <span>{{ number_format($this->calculateProgress($project), 1) }}%</span>
                                    </div>
                                </div>
                                
                                <!-- Budget -->
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-medium">{{ $this->formatCurrency($project->budget_total ?? 0) }}</span>
                                    <span class="text-gray-600">{{ $project->completed_tasks ?? 0 }}/{{ $project->total_tasks ?? 0 }} tasks completed</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="mt-2">No projects found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Tasks -->
        <div class="bg-white rounded-xl shadow-md">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Recent Tasks</h2>
                    <p class="text-sm text-gray-600">Latest tasks across all projects</p>
                </div>
                <a href="{{ route('projects.home') }}" class="text-green-600 hover:text-green-800 font-medium text-sm flex items-center">
                    View All
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($recentTasks as $task)
                        <div class="p-4 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">{{ $task->task_name ?? 'Untitled Task' }}</h4>
                                    <p class="text-sm text-gray-600">{{ $task->project_name ?? 'No Project' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full {{ $this->getPriorityColor($task->priority ?? 'medium') }}">
                                    {{ ucfirst($task->priority ?? 'medium') }}
                                </span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">{{ Str::limit($task->description ?? 'No description', 100) }}</p>
                            
                            <div class="flex justify-between items-center">
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    {{ $task->assigned_to_name ?? 'Unassigned' }}
                                </div>
                                
                                <div class="flex items-center space-x-4">
                                    <!-- Progress -->
                                    <div class="flex items-center text-sm">
                                        <span class="mr-2">Progress:</span>
                                        <span class="font-medium">{{ $task->progress ?? 0 }}%</span>
                                    </div>
                                    
                                    <!-- Status -->
                                    <span class="px-2 py-1 text-xs rounded-full {{ $this->getStatusColor($task->status ?? '') }}">
                                        {{ ucfirst($task->status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Dates -->
                            <div class="flex justify-between text-xs text-gray-500 mt-2">
                                <span>Start: {{ $this->formatDate($task->start_date) }}</span>
                                <span>Due: {{ $this->formatDate($task->end_date) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="mt-2">No tasks found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Budget Overview -->
    <div class="bg-white rounded-xl shadow-md mb-8">
        <div class="px-6 py-4 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Budget Overview</h2>
            <p class="text-sm text-gray-600">Project budget allocation and utilization</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Budget Summary -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Budget Summary</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-700">Total Budget</span>
                            <span class="font-bold text-gray-800">{{ $this->formatCurrency($budgetStats['total_budget'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-700">Total Spent</span>
                            <span class="font-bold text-green-600">{{ $this->formatCurrency($budgetStats['total_spent'] ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-700">Budget Utilization</span>
                            <span class="font-bold text-purple-600">{{ $budgetStats['budget_utilization'] ?? 0 }}%</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <span class="text-gray-700">Remaining Budget</span>
                            <span class="font-bold text-blue-600">
                                {{ $this->formatCurrency($budgetStats['remaining_budget'] ?? 0) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Top Projects by Budget -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Projects by Budget</h3>
                    <div class="space-y-4">
                        @foreach(array_slice($projects, 0, 5) as $project)
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800">{{ Str::limit($project->project_name ?? 'Untitled', 25) }}</h4>
                                    @php
                                        $budgetTotal = $project->budget_total ?? 1;
                                        $actualCost = $project->actual_cost ?? 0;
                                        $spentPercentage = ($budgetTotal > 0) ? min(($actualCost / $budgetTotal) * 100, 100) : 0;
                                    @endphp
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: {{ $spentPercentage }}%">
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold">{{ $this->formatCurrency($project->budget_total ?? 0) }}</span>
                                    <p class="text-xs text-gray-500">
                                        {{ round($spentPercentage, 1) }}% spent
                                    </p>
                                </div>
                            </div>
                        @endforeach
                        @if(count($projects) === 0)
                            <div class="text-center py-6 text-gray-500">
                                <p>No projects found</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('projects.home') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span class="font-medium text-gray-800">New Project</span>
            </a>
            
            <a href="{{ route('projects.home') }}?view=tasks" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span class="font-medium text-gray-800">Manage Tasks</span>
            </a>
            
            <a href="{{ route('projects.budget') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-medium text-gray-800">Budget Planning</span>
            </a>
            
            <a href="{{ route('projects.progress') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                <svg class="w-8 h-8 text-orange-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="font-medium text-gray-800">Progress Reports</span>
            </a>
        </div>
    </div>
</div>