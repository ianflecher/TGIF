<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Services\DashboardOverview;

new #[Layout('components.layouts.app')] class extends Component
{
    public function with(): array
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        return app(DashboardOverview::class)->data();
    }
};
?>
<div class="erp-overview">
    @php $can = fn ($module) => app(\App\Services\AccessControl::class)->allows(auth()->user(), $module); @endphp
    <div class="erp-page-heading">
        <div><p class="erp-eyebrow">Dashboard</p><h1>Workspace overview</h1><p>A clear view of your business, all in one place.</p></div>
        @if($can('reports'))<a href="{{ route('reports.home') }}" class="erp-button secondary"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> View reports <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endif
    </div>
    <section class="erp-overview-welcome" aria-label="Workspace shortcuts">
        <div class="erp-welcome-copy"><span class="erp-welcome-label"><span aria-hidden="true"></span> YOUR DAILY WORKSPACE</span><h2>Good food. Smooth operations.</h2><p>Keep orders moving, stock ready and your team in sync.</p><div class="erp-welcome-actions">
            @if($can('ecommerce'))<a href="{{ route('ecommerce.orders') }}" class="erp-button">Manage orders <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif
            @if($can('inventory'))<a href="{{ route('inventory.stock') }}" class="erp-welcome-link">Review inventory <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endif
        </div></div>
        <div class="erp-welcome-art" aria-hidden="true"><span class="erp-art-orbit"></span><div class="erp-art-ticket"><span class="erp-art-ticket-icon"><i class="fa-solid fa-bag-shopping"></i></span><span>Made to order.<strong>Ready for the day.</strong></span><i class="fa-solid fa-circle-check"></i></div><div class="erp-art-note"><i class="fa-solid fa-seedling"></i> A little more organized.</div></div>
    </section>
    <div class="erp-overview-section-label"><h2>Business snapshot</h2><span>Current records · Delivered sales are all-time</span></div>
    <div class="erp-metrics">
        @foreach([
            ['Delivered sales', '₱'.number_format($metrics['sales'], 2), 'All-time delivered order value', 'peso-sign'],
            ['Orders in progress', number_format($metrics['orders']), 'Draft through shipped', 'basket-shopping'],
            ['Active projects', number_format($metrics['projects']), 'Projects currently in progress', 'diagram-project'],
            ['Active employees', number_format($metrics['employees']), 'Current active employee records', 'users'],
        ] as [$label, $value, $note, $icon])
        <div class="erp-metric"><div class="erp-metric-label"><span>{{ $label }}</span><span class="erp-metric-icon"><i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i></span></div><div class="erp-metric-value">{{ $value }}</div><div class="erp-metric-note"><span class="erp-metric-dot" aria-hidden="true"></span>{{ $note }}</div></div>
        @endforeach
    </div>
    <div class="erp-grid erp-overview-grid">
        <div>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div class="erp-panel-title"><span class="erp-heading-icon"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></span><div><h2>Recent orders</h2><p>Your latest customer activity</p></div></div>@if($can('ecommerce'))<a class="erp-text-link" href="{{ route('ecommerce.orders') }}">View all <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif</div>
                <div class="erp-table-wrap"><table class="erp-table"><thead><tr><th>Order / customer</th><th>Status</th><th class="numeric">Amount</th></tr></thead><tbody>
                @forelse($orders as $order)
                    <tr><td><div class="erp-order-identity"><span class="erp-order-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><div>@if($can('ecommerce'))<a href="{{ route('ecommerce.orders', ['order' => $order->order_id]) }}" class="erp-order-number">{{ $order->order_number }}</a>@else<strong>{{ $order->order_number }}</strong>@endif<small>{{ trim($order->first_name.' '.$order->last_name) ?: 'Customer unavailable' }}</small></div></div></td><td><span class="erp-badge {{ $order->status === 'delivered' ? 'good' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}"><span class="erp-status-dot" aria-hidden="true"></span>{{ str_replace('_', ' ', $order->status) }}</span></td><td class="numeric erp-order-amount">₱{{ number_format($order->grand_total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3"><div class="erp-empty"><span class="erp-empty-icon"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></span><strong>No orders yet</strong>New customer orders will appear here.</div></td></tr>
                @endforelse
                </tbody></table></div>
            </section>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div class="erp-panel-title"><span class="erp-heading-icon"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></span><div><h2>Project progress</h2><p>Average task completion by project</p></div></div>@if($can('projects'))<a class="erp-text-link" href="{{ route('projects.home') }}">All projects <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif</div>
                @forelse($projects as $project)
                    @php $progress = max(0, min(100, round($project->avg_progress ?? 0))); @endphp
                    <div class="erp-project-row"><span class="erp-project-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><div class="erp-project-copy">@if($can('projects'))<a href="{{ route('projects.phase', $project->project_id) }}">{{ $project->project_name }}</a>@else<strong>{{ $project->project_name }}</strong>@endif<small>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</small><div class="erp-progress" role="progressbar" aria-label="{{ $project->project_name }} progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $progress }}%"></span></div></div><span class="erp-project-percent">{{ $progress }}<small>%</small></span></div>
                @empty
                    <div class="erp-empty"><span class="erp-empty-icon"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><strong>A clear view of your projects</strong>Projects and their task progress will appear here.</div>
                @endforelse
            </section>
        </div>
        <div>
            <section class="erp-panel erp-attention-panel">
                <div class="erp-panel-heading"><div class="erp-panel-title"><span class="erp-heading-icon"><i class="fa-regular fa-bell" aria-hidden="true"></i></span><div><h2>Needs attention</h2><p>Your next priorities</p></div></div></div>
                @foreach([
                    ['Stock at reorder level', 'Review replenishment', 'boxes-stacked', 'inventory.stock', $metrics['low_stock'], 'inventory'],
                    ['Open support tickets', 'Help your customers', 'headset', 'customerservice.home', $metrics['tickets'], 'customerservice'],
                    ['Submitted requisitions', 'Review purchasing requests', 'clipboard-check', 'procurement.home', $metrics['approvals'], 'procurement'],
                ] as [$label, $description, $icon, $route, $count, $module])
                    @if($can($module))<a href="{{ route($route) }}" class="erp-attention"><i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i><div><strong>{{ $label }}</strong><small>{{ $description }}</small></div><span class="erp-attention-count {{ $count ? 'has-items' : '' }}">{{ number_format($count) }}</span><span class="erp-attention-arrow" aria-hidden="true">›</span></a>@endif
                @endforeach
                @if(!$can('inventory') && !$can('customerservice') && !$can('procurement'))<div class="erp-empty">Department priorities appear here when you have access.</div>@endif
            </section>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div class="erp-panel-title"><span class="erp-heading-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span><div><h2>Replenishment watch</h2><p>At or below minimum stock</p></div></div>@if($can('inventory'))<a href="{{ route('inventory.stock') }}" class="erp-text-link" aria-label="View stock"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endif</div>
                @forelse($lowStock as $item)
                    <div class="erp-attention"><span class="erp-stock-icon"><i class="fa-solid fa-cube" aria-hidden="true"></i></span><div class="erp-stock-copy"><strong>{{ $item->product_name }}</strong><small>{{ $item->sku }} · Min. {{ $item->min_quantity ?? 10 }}</small></div><span class="erp-badge {{ $item->quantity <= 0 ? 'danger' : 'warning' }}">{{ $item->quantity }} left</span></div>
                @empty
                    <div class="erp-empty"><span class="erp-empty-icon good"><i class="fa-solid fa-check" aria-hidden="true"></i></span><strong>No replenishment alerts</strong>No inventory items are currently at their reorder level.</div>
                @endforelse
            </section>
        </div>
    </div>
    @php $shortcuts = collect([
        ['procurement', 'procurement.createpo', 'Purchase orders', 'Plan your next replenishment', 'clipboard-list'],
        ['finance', 'finance.supplier-payables', 'Supplier payables', 'Invoices, credits & payments', 'file-invoice-dollar'],
        ['hr', 'hr.home', 'People & teams', 'Keep your team organized', 'user-group'],
        ['commerce', 'commerce.settings', 'Store settings', 'Manage your ordering experience', 'store'],
    ])->filter(fn ($item) => $can($item[0])); @endphp
    @if($shortcuts->isNotEmpty())
    <div class="erp-overview-section-label"><h2>Workspace shortcuts</h2><span>Pick up where you need to</span></div>
    <div class="erp-shortcuts">@foreach($shortcuts as [$module, $route, $label, $description, $icon])<a href="{{ route($route) }}" class="erp-shortcut"><span class="erp-shortcut-icon"><i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i></span><strong>{{ $label }}</strong><small>{{ $description }}</small><i class="fa-solid fa-arrow-up-right-from-square erp-shortcut-arrow" aria-hidden="true"></i></a>@endforeach</div>
    @endif
</div>
