<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'TGIF Procurement') - Thanks G Its Fries Day</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green theme for procurement -->
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
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
        }
        
        /* Header */
        .procurement-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--forest-green) 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 12px rgba(34, 197, 94, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
            height: 70px;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
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
        
        .user-info {
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
        
        /* Main Layout */
        .procurement-layout {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            width: 250px;
            border-right: 1px solid #e2e8f0;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar a:hover {
            background: #f1f5f9;
            color: var(--dark-green);
        }
        
        .sidebar a.active {
            background: var(--light-green);
            color: var(--dark-green);
            border-left: 4px solid var(--primary-green);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            background: #f8fafc;
            overflow-y: auto;
        }
        
        .content-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #64748b;
            font-size: 1rem;
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
            border-left: 4px solid var(--primary-green);
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-1px);
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
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .procurement-header {
                padding: 0.75rem 1rem;
            }
            
            .company-tagline {
                display: none;
            }
            
            .procurement-layout {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding: 1rem;
                flex-direction: row;
                overflow-x: auto;
                gap: 0.5rem;
            }
            
            .section-title {
                display: none;
            }
            
            .sidebar a {
                white-space: nowrap;
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .user-badge span:last-child {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="procurement-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('procurement.home') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 40px; width: auto;">
                @else
                    <div style="background: white; color: var(--dark-green); font-weight: bold; padding: 6px 10px; border-radius: 6px; font-size: 1rem;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Procurement Portal</div>
            </div>
        </a>

        <!-- User Info -->
        <div class="user-info">
            @auth
                <div class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <span>{{ Auth::user()->full_name ?? Auth::user()->name ?? 'User' }}</span>
                </div>
            @endauth
        </div>
    </div>
</header>

<!-- Main Layout -->
<div class="procurement-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <p class="section-title">Home</p>
        <a href="{{ route('procurement.home') }}" class="{{ request()->routeIs('procurement.home') ? 'active' : '' }}">
            <i class="fas fa-home"></i>
            Procurement Dashboard
        </a>
        <p class="section-title">Tracking</p>
        <a href="{{ route('supplier.list') }}" class="{{ request()->routeIs('supplier.list') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i>
            Supplier Tracking
        </a>
        <p class="section-title">Tracking</p>
        <a href="{{ route('procurement.status') }}" class="{{ request()->routeIs('procurement.status') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i>
            Track Status
        </a>

        <a href="{{ route('inventory.home') }}" class="{{ request()->routeIs('inventory.home') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i>
           Inventory 
        </a>

        <!-- Spacer to push logout to bottom -->
        <div style="flex-grow: 1;"></div>

        <!-- Logout Button -->
                    <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </form>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-container">
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
        </div>
    </main>
</div>

<!-- JavaScript -->
<script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

</body>
</html>