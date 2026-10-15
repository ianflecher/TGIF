<?php

namespace App\Livewire\Concerns;

use App\Services\CommerceSettings;
use App\Services\OrderPlacement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

trait HandlesCheckout
{
    #[Locked] public array $cartItems = [];
    #[Locked] public float $total = 0;
    #[Locked] public float $subtotal = 0;
    #[Locked] public float $deliveryFee = 0;
    #[Locked] public bool $showConfirmation = false;
    #[Locked] public string $orderNumber = '';
    #[Locked] public ?int $placedOrderId = null;
    #[Locked] public array $orderDetails = [];
    #[Locked] public string $quoteFingerprint = '';
    #[Locked] public string $checkoutToken = '';
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public string $notes = '';
    public string $fulfillmentMode = 'delivery';
    public string $deliveryZone = '';
    public string $postalCode = '';
    public string $scheduledFor = '';

    private function authorizeCheckout(): void
    {
        abort_unless(auth()->user()?->role === 'customer' && !auth()->user()->trashed(), 403);
    }

    public function mount(): void
    {
        $this->authorizeCheckout();
        $customer = DB::table('customers')->where('user_id', auth()->id())->first();
        $this->name = auth()->user()->full_name;
        $this->phone = $customer->phone ?? '';
        $this->address = (string) session('storefront.address', $customer->address ?? '');
        foreach (session('checkout.draft', []) as $key => $value) {
            if (in_array($key, ['name', 'phone', 'address', 'notes', 'fulfillmentMode', 'deliveryZone', 'postalCode', 'scheduledFor'], true) && is_string($value)) $this->$key = $value;
        }
        $settings = $this->commerceOptions();
        if (!$settings['delivery_enabled'] && $settings['pickup_enabled']) $this->fulfillmentMode = 'pickup';
        $this->checkoutToken = (string) Str::uuid();
        $this->loadCart();
    }

    private function fulfillmentInput(): array
    {
        return ['mode' => $this->fulfillmentMode, 'zone' => $this->deliveryZone, 'postal' => $this->postalCode, 'scheduled' => $this->scheduledFor];
    }

    public function commerceOptions(): array { return app(CommerceSettings::class)->get(); }

    public function loadCart(): void
    {
        $this->authorizeCheckout();
        try {
            $quote = app(OrderPlacement::class)->preview($this->fulfillmentInput());
            $this->quoteFingerprint = $quote['fingerprint'];
            $this->total = $quote['total'] / 100;
            $this->subtotal = $quote['subtotal'] / 100;
            $this->deliveryFee = $quote['fee'] / 100;
            $this->cartItems = array_map(fn ($line) => $line['options'] + ['name' => $line['name'], 'image' => $line['image'], 'quantity' => $line['quantity'], 'total' => $line['total'] / 100], $quote['items']);
        } catch (ValidationException $error) {
            $this->quoteFingerprint = '';
            $bag = app(\App\Services\StorefrontCart::class)->summary();
            $this->subtotal = $bag['total'] / 100;
            $this->deliveryFee = 0;
            $this->total = $this->subtotal;
            $this->cartItems = array_map(fn ($line) => $line['options'] + ['name' => $line['name'], 'image' => $line['image'], 'quantity' => $line['quantity'], 'total' => $line['total'] / 100], $bag['items']);
            foreach ($error->errors() as $field => $messages) $this->addError($field, $messages[0]);
        }
    }

    public function updated(string $field): void
    {
        $fields = ['name', 'phone', 'address', 'notes', 'fulfillmentMode', 'deliveryZone', 'postalCode', 'scheduledFor'];
        if (in_array($field, $fields, true)) {
            session(['checkout.draft' => array_combine($fields, array_map(fn ($name) => $this->$name, $fields))]);
            if (in_array($field, ['fulfillmentMode', 'deliveryZone', 'postalCode', 'scheduledFor'], true)) { $this->resetValidation(); $this->loadCart(); }
        }
    }

    #[On('delivery-address-updated')]
    public function refreshDeliveryAddress(): void { $this->address = (string) session('storefront.address', ''); $this->updated('address'); }

    public function placeOrder(): void
    {
        $this->authorizeCheckout();
        if ($this->showConfirmation) return;
        $this->resetValidation();
        $this->validate([
            'name' => 'required|string|max:100', 'phone' => ['required', 'string', 'max:50', function ($field, $value, $fail) { if (!preg_match('/^\+?[0-9\s()\-]{10,30}$/', $value) || strlen(preg_replace('/\D/', '', $value)) < 10 || strlen(preg_replace('/\D/', '', $value)) > 15) $fail('Enter a valid phone number with 10 to 15 digits.'); }],
            'address' => $this->fulfillmentMode === 'pickup' ? 'nullable|string|max:500' : 'required|string|min:10|max:500',
            'notes' => 'nullable|string|max:1000', 'deliveryZone' => 'string|max:100', 'postalCode' => 'string|max:20', 'scheduledFor' => 'string|max:30',
        ]);
        try {
            $order = app(OrderPlacement::class)->place(auth()->user(), $this->only(['name', 'phone', 'address', 'notes']), $this->fulfillmentInput(), $this->quoteFingerprint, $this->checkoutToken);
            $this->orderNumber = $order->order_number;
            $this->placedOrderId = $order->order_id;
            $this->total = (float) $order->grand_total;
            $this->orderDetails = DB::table('order_items')->where('order_id', $order->order_id)->get()->map(fn ($line) => ['name' => $line->product_name, 'quantity' => $line->quantity, 'total' => $line->total])->all();
            $this->showConfirmation = true;
            session()->forget(['checkout.draft', 'cart']);
            $this->dispatch('bag-updated');
        } catch (ValidationException $error) {
            $this->loadCart();
            foreach ($error->errors() as $field => $messages) $this->addError($field, $messages[0]);
        } catch (\Throwable $error) {
            report($error);
            $this->addError('checkout', 'We could not place your order. Your bag and details are saved. Please try again.');
        }
    }

    public function reviewSummary(): void { $this->resetValidation(); $this->loadCart(); }
}
