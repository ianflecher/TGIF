<?php

namespace App\Contracts;

use Illuminate\Http\Request;

/** Provider adapter boundary. No online methods are advertised until an adapter is configured. */
interface HostedPaymentGateway
{
    public function createCheckout(object $order, string $idempotencyKey): array;

    /** Must verify the provider signature, amount, currency, event ID and merchant account before returning an event. */
    public function verifyNotification(Request $request): array;

    public function refund(object $receipt, int $amountCents, string $reason, string $idempotencyKey): array;
}
