<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Business Intelligence Dashboard') - TGIF BI Portal</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js for Data Visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    
    <!-- Custom BI Green Theme -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bi: {
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
                        },
                        intelligence: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        analytics: {
                            blue: '#3b82f6',
                            purple: '#8b5cf6',
                            teal: '#0d9488',
                            amber: '#f59e0b',
                            rose: '#f43f5e'
                        }
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s ease-in-out infinite',
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'slide-down': 'slideDown 0.3s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        slideDown: {
                            '0%': { transform: 'translateY(-10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --bi-primary: #10b981;
            --bi-secondary: #059669;
            --bi-dark: #064e3b;
            --bi-light: #d1fae5;
            --bi-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* BI Header */
        .bi-header {
            background: linear-gradient(135deg, var(--bi-dark) 0%, #065f46 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(6, 78, 59, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Logo */
        .bi-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }
        
        .logo-icon {
            background: rgba(255, 255, 255, 0.15);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .bi-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .bi-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
            color: #a7f3d0;
        }
        
        /* Navigation */
        .bi-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .bi-nav-link {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        
        .bi-nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .bi-nav-link.active {
            background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-secondary) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .bi-nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
        }
        
        /* Navigation Badges */
        .nav-badge {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            margin-left: 0.25rem;
        }
        
        /* Time Period Selector */
        .period-selector {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.25rem;
            display: flex;
            gap: 0.25rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .period-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            background: transparent;
            color: white;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .period-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .period-btn.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* User Actions */
        .bi-user-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .notification-btn {
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .notification-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: #a7f3d0;
        }
        
        /* Main Content */
        .bi-content {
            max-width: 1800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* Page Header */
        .bi-page-header {
            margin-bottom: 2rem;
            animation: slideDown 0.5s ease-out;
        }
        
        .page-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--bi-dark);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--bi-dark) 0%, var(--bi-secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            animation: slideUp 0.3s ease-out;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 180px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--bi-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        /* Dashboard Grid */
        .bi-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* BI Cards */
        .bi-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .bi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bi-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .card-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }
        
        .card-action-btn:hover {
            background: #f1f5f9;
            color: var(--bi-primary);
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        /* KPI Cards */
        .kpi-card {
            background: linear-gradient(135deg, white 0%, #f8fafc 100%);
            border-left: 4px solid var(--bi-primary);
        }
        
        .kpi-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--bi-dark);
            margin-bottom: 0.5rem;
        }
        
        .kpi-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .kpi-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .kpi-change.positive {
            background: var(--bi-light);
            color: var(--bi-secondary);
        }
        
        .kpi-change.negative {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Chart Cards */
        .chart-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .chart-container {
            flex: 1;
            min-height: 300px;
            position: relative;
        }
        
        /* Data Table Cards */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .metric-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .metric-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        
        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--bi-light) 0%, #a7f3d0 100%);
            color: var(--bi-primary);
        }
        
        .metric-info {
            flex: 1;
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--bi-dark);
        }
        
        .metric-label {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        /* Insights Panel */
        .insights-panel {
            background: linear-gradient(135deg, var(--bi-light) 0%, #bbf7d0 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--bi-primary);
        }
        
        .insight-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .insight-item:last-child {
            border-bottom: none;
        }
        
        .insight-title {
            font-weight: 600;
            color: var(--bi-dark);
            margin-bottom: 0.25rem;
        }
        
        .insight-desc {
            font-size: 0.9rem;
            color: #475569;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-secondary) 100%);
            color: white;
            border: none;
        }
        
        .action-btn.primary:hover {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        /* Alert Messages */
        .bi-alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left-color: var(--bi-primary);
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left-color: #f59e0b;
            color: #92400e;
        }
        
        .alert-info {
            background: #f0fdf4;
            border-left-color: #0ea5e9;
            color: #075985;
        }
        
        /* Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 6px;
        }
        
        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }
        
        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .mobile-nav {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--bi-dark);
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 8px 20px rgba(6, 78, 59, 0.3);
        }
        
        /* Responsive Design */
        @media (max-width: 1400px) {
            .bi-dashboard-grid {
                grid-template-columns: repeat(6, 1fr);
            }
            
            .grid-col-span-6 {
                grid-column: span 6;
            }
        }
        
        @media (max-width: 1200px) {
            .bi-content {
                padding: 0 1.5rem;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }
        
        @media (max-width: 992px) {
            .bi-dashboard-grid {
                grid-template-columns: repeat(1, 1fr);
            }
            
            .grid-col-span-12,
            .grid-col-span-6,
            .grid-col-span-4,
            .grid-col-span-3 {
                grid-column: span 1;
            }
            
            .bi-header {
                padding: 0.75rem 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .bi-nav {
                display: none;
            }
            
            .mobile-nav.show {
                display: flex;
            }
            
            .period-selector {
                order: 3;
                width: 100%;
                margin-top: 1rem;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .bi-content {
                padding: 0 1rem;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .card-header {
                padding: 1.25rem;
            }
            
            .card-content {
                padding: 1.25rem;
            }
            
            .kpi-value {
                font-size: 2rem;
            }
            
            .user-info {
                display: none;
            }
            
            .user-profile {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .bi-title {
                font-size: 1.2rem;
            }
            
            .bi-subtitle {
                display: none;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                justify-content: center;
            }
            
            .action-btn {
                flex: 1;
                justify-content: center;
                min-width: 140px;
            }
        }
    </style>
</head>
<body>

<!-- BI Header -->
<header class="bi-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('reports.home') }}" class="bi-logo">
            <div class="logo-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="logo-text">
                <div class="bi-title">TGIF BI Portal</div>
                <div class="bi-subtitle">Business Intelligence & Analytics</div>
            </div>
        </a>

        <!-- Navigation -->
        <nav class="bi-nav">
            <a href="{{ route('reports.home') }}" class="bi-nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="{{ route('reports.summary') }}" class="bi-nav-link">
                <i class="fas fa-chart-bar"></i>Reports            </a>
        </nav>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- User Actions -->
        <div class="bi-user-actions">
            <div class="notification-btn">
                <i class="fas fa-bell"></i>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    BI
                </div>
                <div class="user-info">
                    <div class="user-name">Analytics User</div>
                    <div class="user-role">BI Analyst</div>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileMenu">
        <a href="{{ route('reports.home') }}" class="bi-nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="{{ route('reports.summary') }}" class="bi-nav-link">
            <i class="fas fa-chart-bar"></i>Reports
        </a>
    </nav>
</header>

<!-- Main Content Area -->
<main class="bi-content">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="bi-alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif
    
    @if(session('error'))
        <div class="bi-alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif
    
    @if(session('warning'))
        <div class="bi-alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            {{ session('warning') }}
        </div>
    @endif
    
    @if(session('info'))
        <div class="bi-alert alert-info">
            <i class="fas fa-info-circle"></i>
            {{ session('info') }}
        </div>
    @endif
    

    <!-- Page Content -->
    {{ $slot }}
</main>

<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('show');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (mobileMenu.classList.contains('show') && 
                    !mobileMenu.contains(event.target) && 
                    !mobileMenuBtn.contains(event.target)) {
                    mobileMenu.classList.remove('show');
                }
            });
        }
        
        // Highlight active navigation link
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.bi-nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
            }
        });
        
        // Time period selector
        const periodBtns = document.querySelectorAll('.period-btn');
        periodBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                periodBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update dashboard data based on selected period
                const period = this.getAttribute('data-period');
                updateDashboardPeriod(period);
            });
        });
        
        // Filter change handlers
        const filters = document.querySelectorAll('.filter-select');
        filters.forEach(filter => {
            filter.addEventListener('change', function() {
                applyFilters();
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bi-alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Initialize charts if any on the page
        initializeCharts();
        
        // Simulate data loading
        simulateDataLoading();
    });
    
    function updateDashboardPeriod(period) {
        // This would typically make an API call to update dashboard data
        console.log('Updating dashboard for period:', period);
        
        // Show loading state
        const kpiCards = document.querySelectorAll('.kpi-card');
        kpiCards.forEach(card => {
            const content = card.querySelector('.card-content');
            if (content) {
                content.classList.add('loading-shimmer');
            }
        });
        
        // Simulate API call
        setTimeout(() => {
            kpiCards.forEach(card => {
                const content = card.querySelector('.card-content');
                if (content) {
                    content.classList.remove('loading-shimmer');
                }
            });
            
            // Show success notification
            showNotification('Dashboard updated for ' + period, 'success');
        }, 1500);
    }
    
    function applyFilters() {
        const dateRange = document.getElementById('dateRange')?.value;
        const department = document.getElementById('department')?.value;
        const metricType = document.getElementById('metricType')?.value;
        const comparison = document.getElementById('comparison')?.value;
        
        console.log('Applying filters:', { dateRange, department, metricType, comparison });
        
        // In a real app, this would trigger a dashboard refresh with new filters
        showNotification('Filters applied successfully', 'success');
    }
    
    function initializeCharts() {
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') return;
        
        // Initialize any charts on the page
        const chartCanvases = document.querySelectorAll('canvas[data-chart]');
        
        chartCanvases.forEach(canvas => {
            const chartType = canvas.getAttribute('data-chart-type') || 'line';
            const chartData = JSON.parse(canvas.getAttribute('data-chart-data') || '{}');
            
            if (Object.keys(chartData).length > 0) {
                new Chart(canvas, {
                    type: chartType,
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }
        });
    }
    
    function simulateDataLoading() {
        // Simulate real-time data updates
        setInterval(() => {
            // Randomly update some KPI values
            const kpiValues = document.querySelectorAll('.kpi-value');
            kpiValues.forEach(value => {
                if (Math.random() > 0.7) { // 30% chance to update
                    const current = parseFloat(value.textContent.replace(/[^0-9.-]+/g, ''));
                    if (!isNaN(current)) {
                        const change = (Math.random() - 0.5) * 0.1; // ±5% change
                        const newValue = current * (1 + change);
                        value.textContent = formatNumber(newValue, value.getAttribute('data-format') || 'currency');
                        
                        // Update change indicator
                        const changeElement = value.nextElementSibling?.querySelector('.kpi-change');
                        if (changeElement) {
                            changeElement.textContent = (change > 0 ? '+' : '') + (change * 100).toFixed(1) + '%';
                            changeElement.className = 'kpi-change ' + (change > 0 ? 'positive' : 'negative');
                        }
                    }
                }
            });
        }, 5000); // Update every 5 seconds
    }
    
    function formatNumber(value, format) {
        switch (format) {
            case 'currency':
                return '$' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            case 'percent':
                return (value * 100).toFixed(1) + '%';
            case 'number':
                return value.toLocaleString('en-US');
            default:
                return value;
        }
    }
    
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `bi-alert alert-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;
        
        // Add to page
        const content = document.querySelector('.bi-content');
        if (content) {
            content.insertBefore(notification, content.firstChild);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.transition = 'opacity 0.5s ease';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        }
    }
</script>

</body>
</html>