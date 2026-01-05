<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ItemLocation;
use App\Models\Inventory;

class ItemLocationController extends Controller
{
    public function store(Request $request, $inventoryId)
    {
        // ✅ Validate input
        $validated = $request->validate([
            'warehouse' => 'required|string|max:255',
            'zone' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
        ]);

        // ✅ Make sure inventory exists (optional but good practice)
        $inventory = Inventory::findOrFail($inventoryId);

        // ✅ Create location record
        ItemLocation::create([
            'inventory_id' => $inventory->id,
            'warehouse' => $validated['warehouse'],
            'zone' => $validated['zone'],
            'quantity' => $validated['quantity'],
        ]);

        // ✅ Redirect back with success message
        return redirect()->back()->with('success', 'Location assigned successfully!');
    }

    public function destroy($id)
    {
        // ✅ Delete location
        $location = ItemLocation::findOrFail($id);
        $location->delete();

        return redirect()->back()->with('success', 'Location removed successfully!');
    }

    public function warehouseLogs()
{
    $logs = WarehouseLog::with(['inventory', 'user'])->orderBy('created_at', 'desc')->get();
    return view('inventories.warehouseLogs', compact('logs'));
}


}
