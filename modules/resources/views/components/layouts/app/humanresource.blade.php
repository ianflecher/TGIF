<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'HR Portal') - TGIF Human Resources</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Green theme for HR -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        hr: {
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
                        accent: {
                            500: '#0ea5e9',
                            600: '#0284c7',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --hr-green: #22c55e;
            --hr-dark-green: #15803d;
            --hr-light-green: #dcfce7;
            --hr-blue: #0ea5e9;
            --hr-amber: #f59e0b;
            --hr-purple: #8b5cf6;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            min-height: 100vh;
        }
        
        /* HR Header - Green Theme */
        .hr-header {
            background: linear-gradient(135deg, var(--hr-dark-green) 0%, #14532d 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.2);
            position: sticky;
            top: 0;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
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
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .company-tagline {
            font-size: 0.85rem;
            opacity: 0.9;
            color: #bbf7d0;
        }
        
        /* HR Navigation */
        .hr-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            background: var(--hr-green);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .nav-badge {
            background: var(--hr-purple);
            color: white;
            padding: 0.1rem 0.4rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        /* Main Content */
        .hr-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* HR Dashboard Cards */
        .hr-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid var(--hr-green);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(34, 197, 94, 0.15);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--hr-light-green);
            color: var(--hr-green);
        }
        
        .card-stat {
            font-size: 2rem;
            font-weight: 700;
            color: var(--hr-dark-green);
        }
        
        .card-title {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .card-subtitle {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        /* HR Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #334155;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
        
        .action-btn:hover {
            border-color: var(--hr-green);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.1);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--hr-light-green);
            color: var(--hr-green);
        }
        
        .action-title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .action-desc {
            font-size: 0.8rem;
            color: #64748b;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid var(--hr-green);
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid var(--hr-amber);
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid var(--hr-blue);
            color: #1e40af;
        }
        
        /* Status Badges for HR */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-onleave {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-terminated {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Data Tables */
        .data-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .data-table th {
            background: var(--hr-light-green);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--hr-dark-green);
            border-bottom: 2px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--hr-green) 0%, var(--hr-dark-green) 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--hr-dark-green);
            border: 2px solid var(--hr-green);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--hr-light-green);
        }
        
        /* Form Elements */
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--hr-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .hr-nav {
                display: none; /* Hide desktop nav on tablets */
            }
            
            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }
        }
        
        @media (max-width: 768px) {
            .hr-header {
                padding: 0.75rem 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .hr-dashboard {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hr-content {
                padding: 0 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .company-name {
                font-size: 1.2rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .user-badge span:last-child {
                display: none;
            }
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -300px;
            width: 280px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            padding: 2rem 1.5rem;
            overflow-y: auto;
        }
        
        .mobile-menu.open {
            right: 0;
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .mobile-menu-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
        }
        
        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: #334155;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .mobile-nav-link:hover,
        .mobile-nav-link.active {
            background: var(--hr-light-green);
            color: var(--hr-green);
        }
        
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
        }
        
        .mobile-overlay.show {
            display: block;
        }
        
        /* Chart colors */
        .chart-green {
            background: var(--hr-green);
        }
        
        .chart-blue {
            background: var(--hr-blue);
        }
        
        .chart-purple {
            background: var(--hr-purple);
        }
        
        .chart-amber {
            background: var(--hr-amber);
        }
        
        /* Progress bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--hr-green) 0%, var(--hr-dark-green) 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="hr-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('hr.home') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 50px; width: auto;">
                @else
                    <div style="background: white; color: var(--hr-dark-green); font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 1.2rem;">
                        TGIF HR
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">TGIF Human Resources</div>
                <div class="company-tagline">Employee Management Portal</div>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="hr-nav">
            <a href="{{ route('hr.home') }}" class="nav-link {{ request()->routeIs('hr.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="{{ route('hr.attendance') }}" class="nav-link {{ request()->routeIs('hr.attendance') ? 'active' : '' }}">
                <i class="fas fa-clock"></i>Attendance
            </a>
            <a href="{{ route('hr.payroll') }}" class="nav-link {{ request()->routeIs('hr.payroll') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave"></i>Payroll
            </a>
            <a href="{{ route('hr.applications') }}" class="nav-link {{ request()->routeIs('hr.applications') ? 'active' : '' }}">
                <i class="fas fa-briefcase"></i>Applications
            </a>
            <a href="{{ route('hr.leave') }}" class="nav-link {{ request()->routeIs('hr.leave') ? 'active' : '' }}">
                <i class="fas fa-chart-bar"></i>Leave
            </a>
        </nav>

        <!-- User Actions -->
        <div class="user-actions">
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn lg:hidden" onclick="toggleMobileMenu()" style="background: none; border: none; color: white; font-size: 1.5rem;">
                <i class="fas fa-bars"></i>
            </button>
            
            @auth
                <div class="user-badge hidden md:flex">
                    <i class="fas fa-user-tie"></i>
                    <span>{{ Auth::user()->name ?? 'HR Manager' }}</span>
                </div>
                            <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-link">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="hidden md:inline">Login</span>
                </a>
            @endauth
        </div>
    </div>
</header>

<!-- Mobile Menu Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <h3 style="font-weight: 600; color: var(--hr-dark-green);">HR Menu</h3>
        <button class="mobile-menu-close" onclick="toggleMobileMenu()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav>
        <a href="{{ route('hr.home') }}" class="mobile-nav-link {{ request()->routeIs('hr.home') ? 'active' : '' }}">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="{{ route('hr.attendance') }}" class="mobile-nav-link {{ request()->routeIs('hr.attendance') ? 'active' : '' }}">
            <i class="fas fa-clock"></i>Attendance
        </a>
        <a href="{{ route('hr.payroll') }}" class="mobile-nav-link {{ request()->routeIs('hr.payroll') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave"></i>Payroll
        </a>
        <a href="{{ route('hr.applications') }}" class="mobile-nav-link {{ request()->routeIs('hr.applications') ? 'active' : '' }}">
            <i class="fas fa-briefcase"></i>Applications
            <span style="margin-left: auto; background: var(--hr-purple); color: white; padding: 0.1rem 0.4rem; border-radius: 10px; font-size: 0.7rem;">5</span>
        </a>
        <a href="{{ route('hr.leave') }}" class="mobile-nav-link {{ request()->routeIs('hr.leave') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i>Leave
        </a>
        
        @auth
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem; color: #64748b;">
                    <i class="fas fa-user-tie"></i>
                    <span>{{ Auth::user()->name ?? 'HR Manager' }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" style="margin-top: 0.5rem;">
                    @csrf
                    <button type="submit" style="width: 100%; background: #ef4444; color: white; border: none; padding: 0.75rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </button>
                </form>
            </div>
        @endauth
    </nav>
</div>

<!-- Main Content Area -->
<main class="hr-content">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif
    
    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            {{ session('warning') }}
        </div>
    @endif
    
    @if(session('info'))
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            {{ session('info') }}
        </div>
    @endif
    
    <!-- Page Content -->
    {{ $slot }}
</main>

<!-- JavaScript -->
<script>
    // Toggle mobile menu
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileOverlay');
        
        mobileMenu.classList.toggle('open');
        overlay.classList.toggle('show');
        
        // Prevent body scroll when menu is open
        document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
    }
    
    // Highlight active navigation link
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
    // Close mobile menu on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const mobileMenu = document.getElementById('mobileMenu');
            if (mobileMenu.classList.contains('open')) {
                toggleMobileMenu();
            }
        }
    });
</script>

@stack('scripts')
</body>
</html>