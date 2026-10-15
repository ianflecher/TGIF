<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class FulfillmentRules
{
    public function isOpen(CarbonImmutable $time, array $hours): bool
    {
        foreach ([$time->startOfDay(), $time->subDay()->startOfDay()] as $day) {
            $window = $hours[$day->dayOfWeekIso] ?? null;
            if (!$window || empty($window['open']) || empty($window['close'])) continue;
            $open = $day->setTimeFromTimeString($window['open']);
            $close = $day->setTimeFromTimeString($window['close']);
            if ($close <= $open) $close = $close->addDay();
            if ($time >= $open && $time < $close) return true;
        }
        return false;
    }

    public function quote(int $subtotal, array $input): array
    {
        $settings = app(CommerceSettings::class)->get();
        $mode = $input['mode'] ?? 'delivery';
        if (!in_array($mode, ['delivery', 'pickup'], true) || !$settings[$mode.'_enabled']) {
            throw ValidationException::withMessages(['fulfillmentMode' => 'This fulfillment option is currently unavailable.']);
        }
        if ($mode === 'pickup' && trim($settings['pickup_address']) === '') {
            throw ValidationException::withMessages(['fulfillmentMode' => 'Pickup is not available yet.']);
        }
        $now = CarbonImmutable::now($settings['timezone']);
        $scheduled = null;
        if (!empty($input['scheduled'])) {
            if (!$settings['scheduling_enabled']) throw ValidationException::withMessages(['scheduledFor' => 'Scheduled orders are currently unavailable.']);
            try {
                $scheduled = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input['scheduled'], $settings['timezone']);
                if (!$scheduled || $scheduled->format('Y-m-d\TH:i') !== $input['scheduled']) throw new \InvalidArgumentException;
            } catch (\Throwable) { throw ValidationException::withMessages(['scheduledFor' => 'Choose a valid local date and time.']); }
            if ($scheduled < $now->addMinutes($settings['lead_minutes']) || $scheduled > $now->addDays($settings['schedule_days'])) {
                throw ValidationException::withMessages(['scheduledFor' => 'Choose a time within the available scheduling window and preparation time.']);
            }
        }
        $fee = 0;
        $minimum = $settings['minimum_cents'];
        $zoneLabel = null;
        if ($settings['rules_enabled']) {
            $serviceTime = $scheduled ?? $now;
            if (!$this->isOpen($serviceTime, $settings['hours'])) {
                throw ValidationException::withMessages(['scheduledFor' => $scheduled ? 'The store is closed at that time. Choose an open time.' : 'The store is currently closed. Please choose an available scheduled time or return during opening hours.']);
            }
            if ($mode === 'delivery') {
                $zone = collect($settings['zones'])->firstWhere('code', $input['zone'] ?? '');
                if (!$zone) throw ValidationException::withMessages(['deliveryZone' => 'Select a supported delivery area.']);
                if (!in_array(trim($input['postal'] ?? ''), $zone['postal_codes'], true)) {
                    throw ValidationException::withMessages(['postalCode' => 'This postal code is outside the selected delivery area.']);
                }
                $fee = (int) $zone['fee_cents'];
                $minimum = max($minimum, (int) $zone['minimum_cents']);
                $zoneLabel = $zone['label'];
            }
        }
        if ($subtotal < $minimum) throw ValidationException::withMessages(['checkout' => 'The minimum food order is ₱'.number_format($minimum / 100, 2).'. Add a little more to your bag.']);
        return [
            'subtotal' => $subtotal, 'fee' => $fee, 'total' => $subtotal + $fee, 'mode' => $mode,
            'zone' => $zoneLabel, 'postal' => trim($input['postal'] ?? ''),
            'pickup' => $mode === 'pickup' ? $settings['pickup_address'] : null,
            'scheduled' => $scheduled?->utc()->format('Y-m-d H:i:s'),
            'settings_hash' => hash('sha256', json_encode($settings)),
        ];
    }
}
