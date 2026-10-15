<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CommerceSettings
{
    public function defaults(): array
    {
        return [
            'rules_enabled' => false, 'delivery_enabled' => true, 'pickup_enabled' => false,
            'timezone' => config('app.timezone', 'UTC'), 'pickup_address' => '',
            'minimum_cents' => 0, 'lead_minutes' => 0, 'scheduling_enabled' => false,
            'schedule_days' => 7, 'zones' => [], 'hours' => [],
            'cash_account_id' => null, 'receivable_account_id' => null, 'refund_account_id' => null,
        ];
    }

    public function get(): array
    {
        $row = DB::table('commerce_settings')->where('key', 'storefront')->first();
        return array_replace($this->defaults(), $row ? json_decode($row->value, true, 512, JSON_THROW_ON_ERROR) : []);
    }

    public function save(array $settings): void
    {
        DB::table('commerce_settings')->updateOrInsert(['key' => 'storefront'], [
            'value' => json_encode(array_replace($this->defaults(), $settings), JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
    }
}
