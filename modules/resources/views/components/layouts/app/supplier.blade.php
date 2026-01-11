<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'TGIF Supplier Portal') - Thanks G Its Fries Day</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom green theme for supplier portal -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        supplier: {
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
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --supplier-green: #22c55e;
            --supplier-dark-green: #15803d;
            --supplier-light-green: #dcfce7;
            --supplier-amber: #f59e0b;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%);
            min-height: 100vh;
        }
        
        /* Header */
        .supplier-header {
            background: linear-gradient(135deg, var(--supplier-dark-green) 0%, #14532d 100%);
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
        
        /* Navigation */
        .supplier-nav {
            display: flex;
            gap: 1.5rem;
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
            background: var(--supplier-green);
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
        
        .user-badge.supplier {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.7) 0%, rgba(21, 128, 61, 0.7) 100%);
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
        .supplier-content {
            max-width: 1400px;
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
        }
        
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid var(--supplier-green);
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid var(--supplier-amber);
            color: #92400e;
        }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        /* Page Cards */
        .page-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--supplier-green);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--supplier-dark-green) 0%, var(--supplier-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            opacity: 0.9;
            font-size: 1rem;
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
            border-top: 4px solid var(--supplier-green);
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
            color: var(--supplier-dark-green);
            margin-bottom: 0.5rem;
        }
        
        /* Status Badges for Supplier Portal */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-active {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .status-expired {
            background: #f8fafc;
            color: #475569;
            border: 1px dashed #cbd5e1;
        }
        
        /* Tables for Supplier Data */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        
        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
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
            background: linear-gradient(135deg, var(--supplier-green) 0%, var(--supplier-dark-green) 100%);
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
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
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
            border-color: var(--supplier-green);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
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
            background: var(--supplier-dark-green);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .supplier-header {
                padding: 0.75rem 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .supplier-nav {
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
            
            .supplier-content {
                padding: 0 1rem;
            }
            
            .page-card {
                padding: 1.5rem;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
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
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Main Header -->
<header class="supplier-header">
    <div class="header-content">
        <!-- Logo -->
        <a href="{{ route('supplier.dashboard') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 50px; width: auto;">
                @else
                    <div style="background: white; color: var(--supplier-dark-green); font-weight: bold; padding: 8px 12px; border-radius: 8px; font-size: 1.2rem;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Supplier Portal</div>
            </div>
        </a>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navigation -->
        <nav class="supplier-nav">
            <a href="{{ route('supplier.dashboard') }}" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
        </nav>

        <!-- User Actions -->
        <div class="user-actions">
                    <div class="user-badge supplier">
                        <i class="fas fa-truck"></i>
                        <span>{{ Auth::user()->full_name ?? 'Supplier' }}</span>
                    </div>
                    <form method="POST" action="{{ route('supplier.logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </button>
                    </form>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav" id="mobileMenu">
        <a href="{{ route('supplier.dashboard') }}" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>Dashboard
        </a>
        @auth
            @if(Auth::user()->role === 'supplier')
                <a href="{{ route('supplier.profile') }}" class="nav-link">
                    <i class="fas fa-user-circle"></i>Profile
                </a>
                <a href="{{ route('supplier.products') }}" class="nav-link">
                    <i class="fas fa-boxes"></i>Products
                </a>
                <a href="{{ route('supplier.orders') }}" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>Orders
                </a>
                <a href="{{ route('supplier.invoices') }}" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>Invoices
                </a>
                <a href="{{ route('supplier.documents') }}" class="nav-link">
                    <i class="fas fa-folder"></i>Documents
                </a>
                <a href="{{ route('supplier.performance') }}" class="nav-link">
                    <i class="fas fa-chart-line"></i>Performance
                </a>
                <a href="{{ route('supplier.support') }}" class="nav-link">
                    <i class="fas fa-headset"></i>Support
                </a>
            @endif
        @endauth
    </nav>
</header>

<!-- Main Content Area -->
<main class="supplier-content">
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
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });
    
    // Form validation helper
    function validateForm(formId) {
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