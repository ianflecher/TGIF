<?php

namespace App\Livewire;

use App\Services\StorefrontCatalog;
use App\Services\StorefrontCart;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.storefront')]
class Storefront extends Component
{
    use WithPagination;

    public string $search = '';
    public string $category = '';
    public string $sort = 'popular';
    #[Locked] public bool $showHero = true;
    #[Locked] public ?int $selectedProductId = null;
    public int $quantity = 1;
    public array $options = ['size' => '', 'flavor' => '', 'variety' => ''];

    public function mount(bool $showHero = true): void
    {
        $this->showHero = $showHero;
        $this->category = substr((string) request('category', ''), 0, 100);
        if (($productId = request()->integer('product')) > 0) $this->openProduct($productId);
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'category', 'sort'], true)) $this->resetPage();
    }

    public function selectCategory(string $category = ''): void
    {
        $this->category = substr($category, 0, 100);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'category');
        $this->resetPage();
    }

    public function openProduct(int $productId): void
    {
        abort_unless(DB::table('products')->where('product_id', $productId)->where('status', 'published')->exists(), 404);
        $this->selectedProductId = $productId;
        $this->quantity = 1;
        $this->options = ['size' => '', 'flavor' => '', 'variety' => ''];
        $this->resetValidation();
    }

    public function closeProduct(): void
    {
        $this->selectedProductId = null;
        $this->resetValidation();
    }

    public function addToBag(): void
    {
        if (!$this->selectedProductId) return;
        $this->validate(['quantity' => 'required|integer|min:1|max:99', 'options' => 'array', 'options.*' => 'nullable|string|max:100']);
        app(StorefrontCart::class)->add($this->selectedProductId, $this->quantity, $this->options);
        $this->closeProduct();
        $this->dispatch('bag-updated');
        $this->dispatch('bag-open');
    }

    public function render()
    {
        $catalog = app(StorefrontCatalog::class);
        $query = $catalog->query($this->search, $this->category);
        match ($this->sort) {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('sold_count'),
        };
        $product = $this->selectedProductId ? DB::table('products')->where('product_id', $this->selectedProductId)->where('status', 'published')->first() : null;
        $unit = null;
        if ($product) {
            try { $unit = app(StorefrontCart::class)->price($product, $this->options)['unit']; } catch (\Illuminate\Validation\ValidationException) {}
        }
        return view('livewire.storefront', [
            'products' => $query->orderBy('products.product_id')->paginate(12),
            'categories' => $catalog->categories(), 'selected' => $product,
            'choices' => $product ? StorefrontCatalog::options($product) : [], 'selectedUnit' => $unit,
        ]);
    }
}
