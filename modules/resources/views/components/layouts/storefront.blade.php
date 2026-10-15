<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#234d32">
    <meta name="description" content="Discover TGIF's menu. Choose your favorites, customize your order and enjoy something delicious.">
    <title>{{ $title ?? 'TGIF · Good food. Great days.' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|outfit:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="sf-app">
    <a href="#storefront-content" class="sf-skip">Skip to menu</a>
    <div class="sf-announcement">A little crispy. A little happy. <span>A whole lot of TGIF.</span></div>
    <header class="sf-header">
        <div class="sf-header-inner">
            <a href="{{ route('landing') }}" class="sf-brand" aria-label="TGIF home"><span class="sf-logo" style="background-image:url('{{ asset('TGIF.png') }}')" role="img" aria-label="TGIF potato logo"></span><span><strong>TGIF<span class="sf-brand-dot">.</span></strong><small>THANKS G. IT'S FRIESDAY!</small></span></a>
            <nav class="sf-primary-nav" aria-label="Main navigation"><a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*', 'customer.products.*') ? 'active' : '' }}">Our menu</a><a href="{{ route('landing') }}#our-story">Our story</a></nav>
            <div class="sf-header-actions">@auth<details class="sf-account-menu"><summary class="sf-account" aria-label="My account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><span>My account ⌄</span></summary><div class="sf-account-dropdown"><a href="{{ route(auth()->user()->role === 'customer' ? 'customer.account.profile' : 'profile.edit') }}">My profile</a>@if(auth()->user()->role === 'customer')<a href="{{ route('customer.orders.index') }}">My orders</a>@else<a href="{{ route('dashboard') }}">My workspace</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div></details>@else<a class="sf-account" aria-label="Sign in" href="{{ route('login') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><span>Sign in</span></a>@endauth<livewire:storefront-bag /></div>
        </div>
    </header>
    <main id="storefront-content">{{ $slot }}</main>
    <footer class="sf-footer"><div class="sf-footer-top"><div><a href="{{ route('landing') }}" class="sf-footer-wordmark">TGIF<span>.</span></a><p>Good food. Great company.<br>Make every day a little more Friesday.</p></div><div><h2>Find your favorites</h2><a href="{{ route('products.index') }}">Explore the menu</a><a href="{{ route('customer.orders.index') }}">My orders</a></div><div><h2>Stay in the loop</h2><a href="{{ route('landing') }}#our-story">The TGIF story</a><a href="{{ route('customer.support.index') }}">Need a hand?</a></div><div class="sf-footer-note"><span>MADE FOR YOUR CRAVINGS</span><strong>See you on<br>the delicious side.</strong></div></div><div class="sf-footer-bottom"><span>© {{ date('Y') }} TGIF. All rights reserved.</span><a href="{{ route('admin.login') }}">Team portal ↗</a></div></footer>
    @stack('scripts')
    @fluxScripts
</body>
</html>
