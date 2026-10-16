@php
    $navigation = collect(config('erp.navigation'));
    if (auth()->user()?->role !== 'admin') {
        $navigation = collect([
            ['label' => 'Overview', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'house', 'group' => 'Workspace'],
            ['label' => 'My account', 'route' => 'profile.edit', 'pattern' => 'profile.*', 'icon' => 'user', 'group' => 'Workspace'],
        ]);
    }
    $current = $navigation->first(fn ($item) => request()->routeIs($item['pattern']));
    $section = explode('.', request()->route()?->getName() ?? '')[0];
    $sectionLinks = config('erp.sections.'.$section, []);
    if (auth()->user()?->role === 'admin') {
        $access = app(\App\Services\AccessControl::class);
        $navigation = $navigation->filter(fn ($item) => !$access->module($item['route']) || $access->allows(auth()->user(), $access->module($item['route'])));
        $sectionLinks = array_filter($sectionLinks, fn ($route) => !$access->module($route) || $access->allows(auth()->user(), $access->module($route)));
        $current ??= $navigation->first(fn ($item) => $access->module($item['route']) === $access->module(request()->route()?->getName() ?? ''));
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => ($title ?? $current['label'] ?? 'Workspace').' · TGIF'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('styles')
</head>
<body class="erp-app {{ auth()->user()?->role === 'admin' ? 'erp-admin' : '' }}" x-data="{ menuOpen: false, navQuery: '', isDesktop: window.innerWidth > 800 }" @resize.window="isDesktop = window.innerWidth > 800; if (isDesktop) menuOpen = false" @keydown.escape.window="menuOpen = false">
    <a href="#workspace" class="erp-skip">Skip to content</a>
    <button class="erp-backdrop" x-show="menuOpen" x-cloak @click="menuOpen = false" aria-label="Close navigation"></button>
    <aside id="erp-navigation" class="erp-sidebar" :class="{ 'is-open': menuOpen }" :inert="!isDesktop && !menuOpen" x-trap.inert.noscroll="menuOpen && !isDesktop" aria-label="Main navigation">
        <button class="erp-mobile-close" type="button" @click="menuOpen = false" aria-label="Close navigation">✕</button>
        <a href="{{ route(auth()->user()?->role === 'admin' ? app(\App\Services\AccessControl::class)->home(auth()->user()) : 'dashboard') }}" class="erp-brand"><span class="erp-brand-mark"><img src="{{ asset('TGIF.png') }}" alt="" width="44" height="44"></span><span>TGIF<span class="erp-brand-caption">Business workspace</span></span></a>
        @if(auth()->user()?->role === 'admin')
        <div class="erp-navigation-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" x-model="navQuery" placeholder="Find a department…" aria-label="Find a department" autocomplete="off"><button type="button" x-show="navQuery" x-cloak @click="navQuery = ''" aria-label="Clear department search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
        @endif
        <nav>
            @foreach($navigation->groupBy('group') as $group => $items)
                <div x-show="!navQuery.trim() || {{ \Illuminate\Support\Js::from($items->pluck('label')->values()->all()) }}.some(label => label.toLowerCase().includes(navQuery.trim().toLowerCase()))">
                <p class="erp-nav-heading">{{ $group }}</p>
                @foreach($items as $item)
                    <a href="{{ route($item['route']) }}" x-show="!navQuery.trim() || {{ \Illuminate\Support\Js::from($item['label']) }}.toLowerCase().includes(navQuery.trim().toLowerCase())" class="erp-nav-link {{ ($current['route'] ?? null) === $item['route'] ? 'is-active' : '' }}" @if(($current['route'] ?? null) === $item['route']) aria-current="page" @endif>
                        <i class="fa-solid fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span><i class="fa-solid fa-chevron-right erp-nav-chevron" aria-hidden="true"></i>
                    </a>
                @endforeach
                </div>
            @endforeach
            <p class="erp-navigation-empty" x-show="navQuery.trim() && !{{ \Illuminate\Support\Js::from($navigation->pluck('label')->values()->all()) }}.some(label => label.toLowerCase().includes(navQuery.trim().toLowerCase()))" x-cloak>No matching department.<br><button type="button" @click="navQuery = ''">Show all departments</button></p>
        </nav>
        <a href="{{ route('profile.edit') }}" class="erp-sidebar-footer"><span class="erp-avatar">{{ auth()->user()?->initials() ?? 'TG' }}</span><div><strong>{{ auth()->user()?->full_name ?? 'Workspace' }}</strong><small>{{ ucfirst(auth()->user()?->role ?? '') }} account</small></div><i class="fa-solid fa-gear" aria-hidden="true"></i></a>
    </aside>
    <div class="erp-workspace">
        <header class="erp-topbar">
            <div class="erp-topbar-title"><button type="button" class="erp-menu-button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="erp-navigation" aria-label="Toggle navigation"><i class="fa-solid fa-bars" aria-hidden="true"></i></button><span class="erp-breadcrumb">Workspace <span>/</span> <strong>{{ $current['label'] ?? 'Account settings' }}</strong></span></div>
            <div class="erp-topbar-actions">@if(auth()->user()?->role === 'admin')<a href="{{ route('landing') }}" class="erp-store-link"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View store</a>@else<a href="{{ route('profile.edit') }}" class="erp-quiet-link">My account</a>@endif<form method="POST" action="{{ route(auth()->user()?->role === 'admin' ? 'admin.logout' : 'logout') }}">@csrf<button class="erp-quiet-link" type="submit">Sign out <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></button></form></div>
        </header>
        @if($sectionLinks)
            <nav class="erp-section-nav" aria-label="Department navigation">@foreach($sectionLinks as $label => $route)<a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'is-active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        @endif
        <main id="workspace" class="erp-content" tabindex="-1">{{ $slot }}</main>
        <footer class="erp-footer"><span>TGIF <span aria-hidden="true">/</span> Business workspace</span><span>Thanks G. It’s Fries Day.</span></footer>
    </div>
    @stack('scripts')
    @fluxScripts
</body>
</html>
