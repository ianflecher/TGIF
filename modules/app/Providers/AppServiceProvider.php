<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Recheck the originating portal on every Livewire action, including
        // sessions whose role or employment status changed after page load.
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\EnsurePortalAccess::class]);
        foreach (['mount', 'hydrate'] as $event) {
            \Livewire\on($event, function ($component) use ($event) {
                $access = app(\App\Services\AccessControl::class);
                if ($module = $access->module($component->getName())) $access->authorize(auth()->user(), $module);
                if (str_starts_with($component->getName(), 'employee.')) {
                    $user = auth()->user();
                    if ($component->getName() === 'employee.warehouse' && $user?->role === 'admin') return;
                    abort_unless($user && in_array($user->role, ['employee', 'manager'], true) && !$user->trashed(), 403);
                    $id = \Illuminate\Support\Facades\DB::table('employees')->where('user_id', $user->getKey())->where('status', 'active')->value('employee_id');
                    abort_unless($id, 403);
                    if ($event === 'hydrate') {
                        if (isset($component->employee)) abort_unless((int) $component->employee->employee_id === (int) $id, 403);
                        foreach (['employeeId', 'agentId'] as $property) if (isset($component->$property)) abort_unless((int) $component->$property === (int) $id, 403);
                    }
                }
            });
        }
        \Livewire\on('call', function ($component, $method) {
            $access = app(\App\Services\AccessControl::class);
            if (!$module = $access->module($component->getName())) return;
            $readActions = ['$refresh', 'viewOrder', 'selectInvoice', 'selectJournal', 'selectOrder', 'downloadDocument', 'viewDocuments', 'closeOrderView', 'clearFilters', 'setPage', 'nextPage', 'previousPage', 'gotoPage', 'viewOrderDetails', 'showJournalEntryDetails', 'closeModal', 'generateFinancialStatement', 'generateTrialBalance', 'downloadCSV', 'downloadPDF'];
            $ability = in_array($method, $readActions, true) ? 'read' : (preg_match('/^(approve|post|reject|reverse|closePeriod)/i', $method) ? 'approve' : 'write');
            $access->authorize(auth()->user(), $module, $ability);
        });
        \Livewire\on('update', function ($component, $property) {
            $access = app(\App\Services\AccessControl::class);
            if ($module = $access->module($component->getName())) $access->authorize(auth()->user(), $module, in_array($property, ['search', 'perPage', 'paginators.page'], true) ? 'read' : 'write');
        });
    }
}
