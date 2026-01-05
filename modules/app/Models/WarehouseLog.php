<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseLog extends Model
{
    protected $fillable = [
    'inventory_id',
    'item_name',
    'user_id',
    'action',
    'from_warehouse',
    'to_warehouse',
    
    'changes',
];

    public function inventory() {
        return $this->belongsTo(Inventory::class);
    }

    public function fromWarehouse() {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse() {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}


