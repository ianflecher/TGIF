<!DOCTYPE html>
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.tailwindcss.com"></script>
        
        <!-- Add custom green theme colors -->
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
            }
            
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
            
            .sidebar {
                background: linear-gradient(180deg, var(--forest-green) 0%, #0f4223 100%);
                color: white;
                width: 65px; /* Compact sidebar */
                height: calc(100vh - 60px); /* Full height */
                position: fixed;
                left: 0;
                top: 60px; /* Below header */
                overflow-y: auto;
                overflow-x: hidden;
                padding: 15px 0;
                box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 999;
                scrollbar-width: thin;
                scrollbar-color: var(--primary-green) rgba(255, 255, 255, 0.05);
            }
            
            .sidebar:hover {
                width: 220px; /* Expand on hover */
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
            }
            
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
                margin-left: 220px; /* Expand when sidebar expands */
            }
            
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
            
            .status-badge {
                background: var(--primary-green);
                color: white;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 0.7rem;
                margin-left: auto;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .sidebar:hover .status-badge {
                opacity: 1;
            }
            
            /* Compact header content */
            .header-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }
            
            .logo-container {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .logo-icon {
                background: transparent; /* Remove white background */
                padding: 0; /* Remove padding */
                border-radius: 0; /* Remove border radius */
                color: var(--dark-green);
                font-size: 1.4rem;
            }
            
            .logo-text {
                display: flex;
                flex-direction: column;
            }
            
            .company-name {
                font-size: 1.2rem;
                font-weight: 700;
                letter-spacing: 0.3px;
            }
            
            .company-tagline {
                font-size: 0.75rem;
                opacity: 0.9;
                color: var(--mint-green);
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
            
            /* Collapsible category styling */
            .category-item {
                margin-bottom: 15px;
            }
            
            .category-header {
                display: flex;
                align-items: center;
                padding: 10px 15px;
                color: var(--mint-green);
                font-weight: 600;
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                opacity: 0;
                white-space: nowrap;
                cursor: pointer;
                transition: all 0.3s ease;
                border-radius: 6px;
                margin: 0 5px;
                position: relative;
            }
            
            .sidebar:hover .category-header {
                opacity: 1;
            }
            
            .category-header:hover {
                background: rgba(255, 255, 255, 0.05);
            }
            
            .category-header .module-icon {
                margin-right: 12px;
                font-size: 1rem;
                min-width: 20px;
            }
            
            .category-text {
                opacity: 0;
                transition: opacity 0.3s ease;
                flex-grow: 1;
            }
            
            .sidebar:hover .category-text {
                opacity: 1;
            }
            
            .category-arrow {
                opacity: 0;
                transition: opacity 0.3s ease, transform 0.3s ease;
                font-size: 0.8rem;
                margin-left: 5px;
            }
            
            .sidebar:hover .category-arrow {
                opacity: 0.7;
            }
            
            .category-header.active .category-arrow {
                transform: rotate(90deg);
            }
            
            .submenu {
                list-style: none;
                padding: 0;
                margin: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
                opacity: 0;
            }
            
            .sidebar:hover .submenu {
                opacity: 1;
            }
            
            .category-header.active + .submenu,
            .submenu.expanded {
                max-height: 500px;
                opacity: 1;
            }
            
            .submenu .nav-item {
                padding: 10px 15px 10px 35px; /* Indent submenu items */
                margin: 2px 5px;
                font-size: 0.85rem;
            }
            
            .submenu .module-icon {
                font-size: 1rem;
                min-width: 20px;
            }
            
            /* Remove tooltips from categorized items */
            .category-item .nav-tooltip {
                display: none;
            }
            
            /* Category tooltip for compact mode */
            .category-tooltip {
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
            
            .category-tooltip::before {
                content: '';
                position: absolute;
                right: 100%;
                top: 50%;
                transform: translateY(-50%);
                border-width: 6px;
                border-style: solid;
                border-color: transparent var(--forest-green) transparent transparent;
            }
            
            .category-header:hover .category-tooltip {
                opacity: 1;
                visibility: visible;
            }
            
            .sidebar:hover .category-tooltip {
                display: none;
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
            
            .sidebar::-webkit-scrollbar-thumb:hover {
                background: var(--dark-green);
            }
            
            /* Animation for active state */
            @keyframes gentle-pulse {
                0%, 100% { box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3); }
                50% { box-shadow: 0 2px 12px rgba(34, 197, 94, 0.5); }
            }
            
            .nav-item.active {
                animation: gentle-pulse 3s infinite;
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
            
            @media (max-width: 768px) {
                .sidebar {
                    width: 55px;
                }
                
                .sidebar:hover {
                    width: 200px;
                }
                
                .main-content {
                    margin-left: 55px;
                    padding: 1rem;
                }
                
                .sidebar:hover + .main-content {
                    margin-left: 200px;
                }
                
                .company-name {
                    font-size: 1rem;
                }
                
                .company-tagline {
                    display: none;
                }
                
                .user-badge span:last-child {
                    display: none;
                }
            }
            
            @media (max-width: 480px) {
                .menu-toggle {
                    display: block;
                }
                
                .sidebar {
                    transform: translateX(-100%);
                    width: 250px;
                }
                
                .sidebar.mobile-open {
                    transform: translateX(0);
                }
                
                .main-content {
                    margin-left: 0;
                }
                
                .sidebar:hover {
                    width: 250px;
                }
                
                .sidebar:hover + .main-content {
                    margin-left: 0;
                }
            }
            
            /* Responsive adjustments for categories */
            @media (max-width: 1024px) {
                .sidebar:hover {
                    width: 200px;
                }
                
                .sidebar:hover + .main-content {
                    margin-left: 200px;
                }
            }
        </style>
    </head>
    <body class="bg-gray-50">

    <!-- Main Header -->
    <header class="main-header">
        <div class="header-content">
            <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
            <div class="logo-container">
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
                    <div class="company-tagline">Admin Portal</div>
                </div>
            </div>
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

    <!-- Sidebar with Collapsible Categories -->
    <aside class="sidebar" id="sidebar">
        <h3>🌿 Modules</h3>
        <nav>
            <ul>
                <!-- Core Modules -->
                <li class="category-item">
                    <div class="category-header" onclick="toggleCategory(this)">
                        <span class="module-icon">⭐</span>
                        <span class="category-text">Core</span>
                        <span class="category-arrow">▶</span>
                        <span class="category-tooltip">Core Modules</span>
                    </div>
                    <ul class="submenu">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <span class="module-icon">📊</span>
                                <span class="nav-text">Dashboard</span>
                                <span class="nav-tooltip">Dashboard</span>
                                <span class="status-badge" style="display: {{ request()->routeIs('dashboard') ? 'block' : 'none' }};">Active</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('ecommerce.home') }}" class="nav-item {{ request()->routeIs('ecommerce.*') ? 'active' : '' }}">
                                <span class="module-icon">🛒</span>
                                <span class="nav-text">E-Commerce</span>
                                <span class="nav-tooltip">E-Commerce</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sales.home') }}" class="nav-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
                                <span class="module-icon">📞</span>
                                <span class="nav-text">Sales</span>
                                <span class="nav-tooltip">Sales & CRM</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Operations Modules -->
                <li class="category-item">
                    <div class="category-header" onclick="toggleCategory(this)">
                        <span class="module-icon">⚙️</span>
                        <span class="category-text">Operations</span>
                        <span class="category-arrow">▶</span>
                        <span class="category-tooltip">Operations</span>
                    </div>
                    <ul class="submenu">
                        <li>
                            <a href="{{ route('inventory.home') }}" class="nav-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                                <span class="module-icon">🏭</span>
                                <span class="nav-text">Inventory</span>
                                <span class="nav-tooltip">Inventory & Warehouse</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('procurement.home') }}" class="nav-item {{ request()->routeIs('procurement.*') ? 'active' : '' }}">
                                <span class="module-icon">📦</span>
                                <span class="nav-text">Procurement</span>
                                <span class="nav-tooltip">Procurement</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('supplychain.home') }}" class="nav-item {{ request()->routeIs('supplychain.*') ? 'active' : '' }}">
                                <span class="module-icon">🔄</span>
                                <span class="nav-text">Supply Chain</span>
                                <span class="nav-tooltip">Supply Chain</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Support Modules -->
                <li class="category-item">
                    <div class="category-header" onclick="toggleCategory(this)">
                        <span class="module-icon">💼</span>
                        <span class="category-text">Support</span>
                        <span class="category-arrow">▶</span>
                        <span class="category-tooltip">Support</span>
                    </div>
                    <ul class="submenu expanded"> <!-- Expanded by default -->
                        <li>
                            <a href="{{ route('customerservice.home') }}" class="nav-item {{ request()->routeIs('customerservice.*') ? 'active' : '' }}">
                                <span class="module-icon">💬</span>
                                <span class="nav-text">Customer Service</span>
                                <span class="nav-tooltip">Customer Service</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('finance.home') }}" class="nav-item {{ request()->routeIs('finance.*') ? 'active' : '' }}">
                                <span class="module-icon">💰</span>
                                <span class="nav-text">Finance</span>
                                <span class="nav-tooltip">Finance & Accounting</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('hr.home') }}" class="nav-item {{ request()->routeIs('hr.*') ? 'active' : '' }}">
                                <span class="module-icon">👥</span>
                                <span class="nav-text">Human Resources</span>
                                <span class="nav-tooltip">Human Resources</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Project & Analytics -->
                <li class="category-item">
                    <div class="category-header" onclick="toggleCategory(this)">
                        <span class="module-icon">📈</span>
                        <span class="category-text">Analytics</span>
                        <span class="category-arrow">▶</span>
                        <span class="category-tooltip">Analytics</span>
                    </div>
                    <ul class="submenu">
                        <li>
                            <a href="{{ route('projects.home') }}" class="nav-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                                <span class="module-icon">📋</span>
                                <span class="nav-text">Projects</span>
                                <span class="nav-tooltip">Project Management</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reports.home') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                <span class="module-icon">📈</span>
                                <span class="nav-text">Reports</span>
                                <span class="nav-tooltip">Reports & BI</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        {{ $slot }}
    </main>

    <script>
    // Toggle mobile menu
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }
    
    // Simple category toggle
    function toggleCategory(categoryHeader) {
        const submenu = categoryHeader.nextElementSibling;
        const arrow = categoryHeader.querySelector('.category-arrow');
        
        // Toggle active state
        categoryHeader.classList.toggle('active');
        
        // Toggle submenu
        if (submenu.classList.contains('expanded')) {
            submenu.classList.remove('expanded');
            submenu.style.maxHeight = '0';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        } else {
            submenu.classList.add('expanded');
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
            if (arrow) arrow.style.transform = 'rotate(90deg)';
        }
    }
    
    // Initialize sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        // Set active nav items
        const currentPath = window.location.pathname;
        document.querySelectorAll('.nav-item').forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href.replace(/\/$/, '')) && href !== '/') {
                item.classList.add('active');
                
                // Expand parent category
                const categoryItem = item.closest('.category-item');
                if (categoryItem) {
                    const categoryHeader = categoryItem.querySelector('.category-header');
                    const submenu = categoryItem.querySelector('.submenu');
                    if (categoryHeader && submenu) {
                        categoryHeader.classList.add('active');
                        submenu.classList.add('expanded');
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                        
                        // Rotate arrow
                        const arrow = categoryHeader.querySelector('.category-arrow');
                        if (arrow) arrow.style.transform = 'rotate(90deg)';
                    }
                }
            }
        });
        
        // Expand Support category by default
        const supportCategory = document.querySelector('.category-item:nth-child(3)');
        if (supportCategory) {
            const supportHeader = supportCategory.querySelector('.category-header');
            const supportSubmenu = supportCategory.querySelector('.submenu');
            if (supportHeader && supportSubmenu) {
                supportHeader.classList.add('active');
                supportSubmenu.classList.add('expanded');
                supportSubmenu.style.maxHeight = supportSubmenu.scrollHeight + 'px';
                
                const arrow = supportHeader.querySelector('.category-arrow');
                if (arrow) arrow.style.transform = 'rotate(90deg)';
            }
        }
        
        // Desktop hover behavior
        if (window.innerWidth > 768) {
            sidebar.addEventListener('mouseenter', function() {
                this.style.width = '220px';
                mainContent.style.marginLeft = '220px';
            });
            
            sidebar.addEventListener('mouseleave', function() {
                this.style.width = '65px';
                mainContent.style.marginLeft = '65px';
            });
        }
        
        // Mobile menu close on click
        if (window.innerWidth <= 480) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                });
            });
        }
    });
    
    // Close mobile menu when clicking outside
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
</script>

    </body>
    </html>