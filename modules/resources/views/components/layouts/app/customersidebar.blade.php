<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Add custom blue theme colors -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <title>Thanks G Its Fries Day - Customer Portal</title>

    <style>
        :root {
            --primary-blue: #3b82f6;
            --dark-blue: #1d4ed8;
            --light-blue: #dbeafe;
            --navy-blue: #1e3a8a;
            --sky-blue: #bfdbfe;
        }
        
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .customer-header {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--navy-blue) 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            box-shadow: 0 2px 12px rgba(59, 130, 246, 0.2);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
            display: flex;
            align-items: center;
        }
        
        .customer-sidebar {
            background: linear-gradient(180deg, var(--navy-blue) 0%, #172554 100%);
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
        
        .customer-sidebar:hover {
            width: 220px; /* Expand on hover */
        }
        
        .customer-sidebar h3 {
            padding: 0 15px;
            margin-bottom: 15px;
            color: var(--sky-blue);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            opacity: 0;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }
        
        .customer-sidebar:hover h3 {
            opacity: 1;
        }
        
        .customer-sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .customer-sidebar nav ul li {
            margin-bottom: 2px;
        }
        
        .customer-nav-item {
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
        
        .customer-nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .customer-nav-item.active {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .customer-content {
            margin-left: 65px; /* Match sidebar width */
            margin-top: 60px; /* Match header height */
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 50%);
            transition: margin-left 0.3s ease;
        }
        
        .customer-sidebar:hover + .customer-content,
        .customer-content:hover {
            margin-left: 220px; /* Expand when sidebar expands */
        }
        
        .customer-icon {
            min-width: 24px;
            text-align: center;
            font-size: 1.2rem;
            margin-right: 0;
            transition: margin-right 0.3s ease;
        }
        
        .customer-sidebar:hover .customer-icon {
            margin-right: 12px;
        }
        
        .customer-nav-text {
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 0.9rem;
        }
        
        .customer-sidebar:hover .customer-nav-text {
            opacity: 1;
        }
        
        .customer-badge {
            background: var(--primary-blue);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .customer-sidebar:hover .customer-badge {
            opacity: 1;
        }
        
        /* Customer header content */
        .customer-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .customer-logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-logo-icon {
            background: white;
            padding: 6px;
            border-radius: 8px;
            color: var(--dark-blue);
            font-size: 1.4rem;
        }
        
        .customer-logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .customer-company-name {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        .customer-company-tagline {
            font-size: 0.75rem;
            opacity: 0.9;
            color: var(--sky-blue);
        }
        
        /* User info in header */
        .customer-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .customer-user-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 15px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .customer-logout-btn {
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
        
        .customer-logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }
        
        .welcome-text {
            font-size: 0.9rem;
            opacity: 0.9;
            color: var(--sky-blue);
            margin-right: 10px;
        }
        
        /* Tooltip for compact mode */
        .customer-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--navy-blue);
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
        
        .customer-tooltip::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border-width: 6px;
            border-style: solid;
            border-color: transparent var(--navy-blue) transparent transparent;
        }
        
        .customer-nav-item:hover .customer-tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .customer-sidebar:hover .customer-tooltip {
            display: none;
        }
        
        /* Blue scrollbar */
        .customer-sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .customer-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .customer-sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 2px;
        }
        
        /* Animation for active state */
        @keyframes customer-pulse {
            0%, 100% { box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(59, 130, 246, 0.5); }
        }
        
        .customer-nav-item.active {
            animation: customer-pulse 3s infinite;
        }
        
        /* Mobile menu toggle */
        .customer-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            margin-right: 10px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .customer-sidebar {
                width: 55px;
            }
            
            .customer-sidebar:hover {
                width: 200px;
            }
            
            .customer-content {
                margin-left: 55px;
                padding: 1rem;
            }
            
            .customer-sidebar:hover + .customer-content {
                margin-left: 200px;
            }
            
            .customer-company-name {
                font-size: 1rem;
            }
            
            .customer-company-tagline {
                display: none;
            }
            
            .welcome-text {
                display: none;
            }
            
            .customer-user-badge span:last-child {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .customer-menu-toggle {
                display: block;
            }
            
            .customer-sidebar {
                transform: translateX(-100%);
                width: 200px;
            }
            
            .customer-sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .customer-content {
                margin-left: 0;
            }
        }
        
        /* Customer-specific styles */
        .order-notification {
            position: absolute;
            right: -5px;
            top: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .customer-sidebar:hover .order-notification {
            opacity: 1;
        }
        
        .section-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .customer-sidebar:hover .section-divider {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Customer Header -->
<header class="customer-header">
    <div class="customer-header-content">
        <button class="customer-menu-toggle" onclick="toggleCustomerMobileMenu()">☰</button>
        <div class="customer-logo-container">
            <div class="customer-logo-icon">
                🍟
            </div>
            <div class="customer-logo-text">
                <div class="customer-company-name">Thanks G Its Fries Day</div>
                <div class="customer-company-tagline">Customer Portal</div>
            </div>
        </div>
        <div class="customer-user-info">
            <span class="welcome-text">Welcome back,</span>
            <div class="customer-user-badge">
                <span style="color: var(--sky-blue);">👤</span>
                <span>{{ Auth::user()->name ?? 'Customer' }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="customer-logout-btn">
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Customer Sidebar -->
<aside class="customer-sidebar" id="customerSidebar">
    <h3>🏠 My Account</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.dashboard') }}" class="customer-nav-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
                    <span class="customer-icon">📊</span>
                    <span class="customer-nav-text">Dashboard</span>
                    <span class="customer-tooltip">Dashboard</span>
                    <span class="customer-badge" style="display: {{ request()->routeIs('customer.dashboard') ? 'block' : 'none' }};">Home</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="section-divider"></div>
    
    <h3>🛍️ Shopping</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.products.index') }}" class="customer-nav-item {{ request()->routeIs('customer.products.*') ? 'active' : '' }}">
                    <span class="customer-icon">📱</span>
                    <span class="customer-nav-text">Browse Products</span>
                    <span class="customer-tooltip">Browse Products</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.cart.index') }}" class="customer-nav-item {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}">
                    <span class="customer-icon">🛒</span>
                    <span class="customer-nav-text">My Cart</span>
                    <span class="customer-tooltip">Shopping Cart</span>
                    <span class="order-notification">3</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.wishlist.index') }}" class="customer-nav-item {{ request()->routeIs('customer.wishlist.*') ? 'active' : '' }}">
                    <span class="customer-icon">❤️</span>
                    <span class="customer-nav-text">Wishlist</span>
                    <span class="customer-tooltip">My Wishlist</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="section-divider"></div>
    
    <h3>📦 Orders & Invoices</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.orders.index') }}" class="customer-nav-item {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
                    <span class="customer-icon">📋</span>
                    <span class="customer-nav-text">My Orders</span>
                    <span class="customer-tooltip">Order History</span>
                    <span class="order-notification">2</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.invoices.index') }}" class="customer-nav-item {{ request()->routeIs('customer.invoices.*') ? 'active' : '' }}">
                    <span class="customer-icon">🧾</span>
                    <span class="customer-nav-text">Invoices</span>
                    <span class="customer-tooltip">My Invoices</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.checkout.index') }}" class="customer-nav-item {{ request()->routeIs('customer.checkout.*') ? 'active' : '' }}">
                    <span class="customer-icon">💳</span>
                    <span class="customer-nav-text">Checkout</span>
                    <span class="customer-tooltip">Proceed to Checkout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="section-divider"></div>
    
    <h3>👤 Account</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.account.profile') }}" class="customer-nav-item {{ request()->routeIs('customer.account.*') ? 'active' : '' }}">
                    <span class="customer-icon">👤</span>
                    <span class="customer-nav-text">My Profile</span>
                    <span class="customer-tooltip">Profile Settings</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.account.addresses') }}" class="customer-nav-item {{ request()->routeIs('customer.account.*') ? 'active' : '' }}">
                    <span class="customer-icon">📍</span>
                    <span class="customer-nav-text">Addresses</span>
                    <span class="customer-tooltip">Shipping Addresses</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.account.security') }}" class="customer-nav-item {{ request()->routeIs('customer.account.*') ? 'active' : '' }}">
                    <span class="customer-icon">🔒</span>
                    <span class="customer-nav-text">Security</span>
                    <span class="customer-tooltip">Password & Security</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="section-divider"></div>
    
    <h3>💬 Support</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.support.tickets') }}" class="customer-nav-item {{ request()->routeIs('customer.support.*') ? 'active' : '' }}">
                    <span class="customer-icon">🎫</span>
                    <span class="customer-nav-text">Support Tickets</span>
                    <span class="customer-tooltip">Customer Support</span>
                    <span class="order-notification">1</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.support.faq') }}" class="customer-nav-item {{ request()->routeIs('customer.support.*') ? 'active' : '' }}">
                    <span class="customer-icon">❓</span>
                    <span class="customer-nav-text">FAQ</span>
                    <span class="customer-tooltip">Frequently Asked Questions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customer.support.contact') }}" class="customer-nav-item {{ request()->routeIs('customer.support.*') ? 'active' : '' }}">
                    <span class="customer-icon">📞</span>
                    <span class="customer-nav-text">Contact Us</span>
                    <span class="customer-tooltip">Get in Touch</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="section-divider"></div>
    
    <h3>⭐ Feedback</h3>
    <nav>
        <ul>
            <li>
                <a href="{{ route('customer.reviews.index') }}" class="customer-nav-item {{ request()->routeIs('customer.reviews.*') ? 'active' : '' }}">
                    <span class="customer-icon">⭐</span>
                    <span class="customer-nav-text">My Reviews</span>
                    <span class="customer-tooltip">Product Reviews</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Customer Content -->
<main class="customer-content" id="customerContent">
    {{ $slot }}
</main>

<!-- JavaScript for Interactions -->
<script>
    // Toggle mobile menu
    function toggleCustomerMobileMenu() {
        const sidebar = document.getElementById('customerSidebar');
        sidebar.classList.toggle('mobile-open');
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('customerSidebar');
        const menuToggle = document.querySelector('.customer-menu-toggle');
        
        if (window.innerWidth <= 480 && 
            !sidebar.contains(event.target) && 
            !menuToggle.contains(event.target) && 
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    // Handle sidebar hover behavior
    const customerSidebar = document.getElementById('customerSidebar');
    const customerContent = document.getElementById('customerContent');
    
    customerSidebar.addEventListener('mouseenter', function() {
        if (window.innerWidth > 768) {
            this.style.width = '220px';
            customerContent.style.marginLeft = '220px';
        }
    });
    
    customerSidebar.addEventListener('mouseleave', function() {
        if (window.innerWidth > 768) {
            this.style.width = '65px';
            customerContent.style.marginLeft = '65px';
        }
    });
    
    // Update active state
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.customer-nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                item.classList.add('active');
                const badge = item.querySelector('.customer-badge');
                if (badge) {
                    badge.style.display = 'block';
                }
            }
        });
        
        // Auto-close mobile menu on item click
        if (window.innerWidth <= 480) {
            document.querySelectorAll('.customer-nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    customerSidebar.classList.remove('mobile-open');
                });
            });
        }
        
        // Add notification counts (these would come from your backend)
        updateNotificationCounts();
    });
    
    // Simulate notification updates
    function updateNotificationCounts() {
        // In a real app, you would fetch these from your API
        const notifications = {
            cart: 3,
            orders: 2,
            tickets: 1
        };
        
        // Update cart notification
        const cartNotification = document.querySelector('a[href*="cart"] .order-notification');
        if (cartNotification) {
            cartNotification.textContent = notifications.cart;
        }
        
        // Update orders notification
        const ordersNotification = document.querySelector('a[href*="orders"] .order-notification');
        if (ordersNotification) {
            ordersNotification.textContent = notifications.orders;
        }
        
        // Update tickets notification
        const ticketsNotification = document.querySelector('a[href*="tickets"] .order-notification');
        if (ticketsNotification) {
            ticketsNotification.textContent = notifications.tickets;
        }
    }
</script>

</body>
</html>