<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{

    
    protected $fillable = [
         'sku',
        'name',
        'description',
        'quantity',
        'category',
        'user_id',        // <-- new field
        'expiration_date',
         'warehouse', 'zone',
    'min_quantity', 'max_quantity', 'reorder_level',  'default_supplier', 'reorder_generated',
     'supplier_name'

     
    ];

     protected static function boot()
    {
        parent::boot();
        // Existing: Auto-generate SKU on creation
        static::creating(function ($inventory) {
            if (empty($inventory->sku)) {
                $lastSku = Inventory::max('sku'); // get highest SKU
                $inventory->sku = $lastSku ? $lastSku + 1 : 1; // start at 1
            }
        });
        // New: Auto-set supplier_name on saving (create or update) if missing
        static::saving(function ($inventory) {
            if (empty($inventory->supplier_name)) {
                // Inherit from the latest item with the same name and a non-empty supplier_name
                $latestWithSupplier = Inventory::where('name', $inventory->name)
                    ->whereNotNull('supplier_name')
                    ->where('supplier_name', '!=', '')  // Also check for empty strings
                    ->orderBy('id', 'desc')
                    ->first();
                $inventory->supplier_name = $latestWithSupplier ? $latestWithSupplier->supplier_name : 'Unknown';
            }
            
        });
    }




    // Relationship: each inventory item belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locations()
{
    return $this->hasMany(ItemLocation::class, 'inventory_id');
}

public function purchaseOrders()
{
    return $this->hasMany(PurchaseOrder::class);
}


}
