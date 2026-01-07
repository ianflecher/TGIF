<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind custom green theme -->
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
    
    <title>Thanks G Its Fries Day</title>

    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
            --mint-green: #bbf7d0;
        }

        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Poppins','Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            color: #1f2b16;
        }

        /* --- Header --- */
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
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        /* --- Sidebar --- */
        .sidebar {
            background: linear-gradient(180deg, var(--forest-green) 0%, #0f4223 100%);
            color: white;
            width: 65px; /* Compact sidebar */
            min-height: calc(100vh - 60px);
            position: fixed;
            left: 0;
            top: 60px; /* Below header */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 15px 0;
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
            color: #e2e8f0;
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
            animation: gentle-pulse 3s infinite;
        }

        /* --- Main Content --- */
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

        /* Logo & Branding */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: white;
        }
        
        .company-tagline {
            font-size: 0.75rem;
            opacity: 0.9;
            color: var(--mint-green);
        }

        /* Sidebar Icons & Text */
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

        /* Animation for active state */
        @keyframes gentle-pulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(34, 197, 94, 0.5); }
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
            
            .company-name {
                font-size: 1rem;
            }
            
            .company-tagline {
                display: none;
            }
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
            
            .user-badge span:last-child {
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

        /* --- Table & Cards (Keep existing) --- */
        .data-table { margin-top:1.5rem; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:0.75rem 1rem; border-bottom:1px solid #eee; text-align:left; }
        th { background:#dcfce7; color:#166534; font-weight:600; }
        tr:hover { background:#f0fdf4; }

        .dashboard-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:1rem; }
        .card { background:#fff; padding:1rem; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.08); }
        .card-title { font-size:1rem; margin-bottom:0.5rem; color:#1b5e20; }
        .card-value { font-size:1.6rem; font-weight:600; color:#2e7d32; }

        /* Buttons */
        .btn { padding:0.5rem 1rem; border:none; border-radius:6px; cursor:pointer; }
        .btn-primary { background:#2e7d32; color:#fff; }
        .btn-warning { background:#ffb300; color:#1f2b16; }
        .btn-success { background:#43a047; color:#fff; }
        .btn-danger { background:#e53935; color:#fff; }
    </style>
</head>
<body>

<!-- Main Header -->
<header class="main-header">
    <div class="header-content">
        <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
        
        <!-- Logo - Centered -->
        <a href="{{ route('projects.home') ?: route('projects.projects') }}" class="logo-container">
            <div class="logo-icon">
                @if(file_exists(public_path('TGIF.png')))
                    <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 40px; width: auto;">
                @else
                    <div style="background: white; color: var(--dark-green); font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                        TGIF
                    </div>
                @endif
            </div>
            <div class="logo-text">
                <div class="company-name">Thanks G Its Fries Day</div>
                <div class="company-tagline">Admin Portal</div>
            </div>
        </a>
        
        <!-- User Info - Right aligned -->
        <div class="user-info">
            <div class="user-badge">
                <span style="color: var(--mint-green);">👤</span>
                <span>{{ Auth::user()->name ?? 'User' }}</span>
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
    <h3>Navigation</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('ecommerce.home') }}" class="nav-item {{ request()->routeIs('ecommerce.home') ? 'active' : '' }}">
                    <span class="module-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                    <span class="nav-tooltip">Overview</span>
                </a>
            </li>
            <!-- <li>
                <a href="{{ route('ecommerce.products') }}" class="nav-item {{ request()->routeIs('ecommerce.products') ? 'active' : '' }}">
                    <span class="module-icon">🛒</span>
                    <span class="nav-text">Products</span>
                    <span class="nav-tooltip">Manage Products</span>
                </a>
            </li>
            <li> -->
                <a href="{{ route('ecommerce.orders') }}" class="nav-item {{ request()->routeIs('ecommerce.orders') ? 'active' : '' }}">
                    <span class="module-icon">📦</span>
                    <span class="nav-text">Orders</span>
                    <span class="nav-tooltip">Manage Orders</span>
                </a>
            </li>
            <!-- <li>
                <a href="#" class="nav-item {{ request()->routeIs('ecommerce.customers') ? 'active' : '' }}">
                    <span class="module-icon">👥</span>
                    <span class="nav-text">Customers</span>
                    <span class="nav-tooltip">Customer List</span>
                </a>
            </li>
            <li>
                <a href="#" class="nav-item {{ request()->routeIs('ecommerce.reports') ? 'active' : '' }}">
                    <span class="module-icon">📊</span>
                    <span class="nav-text">Reports</span>
                    <span class="nav-tooltip">Sales & Inventory</span>
                </a>
            </li> -->
        </ul>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content" id="mainContent">
    {{ $slot }}
</main>

<script>
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.querySelector('.menu-toggle');

        if (window.innerWidth <= 480 &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target) &&
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

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

    // Auto-close mobile menu on item click
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 480) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                });
            });
        }
    });
</script>
</body>
</html>