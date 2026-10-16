<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => ($title ?? $department['label']).' · TGIF'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('styles')
    <style>
        .department-app{margin:0;background:#f4f7f5;color:#19362b;font-family:'Instrument Sans',sans-serif}
        .department-header{background:white;border-bottom:1px solid #dde7df;padding:18px clamp(18px,4vw,64px);display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
        .department-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;font-weight:600;font-size:19px}.department-brand img{width:46px;height:46px;object-fit:contain}.department-brand small{display:block;font-size:12px;color:#64796e;font-weight:400}
        .department-account{display:flex;gap:18px;align-items:center;flex-wrap:wrap;font-size:13px}.department-account a,.department-account button{color:#315a47;text-decoration:none;cursor:pointer}.department-account form{margin:0}.department-account button{background:transparent;border:0;font:inherit}
        .department-intro{max-width:1500px;margin:auto;padding:30px clamp(18px,4vw,64px) 24px;display:flex;gap:18px;align-items:center}.department-emblem{width:52px;height:52px;display:grid;place-items:center;border-radius:16px;background:#e2eddc;color:#24543b;font-size:22px}.department-intro h1{font-size:25px;font-weight:600;margin:0 0 4px}.department-intro p{margin:0;color:#64796e;font-size:14px}
        .department-tabs{background:#fff;border-block:1px solid #dde7df;display:flex;gap:6px;padding:0 clamp(18px,4vw,64px);overflow-x:auto}.department-tabs a{padding:16px 18px;white-space:nowrap;color:#64796e;text-decoration:none;border-bottom:3px solid transparent;font-size:14px}.department-tabs a[aria-current]{color:#194b32;border-color:#2d704b;font-weight:600}
        .department-content{max-width:1500px;margin:0 auto;padding:28px clamp(18px,4vw,64px);min-height:60vh}.department-content>div{max-width:100%}.department-footer{max-width:1500px;margin:auto;padding:22px clamp(18px,4vw,64px);display:flex;justify-content:space-between;gap:16px;color:#718278;font-size:12px}
        @media(max-width:600px){.department-account{gap:12px}.department-user{display:none}.department-intro{padding-block:22px}.department-intro h1{font-size:22px}.department-intro p{font-size:12px}.department-content{padding:20px 14px}.department-footer{flex-wrap:wrap}}
    </style>
</head>
<body class="department-app erp-app" data-portal="{{ $section }}">
    <a href="#workspace" class="erp-skip">Skip to content</a>
    <header class="department-header">
        <a href="{{ route($department['home']) }}" class="department-brand"><img src="{{ asset('TGIF.png') }}" alt="TGIF"><span>{{ $department['label'] }} portal<small>TGIF · Department workspace</small></span></a>
        <div class="department-account">
            <span class="department-user">{{ auth()->user()->full_name }}</span>
            <a href="{{ route('portals.index') }}">My portals</a>
            @if($access->isActiveEmployee(auth()->user()))<a href="{{ route('employee.dashboard') }}">My employee portal</a>@endif
            <a href="{{ route('profile.edit') }}">My account</a>
            <form method="POST" action="{{ route('department.logout') }}">@csrf<input type="hidden" name="department" value="{{ $section }}"><button type="submit">Sign out</button></form>
        </div>
    </header>
    <section class="department-intro" aria-label="Department">
        <span class="department-emblem"><i class="fa-solid fa-{{ $department['icon'] }}" aria-hidden="true"></i></span>
        <div><h1>{{ $department['label'] }}</h1><p>{{ $department['description'] }}</p></div>
    </section>
    <nav class="department-tabs" aria-label="Department navigation">
        @foreach($sectionLinks ?: ['Overview' => $department['home']] as $label => $route)
            <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <main id="workspace" class="department-content" tabindex="-1">{{ $slot }}</main>
    <footer class="department-footer"><span>TGIF · {{ $department['label'] }} portal</span><span>Thanks G. It’s Fries Day.</span></footer>
    @stack('scripts')
    @fluxScripts
</body>
</html>
