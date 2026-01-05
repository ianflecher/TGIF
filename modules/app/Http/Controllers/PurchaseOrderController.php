<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\Inventory;

class PurchaseOrderController extends Controller
{
    public function create(Request $request, $id)
{
    $inventory = Inventory::findOrFail($id);

    // Calculate max quantity to request
    // max_quantity = total maximum allowed - current quantity
    $maxQuantity = ($inventory->max_quantity ?? 0) - $inventory->quantity;
    $maxQuantity = $maxQuantity > 0 ? $maxQuantity : 0; // ensure non-negative

    // Optional: check if already requested
    $existing = PurchaseOrder::where('inventory_id', $inventory->id)
                             ->where('status', 'Pending')
                             ->first();
    if($existing){
        return redirect()->back()->with('error', 'Purchase order already requested for this item.');
    }

    // Create new purchase order
    PurchaseOrder::create([
        'inventory_id' => $inventory->id,
        'supplier_name' => $inventory->supplier_name ?? 'Unknown',
        'max_quantity' => $maxQuantity,
        'status' => 'Pending',
    ]);

    return redirect()->back()->with('success', 'Purchase order requested successfully.');
}

}
