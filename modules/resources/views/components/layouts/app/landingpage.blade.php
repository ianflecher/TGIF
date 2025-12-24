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
        
        /* Compact Header - Matches Admin Design Exactly */
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
        
        /* Logo Container - Matches Admin Exactly */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        /* Cart Badge */
        .cart-badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 4px;
        }
        
        /* Auth Area */
        .auth-area {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 1rem;
        }
        
        /* Auth Buttons - Matches Admin Style */
        .btn-auth {
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            white-space: nowrap;
        }
        
        .btn-login {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-login:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }
        
        .btn-signup {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(34, 197, 94, 0.3);
        }
        
        .btn-signup:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(34, 197, 94, 0.4);
        }
        
        /* User Dropdown - Matches Admin */
        .user-dropdown {
            position: relative;
        }
        
        .user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .user-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .user-avatar {
            width: 28px;
            height: 28px;
            background: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 200px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            padding: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1001;
        }
        
        .user-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: #374151;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background: #f0fdf4;
            color: var(--dark-green);
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }
        
        .logout-btn {
            color: #dc2626;
        }
        
        .logout-btn:hover {
            background: #fef2f2;
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
        
        .mobile-auth-buttons {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        /* Logout Button - Matches Admin */
        .logout-btn-admin {
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
        
        .logout-btn-admin:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }
            
            .desktop-nav, .auth-area {
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
        }
        
        @media (min-width: 769px) {
            .mobile-menu {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Compact Header -->
    <header class="main-header">
        <div class="header-content">
            <!-- Logo - Exact match to admin -->
            <a href="{{ url('/') }}" class="logo-container">
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
                    <div class="company-tagline">Delicious Fries & Snacks</div>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="desktop-nav">
                <a href="{{ route('customer.products.index') }}" class="nav-item {{ request()->routeIs('customer.products.*') ? 'active' : '' }}">
                    <i class="fas fa-store text-sm"></i>
                    Shop
                </a>
                
                @auth
                    @if(auth()->user()->role === 'customer')
                        <a href="{{ route('customer.cart.index') }}" class="nav-item {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}">
                            <i class="fas fa-shopping-cart text-sm"></i>
                            Cart
                            <span class="cart-badge">3</span>
                        </a>
                        <a href="{{ route('customer.orders.index') }}" class="nav-item {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
                            <i class="fas fa-receipt text-sm"></i>
                            Orders
                        </a>
                    @endif
                @endauth
                
                <a href="{{ route('customer.support.index') }}" class="nav-item {{ request()->routeIs('customer.support.*') ? 'active' : '' }}">
                    <i class="fas fa-headset text-sm"></i>
                    Support
                </a>
                
                <!-- Auth Area -->
                <div class="auth-area">
                    @auth
                        @if(auth()->user()->role === 'customer')
                            <div class="user-dropdown">
                                <button class="user-btn">
                                    <div class="user-avatar">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                    <span class="text-sm">{{ auth()->user()->name }}</span>
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </button>
                                
                                <div class="dropdown-menu">
                                    <a href="{{ route('customer.dashboard') }}" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt text-green-600"></i>
                                        Dashboard
                                    </a>
                                    <a href="{{ route('customer.account.profile') }}" class="dropdown-item">
                                        <i class="fas fa-user-circle text-green-600"></i>
                                        My Profile
                                    </a>
                                    <a href="{{ route('customer.orders.index') }}" class="dropdown-item">
                                        <i class="fas fa-receipt text-green-600"></i>
                                        My Orders
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="logout-btn-admin w-full">
                                            <i class="fas fa-sign-out-alt mr-2"></i>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('dashboard') }}" class="btn-auth btn-login">
                                <i class="fas fa-cog"></i>
                                Admin Panel
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn-auth btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="btn-auth btn-signup">
                            <i class="fas fa-user-plus"></i>
                            Sign Up
                        </a>
                    @endauth
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
        <div class="space-y-2">
            <a href="{{ route('customer.products.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.products.*') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-store text-green-600"></i>
                    <span>Shop Products</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            @auth
                @if(auth()->user()->role === 'customer')
                    <a href="{{ route('customer.cart.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}">
                        <div class="mobile-nav-left">
                            <i class="fas fa-shopping-cart text-green-600"></i>
                            <span>Shopping Cart</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="cart-badge">3</span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </div>
                    </a>
                    
                    <a href="{{ route('customer.orders.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
                        <div class="mobile-nav-left">
                            <i class="fas fa-receipt text-green-600"></i>
                            <span>My Orders</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </a>
                @endif
            @endauth
            
            <a href="{{ route('customer.support.index') }}" class="mobile-nav-item {{ request()->routeIs('customer.support.*') ? 'active' : '' }}">
                <div class="mobile-nav-left">
                    <i class="fas fa-headset text-green-600"></i>
                    <span>Customer Support</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </a>
            
            <!-- Mobile Auth -->
            <div class="mobile-auth-buttons">
                @auth
                    @if(auth()->user()->role === 'customer')
                        <a href="{{ route('customer.dashboard') }}" class="mobile-nav-item">
                            <div class="mobile-nav-left">
                                <i class="fas fa-tachometer-alt text-green-600"></i>
                                <span>Dashboard</span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="logout-btn-admin w-full">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('dashboard') }}" class="mobile-nav-item bg-green-50 text-green-700">
                            <div class="mobile-nav-left">
                                <i class="fas fa-cog"></i>
                                <span>Admin Dashboard</span>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="mobile-nav-item border border-green-600 text-green-600">
                        <div class="mobile-nav-left">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="{{ route('register') }}" class="mobile-nav-item bg-green-600 text-white">
                        <div class="mobile-nav-left">
                            <i class="fas fa-user-plus"></i>
                            <span>Sign Up</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                @endauth
            </div>
        </div>
    </div>

    <!-- THIS IS THE MISSING PART - Main Content Area -->
    <main class="pt-16">
        {{ $slot }}
    </main>

    <script>
        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        
        mobileToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mobileToggle.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        });
        
        // Close mobile menu when clicking a link
        document.querySelectorAll('#mobileMenu a, #mobileMenu button').forEach(element => {
            element.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
            });
        });
        
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
    </script>
</body>
</html>