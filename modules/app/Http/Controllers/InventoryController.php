<?php

namespace App\Http\Controllers;

use App\Models\WarehouseLog; // ← add this

use App\Models\Inventory;
use App\Models\InventoryHistory; // 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // needed for Auth::id()
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\StockTransaction; 


class InventoryController extends Controller
{
public function index(Request $request)
{
    $query = Inventory::query();

    // ✅ Filter by warehouse
    if ($request->has('warehouse') && $request->warehouse != '') {
        $query->where('warehouse', $request->warehouse);
    }

    // ✅ Filter by search term
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('sku', 'like', "%$search%")
              ->orWhere('name', 'like', "%$search%")
              ->orWhere('category', 'like', "%$search%");
        });
    }
    if ($request->has('category') && $request->category != '') {
        $query->where('category', $request->category);
    }

    // ✅ Filter by stock status (Out of Stock / In Stock)
    if ($request->has('stock_status') && $request->stock_status != '') {
        if ($request->stock_status == 'out') {
            $query->where('quantity', '<=', 0);
        } elseif ($request->stock_status == 'in') {
            $query->where('quantity', '>', 0);
        }
        elseif ($request->stock_status == 'near_out') {

            // ⚠️ If min_quantity is set, use it. Otherwise, use a fallback (e.g., 5)
            $FALLBACK_THRESHOLD = 5;

            $query->where(function ($q) use ($FALLBACK_THRESHOLD) {

                // 1. Items with min_quantity set and quantity <= min_quantity (but > 0)
                $q->whereNotNull('min_quantity')
                  ->whereColumn('quantity', '<=', 'min_quantity')
                  ->where('quantity', '>', 0);

                // 2. Items with no min_quantity, fallback to default threshold
                $q->orWhere(function ($q2) use ($FALLBACK_THRESHOLD) {
                    $q2->where(function ($sub) {
                            $sub->whereNull('min_quantity')
                                ->orWhere('min_quantity', 0);
                        })
                        ->where('quantity', '<=', $FALLBACK_THRESHOLD)
                        ->where('quantity', '>', 0);
                });
            });
        }
    }

    // ✅ Filter by expiration status (Expired / Not Expired)
    if ($request->has('expired_status') && $request->expired_status != '') {
        if ($request->expired_status == 'expired') {
            $query->whereNotNull('expiration_date')
                  ->whereDate('expiration_date', '<=', now());
        } elseif ($request->expired_status == 'valid') {
            $query->where(function($q) {
                $q->whereNull('expiration_date')
                  ->orWhereDate('expiration_date', '>', now()); // strictly future
            });
        } elseif ($request->expired_status == 'near') {
            $query->whereNotNull('expiration_date')
                  ->whereDate('expiration_date', '>', now())
                  ->whereDate('expiration_date', '<=', now()->addMonth());
        }
    }

    $items = $query->get()->groupBy('category');

    // 🔔 ADD ALERT DATA HERE (NOTHING REMOVED)
    $outOfStock = Inventory::where('quantity', '<=', 0)->get();

    $lowStock = Inventory::where(function($q) {
        $q->whereColumn('quantity', '<=', 'min_quantity')
          ->where('quantity', '>', 0);
    })->get();

    $isExpired = Inventory::whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<=', now())
                ->get();

    $nearExpired = Inventory::whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>', now())
                ->whereDate('expiration_date', '<=', now()->addMonth())
                ->get();

                  if ($request->ajax()) {
        return view('inventories.partials.inventory-table', compact('items'))->render();
    }
  $categories = [
        'Food & Ingredients (Consumables)',
        'Seasoning powders',
        'Drink powders',
        'Packaging & Serving',
        'Cleaning & Safety',
    ];
    // 🔥 RETURN EVERYTHING TO THE VIEW
    return view('inventories.index', [
        'items' => $items,
        'outOfStock' => $outOfStock,
        'lowStock' => $lowStock,
        'isExpired' => $isExpired,
        'nearExpired' => $nearExpired,
         'categories' => $categories
    ]);
}




// Show all products and their rows
public function manageWarehouses(Request $request)
{
    $warehouses = config('locations.warehouses');
    $zones = config('locations.zones');

    $query = Inventory::query();

    // Filter by warehouse
    if ($request->warehouse) {
        $query->where('warehouse', $request->warehouse);
    }

    // Filter by search keyword (product name or SKU)
    if ($request->search) {
        $query->where(function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->search . '%')
              ->orWhere('sku', 'like', '%' . $request->search . '%');
        });
    }

    $items = $query->get()->groupBy('name');

    return view('inventories.manageWarehouses', compact('items', 'warehouses', 'zones'));
}



// Assign all rows of a product to the same warehouse & zone
public function assignWarehouse(Request $request)
{
    $request->validate([
        'product_name' => 'required|string',
        'warehouse' => 'required|string',
        'zone' => 'required|string',
    ]);

    $productRows = Inventory::where('name', $request->product_name)->get();

    // Prevent assigning to the same warehouse if already assigned
    if ($productRows->first()->warehouse === $request->warehouse) {
        return redirect()->back()->withErrors(['warehouse' => 'Product is already in this warehouse.']);
    }

    foreach ($productRows as $row) {
        $row->warehouse = $request->warehouse;
        $row->zone = $request->zone;
        $row->save();
    }

    return redirect()->back()->with('success', 'Warehouse assigned successfully!');
}


// Show form
public function showTransferForm($id)
{
    $item = Inventory::findOrFail($id);
    $warehouses = config('locations.warehouses');
    $zones = config('locations.zones');

    return view('inventories.transfer', compact('item', 'warehouses', 'zones'));
}



// Process transfer
public function transfer(Request $request, $id)
{
    $request->validate([
        'warehouse' => 'required|string|max:255',
        'zone'      => 'required|string|max:255',
    ]);

    $item = Inventory::findOrFail($id);

    // Check if item has a current warehouse
    if (!$item->warehouse) {
        return redirect()->back()->withErrors([
            'warehouse' => 'Cannot transfer this item because it does not have a warehouse assigned yet.'
        ])->withInput();
    }

    // Prevent transferring to the same warehouse
    if ($item->warehouse === $request->warehouse) {
        return redirect()->back()->withErrors([
            'warehouse' => 'Cannot transfer to the same warehouse.'
        ])->withInput();
    }

    // Capture previous data
    $oldWarehouse = $item->warehouse;
    $oldZone      = $item->zone;

    // Update warehouse & zone
    $item->warehouse = $request->warehouse;
    $item->zone      = $request->zone;
    $item->save();

    // Log the transfer
    // Transfer
WarehouseLog::create([
    'inventory_id' => $item->id,
        'item_name'    => $item->name,
    'user_id'        => Auth::id(),
    'action'         => 'transferred',
    'from_warehouse' => $oldWarehouse,
    'to_warehouse'   => $item->warehouse,
    'changes'        => json_encode([
        'warehouse' => [
            'old' => $oldWarehouse ?? 'N/A',
            'new' => $item->warehouse,
        ],
        'zone' => [
            'old' => $oldZone ?? 'N/A',
            'new' => $item->zone,
        ],
    ]),
]);


    return redirect()->route('warehouses.manage')
                     ->with('success', 'Item transferred successfully!');
}




public function showLocations($id, Request $request)
{
    // Kunin yung item mula sa inventories table
    $item = Inventory::findOrFail($id);

    // Kunin ang warehouse na pinili (kung meron)
    $selectedWarehouse = $request->query('warehouse');

    // Kunin lahat ng warehouses at zones mula config
    $warehouses = config('locations.warehouses');
    $zones = config('locations.zones');

    // Check kung tugma yung warehouse ng item sa selected warehouse
    $match = true;
    if ($selectedWarehouse && $item->warehouse !== $selectedWarehouse) {
        $match = false;
    }

    return view('inventories.locations', compact(
        'item',
        'warehouses',
        'zones',
        'selectedWarehouse',
        'match'
    ));
}

public function assignLocation(Request $request, $id)
{
    $request->validate([
        'warehouse' => 'required|string|max:255',
        'zone'      => 'required|string|max:255',
    ]);

    $inventory = Inventory::findOrFail($id);

    // Prevent assigning to the same warehouse
    if ($inventory->warehouse === $request->warehouse) {
        return redirect()->back()->withErrors([
            'warehouse' => 'This item is already assigned to the selected warehouse.'
        ])->withInput();
    }

    // Capture previous data
    $oldWarehouse = $inventory->warehouse;
    $oldZone      = $inventory->zone;

    // Update inventory
    $inventory->warehouse = $request->warehouse;
    $inventory->zone      = $request->zone;
    $inventory->save();

    // Log the assignment
    WarehouseLog::create([
        'inventory_id'   => $inventory->id,
        'item_name'      => $inventory->name,
        'user_id'        => Auth::id(),
        'action'         => 'assigned',
        'from_warehouse' => $oldWarehouse,
        'to_warehouse'   => $inventory->warehouse,
        'changes'        => json_encode([
            'warehouse' => [
                'old' => $oldWarehouse ?? 'N/A',
                'new' => $inventory->warehouse,
            ],
            'zone' => [
                'old' => $oldZone ?? 'N/A',
                'new' => $inventory->zone,
            ],
        ]),
    ]);

    return redirect()->route('warehouses.manage')
                     ->with('success', 'Location assigned successfully!');
}






public function create()
{
    $categories = [
        'Food & Ingredients (Consumables)',
        'Seasoning powders',
        'Drink powders',
        'Packaging & Serving',
        'Cleaning & Safety',
    ];

    $items = Inventory::all(); // <-- fetch all existing items

    // ✅ Add these two lines only
    $warehouses = config('locations.warehouses');
    $zones = config('locations.zones');

    return view('inventories.create', compact('categories', 'items', 'warehouses', 'zones'));
}



  // InventoriesController.php
public function store(Request $request)
{
    // ✅ Get warehouse and zone (even if disabled in form)
    $warehouse = $request->input('warehouse') ?? $request->input('warehouse_hidden');
    $zone = $request->input('zone') ?? $request->input('zone_hidden');

    // ✅ Check if item already exists
    $existingItem = \App\Models\Inventory::where('name', $request->name)
        ->orderBy('id', 'desc')
        ->first();

    // ✅ Validation rules
    $rules = [
        'name'          => 'required|string',
        'quantity'      => 'required|integer|min:1',
        'warehouse'     => 'required_without:warehouse_hidden|string|max:255',
        'zone'          => 'required_without:zone_hidden|string|max:255',
        'supplier_name' => 'nullable|string|max:255', // 🆕 supplier_name validation
    ];

    // ✅ If item does not exist → minimum & maximum required
    if (!$existingItem) {
        $rules['minimum'] = 'required|integer|min:0';
        $rules['maximum'] = 'required|integer|gt:minimum';
    }

    $request->validate($rules);

    // ✅ Determine min and max to use (inherit if existing)
    $minValue = $existingItem ? $existingItem->min_quantity : $request->minimum;
    $maxValue = $existingItem ? $existingItem->max_quantity : $request->maximum;

    // ✅ If existing min or max is 0/null, update with the new values
    if ($existingItem) {
        if (empty($existingItem->min_quantity) || $existingItem->min_quantity == 0) {
            $existingItem->min_quantity = $request->minimum ?? 0;
        }
        if (empty($existingItem->max_quantity) || $existingItem->max_quantity == 0) {
            $existingItem->max_quantity = $request->maximum ?? 0;
        }

        // ✅ Auto-fill supplier_name if empty
        if (empty($existingItem->supplier_name) && $request->supplier_name) {
            $existingItem->supplier_name = $request->supplier_name;
        }

        $existingItem->save();

        // ✅ Make sure we use the latest updated values for frontend & calculations
        $minValue = $existingItem->min_quantity;
        $maxValue = $existingItem->max_quantity;
    }

    // ✅ Check quantity range
    if ($request->quantity < $minValue) {
        return back()->withErrors(['quantity' => 'The quantity must not be less than the minimum of ' . $minValue . '.'])->withInput();
    }
    if ($request->quantity > $maxValue) {
        return back()->withErrors(['quantity' => 'The quantity must not exceed the maximum of ' . $maxValue . '.'])->withInput();
    }

    if ($existingItem) {
        // ✅ If expiration date matches, update existing batch
        $sameBatch = \App\Models\Inventory::where('name', $request->name)
            ->where(function ($q) use ($request) {
                if ($request->expiration_date) {
                    $q->where('expiration_date', $request->expiration_date);
                } else {
                    $q->whereNull('expiration_date');
                }
            })->first();

        if ($sameBatch) {
            $sameBatch->quantity += $request->quantity;
            $sameBatch->min_quantity = $minValue; // 🆕 keep updated min/max
            $sameBatch->max_quantity = $maxValue;

            // ✅ Inherit supplier_name if not provided
            $sameBatch->supplier_name = $request->supplier_name ?? $sameBatch->supplier_name;

            $sameBatch->save();

            \App\Models\InventoryHistory::create([
                'inventory_id' => $sameBatch->id,
                'user_id'      => Auth::id(),
                'action'       => 'added',
                'changes'      => json_encode([
                    'name'            => ['new' => $sameBatch->name],
                    'description'     => ['new' => $sameBatch->description],
                    'category'        => ['new' => $sameBatch->category],
                    'quantity'        => ['new' => $sameBatch->quantity],
                    'expiration_date' => ['new' => $sameBatch->expiration_date],
                    'sku'             => ['new' => $sameBatch->sku],
                    'warehouse'       => ['new' => $sameBatch->warehouse],
                    'zone'            => ['new' => $sameBatch->zone],
                    'minimum'         => ['new' => $sameBatch->min_quantity],
                    'maximum'         => ['new' => $sameBatch->max_quantity],
                    'supplier_name'   => ['new' => $sameBatch->supplier_name], // 🆕 log supplier_name
                ]),
            ]);

            \App\Models\StockTransaction::create([
                'inventory_id' => $sameBatch->id,
                'user_id'      => Auth::id(),
                'type'         => 'in',
                'quantity'     => $request->quantity,
            ]);

            return redirect()->route('inventories.index')
                ->with('success', 'Stock updated for existing item!');
        }

        // ✅ Otherwise, same name, different expiration — inherit min/max, description, category, warehouse, zone, supplier_name
        $newItem = \App\Models\Inventory::create([
            'name'            => $request->name,
            'description'     => $existingItem->description,
            'category'        => $existingItem->category,
            'quantity'        => $request->quantity,
            'expiration_date' => $request->expiration_date,
            'warehouse'       => $warehouse,
            'zone'            => $zone,
            'min_quantity'    => $minValue,
            'max_quantity'    => $maxValue,
            'supplier_name'   => $request->supplier_name ?? $existingItem->supplier_name, // 🆕 inherit supplier_name
        ]);

        // ✅ Create history, stock, warehouse log (unchanged)
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
                'minimum'         => ['new' => $newItem->min_quantity],
                'maximum'         => ['new' => $newItem->max_quantity],
                'supplier_name'   => ['new' => $newItem->supplier_name], // 🆕 log supplier_name
            ]),
        ]);

        \App\Models\StockTransaction::create([
            'inventory_id' => $newItem->id,
            'user_id'      => Auth::id(),
            'type'         => 'in',
            'quantity'     => $newItem->quantity,
        ]);

        \App\Models\WarehouseLog::create([
            'inventory_id'   => $newItem->id,
            'item_name'      => $newItem->name,
            'user_id'        => Auth::id(),
            'action'         => 'assigned',
            'from_warehouse' => null,
            'to_warehouse'   => $newItem->warehouse,
            'changes'        => json_encode([
                'warehouse' => ['old' => 'N/A', 'new' => $newItem->warehouse],
                'zone'      => ['old' => 'N/A', 'new' => $newItem->zone],
            ]),
        ]);

        return redirect()->route('inventories.index')
            ->with('success', 'New batch added for existing item!');
    }

    // ✅ No existing item → min & max required, save normally with supplier_name
    $newItem = \App\Models\Inventory::create([
        'name'            => $request->name,
        'description'     => $request->description,
        'category'        => $request->category,
        'quantity'        => $request->quantity,
        'expiration_date' => $request->expiration_date,
        'warehouse'       => $warehouse,
        'zone'            => $zone,
        'min_quantity'    => $minValue,
        'max_quantity'    => $maxValue,
        'supplier_name'   => $request->supplier_name,
    ]);

    // ✅ Create history, stock, warehouse log (unchanged)
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
            'minimum'         => ['new' => $newItem->min_quantity],
            'maximum'         => ['new' => $newItem->max_quantity],
            'supplier_name'   => ['new' => $newItem->supplier_name],
        ]),
    ]);

    \App\Models\StockTransaction::create([
        'inventory_id' => $newItem->id,
        'user_id'      => Auth::id(),
        'type'         => 'in',
        'quantity'     => $newItem->quantity,
    ]);

    \App\Models\WarehouseLog::create([
        'inventory_id'   => $newItem->id,
        'item_name'      => $newItem->name,
        'user_id'        => Auth::id(),
        'action'         => 'assigned',
        'from_warehouse' => null,
        'to_warehouse'   => $newItem->warehouse,
        'changes'        => json_encode([
            'warehouse' => ['old' => 'N/A', 'new' => $newItem->warehouse],
            'zone'      => ['old' => 'N/A', 'new' => $newItem->zone],
        ]),
    ]);

    // ✅ Reset the alert viewed session when a new item is added
    session()->forget('alerts_viewed');

    return redirect()->route('inventories.index')
        ->with('success', 'New item added successfully!');
}












   public function edit(Inventory $inventory)
{
    // Define all categories
    $categories = [
        'Food & Ingredients (Consumables)',
        'Seasoning powders',
        'Drink powders',
        'Packaging & Serving',
        'Cleaning & Safety',
    ];

    // Pass both inventory and categories to the view
    return view('inventories.edit', compact('inventory', 'categories'));
}

public function warehouselogs(Request $request)
{
    $query = WarehouseLog::with(['inventory', 'user']);

    // 🔍 Apply search filters
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

    // Also allow viewing only selected rows (if you want consistency)
    if ($request->filled('selected_ids')) {
        $selectedIds = explode(',', $request->selected_ids);
        $query->whereIn('id', $selectedIds);
    }

    $logs = $query->orderBy('created_at', 'desc')->get();

    return view('inventories.warehouselogs', compact('logs'));
}


public function update(Request $request, Inventory $inventory)
{
    $request->validate([
        'name'        => 'required',
        'description' => 'nullable',
    ]);

    // Capture current data before update
    $oldData = $inventory->only([
        'name', 'description'
    ]);

    $inventory->update(
        $request->only([
            'name', 'description'
        ]) + ['user_id' => Auth::id()]
    );

    // Capture only the changed fields
    $changes = [];
    foreach (['name', 'description'] as $field) {
        if ($oldData[$field] != $request->$field) {
            $changes[$field] = [
                'old' => $oldData[$field],
                'new' => $request->$field,
            ];
        }
    }

       if ($inventory->quantity == 0 && $oldData['quantity'] != 0) {
        $changes['status'] = [
            'old' => 'In Stock',
            'new' => 'Out of Stock',
        ];
    }

    if (!empty($changes)) {
        InventoryHistory::create([
            'inventory_id' => $inventory->id,
            'user_id'      => Auth::id(),
            'action'       => 'updated',
            'changes'      => json_encode($changes),
        ]);
    }

    return redirect()
        ->route('inventories.index')
        ->with('success', 'Item updated successfully.');
}




public function history(Request $request)
{
    $query = InventoryHistory::with(['inventory', 'user']);

    // 🔍 Apply search filters
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

    // ✔ Support selected IDs (same as export)
    if ($request->filled('selected_ids')) {
        $selectedIds = explode(',', $request->selected_ids);
        $query->whereIn('id', $selectedIds);
    }

    // Results (sorted newest first)
    $histories = $query->orderBy('created_at', 'desc')->get();

    // Your existing logic — keep deleted only list
    $deletedHistories = $histories->where('action', 'deleted');

    return view('inventories.history', compact('histories', 'deletedHistories'));
}





public function destroy($id)
{
    $inventory = Inventory::findOrFail($id);

    // Save a history record
    InventoryHistory::create([
        'inventory_id' => $inventory->id,
        'user_id' => Auth::id(),
        'action' => 'deleted',
        'changes' => json_encode(['old' => $inventory->toArray()]),
    ]);

    // Delete the item
    $inventory->delete();

    return redirect()->route('inventories.index')->with('success', 'Item deleted successfully.');
}

public function logout(Request $request)
{
      Auth::guard('web')->logout();
    $request->session()->invalidate(); // Invalidate session
    $request->session()->regenerateToken(); // Regenerate CSRF token

    return redirect()->route('login'); // Redirect to login page
}

public function locations()
{
    return $this->hasMany(ItemLocation::class);
}

public function alerts()
{
    // Out of Stock
$outOfStock = Inventory::where('quantity', 0)->get();

// Low Stock (strictly > 0)
$lowStock = Inventory::where('quantity', '>', 0)
    ->where(function ($q) {
        $q->whereColumn('quantity', '<=', 'min_quantity')
          ->orWhere(function ($q2) {
              $q2->whereNull('min_quantity')->where('quantity', '<=', 5);
          });
    })->get();
  
  // ✅ Expired (same as your working filter in index)
$isExpired = Inventory::whereNotNull('expiration_date')
    ->whereDate('expiration_date', '<=', now()) // include today as expired
    ->get();

// ✅ Near Expired (strictly after today, within 1 month)
$nearExpired = Inventory::whereNotNull('expiration_date')
    ->whereDate('expiration_date', '>', now()) // strictly after today
    ->whereDate('expiration_date', '<=', now()->addMonth())
    ->get();



    // ✅ Merge all alert sets
    $alertIds = collect()
        ->merge($outOfStock->pluck('id'))
        ->merge($lowStock->pluck('id'))
        ->merge($isExpired->pluck('id'))
        ->merge($nearExpired->pluck('id'))
        ->unique()
        ->values()
        ->all();

           // 👉 check if new alert appeared after last snapshot
    $previousSnapshot = session('alerts_snapshot', []);
    $newAlertIds = array_diff($alertIds, $previousSnapshot);

    if (!empty($newAlertIds)) {
        // 🧹 Clear old snapshot when new alerts (e.g. newly expired) appear
        session()->forget('alerts_snapshot');
    }


    // ✅ Save the current alert snapshot and reset badge
    session(['alerts_snapshot' => $alertIds]);
    
    session(['last_alert_count' => 0]);

    // ✅ Pass all data to the view
    return view('inventories.alerts', compact('outOfStock', 'lowStock', 'isExpired', 'nearExpired'));
}





public function checkReorderLevels()
{
    $items = Inventory::whereColumn('quantity', '<=', 'min_quantity')
        ->where('reorder_generated', false)
        ->get();

    foreach ($items as $item) {
        // ✅ Calculate total current quantity of all rows with the same name
        $currentTotal = Inventory::where('name', $item->name)->sum('quantity');

        // ✅ Calculate remaining stock that can be ordered
        $remainingStock = $item->max_quantity - $currentTotal;
        if ($remainingStock < 0) {
            $remainingStock = 0; // avoid negative
        }

        // ✅ Supplier name from inventory
        $supplierName = $item->supplier_name ?? 'N/A';

        // ✅ Create purchase order with updated max_quantity
        PurchaseOrder::create([
            'inventory_id'  => $item->id,
            'supplier_name' => $supplierName,
            'max_quantity'  => $remainingStock, // ✅ save remaining stock as max_quantity
            // 'quantity' => $reorderQty,        // optional, remove if you no longer want quantity
        ]);

        // ✅ Mark as already reordered
        $item->update(['reorder_generated' => true]);
    }

    return redirect()->back()->with('success', 'Purchase orders generated for low-stock items.');
}



public function viewPurchaseOrders(Request $request)
{
    // Start query
    $query = \App\Models\PurchaseOrder::with('inventory')->orderBy('created_at', 'desc');

    // Apply search filters if provided
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

    // Paginate results (20 per page) and keep query string for pagination links
    $orders = $query->paginate(20)->withQueryString();

    return view('inventories.purchase_orders', compact('orders'));
}


public function showSuppliers()
{
    // Get all unique supplier names from inventory
    $suppliers = \App\Models\Inventory::select('supplier_name')
        ->whereNotNull('supplier_name')
        ->distinct()
        ->get();

    return view('inventories.suppliers', compact('suppliers'));
}

// InventoryController.php
public function resetAlerts()
{
    // Get all current alert IDs
    $currentAlertIds = \App\Models\Inventory::where(function($q){
        $q->where('quantity', 0)
          ->orWhere(function($q){
              $q->where('quantity', '<=', 5)->where('quantity', '>', 0);
          })
          ->orWhere(function($q){
              $q->whereDate('expiration_date', '<=', now()->addMonth())
                ->whereDate('expiration_date', '>=', now());
          })
          ->orWhere(function($q){
              $q->whereDate('expiration_date', '<', now());
          });
    })->pluck('id')->toArray();

    // Store them in session as "seen"
    session(['alerts_snapshot' => $currentAlertIds]);

    // Redirect back to the previous page
    return redirect()->back();
}

// In InventoryController.php
public function liveSearch(Request $request)
{
    $query = Inventory::query();

    if ($request->has('search') && $request->search != '') {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('sku', 'like', "%$search%")
              ->orWhere('name', 'like', "%$search%")
              ->orWhere('category', 'like', "%$search%");
        });
    }

    $items = $query->get()->groupBy('category');

    // Return a partial view or JSON
    return view('inventories.partials.items', compact('items')); 
    // Or: return response()->json($items); if using JSON
}




    // Export Inventory History PDF (with optional search filters)
public function exportHistoryPdf(Request $request)
{
    $query = InventoryHistory::with(['inventory', 'user']);

    // 🔍 Apply search filters
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

    // ✔ Selected checkboxes
    if ($request->filled('selected_ids')) {
        $selectedIds = explode(',', $request->selected_ids);
        $query->whereIn('id', $selectedIds);
    }

    $histories = $query->orderBy('created_at', 'desc')->get();

    $pdf = Pdf::loadView('inventory-history-pdf', compact('histories'));
    $pdf->setPaper('a4', 'portrait');
    return $pdf->download('selected-inventory-history.pdf');
}


    // Export Warehouse Logs PDF (with optional search filters)
public function exportWarehousePdf(Request $request)
{
    $query = WarehouseLog::with(['inventory', 'user']); // Adjust model name if different

    // 🔍 Apply search filters
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

    // ✔ Keep your existing selected IDs filter
    if ($request->filled('selected_ids')) {
        $selectedIds = explode(',', $request->selected_ids);
        $query->whereIn('id', $selectedIds);
    }

    $logs = $query->orderBy('created_at', 'desc')->get();

    // Export
    $pdf = Pdf::loadView('warehouse-logs-pdf', compact('logs'));
    $pdf->setPaper('a4', 'portrait');
    return $pdf->download('selected-warehouse-logs.pdf');
}


    // Export Transaction History PDF (with optional search filters)
  public function exportTransactionPdf(Request $request)
{
    $query = StockTransaction::with(['inventory', 'user']); // Adjust model name if different

    // Apply search filters
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

    $transactions = $query->get();

    $pdf = Pdf::loadView('transaction-history-pdf', compact('transactions'));
    $pdf->setPaper('a4', 'portrait'); // Portrait orientation
    return $pdf->download('transaction-history.pdf');
}


    // Export Purchase Orders PDF (with optional search filters)
public function exportPurchasePdf(Request $request)
{
    $query = \App\Models\PurchaseOrder::with(['inventory']);

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

    $orders = $query->get();

    $pdf = Pdf::loadView('purchase-orders-pdf', compact('orders'));
    $pdf->setPaper('a4', 'portrait');
    return $pdf->download('purchase-orders.pdf');
}



public function exportInventoryPdf(Request $request)
{
    $query = Inventory::query();

    // Apply filters (same as in your index method)
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('sku', 'like', '%' . $search . '%')
              ->orWhere('name', 'like', '%' . $search . '%')
              ->orWhere('category', 'like', '%' . $search . '%');
        });
    }
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }
    if ($request->filled('stock_status')) {
        if ($request->stock_status == 'out') {
            $query->where('quantity', 0);
        } elseif ($request->stock_status == 'in') {
            $query->where('quantity', '>', 0);
        } elseif ($request->stock_status == 'near_out') {
            $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'min_quantity');
        }
    }
    if ($request->filled('expired_status')) {
        if ($request->expired_status == 'expired') {
            $query->whereNotNull('expiration_date')->whereDate('expiration_date', '<', Carbon::now());
        } elseif ($request->expired_status == 'valid') {
            $query->where(function($q) {
                $q->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', Carbon::now());
            });
        } elseif ($request->expired_status == 'near') {
            $query->whereNotNull('expiration_date')
                  ->whereDate('expiration_date', '>=', Carbon::now())
                  ->whereDate('expiration_date', '<=', Carbon::now()->addMonth());
        }
    }

    $items = $query->get();

    $pdf = Pdf::loadView('inventory-pdf', compact('items'));
    $pdf->setPaper('a4', 'portrait');
    return $pdf->download('filtered-inventory.pdf');
}


















}
