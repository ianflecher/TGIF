<div class="sf-bag-tools">
    <button class="sf-delivery" wire:click="$set('addressOpen', true)" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span><small>WHERE'S THE GOOD FOOD GOING?</small><strong>{{ session('storefront.address') ? \Illuminate\Support\Str::limit(session('storefront.address'), 24) : 'Add delivery details' }}</strong></span><span aria-hidden="true">⌄</span></button>
    <button class="sf-bag-button" wire:click="openBag" aria-label="Open bag, {{ $bag['count'] }} items"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14l1 14H4L5 7Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg><span class="sf-bag-label">My bag</span><span class="sf-bag-count">{{ $bag['count'] }}</span></button>
    @if($open)
        <div class="sf-overlay" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.set('open', false)">
            <button class="sf-overlay-dismiss" tabindex="-1" wire:click="$set('open', false)" aria-label="Close bag"></button>
            <section class="sf-bag-drawer" role="dialog" aria-modal="true" aria-labelledby="bag-title">
                <div class="sf-modal-header"><div><span class="sf-kicker">SOMETHING GOOD IS IN HERE</span><h2 id="bag-title">Your bag <span>({{ $bag['count'] }})</span></h2></div><button class="sf-close" wire:click="$set('open', false)" aria-label="Close your bag">✕</button></div>
                <div class="sf-bag-content">
                    @if(session('storefront.notice'))<p class="sf-alert">{{ session('storefront.notice') }}</p>@endif
                    @error('cart')<p class="sf-alert" role="alert">{{ $message }}</p>@enderror
                    @forelse($bag['items'] as $item)
                        <article class="sf-bag-item" wire:key="bag-{{ $item['key'] }}"><img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"><div class="sf-bag-item-info"><h3>{{ $item['name'] }}</h3><p>{{ implode(' · ', array_filter($item['options'])) ?: 'Original selection' }}</p><strong>₱{{ number_format($item['total'] / 100, 2) }}</strong>@if(!$item['available'])<p class="sf-item-unavailable">Unavailable in this quantity. Please adjust or remove.</p>@endif<div class="sf-bag-item-actions"><div class="sf-stepper"><button wire:click="change('{{ $item['key'] }}', -1)" aria-label="Decrease {{ $item['name'] }} quantity" wire:loading.attr="disabled">−</button><span>{{ $item['quantity'] }}</span><button wire:click="change('{{ $item['key'] }}', 1)" aria-label="Increase {{ $item['name'] }} quantity" wire:loading.attr="disabled">+</button></div><button wire:click="remove('{{ $item['key'] }}')" class="sf-remove" wire:loading.attr="disabled">Remove</button></div></div></article>
                    @empty
                        <div class="sf-bag-empty"><span aria-hidden="true">♡</span><h3>A little empty.<br>A lot of possibilities.</h3><p>Your next favorite is waiting on the menu.</p><button wire:click="$set('open', false)" class="sf-button">Let's find something good ↗</button></div>
                    @endforelse
                    @if($bag['count'])<p class="sf-bag-footnote">Items stay available for everyone until your order is placed. We'll check availability again at checkout.</p>@endif
                </div>
                @if($bag['count'])<div class="sf-bag-checkout"><div><span>Food subtotal</span><strong>₱{{ number_format($bag['total'] / 100, 2) }}</strong></div><p>No extra fees are added to this bag total. Review your final order at checkout.</p><button class="sf-button sf-full" wire:click="checkout" wire:loading.attr="disabled" @disabled(!$bag['canCheckout'])>{{ auth()->check() ? 'Continue to checkout' : 'Sign in to checkout' }} <span>↗</span></button><button class="sf-continue" wire:click="$set('open', false)">Keep exploring the menu</button></div>@endif
            </section>
        </div>
    @endif
    @if($addressOpen)
        <div class="sf-overlay sf-centered" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.set('addressOpen', false)"><section class="sf-small-modal" role="dialog" aria-modal="true" aria-labelledby="address-title"><div class="sf-modal-header"><div><span class="sf-kicker">PLAN YOUR NEXT BITE</span><h2 id="address-title">Where to?</h2></div><button wire:click="$set('addressOpen', false)" class="sf-close" aria-label="Close delivery details">✕</button></div><form wire:submit="saveAddress" class="sf-address-form"><label for="delivery-address">Delivery address</label><textarea id="delivery-address" wire:model="address" rows="3" maxlength="500" placeholder="House number, street, barangay and city" required></textarea>@error('address')<p class="sf-alert" role="alert">{{ $message }}</p>@enderror<p>We'll carry this address into checkout. You can review and edit it before placing an order.</p><button class="sf-button sf-full" type="submit" wire:loading.attr="disabled">Save delivery details ↗</button></form></section></div>
    @endif
</div>
