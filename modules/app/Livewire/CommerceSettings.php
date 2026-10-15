<?php

namespace App\Livewire;

use App\Services\CommerceSettings as SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CommerceSettings extends Component
{
    public bool $rulesEnabled = false;
    public bool $deliveryEnabled = true;
    public bool $pickupEnabled = false;
    public string $timezone = '';
    public string $pickupAddress = '';
    public string $minimum = '0';
    public int $leadMinutes = 0;
    public bool $schedulingEnabled = false;
    public int $scheduleDays = 7;
    public array $zones = [];
    public array $hours = [];
    public string $cashAccount = '';
    public string $receivableAccount = '';
    public string $refundAccount = '';

    private function authorizeSettings(): void { abort_unless(auth()->user()?->role === 'admin' && !auth()->user()->trashed(), 403); }

    public function mount(): void
    {
        $this->authorizeSettings();
        $settings = app(SettingsStore::class)->get();
        foreach (['rulesEnabled' => 'rules_enabled', 'deliveryEnabled' => 'delivery_enabled', 'pickupEnabled' => 'pickup_enabled', 'schedulingEnabled' => 'scheduling_enabled', 'timezone' => 'timezone', 'pickupAddress' => 'pickup_address', 'leadMinutes' => 'lead_minutes', 'scheduleDays' => 'schedule_days'] as $property => $key) $this->$property = $settings[$key];
        $this->minimum = number_format($settings['minimum_cents'] / 100, 2, '.', '');
        foreach ($settings['zones'] as $zone) $this->zones[] = ['code' => $zone['code'], 'label' => $zone['label'], 'fee' => number_format($zone['fee_cents'] / 100, 2, '.', ''), 'minimum' => number_format($zone['minimum_cents'] / 100, 2, '.', ''), 'postal' => implode(', ', $zone['postal_codes'])];
        foreach (range(1, 7) as $day) $this->hours[$day] = ['open' => $settings['hours'][$day]['open'] ?? '', 'close' => $settings['hours'][$day]['close'] ?? ''];
        $this->cashAccount = (string) ($settings['cash_account_id'] ?? '');
        $this->receivableAccount = (string) ($settings['receivable_account_id'] ?? '');
        $this->refundAccount = (string) ($settings['refund_account_id'] ?? '');
    }

    public function addZone(): void { $this->authorizeSettings(); $this->zones[] = ['code' => (string) Str::uuid(), 'label' => '', 'fee' => '0', 'minimum' => '0', 'postal' => '']; }
    public function removeZone(int $index): void { $this->authorizeSettings(); unset($this->zones[$index]); $this->zones = array_values($this->zones); }

    public function save(): void
    {
        $this->authorizeSettings();
        $this->validate([
            'rulesEnabled' => 'boolean', 'deliveryEnabled' => 'boolean', 'pickupEnabled' => 'boolean', 'schedulingEnabled' => 'boolean',
            'timezone' => 'required|timezone', 'pickupAddress' => ($this->pickupEnabled ? 'required|min:10|' : 'nullable|').'string|max:500',
            'minimum' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'leadMinutes' => 'integer|min:0|max:1440', 'scheduleDays' => 'integer|min:1|max:30',
            'zones' => 'array|max:50', 'zones.*.code' => 'required|uuid|distinct', 'zones.*.label' => 'required|string|max:100',
            'zones.*.fee' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'zones.*.minimum' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'zones.*.postal' => 'required|string|max:1000',
            'hours' => 'array|size:7', 'hours.*.open' => 'nullable|date_format:H:i', 'hours.*.close' => 'nullable|date_format:H:i',
            'cashAccount' => ['nullable', Rule::exists('chart_of_accounts', 'account_id')->where('is_active', true)->where('account_type', 'asset')->where('normal_balance', 'debit')],
            'receivableAccount' => ['nullable', Rule::exists('chart_of_accounts', 'account_id')->where('is_active', true)->where('account_type', 'asset')->where('normal_balance', 'debit')],
            'refundAccount' => ['nullable', Rule::exists('chart_of_accounts', 'account_id')->where('is_active', true)->where('normal_balance', 'debit')->where(fn ($q) => $q->whereIn('account_type', ['revenue', 'expense']))],
        ]);
        $hours = [];
        foreach (range(1, 7) as $day) {
            $window = $this->hours[$day] ?? [];
            if (empty($window['open']) !== empty($window['close'])) throw ValidationException::withMessages(['hours' => 'Set both opening and closing times for each open day.']);
            if (!empty($window['open'])) $hours[$day] = $window;
        }
        if ($this->rulesEnabled && (!$hours || (!$this->deliveryEnabled && !$this->pickupEnabled) || ($this->deliveryEnabled && !$this->zones))) throw ValidationException::withMessages(['rulesEnabled' => 'Before enabling rules, configure opening hours, an available fulfillment mode, and postal-code zones for delivery.']);
        if (($this->cashAccount === '') !== ($this->receivableAccount === '') || ($this->cashAccount !== '' && $this->cashAccount === $this->receivableAccount) || ($this->refundAccount !== '' && $this->refundAccount === $this->cashAccount)) throw ValidationException::withMessages(['cashAccount' => 'Select distinct cash and receivable accounts together. The refund account must also differ from cash.']);
        $zones = array_map(function ($zone) {
            $postals = array_values(array_unique(array_filter(array_map('trim', explode(',', $zone['postal'])))));
            foreach ($postals as $postal) if (!preg_match('/^[A-Za-z0-9 -]{2,20}$/', $postal)) throw ValidationException::withMessages(['zones' => 'Use valid postal codes separated by commas.']);
            if (!$postals) throw ValidationException::withMessages(['zones' => 'Each delivery area needs supported postal codes.']);
            return ['code' => $zone['code'], 'label' => $zone['label'], 'fee_cents' => (int) round((float) $zone['fee'] * 100), 'minimum_cents' => (int) round((float) $zone['minimum'] * 100), 'postal_codes' => $postals];
        }, $this->zones);
        $before = app(SettingsStore::class)->get();
        $settings = [
            'rules_enabled' => $this->rulesEnabled, 'delivery_enabled' => $this->deliveryEnabled, 'pickup_enabled' => $this->pickupEnabled,
            'timezone' => $this->timezone, 'pickup_address' => trim($this->pickupAddress), 'minimum_cents' => (int) round((float) $this->minimum * 100),
            'lead_minutes' => $this->leadMinutes, 'scheduling_enabled' => $this->schedulingEnabled, 'schedule_days' => $this->scheduleDays,
            'zones' => $zones, 'hours' => $hours, 'cash_account_id' => $this->cashAccount !== '' ? (int) $this->cashAccount : null,
            'receivable_account_id' => $this->receivableAccount !== '' ? (int) $this->receivableAccount : null, 'refund_account_id' => $this->refundAccount !== '' ? (int) $this->refundAccount : null,
        ];
        DB::transaction(function () use ($settings, $before) {
            app(SettingsStore::class)->save($settings);
            DB::table('audit_logs')->insert(['action' => 'update', 'table_name' => 'commerce_settings', 'record_id' => 0, 'user_id' => auth()->id(), 'old_values' => json_encode($before), 'new_values' => json_encode($settings), 'created_at' => now(), 'updated_at' => now()]);
        });
        session()->flash('message', 'Storefront settings saved.');
    }

    public function render()
    {
        $this->authorizeSettings();
        return view('livewire.commerce-settings', ['accounts' => DB::table('chart_of_accounts')->where('is_active', true)->orderBy('account_code')->get()]);
    }
}
