<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - @yield('title')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Potato CSS -->
    <link rel="stylesheet" href="{{ asset('css/potato.css') }}">
</head>
<body class="potato-body">
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-title">Inventory</div>
            <ul class="sidebar-menu">
                  
                  <li>  <a href="{{ route('inventories.index') }}" class="nav-item {{ request()->routeIs('inventories.index') ? 'active' : '' }}">
                        <span class="icon"></span> Inventory
                    </a>
                </li>

                <li class="nav-item">
    <a href="{{ route('warehouses.manage') }}" class="nav-link">Manage Warehouses</a>
</li>
<li>
   

                
                <li>
                    <a href="{{ route('transactions.transactionHistory') }}" class="nav-item {{ request()->routeIs('transactions.transactionHistory') ? 'active' : '' }}">

                        <span class="icon"></span> Transaction History
                    </a>
                </li>
                <li>
                       <a href="{{ route('inventories.history') }}" class="nav-item {{ request()->routeIs('inventories.history') ? 'active' : '' }}">
                        <span class="icon"></span> History
                    </a>
                </li>
                <li>
                       <a href="{{ route('inventories.warehouselogs') }}" class="nav-item {{ request()->routeIs('inventories.warehouselogs') ? 'active' : '' }}">
                        <span class="icon"></span> warehouselogs
                    </a>
                </li>


 <li>
                       <a href="{{ route('inventories.purchase_order') }}" class="nav-item {{ request()->routeIs('inventories.purchase_order') ? 'active' : '' }}">
                        <span class="icon"></span> Purchase Order
                    </a>
                </li>

                 









            </ul>
        </aside>

        <!-- Main content -->
        <!-- Main content -->
<main class="main-content" style="margin-left: 200px; padding: 1rem;">
    @yield('content')
</main>

</body>

</html>
