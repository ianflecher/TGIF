<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use App\Services\StorefrontCart;

#[Layout('components.layouts.storefront')]
class StorefrontCartPage extends StorefrontBag
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'customer', 403);
    }

    public function change(string $key, int $delta): void
    {
        parent::change($key, $delta);
        $this->dispatch('bag-updated');
    }

    public function remove(string $key): void
    {
        parent::remove($key);
        $this->dispatch('bag-updated');
    }

    public function render()
    {
        return view('livewire.storefront-cart-page', ['bag' => app(StorefrontCart::class)->summary()]);
    }
}
