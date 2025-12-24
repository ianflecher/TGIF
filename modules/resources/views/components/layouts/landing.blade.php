<x-layouts.app.landingpage :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts.app.landingpage>
