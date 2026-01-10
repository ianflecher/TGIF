<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'TGIF Finance Portal') - Thanks G Its Fries Day</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green theme for finance portal -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        finance: {
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
                        success: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#10b981',
                            600: '#059669',
                        },
                        warning: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        danger: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#ef4444',
                            600: '#dc2626',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --finance-green: #22c55e;
            --finance-dark-green: #166534;
            --finance-light-green: #dcfce7;
            --finance-teal: #0d9488;
            --finance-emerald: #10b981;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            min-height: 100vh;
        }
        
        /* Header */
        .finance-header {
            background: linear-gradient(135deg, var(--finance-dark-green) 0%, #14532d 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1600px;
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
        
        /* Navigation */
        .finance-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .nav-link.active {
            background: var(--finance-green);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: white;
            border-radius: 2px;
        }
        
        .nav-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 220px;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .nav-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-link {
            color: #334155;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .dropdown-link:hover {
            background: #f0fdf4;
            color: var(--finance-green);
            padding-left: 1.25rem;
        }
        
        .dropdown-link:last-child {
            border-bottom: none;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .finance-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .action-btn {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
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
        
        .user-badge.finance {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.7) 0%, rgba(22, 101, 52, 0.7) 100%);
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
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        /* Main Content */
        .finance-content {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid var(--finance-green);
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid var(--danger-500);
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid var(--warning-500);
            color: #92400e;
        }
        
        .alert-info {
            background: #f0fdf4;
            border-left: 4px solid var(--finance-green);
            color: #166534;
        }
        
        /* Page Cards */
        .page-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--finance-green);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--finance-dark-green) 0%, var(--finance-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .page-header p {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }
        
        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid var(--finance-green);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--finance-green), var(--finance-teal));
        }
        
        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .dashboard-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--finance-dark-green);
            margin-bottom: 0.5rem;
        }
        
        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .trend-up {
            background: #dcfce7;
            color: #166534;
        }
        
        .trend-down {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Financial Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-paid {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .status-approved {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .status-rejected {
            background: #f1f5f9;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }
        
        .status-draft {
            background: #f8fafc;
            color: #475569;
            border: 1px dashed #94a3b8;
        }
        
        /* Financial Tables */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .financial-table th {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 1.25rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #bbf7d0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .financial-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        .financial-table tr:hover {
            background: #f8fafc;
        }
        
        .table-amount {
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
            text-align: right;
        }
        
        .amount-positive {
            color: #166534;
        }
        
        .amount-negative {
            color: #dc2626;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--finance-green) 0%, var(--finance-dark-green) 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--finance-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        /* Financial Charts Container */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        /* Quick Stats Bar */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-stat {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        
        .quick-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .icon-income {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: var(--finance-green);
        }
        
        .icon-expense {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger-500);
        }
        
        .icon-profit {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #10b981;
        }
        
        .icon-budget {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: var(--warning-500);
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
            gap: 1rem;
            padding: 1rem;
            background: var(--finance-dark-green);
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .finance-header {
                padding: 0.75rem 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .finance-nav {
                display: none;
            }
            
            .mobile-nav.show {
                display: flex;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            .logo-container {
                flex: 1;
            }
            
            .user-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 0.5rem;
            }
            
            .finance-content {
                padding: 0 1rem;
            }
            
            .page-card {
                padding: 1.5rem;
            }
            
            .financial-table {
                display: block;
                overflow-x: auto;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .company-name {
                font-size: 1.2rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .user-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .logout-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .action-btn span {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="finance-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('finance.home') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 50px; width: auto;">
                @else
                    <div style="background: white; color: var(--finance-dark-green); font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 1.2rem;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Finance & Accounting Portal</div>
            </div>
        </a>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="finance-nav">
    <a href="{{ route('finance.home') }}" class="nav-link">
        <i class="fas fa-tachometer-alt"></i>Dashboard
    </a>
    
    <a href="{{ route('finance.approval') }}" class="nav-link">
        <i class="fas fa-file-invoice-dollar"></i>Approval
    </a>
    
    <!-- <a href="{{ route('finance.invoice') }}" class="nav-link">
        <i class="fas fa-plus-circle"></i>Invoices
    </a>
     -->
    <a href="{{ route('finance.accounts') }}" class="nav-link">
        <i class="fas fa-address-book"></i>Accounts
    </a>
</nav>

    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileMenu">
        <a href="{{ route('finance.home') }}" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
         <a href="{{ route('finance.approval') }}" class="dropdown-link">
                        <i class="fas fa-list"></i>All Invoices
                    </a>
                    <!-- <a href="{{ route('finance.invoice') }}" class="dropdown-link">
                        <i class="fas fa-plus"></i>Create Invoice
                    </a> -->
                    <a href="{{ route('finance.accounts') }}" class="dropdown-link">
                        <i class="fas fa-plus"></i>Accounts
                    </a>
    </nav>
</header>

<!-- Main Content Area -->
<main class="finance-content">
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
    // Highlight active navigation link
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
                
                // Also highlight parent dropdown if in a dropdown menu
                if (link.closest('.nav-dropdown')) {
                    const parentLink = link.closest('.nav-dropdown').querySelector('.nav-link');
                    if (parentLink) {
                        parentLink.classList.add('active');
                    }
                }
            }
        });
        
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
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Format currency amounts
        function formatCurrency(amount, currency = 'USD') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
        
        // Apply currency formatting to elements with data-currency attribute
        document.querySelectorAll('[data-currency]').forEach(element => {
            const amount = parseFloat(element.textContent);
            if (!isNaN(amount)) {
                const currency = element.getAttribute('data-currency') || 'USD';
                element.textContent = formatCurrency(amount, currency);
            }
        });
    });
    
    // Form validation helper for finance forms
    function validateFinanceForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return true;
        
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ef4444';
                field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                
                // Add error message if not already present
                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message text-red-600 text-sm mt-1';
                    errorMsg.textContent = 'This field is required';
                    field.parentNode.appendChild(errorMsg);
                }
            } else {
                field.style.borderColor = '#e2e8f0';
                field.style.boxShadow = 'none';
                
                // Remove error message if present
                const errorMsg = field.nextElementSibling;
                if (errorMsg && errorMsg.classList.contains('error-message')) {
                    errorMsg.remove();
                }
            }
        });
        
        return isValid;
    }
</script>

</body>
</html>