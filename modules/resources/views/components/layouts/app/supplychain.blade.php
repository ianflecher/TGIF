<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'TGIF Supply Chain Portal') - Thanks G Its Fries Day</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green theme for supply chain portal -->
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
                        secondary: {
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
                        accent: {
                            blue: '#3b82f6',
                            amber: '#f59e0b',
                            indigo: '#6366f1',
                            teal: '#14b8a6'
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --primary-green: #22c55e;
            --primary-dark-green: #15803d;
            --primary-light-green: #dcfce7;
            --accent-blue: #3b82f6;
            --accent-amber: #f59e0b;
            --accent-indigo: #6366f1;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 30%, #ecfdf5 100%);
            min-height: 100vh;
        }
        
        /* Supply Chain Header */
        .scm-header {
            background: linear-gradient(135deg, var(--primary-dark-green) 0%, #0f766e 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 15px rgba(21, 128, 61, 0.25);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 3px solid var(--accent-amber);
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
            position: relative;
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
            font-weight: 500;
        }
        
        /* Navigation */
        .scm-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .scm-nav-link {
            color: white;
            text-decoration: none;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .scm-nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .scm-nav-link.active {
            background: var(--primary-green);
            color: white;
            box-shadow: 0 2px 10px rgba(34, 197, 94, 0.4);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .scm-nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 3px;
            background: var(--accent-amber);
            border-radius: 2px;
        }
        
        /* User Actions */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-badge {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.8) 0%, rgba(21, 128, 61, 0.8) 100%);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .user-badge.scm {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.8) 0%, rgba(37, 99, 235, 0.8) 100%);
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
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        /* Main Content */
        .scm-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* Alert Messages */
        .scm-alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .scm-alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 5px solid var(--primary-green);
            color: #166534;
        }
        
        .scm-alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 5px solid #ef4444;
            color: #991b1b;
        }
        
        .scm-alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-left: 5px solid var(--accent-amber);
            color: #92400e;
        }
        
        .scm-alert-info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 5px solid var(--accent-blue);
            color: #1e40af;
        }
        
        /* Page Cards */
        .scm-page-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border-top: 4px solid var(--primary-green);
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .scm-page-header {
            background: linear-gradient(135deg, var(--primary-dark-green) 0%, #0f766e 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(21, 128, 61, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .scm-page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .scm-page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .scm-page-header p {
            opacity: 0.9;
            font-size: 1rem;
            max-width: 800px;
            position: relative;
        }
        
        /* Dashboard Cards */
        .scm-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .scm-dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid var(--accent-blue);
            position: relative;
            overflow: hidden;
        }
        
        .scm-dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-blue) 0%, var(--primary-green) 100%);
        }
        
        .scm-dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .scm-dashboard-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .scm-dashboard-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .scm-stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-dark-green);
            margin-bottom: 0.5rem;
        }
        
        .scm-stat-change {
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .scm-stat-change.positive {
            color: var(--primary-green);
        }
        
        .scm-stat-change.negative {
            color: #ef4444;
        }
        
        /* Status Badges for Supply Chain */
        .scm-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0.9rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fbbf24;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #22c55e;
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .status-active {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #64748b;
            border: 1px solid #cbd5e1;
        }
        
        .status-delayed {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px dashed #f59e0b;
        }
        
        .status-ontrack {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #16a34a;
        }
        
        .status-critical {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 2px solid #dc2626;
        }
        
        /* Tables for Supply Chain Data */
        .scm-data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .scm-data-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .scm-data-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        .scm-data-table tr:hover {
            background: #f8fafc;
        }
        
        /* Buttons */
        .scm-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .scm-btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-dark-green) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .scm-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(34, 197, 94, 0.4);
        }
        
        .scm-btn-secondary {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .scm-btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .scm-btn-success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
        }
        
        .scm-btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(34, 197, 94, 0.4);
        }
        
        .scm-btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .scm-btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4);
        }
        
        /* Forms */
        .scm-form-group {
            margin-bottom: 1.5rem;
        }
        
        .scm-form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #334155;
            font-size: 0.95rem;
        }
        
        .scm-form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .scm-form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }
        
        /* Quick Action Cards */
        .scm-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .scm-action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .scm-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-green);
        }
        
        .scm-action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light-green) 0%, #bbf7d0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--primary-dark-green);
        }
        
        /* Mobile Menu */
        .scm-mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
        }
        
        .scm-mobile-nav {
            display: none;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-dark-green) 0%, #0f766e 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .scm-dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 1024px) {
            .scm-nav {
                gap: 0.25rem;
            }
            
            .scm-nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .scm-header {
                padding: 0.75rem 1rem;
            }
            
            .scm-mobile-menu-btn {
                display: block;
            }
            
            .scm-nav {
                display: none;
            }
            
            .scm-mobile-nav.show {
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
            
            .scm-content {
                padding: 0 1rem;
            }
            
            .scm-page-card {
                padding: 1.5rem;
            }
            
            .scm-data-table {
                display: block;
                overflow-x: auto;
            }
            
            .scm-page-header h1 {
                font-size: 1.75rem;
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
            
            .scm-page-header h1 {
                font-size: 1.5rem;
            }
            
            .scm-dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .scm-actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50">

<!-- Supply Chain Header -->
<header class="scm-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('supplychain.home') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 50px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
                @else
                    <div style="background: white; color: var(--primary-dark-green); font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 1.2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Supply Chain Management Portal</div>
            </div>
        </a>

        <!-- Mobile Menu Button -->
        <button class="scm-mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="scm-nav">
            <a href="{{ route('supplychain.home') }}" class="scm-nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="{{ route('supplychain.logistic') }}" class="scm-nav-link">
                <i class="fas fa-shipping-fast"></i>Logistics
            </a>
            <a href="{{ route('supplychain.tracking') }}" class="scm-nav-link">
                <i class="fas fa-truck"></i>Tracking
            </a>

        </nav>

        <!-- User Actions -->
        <div class="user-actions">
            @auth
                @if(Auth::user()->role === 'supply_chain')
                    <div class="user-badge scm">
                        <i class="fas fa-link"></i>
                        <span>{{ Auth::user()->full_name ?? 'SCM Manager' }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </button>
                    </form>
                @endif
            @else
                <a href="{{ route('scm.login') }}" class="scm-nav-link">
                    <i class="fas fa-sign-in-alt"></i>Login
                </a>
            @endauth
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="scm-mobile-nav" id="mobileMenu">
        <a href="{{ route('supplychain.home') }}" class="scm-nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        <a href="{{ route('supplychain.logistic') }}" class="scm-nav-link">
            <i class="fas fa-shipping-fast"></i>Logistics
        </a>
        <a href="{{ route('supplychain.tracking') }}" class="scm-nav-link">
                <i class="fas fa-truck"></i>Tracking
            </a>

    </nav>
</header>

<!-- Main Content Area -->
<main class="scm-content">
    <!-- Alert Messages -->
    @if(session('success'))
        <div class="scm-alert scm-alert-success">
            <i class="fas fa-check-circle text-green-600"></i>
            <div class="flex-1">{{ session('success') }}</div>
            <button class="text-green-700 opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif
    
    @if(session('error'))
        <div class="scm-alert scm-alert-error">
            <i class="fas fa-exclamation-circle text-red-600"></i>
            <div class="flex-1">{{ session('error') }}</div>
            <button class="text-red-700 opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif
    
    @if(session('warning'))
        <div class="scm-alert scm-alert-warning">
            <i class="fas fa-exclamation-triangle text-amber-600"></i>
            <div class="flex-1">{{ session('warning') }}</div>
            <button class="text-amber-700 opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif
    
    @if(session('info'))
        <div class="scm-alert scm-alert-info">
            <i class="fas fa-info-circle text-blue-600"></i>
            <div class="flex-1">{{ session('info') }}</div>
            <button class="text-blue-700 opacity-70 hover:opacity-100" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
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
        const navLinks = document.querySelectorAll('.scm-nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (href !== '/' && currentPath.includes(href.replace(/\/$/, '')))) {
                link.classList.add('active');
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
                if (!mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target) && mobileMenu.classList.contains('show')) {
                    mobileMenu.classList.remove('show');
                }
            });
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.scm-alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(20px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Add click handler to alert close buttons
        document.querySelectorAll('.scm-alert button').forEach(button => {
            button.addEventListener('click', function() {
                const alert = this.parentElement;
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        });
    });
    
    // Form validation helper
    function validateSCMForm(formId) {
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
    
    // Chart data simulation for dashboard
    function initializeSCMCharts() {
        // This function would be implemented with a charting library like Chart.js
        console.log('SCM Charts initialized');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSCMCharts);
    } else {
        initializeSCMCharts();
    }
</script>

</body>
</html>