<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Add custom green theme colors for employee -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
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
                        },
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>
    
    <title>Thanks G Its Fries Day - Employee Portal</title>

    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
            --mint-green: #86efac;
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: linear-gradient(135deg, #f9fafb 0%, #f0fdf4 50%);
        }
        
        .main-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--forest-green) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            display: flex;
            align-items: center;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--forest-green) 0%, #0c4224 100%);
            color: white;
            width: 65px; /* Compact sidebar */
            min-height: calc(100vh - 60px);
            position: fixed;
            left: 0;
            top: 60px; /* Below header */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 15px 0;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
        }
        
        .sidebar:hover {
            width: 200px; /* Expand on hover */
        }
        
        .sidebar h3 {
            padding: 0 15px;
            margin-bottom: 15px;
            color: var(--mint-green);
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
            color: #d1fae5;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 6px;
            margin: 0 5px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item.active {
            background: var(--primary-green);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .main-content {
            margin-left: 65px; /* Match sidebar width */
            margin-top: 60px; /* Match header height */
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            transition: margin-left 0.3s ease;
        }
        
        .sidebar:hover + .main-content,
        .main-content:hover {
            margin-left: 200px; /* Expand when sidebar expands */
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
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
        
        /* Compact header content */
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
            background: transparent;
            padding: 0;
            border-radius: 0;
            color: white;
            font-size: 1.4rem;
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
            color: var(--mint-green);
        }
        
        /* User info in header */
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
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
        
        /* Tooltip for compact mode */
        .nav-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--forest-green);
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
            border-color: transparent var(--forest-green) transparent transparent;
        }
        
        .nav-item:hover .nav-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .sidebar:hover .nav-tooltip {
            display: none;
        }
        
        /* Responsive design */
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
        
        /* Green scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-green);
            border-radius: 2px;
        }
        
        /* Animation for active state */
        @keyframes gentle-pulse-green {
            0%, 100% { box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(34, 197, 94, 0.5); }
        }
        
        .nav-item.active {
            animation: gentle-pulse-green 3s infinite;
        }
        
        /* Mobile menu toggle */
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
        
        /* Green theme enhancements */
        .greenery-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle at 30% 30%, rgba(134, 239, 172, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .leaf-decoration {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle at 70% 70%, rgba(34, 197, 94, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        
        /* Green theme cards */
        .green-card {
            background: white;
            border: 1px solid #dcfce7;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(34, 197, 94, 0.08);
            transition: all 0.3s ease;
        }
        
        .green-card:hover {
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.12);
            border-color: #86efac;
        }
        
        /* Success status indicators */
        .status-success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .status-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #fbbf24;
        }
        
        .status-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
        }
        
        /* Green theme buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--dark-green);
            border: 2px solid var(--primary-green);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--light-green);
            border-color: var(--dark-green);
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Decorative greenery patterns -->
<div class="greenery-pattern"></div>
<div class="leaf-decoration"></div>

<!-- Main Header -->
<header class="main-header">
    <div class="header-content">
        <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
        <!-- Logo -->
        <div class="logo-container">
            <div class="logo-icon" style="display: flex; align-items: center; justify-content: center;">
            @if(file_exists(public_path('TGIF.png')))
                <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 100px; width: auto; filter: brightness(0) invert(1) sepia(1) saturate(5) hue-rotate(90deg);">
            @else
                <div style="background: rgba(255, 255, 255, 0.2); color: white; font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                    🌿 TGIF
                </div>
            @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Employee Portal 🌱</div>
            </div>
        </div>
        <!-- User info and logout -->
        <div class="user-info">
            <div class="user-badge">
                <span style="color: var(--mint-green);">👤</span>
                <span>{{ Auth::user()->name ?? 'Employee' }}</span>
                <span class="employee-role">EMPLOYEE</span>
            </div>
            
            <form method="POST" action="{{ route('logout') }}">
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
    <h3>👨‍💼 Employee Modules</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('employee.dashboard') }}" class="nav-item {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
                    <span class="module-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                    <span class="nav-tooltip">Dashboard</span>
                    <span class="employee-badge" style="display: {{ request()->routeIs('employee.dashboard') ? 'block' : 'none' }};">Live</span>
                </a>
            </li>
            <li>
                <a href="{{ route('employee.tasks') }}" class="nav-item {{ request()->routeIs('employee.tasks.*') ? 'active' : '' }}">
                    <span class="module-icon">✅</span>
                    <span class="nav-text">My Tasks</span>
                    <span class="nav-tooltip">Task Management</span>
                    <span class="task-indicator"></span>
                </a>
            </li>
            <li>
                <a href="{{ route('employee.attendance') }}" class="nav-item {{ request()->routeIs('employee.attendance.*') ? 'active' : '' }}">
                    <span class="module-icon">🕒</span>
                    <span class="nav-text">Attendance</span>
                    <span class="nav-tooltip">Attendance Tracking</span>
                </a>
            </li>
            <li>
                <a href="{{ route('employee.payroll') }}" class="nav-item {{ request()->routeIs('employee.payroll.*') ? 'active' : '' }}">
                    <span class="module-icon">💰</span>
                    <span class="nav-text">Payroll</span>
                    <span class="nav-tooltip">Payroll & Payslips</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-item {{ request()->routeIs('employee.profile.*') ? 'active' : '' }}">
                    <span class="module-icon">👤</span>
                    <span class="nav-text">Profile</span>
                    <span class="nav-tooltip">My Profile</span>
                </a>
            </li>
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
            document.querySelectorAll('.nav-item').forEach(item => {
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