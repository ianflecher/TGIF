<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

// Compatibility entry point; storefront logic is shared by the routed PHP components.
new #[Layout('components.layouts.storefront')] class extends Component
{
    public function mount(): void
    {
        $this->redirectRoute('customer.products.index');
    }
};
?>
<div></div>
