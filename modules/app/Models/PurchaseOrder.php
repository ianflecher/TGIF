<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

   protected $fillable = [
    'inventory_id',
    'supplier_name',
    'max_quantity',
    'status',
];


    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * ✅ Automatically fill supplier_name from inventory if not set.
     */
    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->supplier_name) && $order->inventory_id) {
                $order->supplier_name = optional($order->inventory)->supplier_name ?? 'N/A';
            }
        });
    }
}
