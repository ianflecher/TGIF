<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Add custom department-based theme colors -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Support Department Theme (Blue)
                        support: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        // Warehouse Department Theme (Orange)
                        warehouse: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        },
                        // Procurement Department Theme (Purple)
                        procurement: {
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7e22ce',
                            800: '#6b21a8',
                            900: '#581c87',
                        },
                        // Default Green Theme
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <title>Thanks G Its Fries Day - {{ ucfirst(strtolower(Auth::user()->employee->department->department_name ?? 'Employee')) }} Portal</title>

    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
            --mint-green: #86efac;
        }
        
        /* Dynamic department colors */
        .department-support {
            --dept-primary: #2563eb;
            --dept-dark: #1d4ed8;
            --dept-light: #dbeafe;
            --dept-accent: #60a5fa;
        }
        
        .department-warehouse {
            --dept-primary: #f97316;
            --dept-dark: #c2410c;
            --dept-light: #ffedd5;
            --dept-accent: #fb923c;
        }
        
        .department-procurement {
            --dept-primary: #a855f7;
            --dept-dark: #7e22ce;
            --dept-light: #f3e8ff;
            --dept-accent: #c084fc;
        }
        
        .department-default {
            --dept-primary: #22c55e;
            --dept-dark: #15803d;
            --dept-light: #dcfce7;
            --dept-accent: #86efac;
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: linear-gradient(135deg, #f9fafb 0%, var(--dept-light) 50%);
        }
        
        .main-header {
            background: linear-gradient(135deg, var(--dept-dark) 0%, var(--dept-primary) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            box-shadow: 0 2px 12px rgba(var(--dept-primary-rgb), 0.2);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            display: flex;
            align-items: center;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--dept-dark) 0%, var(--dept-primary) 100%);
            color: white;
            width: 65px;
            min-height: calc(100vh - 60px);
            position: fixed;
            left: 0;
            top: 60px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 15px 0;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .sidebar:hover {
            width: 200px;
        }
        
        .sidebar h3 {
            padding: 0 15px;
            margin-bottom: 15px;
            color: var(--dept-accent);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        
        .sidebar:hover h3 {
            opacity: 1;
        }
        
        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar nav ul li {
            margin-bottom: 2px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 5px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        
        .nav-item.active {
            background: var(--dept-primary);
            color: white;
            box-shadow: 0 2px 8px rgba(var(--dept-primary-rgb), 0.3);
        }
        
        .main-content {
            margin-left: 65px;
            margin-top: 60px;
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
            background: linear-gradient(135deg, #f8fafc 0%, var(--dept-light) 50%);
            transition: margin-left 0.3s ease;
        }
        
        .sidebar:hover + .main-content,
        .main-content:hover {
            margin-left: 200px;
        }
        
        .module-icon {
            min-width: 24px;
            text-align: center;
            font-size: 1.2rem;
            margin-right: 0;
            transition: margin-right 0.3s ease;
        }
        
        .sidebar:hover .module-icon {
            margin-right: 12px;
        }
        
        .nav-text {
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 0.9rem;
        }
        
        .sidebar:hover .nav-text {
            opacity: 1;
        }
        
        .employee-badge {
            background: linear-gradient(135deg, var(--dept-accent), var(--dept-primary));
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
            font-weight: bold;
        }
        
        .sidebar:hover .employee-badge {
            opacity: 1;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        .company-tagline {
            font-size: 0.75rem;
            opacity: 0.9;
            color: var(--dept-accent);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 12px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
        }
        
        .employee-role {
            background: linear-gradient(135deg, var(--dept-accent), var(--dept-primary));
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 4px;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        .nav-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--dept-dark);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            margin-left: 10px;
        }
        
        .nav-tooltip::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border-width: 6px;
            border-style: solid;
            border-color: transparent var(--dept-dark) transparent transparent;
        }
        
        .nav-item:hover .nav-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar:hover .nav-tooltip {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 55px;
            }
            
            .sidebar:hover {
                width: 180px;
            }
            
            .main-content {
                margin-left: 55px;
                padding: 1rem;
            }
            
            .sidebar:hover + .main-content {
                margin-left: 180px;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .user-badge span:first-child {
                display: none;
            }
        }
        
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--dept-primary);
            border-radius: 2px;
        }
        
        @keyframes gentle-pulse-dept {
            0%, 100% { box-shadow: 0 2px 8px rgba(var(--dept-primary-rgb), 0.3); }
            50% { box-shadow: 0 2px 12px rgba(var(--dept-primary-rgb), 0.5); }
        }
        
        .nav-item.active {
            animation: gentle-pulse-dept 3s infinite;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            margin-right: 10px;
        }
        
        @media (max-width: 480px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                width: 200px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        .greenery-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle at 30% 30%, rgba(var(--dept-accent-rgb), 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .leaf-decoration {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle at 70% 70%, rgba(var(--dept-primary-rgb), 0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .dept-card {
            background: white;
            border: 1px solid var(--dept-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(var(--dept-primary-rgb), 0.08);
            transition: all 0.3s ease;
        }
        
        .dept-card:hover {
            box-shadow: 0 4px 20px rgba(var(--dept-primary-rgb), 0.12);
            border-color: var(--dept-accent);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--dept-primary), var(--dept-dark));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(var(--dept-primary-rgb), 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--dept-primary-rgb), 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--dept-dark);
            border: 2px solid var(--dept-primary);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--dept-light);
            border-color: var(--dept-dark);
        }
        
        /* Access control styles */
        .nav-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .access-denied {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
    
    <script>
        // Set department class and RGB values
        document.addEventListener('DOMContentLoaded', function() {
            const departmentName = "{{ strtolower(Auth::user()->employee->department->department_name ?? 'default') }}";
            const departmentClass = getDepartmentClass(departmentName);
            const colors = getDepartmentColors(departmentClass);
            
            // Add department class to body
            document.body.classList.add(departmentClass);
            
            // Set CSS custom properties
            document.documentElement.style.setProperty('--dept-primary', colors.primary);
            document.documentElement.style.setProperty('--dept-dark', colors.dark);
            document.documentElement.style.setProperty('--dept-light', colors.light);
            document.documentElement.style.setProperty('--dept-accent', colors.accent);
            document.documentElement.style.setProperty('--dept-primary-rgb', colors.primaryRgb);
            document.documentElement.style.setProperty('--dept-accent-rgb', colors.accentRgb);
            
            // Control module access based on department
            controlModuleAccess(departmentName);
            
            function getDepartmentClass(deptName) {
                const dept = deptName.toLowerCase();
                if (dept.includes('support') || dept.includes('help') || dept.includes('service')) {
                    return 'department-support';
                } else if (dept.includes('warehouse') || dept.includes('storage') || dept.includes('inventory')) {
                    return 'department-warehouse';
                } else if (dept.includes('procurement') || dept.includes('purchasing') || dept.includes('purchase')) {
                    return 'department-procurement';
                } else {
                    return 'department-default';
                }
            }
            
            function getDepartmentColors(deptClass) {
                const colorMap = {
                    'department-support': {
                        primary: '#2563eb',
                        dark: '#1d4ed8',
                        light: '#dbeafe',
                        accent: '#60a5fa',
                        primaryRgb: '37, 99, 235',
                        accentRgb: '96, 165, 250'
                    },
                    'department-warehouse': {
                        primary: '#f97316',
                        dark: '#c2410c',
                        light: '#ffedd5',
                        accent: '#fb923c',
                        primaryRgb: '249, 115, 22',
                        accentRgb: '251, 146, 60'
                    },
                    'department-procurement': {
                        primary: '#a855f7',
                        dark: '#7e22ce',
                        light: '#f3e8ff',
                        accent: '#c084fc',
                        primaryRgb: '168, 85, 247',
                        accentRgb: '192, 132, 252'
                    },
                    'department-default': {
                        primary: '#22c55e',
                        dark: '#15803d',
                        light: '#dcfce7',
                        accent: '#86efac',
                        primaryRgb: '34, 197, 94',
                        accentRgb: '134, 239, 172'
                    }
                };
                
                return colorMap[deptClass] || colorMap['department-default'];
            }
            
            function controlModuleAccess(deptName) {
                const dept = deptName.toLowerCase();
                const supportModules = ['dashboard', 'tasks', 'support', 'attendance', 'payroll', 'leave'];
                const warehouseModules = ['dashboard', 'tasks', 'attendance', 'payroll', 'leave', 'warehouse'];
                const procurementModules = ['dashboard', 'tasks', 'attendance', 'payroll', 'leave', 'procurement.create'];
                
                let allowedModules = [];
                
                if (dept.includes('support')) {
                    allowedModules = supportModules;
                } else if (dept.includes('warehouse')) {
                    allowedModules = warehouseModules;
                } else if (dept.includes('procurement') || dept.includes('purchasing')) {
                    allowedModules = procurementModules;
                } else {
                    // Default: show all modules
                    allowedModules = ['dashboard', 'tasks', 'support', 'attendance', 'payroll', 'leave', 'warehouse', 'procurement.create'];
                }
                
                // Disable modules not allowed for this department
                document.querySelectorAll('.nav-item').forEach(item => {
                    const href = item.getAttribute('href');
                    const moduleName = href ? href.split('/').pop() : '';
                    const routeName = href ? href.replace(/^\//, '').replace(/\//g, '.') : '';
                    
                    let isAllowed = false;
                    for (const allowedModule of allowedModules) {
                        if (routeName.includes(allowedModule) || href.includes(allowedModule)) {
                            isAllowed = true;
                            break;
                        }
                    }
                    
                    if (!isAllowed && href) {
                        item.classList.add('disabled');
                        const icon = item.querySelector('.module-icon');
                        const text = item.querySelector('.nav-text');
                        const tooltip = item.querySelector('.nav-tooltip');
                        
                        if (icon) icon.textContent = '🚫';
                        if (text) {
                            text.textContent = 'Access Restricted';
                            text.classList.add('access-denied');
                        }
                        if (tooltip) {
                            tooltip.textContent = 'Not Available for Your Department';
                            tooltip.style.background = '#9ca3af';
                        }
                    }
                });
            }
        });
    </script>
</head>
<body>

<!-- Decorative patterns -->
<div class="greenery-pattern"></div>
<div class="leaf-decoration"></div>

<!-- Main Header -->
<header class="main-header">
    <div class="header-content">
        <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
        
        <!-- Logo -->
        <div class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 40px; width: auto; filter: brightness(0) invert(1);">
                @else
                    <div style="background: rgba(255, 255, 255, 0.2); color: white; font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">
                    {{ ucfirst(Auth::user()->employee->department->department_name ?? 'Employee') }} Portal
                </div>
            </div>
        </div>
        
        <!-- User info and logout -->
        <div class="user-info">
            <div class="user-badge">
                <span style="color: var(--dept-accent);">👤</span>
                <span>{{ Auth::user()->name ?? 'Employee' }}</span>
                <span class="employee-role">
                    {{ strtoupper(Auth::user()->employee->department->department_name ?? 'EMPLOYEE') }}
                </span>
            </div>
            
            <form method="POST" action="{{ route('employee.logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <h3>{{ strtoupper(Auth::user()->employee->department->department_name ?? 'EMPLOYEE') }} MODULES</h3>

    <nav>
        <ul>
            <!-- Dashboard -->
            <li>
                <a href="{{ route('employee.dashboard') }}"
                   class="nav-item {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                    <span class="module-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                    <span class="nav-tooltip">Dashboard</span>
                    <span class="employee-badge"
                          style="display: {{ request()->routeIs('employee.dashboard') ? 'block' : 'none' }}">
                        Live
                    </span>
                </a>
            </li>

            <!-- Tasks -->
            <li>
                <a href="{{ route('employee.tasks') }}"
                   class="nav-item {{ request()->routeIs('employee.tasks.*') ? 'active' : '' }}">
                    <span class="module-icon">✅</span>
                    <span class="nav-text">My Tasks</span>
                    <span class="nav-tooltip">Task Management</span>
                </a>
            </li>

            <!-- Support (Only for Support Department) -->
            @php
                $deptName = strtolower(Auth::user()->employee->department->department_name ?? '');
            @endphp
            
            @if(str_contains($deptName, 'support'))
            <li>
                <a href="{{ route('employee.support') }}"
                   class="nav-item {{ request()->routeIs('employee.support') ? 'active' : '' }}">
                    <span class="module-icon">🛠️</span>
                    <span class="nav-text">Support</span>
                    <span class="nav-tooltip">Support Tickets</span>
                </a>
            </li>
            @endif

            <!-- Attendance -->
            <li>
                <a href="{{ route('employee.attendance') }}"
                   class="nav-item {{ request()->routeIs('employee.attendance.*') ? 'active' : '' }}">
                    <span class="module-icon">🕒</span>
                    <span class="nav-text">Attendance</span>
                    <span class="nav-tooltip">Attendance Tracking</span>
                </a>
            </li>

            <!-- Payroll -->
            <li>
                <a href="{{ route('employee.payroll') }}"
                   class="nav-item {{ request()->routeIs('employee.payroll.*') ? 'active' : '' }}">
                    <span class="module-icon">💰</span>
                    <span class="nav-text">Payroll</span>
                    <span class="nav-tooltip">Payroll & Payslips</span>
                </a>
            </li>

            <!-- Leave -->
            <li>
                <a href="{{ route('employee.leave') }}"
                   class="nav-item {{ request()->routeIs('employee.leave.*') ? 'active' : '' }}">
                    <span class="module-icon">🏖️</span>
                    <span class="nav-text">Leave</span>
                    <span class="nav-tooltip">Leave Management</span>
                </a>
            </li>

            <!-- Warehouse (Only for Warehouse Department) -->
            @if(str_contains($deptName, 'warehouse'))
            <li class="mt-5">
                <h3>📦 WAREHOUSE OPERATIONS</h3>
            </li>
            <li>
                <a href="{{ route('employee.warehouse') }}"
                   class="nav-item {{ request()->routeIs('employee.warehouse.*') ? 'active' : '' }}">
                    <span class="module-icon">🏪</span>
                    <span class="nav-text">Warehouse</span>
                    <span class="nav-tooltip">Warehouse Management</span>
                </a>
            </li>
            @endif

            <!-- Purchase Requisition (Only for Procurement/Purchasing Department) -->
            @if(str_contains($deptName, 'procurement') || str_contains($deptName, 'purchasing'))
            <li class="mt-5">
                <h3>📋 PROCUREMENT OPERATIONS</h3>
            </li>
            <li>
                <a href="{{ route('procurement.create') }}"
                   class="nav-item {{ request()->routeIs('procurement.create') ? 'active' : '' }}">
                    <span class="module-icon">📝</span>
                    <span class="nav-text">Purchase Request</span>
                    <span class="nav-tooltip">Create Purchase Requisition</span>
                </a>
            </li>
            @endif
        </ul>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content" id="mainContent">
    {{ $slot }}
</main>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle mobile menu
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
        }
        window.toggleMobileMenu = toggleMobileMenu;

        // Handle sidebar hover behavior
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (sidebar && mainContent) {
            sidebar.addEventListener('mouseenter', function() {
                if (window.innerWidth > 768) {
                    this.style.width = '200px';
                    mainContent.style.marginLeft = '200px';
                }
            });
            
            sidebar.addEventListener('mouseleave', function() {
                if (window.innerWidth > 768) {
                    this.style.width = '65px';
                    mainContent.style.marginLeft = '65px';
                }
            });
        }

        // Update active state
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            if (item.classList.contains('disabled')) return;
            
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                item.classList.add('active');
                const badge = item.querySelector('.employee-badge');
                if (badge) {
                    badge.style.display = 'block';
                }
            }
        });
        
        // Auto-close mobile menu on item click
        if (window.innerWidth <= 480) {
            document.querySelectorAll('.nav-item:not(.disabled)').forEach(item => {
                item.addEventListener('click', () => {
                    const sidebar = document.getElementById('sidebar');
                    if (sidebar) sidebar.classList.remove('mobile-open');
                });
            });
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 480 && sidebar && menuToggle && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
    });
</script>
</body>
</html>