<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StorefrontCatalog
{
    public function query(string $search = '', string $category = ''): Builder
    {
        $query = DB::table('products')->where('products.status', 'published')->select('products.*');
        if (($search = trim(substr($search, 0, 100))) !== '') {
            $query->where(fn ($q) => $q->where('product_name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%'));
        }
        if ($category !== '') {
            $query->where(function ($q) use ($category) {
                $q->where('products.category', $category)->orWhereExists(function ($pivot) use ($category) {
                    $pivot->selectRaw('1')->from('product_category_pivot')->join('product_categories', 'product_categories.id', '=', 'product_category_pivot.category_id')
                        ->whereColumn('product_category_pivot.product_id', 'products.product_id')->where('product_categories.name', $category);
                });
            });
        }
        return $query;
    }

    public function categories(): array
    {
        $direct = DB::table('products')->where('status', 'published')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category');
        $linked = DB::table('product_categories')->join('product_category_pivot', 'product_categories.id', '=', 'product_category_pivot.category_id')
            ->join('products', 'products.product_id', '=', 'product_category_pivot.product_id')->where('products.status', 'published')->distinct()->pluck('product_categories.name');
        return $direct->merge($linked)->unique()->sort()->values()->all();
    }

    public static function image(?string $images): string
    {
        $decoded = json_decode($images ?? '[]', true);
        $path = is_array($decoded) ? ($decoded[0] ?? null) : null;
        if (!is_string($path) || trim($path) === '') return asset('TGIF.png');
        if (str_starts_with($path, 'images/')) return asset($path);
        if (str_starts_with($path, 'storage/')) return asset($path);
        if (preg_match('#^https?://#i', $path)) return $path;
        return asset('storage/'.ltrim($path, '/'));
    }

    public static function options(object $product): array
    {
        $attributes = json_decode($product->attributes ?? '{}', true);
        $options = [];
        foreach (['size' => 'sizes', 'flavor' => 'flavors', 'variety' => 'varieties'] as $key => $attribute) {
            $values = is_array($attributes) ? ($attributes[$attribute] ?? []) : [];
            $options[$key] = is_array($values) ? array_values(array_unique(array_filter($values, fn ($value) => is_string($value) && trim($value) !== ''))) : [];
        }
        return $options;
    }
}
