<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryHistory extends Model
{
    protected $fillable = [
        'inventory_id',
        'user_id',
        'action',
        'changes',
    ];

    // Relation to the user who performed the action
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation to the inventory item
    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
