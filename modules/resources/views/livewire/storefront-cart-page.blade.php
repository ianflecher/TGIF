<section class="sf-menu-section sf-cart-page">
    <div class="sf-section-heading"><div><p class="sf-kicker">YOUR NEXT GOOD MOMENT</p><h2>A bag full of happy<span>.</span></h2><p>Review your favorites before making it official.</p></div><a href="{{ route('products.index') }}" class="sf-text-link">Keep exploring ↗</a></div>
    @error('cart')<p class="sf-alert" role="alert">{{ $message }}</p>@enderror
    @if(session('storefront.notice'))<p class="sf-alert">{{ session('storefront.notice') }}</p>@endif
    <div class="sf-cart-page-grid"><div>
        @forelse($bag['items'] as $item)
            <article class="sf-bag-item" wire:key="line-{{ $item['key'] }}"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"><div class="sf-bag-item-info"><h3>{{ $item['name'] }}</h3><p>{{ implode(' · ', array_filter($item['options'])) ?: 'Original selection' }}</p><strong>₱{{ number_format($item['total']/100, 2) }}</strong>@if(!$item['available'])<p class="sf-item-unavailable">This selection is unavailable. Please adjust or remove it.</p>@endif<div class="sf-bag-item-actions"><div class="sf-stepper"><button wire:click="change('{{ $item['key'] }}', -1)" wire:loading.attr="disabled" aria-label="Decrease {{ $item['name'] }} quantity">−</button><span>{{ $item['quantity'] }}</span><button wire:click="change('{{ $item['key'] }}', 1)" wire:loading.attr="disabled" aria-label="Increase {{ $item['name'] }} quantity">+</button></div><button wire:click="remove('{{ $item['key'] }}')" class="sf-remove">Remove</button></div></div></article>
        @empty
            <div class="sf-bag-empty"><span aria-hidden="true">♡</span><h3>Your favorites belong here.</h3><p>Find something delicious and make it yours.</p><a href="{{ route('products.index') }}" class="sf-button">Explore the menu ↗</a></div>
        @endforelse
    </div><aside class="sf-cart-summary"><h3>Your order</h3><div><span>{{ $bag['count'] }} {{ $bag['count'] === 1 ? 'item' : 'items' }}</span><strong>₱{{ number_format($bag['total']/100, 2) }}</strong></div><p>This is your food subtotal. Delivery fees, if applicable, are calculated at checkout.</p><button wire:click="checkout" class="sf-button sf-full" wire:loading.attr="disabled" @disabled(!$bag['canCheckout'])>Continue to checkout ↗</button></aside></div>
</section>
