<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TGIF - Thanks G Its Fries Day</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-green: #22c55e;
            --dark-green: #15803d;
            --light-green: #dcfce7;
            --forest-green: #14532d;
            --mint-green: #bbf7d0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
        }
        
        /* Compact Header - Matches Design Exactly */
        .main-header {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--forest-green) 100%);
            color: white;
            height: 60px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 12px rgba(21, 128, 61, 0.2);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Logo Container */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .logo-icon {
            background: transparent;
            padding: 0;
            border-radius: 0;
            color: var(--dark-green);
            font-size: 1.4rem;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
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
        
        /* Desktop Navigation */
        .desktop-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-item {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
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
        
        @keyframes gentle-pulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(34, 197, 94, 0.5); }
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .user-dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1001;
            border: 1px solid #e5e7eb;
        }
        
        .user-dropdown-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 8px 8px 0 0;
        }
        
        .user-name {
            font-weight: 600;
            color: #111827;
            font-size: 0.875rem;
        }
        
        .user-email {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }
        
        .dropdown-item:hover {
            background: #f0fdf4;
        }
        
        .dropdown-item i {
            color: var(--dark-green);
            width: 16px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
        }
        
        .dropdown-item.logout {
            color: #dc2626;
        }
        
        .dropdown-item.logout:hover {
            background: #fef2f2;
        }
        
        .dropdown-item.logout i {
            color: #dc2626;
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
        }
        
        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 999;
            border-radius: 0 0 12px 12px;
        }
        
        .mobile-menu.active {
            transform: translateY(0);
            opacity: 1;
        }
        
        .mobile-nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .mobile-nav-item:hover {
            background: #f0fdf4;
        }
        
        .mobile-nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mobile-user-info {
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }
            
            .desktop-nav {
                display: none;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .company-tagline {
                display: none;
            }
            
            .company-name {
                font-size: 1rem;
            }
            
            .user-dropdown-toggle span:not(.user-avatar) {
                display: none;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu {
                display: none;
            }
        }
        
        /* User Stats */
        .user-stats {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .stat-item i {
            color: var(--mint-green);
        }
    </style>
</head>
<body>
    <!-- Compact Header -->
    <header class="main-header">
        <div class="header-content">
            <!-- Logo -->
            <a href="{{ route('customer.dashboard') }}" class="logo-container">
                <div class="logo-icon" style="display: flex; align-items: center; justify-content: center;">
                    @if(file_exists(public_path('TGIF.png')))
                        <img src="{{ asset('TGIF.png') }}" alt="TGIF Logo" style="height: 100px; width: auto;">
                    @else
                        <div style="background: white; color: var(--dark-green); font-weight: bold; padding: 4px 8px; border-radius: 6px;">
                            TGIF
                        </div>
                    @endif
                </div>
                <div class="logo-text">
                    <div class="company-name">Thanks G Its Fries Day</div>
                    <div class="company-tagline">Customer Portal</div>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="desktop-nav">
                <!-- Shop Link -->
                <a href="{{ route('customer.products.index') }}" class="nav-item {{ request()->routeIs('customer.products.*') ? 'active' : '' }}">
                    <i class="fas fa-store text-sm"></i>
                    Shop
                </a>
                
                <!-- Cart with Badge - FIXED: Using session instead of model method -->
                <a href="{{ route('customer.cart.index') }}" class="nav-item {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}">
                    <i class="fas fa-shopping-cart text-sm"></i>
                    Cart
                    @php
                        // Get cart from session instead of calling undefined cartItems() method
                        $cart = session()->get('cart', []);
                        $cartCount = array_sum(array_column($cart, 'quantity'));
                    @endphp
                    @if($cartCount > 0)
                        <span class="notification-badge">{{ $cartCount }}</span>
                    @endif
                </a>
                
                <!-- Orders -->
                <a href="{{ route('customer.orders.index') }}" class="nav-item {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-box text-sm"></i>
                    Orders
                </a>
                
                <!-- User Dropdown -->
                <div class="user-dropdown">
                    <div class="user-dropdown-toggle" id="userDropdownToggle">
                        <div class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <span>{{ Auth::user()->name }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                    
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <!-- User Info -->
                        <div class="user-dropdown-header">
                            <div class="user-name">{{ Auth::user()->name }}</div>
                            <div class="user-email">{{ Auth::user()->email }}</div>
                        </div>
                        
                        <!-- Dashboard -->
                        <a href="{{ route('customer.dashboard') }}" class="dropdown-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        
                        <!-- Profile -->
                        <a href="{{ route('customer.account.profile') }}" class="dropdown-item {{ request()->routeIs('customer.account.*') ? 'active' : '' }}">
                            <i class="fas fa-user-circle"></i>
                            My Profile
                        </a>
                        
                        <!-- Orders -->
                        <a href="{{ route('customer.orders.index') }}" class="dropdown-item">
                            <i class="fas fa-box"></i>
                            My Orders
                        </a>
                        
                        <div class="dropdown-divider"></div>
                        
                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <button type="submit" class="dropdown-item logout w-full text-left">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </nav>

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <!-- User Info -->
        <div class="mobile-user-info">
            <div class="font-medium text-gray-900">{{ Auth::user()->name }}</div>
            <div class="text-sm text-gray-600">{{ Auth::user()->email }}</div>
        </div>
        
        <div class="space-y-2">
            <!-- Dashboard -->
            <a href="{{ route('customer.dashboard') }}" class="mobile-nav-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-tachometer-alt text-green-600"></i>
                    <span>Dashboard</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Shop -->
            <a href="{{ route('customer.products.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.products.*') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-store text-green-600"></i>
                    <span>Shop</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Cart -->
            <a href="{{ route('customer.cart.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-shopping-cart text-green-600"></i>
                    <span>Cart</span>
                    @if($cartCount > 0)
                        <span class="ml-2 px-2 py-1 text-xs bg-red-500 text-white rounded-full">{{ $cartCount }}</span>
                    @endif
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Orders -->
            <a href="{{ route('customer.orders.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-box text-green-600"></i>
                    <span>Orders</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Profile -->
            <a href="{{ route('customer.account.profile') }}" class="mobile-nav-item">
                <div class="mobile-nav-left">
                    <i class="fas fa-user-circle text-green-600"></i>
                    <span>Profile</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Support -->
            <a href="{{ route('customer.support.tickets') }}" class="mobile-nav-item">
                <div class="mobile-nav-left">
                    <i class="fas fa-headset text-green-600"></i>
                    <span>Support</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Divider -->
            <div class="border-t border-gray-200 pt-2 mt-2">
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="mobile-nav-item text-red-600 w-full text-left">
                        <div class="mobile-nav-left">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="pt-16">
        {{ $slot }}
    </main>

    <script>
        // Wrap everything in an IIFE to avoid redeclaration
        (function() {
            // Initialize only if not already initialized
            if (window.customerHeaderInitialized) {
                return;
            }
            
            window.customerHeaderInitialized = true;
            
            // Mobile menu toggle
            const mobileToggle = document.getElementById('mobileToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('active');
                });
            }
            
            // User dropdown toggle
            const userDropdownToggle = document.getElementById('userDropdownToggle');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userDropdownToggle) {
                userDropdownToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdownMenu.classList.toggle('active');
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', (e) => {
                // Close mobile menu
                if (mobileToggle && mobileMenu && !mobileToggle.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.remove('active');
                }
                
                // Close user dropdown
                if (userDropdownToggle && userDropdownMenu && !userDropdownToggle.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    userDropdownMenu.classList.remove('active');
                }
            });
            
            // Close mobile menu when clicking a link
            if (mobileMenu) {
                document.querySelectorAll('#mobileMenu a, #mobileMenu button').forEach(element => {
                    element.addEventListener('click', () => {
                        mobileMenu.classList.remove('active');
                    });
                });
            }
            
            // Update active nav items based on current URL
            document.addEventListener('DOMContentLoaded', () => {
                const currentPath = window.location.pathname;
                const navItems = document.querySelectorAll('.nav-item, .mobile-nav-item');
                
                navItems.forEach(item => {
                    const href = item.getAttribute('href');
                    if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                        item.classList.add('active');
                    }
                });
            });
            
            // Listen for cart updates from other components
            document.addEventListener('cart-updated', function() {
                // Reload the page or make an AJAX request to update cart count
                location.reload();
            });
        })();
    </script>
</body>
</html>