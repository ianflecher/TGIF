<div class="erp-portal-login">
    <a href="{{ route('portals.index') }}" class="erp-text-link">← All portals</a>
    <div class="erp-portal-login-card">
        <span class="erp-portal-icon"><i class="fa-solid fa-{{ $portal['icon'] }}" aria-hidden="true"></i></span>
        <p class="erp-eyebrow">TGIF DEPARTMENT PORTAL</p><h1>{{ $portal['label'] }}</h1><p>{{ $portal['description'] }}</p>
        <form wire:submit="login">
            <label>Username or email<input class="erp-input" wire:model="username" autocomplete="username" required autofocus></label>
            @error('username')<p role="alert" class="erp-portal-error">{{ $message }}</p>@enderror
            <label>Password<input class="erp-input" type="password" wire:model="password" autocomplete="current-password" required></label>
            @error('password')<p role="alert" class="erp-portal-error">{{ $message }}</p>@enderror
            <label class="erp-portal-remember"><input type="checkbox" wire:model="remember"> Remember me</label>
            <button class="erp-button" wire:loading.attr="disabled">Sign in to {{ $portal['label'] }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
        </form><p class="erp-portal-login-note">Only assigned staff can enter. Your account permissions control what you can view, change and approve.</p>
    </div>
</div>
