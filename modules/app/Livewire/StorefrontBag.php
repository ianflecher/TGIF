<?php

namespace App\Livewire;

use App\Services\StorefrontCart;
use Livewire\Attributes\On;
use Livewire\Component;

class StorefrontBag extends Component
{
    public bool $open = false;
    public bool $addressOpen = false;
    public string $address = '';

    public function mount(): void
    {
        $this->address = (string) session('storefront.address', '');
        $this->open = request()->routeIs('customer.cart.index');
    }

    #[On('bag-updated')]
    public function refreshBag(): void {}

    #[On('bag-open')]
    public function openBag(): void { $this->open = true; $this->resetValidation(); }

    public function change(string $key, int $delta): void
    {
        app(StorefrontCart::class)->change($key, $delta);
    }

    public function remove(string $key): void
    {
        app(StorefrontCart::class)->remove($key);
    }

    public function saveAddress(): void
    {
        $this->validate(['address' => 'required|string|min:10|max:500']);
        session(['storefront.address' => trim($this->address)]);
        $this->dispatch('delivery-address-updated');
        $this->addressOpen = false;
    }

    public function checkout()
    {
        app(StorefrontCart::class)->reprice();
        if (!auth()->check()) {
            session(['url.intended' => route('customer.checkout')]);
            return redirect()->route('login');
        }
        return redirect()->route('customer.checkout');
    }

    public function render()
    {
        return view('livewire.storefront-bag', ['bag' => app(StorefrontCart::class)->summary()]);
    }
}
