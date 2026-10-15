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
<div>
    <div class="erp-page-heading">
        <div><p class="erp-eyebrow">Your business at a glance</p><h1>Workspace overview</h1><p>Orders, people and inventory — the details that need your attention.</p></div>
        <a href="{{ route('reports.home') }}" class="erp-button secondary"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i> View reports</a>
    </div>
    <div class="erp-metrics">
        @foreach([
            ['Delivered sales', '₱'.number_format($metrics['sales'], 2), 'All-time delivered order value', 'peso-sign'],
            ['Orders in progress', number_format($metrics['orders']), 'Draft through shipped', 'basket-shopping'],
            ['Active projects', number_format($metrics['projects']), 'Projects currently in progress', 'diagram-project'],
            ['Active employees', number_format($metrics['employees']), 'Current active employee records', 'users'],
        ] as [$label, $value, $note, $icon])
            <div class="erp-metric"><div class="erp-metric-label">{{ $label }}<i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i></div><div class="erp-metric-value">{{ $value }}</div><div class="erp-metric-note">{{ $note }}</div></div>
        @endforeach
    </div>
    <div class="erp-grid">
        <div>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div><h2>Recent orders</h2><p>The latest customer activity</p></div><a class="erp-text-link" href="{{ route('ecommerce.orders') }}">View all orders →</a></div>
                <div class="erp-table-wrap"><table class="erp-table"><thead><tr><th>Order / customer</th><th>Status</th><th class="numeric">Total</th></tr></thead><tbody>
                    @forelse($orders as $order)
                        <tr><td><a href="{{ route('ecommerce.orders', ['order' => $order->order_id]) }}" class="erp-text-link">{{ $order->order_number }}</a><div style="font-size:11px;color:#8b998e;margin-top:5px">{{ trim($order->first_name.' '.$order->last_name) ?: 'Customer unavailable' }}</div></td><td><span class="erp-badge {{ $order->status === 'delivered' ? 'good' : ($order->status === 'cancelled' ? 'danger' : 'warning') }}">{{ str_replace('_', ' ', $order->status) }}</span></td><td class="numeric">₱{{ number_format($order->grand_total, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3"><div class="erp-empty"><strong>No orders yet</strong>New customer orders will appear here.</div></td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div><h2>Project progress</h2><p>Average task completion across each project's phases</p></div><a class="erp-text-link" href="{{ route('projects.home') }}">Open projects →</a></div>
                @forelse($projects as $project)
                    <a class="erp-attention" href="{{ route('projects.phase', $project->project_id) }}"><div style="flex:1;min-width:0"><strong>{{ $project->project_name }}</strong><small>{{ ucfirst(str_replace('_', ' ', $project->status)) }} · {{ round($project->avg_progress ?? 0) }}% complete</small><div class="erp-progress" role="progressbar" aria-label="{{ $project->project_name }} progress" aria-valuenow="{{ round($project->avg_progress ?? 0) }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ max(0, min(100, $project->avg_progress ?? 0)) }}%"></span></div></div><span aria-hidden="true">↗</span></a>
                @empty
                    <div class="erp-empty"><strong>A clear view of your projects</strong>Projects and their task progress will appear here.</div>
                @endforelse
            </section>
        </div>
        <div>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div><h2>Needs attention</h2><p>Open work across your departments</p></div></div>
                @foreach([
                    ['Stock at reorder level', 'Review quantities and replenishment', 'boxes-stacked', 'inventory.stock', $metrics['low_stock']],
                    ['Open support tickets', 'Help customers and clear requests', 'headset', 'customerservice.home', $metrics['tickets']],
                    ['Submitted requisitions', 'Review purchasing requests', 'clipboard-check', 'procurement.home', $metrics['approvals']],
                ] as [$label, $description, $icon, $route, $count])
                    <a href="{{ route($route) }}" class="erp-attention"><i class="fa-solid fa-{{ $icon }}" aria-hidden="true"></i><div><strong>{{ $label }}</strong><small>{{ $description }}</small></div><span class="erp-count">{{ $count }}</span></a>
                @endforeach
            </section>
            <section class="erp-panel">
                <div class="erp-panel-heading"><div><h2>Replenishment watch</h2><p>Items at or below their minimum quantity</p></div><a href="{{ route('inventory.stock') }}" class="erp-text-link">View stock →</a></div>
                @forelse($lowStock as $item)
                    <div class="erp-attention"><div style="flex:1"><strong>{{ $item->product_name }}</strong><small>{{ $item->sku }} · Minimum {{ $item->min_quantity ?? 10 }}</small></div><span class="erp-badge {{ $item->quantity <= 0 ? 'danger' : 'warning' }}">{{ $item->quantity }} left</span></div>
                @empty
                    <div class="erp-empty"><strong>No replenishment alerts</strong>No inventory items are currently at their reorder level.</div>
                @endforelse
            </section>
        </div>
    </div>
</div>
