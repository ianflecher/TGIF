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
<body class="erp-app" x-data="{ menuOpen: false, isDesktop: window.innerWidth > 800 }" @resize.window="isDesktop = window.innerWidth > 800; if (isDesktop) menuOpen = false" @keydown.escape.window="menuOpen = false">
    <a href="#workspace" class="erp-skip">Skip to content</a>
    <button class="erp-backdrop" x-show="menuOpen" x-cloak @click="menuOpen = false" aria-label="Close navigation"></button>
    <aside id="erp-navigation" class="erp-sidebar" :class="{ 'is-open': menuOpen }" :inert="!isDesktop && !menuOpen" x-trap.inert.noscroll="menuOpen && !isDesktop" aria-label="Main navigation">
        <button class="erp-mobile-close" type="button" @click="menuOpen = false" aria-label="Close navigation">✕</button>
        <a href="{{ route(auth()->user()?->role === 'admin' ? 'admin.dashboard' : 'dashboard') }}" class="erp-brand"><span class="erp-brand-mark">T</span><span>TGIF<span class="erp-brand-caption">Business workspace</span></span></a>
        <nav>
            @foreach($navigation->groupBy('group') as $group => $items)
                <p class="erp-nav-heading">{{ $group }}</p>
                @foreach($items as $item)
                    <a href="{{ route($item['route']) }}" class="erp-nav-link {{ request()->routeIs($item['pattern']) ? 'is-active' : '' }}" @if(request()->routeIs($item['pattern'])) aria-current="page" @endif>
                        <i class="fa-solid fa-{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="erp-sidebar-footer"><span class="erp-avatar">{{ auth()->user()?->initials() ?? 'TG' }}</span><div><strong>{{ auth()->user()?->full_name ?? 'Workspace' }}</strong><small>{{ ucfirst(auth()->user()?->role ?? '') }}</small></div></div>
    </aside>
    <div class="erp-workspace">
        <header class="erp-topbar">
            <div class="erp-topbar-title"><button type="button" class="erp-menu-button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="erp-navigation" aria-label="Toggle navigation"><i class="fa-solid fa-bars" aria-hidden="true"></i></button><span class="erp-breadcrumb">Workspace <span>/</span> <strong>{{ $current['label'] ?? 'Account settings' }}</strong></span></div>
            <div class="erp-topbar-actions"><a href="{{ route('profile.edit') }}" class="erp-quiet-link">My account</a><form method="POST" action="{{ route(auth()->user()?->role === 'admin' ? 'admin.logout' : 'logout') }}">@csrf<button class="erp-quiet-link" type="submit">Sign out <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></button></form></div>
        </header>
        @if($sectionLinks)
            <nav class="erp-section-nav" aria-label="Department navigation">@foreach($sectionLinks as $label => $route)<a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'is-active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        @endif
        <main id="workspace" class="erp-content" tabindex="-1">{{ $slot }}</main>
        <footer class="erp-footer">TGIF · Business operations</footer>
    </div>
    @stack('scripts')
    @fluxScripts
</body>
</html>
