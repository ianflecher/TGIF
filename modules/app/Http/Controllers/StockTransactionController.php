<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\Auth;

class StockTransactionController extends Controller
{
    /* -------------------- STOCK-IN -------------------- */
public function create(Inventory $inventory)
{
    return view('transactions.stock_in', compact('inventory'));
}

public function storeOut(Request $request, Inventory $inventory)
{
    $request->validate([
        'quantity' => 'required|integer|min:1|max:' . $inventory->quantity,
    ]);

    // Subtract from inventory quantity
    $inventory->decrement('quantity', $request->quantity);

    // Record the transaction
    StockTransaction::create([
        'inventory_id' => $inventory->id,
        'type'         => 'out',
        'quantity'     => $request->quantity,
        'user_id'      => Auth::id(),
    ]);

    $inventory->refresh(); // make sure we have the updated quantity

    $minQuantity = $inventory->min_quantity ?? 5;

    // ✅ Use the same expiration logic as index & alerts page
    $isExpired = $inventory->expiration_date && \Carbon\Carbon::parse($inventory->expiration_date)->lt(now());
    $nearExpired = $inventory->expiration_date &&
                   \Carbon\Carbon::parse($inventory->expiration_date)->between(now(), now()->addMonth(1));

    // ✅ AUTOMATED PURCHASE ORDER CREATION (new feature)
    if ($inventory->quantity <= $inventory->reorder_level) {
        $existingPO = \App\Models\PurchaseOrder::where('inventory_id', $inventory->id)
            ->where('status', 'pending')
            ->first();

        if (!$existingPO) {
    // ✅ Calculate total current quantity of all rows with the same name
    $currentTotal = \App\Models\Inventory::where('name', $inventory->name)->sum('quantity');

    // ✅ Calculate remaining stock that can be ordered
    $remainingStock = $inventory->max_quantity - $currentTotal;
    if ($remainingStock < 0) {
        $remainingStock = 0; // avoid negative values
    }

    \App\Models\PurchaseOrder::create([
        'inventory_id'  => $inventory->id,
        'supplier_name' => $inventory->supplier_name ?? 'Unknown', // optional: replace with real supplier
        'max_quantity'  => $remainingStock, // how many to order
        'status'        => 'pending',
    ]);
}

    }

    return redirect()->route('inventories.index')
                     ->with('success', 'Stock-out recorded successfully! (Reorder checked automatically)');
}








public function index()
{
    $transactions = \App\Models\StockTransaction::with(['inventory', 'user'])
        ->orderBy('created_at', 'desc')
        ->paginate(20);

    return view('transactions.transaction_history', compact('transactions'));
}



    
public function store(Request $request)
{
    $request->validate([
        'name'            => 'required|string',
        'description'     => 'nullable|string',
        'category'        => 'required|string',
        'quantity'        => 'required|integer|min:1',
        'expiration_date' => 'nullable|date',
         // optional supplier field
    ]);

    // Check if an item with the same name AND expiration date exists
    $existingItem = Inventory::where('name', $request->name)
        ->where(function ($q) use ($request) {
            if ($request->expiration_date) {
                $q->where('expiration_date', $request->expiration_date);
            } else {
                $q->whereNull('expiration_date');
            }
        })
        ->first();

    if ($existingItem) {
        // Stock-in existing item
        $oldQuantity = $existingItem->quantity;
        $existingItem->quantity += $request->quantity;
        $existingItem->save();

        // Record in StockTransaction
        \App\Models\StockTransaction::create([
            'inventory_id' => $existingItem->id,
            'user_id'      => Auth::id(),
            'type'         => 'in',
            'quantity'     => $request->quantity,
            


            'expiration_date' => $request->expiration_date ?? null,
        ]);

        return redirect()->route('inventories.index')
                         ->with('success', 'Stock updated for existing item batch!');
    }

    // New item → create inventory row
    $newItem = Inventory::create([
        'name'            => $request->name,
        'description'     => $request->description,
        'category'        => $request->category,
        'quantity'        => $request->quantity,
        'expiration_date' => $request->expiration_date,
    ]);



    // Record creation in InventoryHistory
    \App\Models\InventoryHistory::create([
        'inventory_id' => $newItem->id,
        'user_id'      => Auth::id(),
        'action'       => 'added',
        'changes'      => json_encode([
            'name'            => ['new' => $newItem->name],
            'description'     => ['new' => $newItem->description],
            'category'        => ['new' => $newItem->category],
            'quantity'        => ['new' => $newItem->quantity],
            'expiration_date' => ['new' => $newItem->expiration_date],
            'sku'             => ['new' => $newItem->sku],
        ]),
    ]);

    // Record initial stock-in in StockTransaction
    \App\Models\StockTransaction::create([
        'inventory_id' => $newItem->id,
        'user_id'      => Auth::id(),
        'type'         => 'in',
        'quantity'     => $newItem->quantity,
        
        'expiration_date' => $request->expiration_date ?? null,
    ]);

    return redirect()->route('inventories.index')
                     ->with('success', 'New item batch added successfully!');
}



    /* -------------------- STOCK-OUT ------------------- */
    public function createOut(Inventory $inventory)
    {
        return view('transactions.stock_out', compact('inventory'));
    }





public function transactionHistory(Request $request)
{
    $query = \App\Models\StockTransaction::with(['inventory', 'user'])
        ->orderBy('created_at', 'desc');

    // Apply search filters if filled
    if ($request->filled('item_name')) {
        $query->whereHas('inventory', function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->item_name . '%');
        });
    }

    if ($request->filled('date_from')) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }

    // Paginate the filtered results
    $transactions = $query->paginate(20)->withQueryString(); // keeps search params in pagination links

    return view('transactions.transaction_history', compact('transactions'));
}

}
