<x-layouts.portal-auth>
    @php
        $access = app(\App\Services\AccessControl::class);
        $all = !auth()->check() || $access->isSuperAdmin(auth()->user());
        $available = collect(config('portals'))->filter(fn ($portal, $key) => $all || $access->allows(auth()->user(), $key));
        $personal = collect([['Applicant','applicant.login','Applications, documents and hiring status.'],['Employee','employee.login','Your tasks, attendance, leave and payslips.'],['Supplier','supplier.login','Your purchase orders, invoices and payments.'],['Customer','login','Your orders, payments and support.'],['Super admin','admin.login','All departments, permissions and administration.']])->filter(fn ($item) => $all || ($item[0] === 'Employee' && $access->isActiveEmployee(auth()->user())) || ($item[0] === 'Applicant' && $access->isApplicant(auth()->user())) || ($item[0] === 'Customer' && auth()->user()?->role === 'customer'));
    @endphp
    <header class="erp-portal-heading"><a href="{{ route('landing') }}" class="erp-text-link">← TGIF store</a><p class="erp-eyebrow">TGIF WORKSPACES</p><h1>Choose your portal</h1><p>A dedicated workspace for each department. Sign in with your assigned account.</p></header>
    <div class="erp-portal-grid">@forelse($available as $key => $portal)<a class="erp-portal-tile" href="{{ auth()->check() ? route($portal['home']) : route('portals.'.$key.'.login') }}"><span class="erp-portal-icon"><i class="fa-solid fa-{{ $portal['icon'] }}" aria-hidden="true"></i></span><h2>{{ $portal['label'] }}</h2><p>{{ $portal['description'] }}</p><span class="erp-text-link">Open portal →</span></a>@empty<p>No department access assigned. Use your personal portal or contact your administrator.</p>@endforelse</div>
    <h2 class="erp-portal-subtitle">Personal & partner portals</h2><div class="erp-portal-grid">@foreach($personal as [$label,$route,$description])<a href="{{ route($route) }}" class="erp-portal-tile"><h2>{{ $label }}</h2><p>{{ $description }}</p><span class="erp-text-link">Sign in →</span></a>@endforeach</div>
</x-layouts.portal-auth>
