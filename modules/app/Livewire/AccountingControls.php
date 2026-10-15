<?php
namespace App\Livewire;

use App\Services\AccessControl;
use App\Services\AccountingLedger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.finance')]
class AccountingControls extends Component
{
    #[Locked] public ?int $journalId = null;
    public string $start = ''; public string $end = ''; public string $reason = ''; public string $reversalDate = '';
    public function mount(): void { $this->reversalDate = now()->toDateString(); }
    public function selectJournal(int $id): void { app(AccessControl::class)->authorize(auth()->user(), 'finance'); abort_unless(DB::table('journal_entries')->where('journal_id', $id)->exists(), 404); $this->journalId = $id; $this->resetErrorBag(); }
    public function createPeriod(): void { app(AccountingLedger::class)->period(auth()->user(), $this->start, $this->end); session()->flash('message', 'Open financial period created.'); }
    public function closePeriod(int $id): void { app(AccountingLedger::class)->closePeriod(auth()->user(), $id); session()->flash('message', 'Financial period closed. Reversals use a new open period.'); }
    public function submit(): void { if ($this->journalId) app(AccountingLedger::class)->submit(auth()->user(), $this->journalId); }
    public function approve(): void { if ($this->journalId) app(AccountingLedger::class)->approve(auth()->user(), $this->journalId); }
    public function post(): void { if ($this->journalId) app(AccountingLedger::class)->post(auth()->user(), $this->journalId); }
    public function reverse(): void { if ($this->journalId) $this->journalId = app(AccountingLedger::class)->reverse(auth()->user(), $this->journalId, $this->reversalDate, $this->reason); }
    public function returnForReview(): void { if ($this->journalId) app(AccountingLedger::class)->returnForReview(auth()->user(), $this->journalId, $this->reason); }
    public function render()
    {
        app(AccessControl::class)->authorize(auth()->user(), 'finance');
        return view('livewire.accounting-controls', [
            'journals' => DB::table('journal_entries')->latest('journal_id')->limit(50)->get(),
            'entry' => $this->journalId ? DB::table('journal_entries')->where('journal_id', $this->journalId)->first() : null,
            'lines' => DB::table('journal_details as d')->join('chart_of_accounts as a', 'd.account_id', '=', 'a.account_id')->where('journal_id', $this->journalId)->select('d.*', 'a.account_name')->get(),
            'periods' => DB::table('financial_periods')->orderByDesc('starts_on')->get(),
            'totals' => app(\App\Services\LedgerReports::class)->totals(),
        ]);
    }
}
