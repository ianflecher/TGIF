<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new #[Layout('components.layouts.supplychain')] class extends Component
{
    use WithPagination;

    // Store creation form fields
    public $showCreateStoreModal = false;
    public $newStore = [
        'store_code' => '',
        'store_name' => '',
        'store_type' => 'restaurant',
        'address' => '',
        'city' => '',
        'state' => '',
        'zip_code' => '',
        'contact_person' => '',
        'contact_phone' => '',
        'contact_email' => '',
        'latitude' => null,
        'longitude' => null,
        'delivery_window' => '',
        'delivery_instructions' => '',
        'freezer_capacity_cu_ft' => null,
        'dry_storage_capacity_cu_ft' => null,
        'refrigerator_capacity_cu_ft' => null,
    ];

    // Shipment creation form fields
    public $store_id = '';
    public $shipment_type = 'scheduled_replenishment';
    public $scheduled_date = '';
    public $delivery_window_start = '09:00';
    public $delivery_window_end = '17:00';
    public $vehicle_id = '';
    public $driver_name = '';
    public $driver_phone = '';
    public $delivery_notes = '';

    // Shipment items
    public $items = [];

    // Modal states
    public $showAddItemModal = false;
    public $newItem = [
        'inventory_id' => '',
        'quantity' => 1,
        'unit_price' => 0
    ];

    // Driver creation form fields
    public $showCreateDriverModal = false;
    public $newDriver = [
        'employee_id' => '',
        'license_number' => '',
        'license_type' => 'commercial',
        'license_expiry' => '',
        'years_experience' => 0,
        'current_location' => '',
        'vehicle_types_allowed' => []
    ];

    // Vehicle creation form fields
    public $showCreateVehicleModal = false;
    public $newVehicle = [
        'vehicle_number' => '',
        'vehicle_type' => 'box_truck',
        'make' => '',
        'model' => '',
        'capacity_kg' => 1000,
        'capacity_m3' => 10,
        'current_location' => '',
        'fuel_efficiency' => null,
        'features' => [],
        'driver_id' => null
    ];

    // Success state
    public $showSuccess = false;
    public $shipment_number = '';

    // Store list pagination
    public $perPage = 10;
    public $searchStores = '';

    // Driver and Vehicle lists
    public $drivers = [];
    public $vehicles = [];

    public function mount()
    {
        $this->scheduled_date = now()->addDay()->format('Y-m-d');
        $this->newDriver['license_expiry'] = now()->addYear()->format('Y-m-d');
        $this->loadDriversAndVehicles();
    }

    public function loadDriversAndVehicles()
    {
        $this->drivers = DB::table('logistics_drivers')
            ->where('status', 'available')
            ->orderBy('license_number')
            ->get();

        $this->vehicles = DB::table('logistics_vehicles')
            ->where('status', 'available')
            ->orderBy('vehicle_number')
            ->get();
    }

   public function createStore()
{
    try {
        Log::info('Starting store creation', ['data' => $this->newStore]);
        
        // Clean up latitude/longitude values before validation
        $lat = $this->newStore['latitude'] ? floatval($this->newStore['latitude']) : null;
        $lng = $this->newStore['longitude'] ? floatval($this->newStore['longitude']) : null;
        
        // Add custom validation for database precision constraints
        if ($lat !== null) {
            // DECIMAL(10,8) means max 2 digits before decimal, 8 after
            if ($lat > 99.99999999 || $lat < -99.99999999) {
                throw new \Exception('Latitude value is too precise for database storage. Maximum allowed range is -99.99999999 to 99.99999999');
            }
            // Also check for valid latitude range
            if ($lat < -90 || $lat > 90) {
                throw new \Exception('Latitude must be between -90 and 90 degrees');
            }
        }
        
        if ($lng !== null) {
            // DECIMAL(10,8) means max 2 digits before decimal, 8 after
            // But longitude can go up to 180, so limit to 99.99999999
            if ($lng > 99.99999999 || $lng < -99.99999999) {
                throw new \Exception('Longitude value is too precise for database storage. Maximum allowed range is -99.99999999 to 99.99999999');
            }
            // Also check for valid longitude range
            if ($lng < -180 || $lng > 180) {
                throw new \Exception('Longitude must be between -180 and 180 degrees');
            }
        }
        
        // Temporary store for validation
        $tempStore = $this->newStore;
        $tempStore['latitude'] = $lat;
        $tempStore['longitude'] = $lng;
        
        // Validate with proper field names
        $this->validate([
            'newStore.store_code' => 'required|unique:tgif_stores,store_code|max:20',
            'newStore.store_name' => 'required|max:150',
            'newStore.store_type' => 'required|in:restaurant,kiosk,food_truck,franchise',
            'newStore.address' => 'required',
            'newStore.city' => 'required',
            'newStore.state' => 'required',
            'newStore.zip_code' => 'required',
            'newStore.contact_person' => 'required',
            'newStore.contact_phone' => 'required',
            'newStore.contact_email' => 'nullable|email',
            'newStore.latitude' => 'nullable|numeric|between:-90,90',
            'newStore.longitude' => 'nullable|numeric|between:-180,180',
            'newStore.freezer_capacity_cu_ft' => 'nullable|integer|min:0',
            'newStore.dry_storage_capacity_cu_ft' => 'nullable|integer|min:0',
            'newStore.refrigerator_capacity_cu_ft' => 'nullable|integer|min:0',
        ], [
            'newStore.latitude.between' => 'Latitude must be between -90 and 90 degrees',
            'newStore.longitude.between' => 'Longitude must be between -180 and 180 degrees',
        ]);
        
        // Additional validation for database precision
        if ($lat !== null && abs($lat) > 99.99999999) {
            throw new \Exception('Latitude value exceeds database precision. Please use values between -99.99999999 and 99.99999999');
        }
        
        if ($lng !== null && abs($lng) > 99.99999999) {
            throw new \Exception('Longitude value exceeds database precision. Please use values between -99.99999999 and 99.99999999');
        }
        
        Log::info('Validation passed. Inserting into database...');
        
        // Insert data - convert null strings to actual null
        $insertData = [
            'store_code' => $this->newStore['store_code'],
            'store_name' => $this->newStore['store_name'],
            'store_type' => $this->newStore['store_type'],
            'address' => $this->newStore['address'],
            'city' => $this->newStore['city'],
            'state' => $this->newStore['state'],
            'zip_code' => $this->newStore['zip_code'],
            'contact_person' => $this->newStore['contact_person'],
            'contact_phone' => $this->newStore['contact_phone'],
            'contact_email' => $this->newStore['contact_email'] ?: null,
            'latitude' => $lat,
            'longitude' => $lng,
            'delivery_window' => $this->newStore['delivery_window'] ?: null,
            'delivery_instructions' => $this->newStore['delivery_instructions'] ?: null,
            'freezer_capacity_cu_ft' => $this->newStore['freezer_capacity_cu_ft'] ? intval($this->newStore['freezer_capacity_cu_ft']) : null,
            'dry_storage_capacity_cu_ft' => $this->newStore['dry_storage_capacity_cu_ft'] ? intval($this->newStore['dry_storage_capacity_cu_ft']) : null,
            'refrigerator_capacity_cu_ft' => $this->newStore['refrigerator_capacity_cu_ft'] ? intval($this->newStore['refrigerator_capacity_cu_ft']) : null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        Log::info('Inserting store data', $insertData);
        
        // Insert with error catching
        $result = DB::table('tgif_stores')->insert($insertData);
        
        if ($result) {
            Log::info('Store created successfully', ['store_code' => $this->newStore['store_code']]);
            
            $this->showCreateStoreModal = false;
            $this->reset('newStore');
            
            session()->flash('store-success', 'Store created successfully!');
            
            // Refresh the page to show new store
            $this->dispatch('store-created');
        } else {
            Log::error('Insert failed - no rows affected');
            session()->flash('store-error', 'Failed to create store: Insert operation failed');
        }
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Store validation errors in session so they persist after modal closes
        $errorMessages = [];
        foreach ($e->errors() as $field => $errors) {
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
        }
        
        session()->flash('store-error', implode('<br>', $errorMessages));
        
        Log::error('Store creation validation failed', ['errors' => $e->errors()]);
        // Don't re-throw the exception - we're handling it with flash messages
        
    } catch (\Exception $e) {
        // Database or other errors
        Log::error('Store creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        session()->flash('store-error', 'Failed to create store: ' . $e->getMessage());
    }
}

    // Create Driver Method
    public function createDriver()
    {
        try {
            Log::info('Starting driver creation', ['data' => $this->newDriver]);

            // Validate driver data
            $this->validate([
                'newDriver.employee_id' => 'required|integer|unique:logistics_drivers,employee_id|exists:employees,employee_id',
                'newDriver.license_number' => 'required|unique:logistics_drivers,license_number|max:255',
                'newDriver.license_type' => 'required|in:commercial,non_commercial,learner',
                'newDriver.license_expiry' => 'required|date|after_or_equal:today',
                'newDriver.years_experience' => 'required|integer|min:0|max:50',
                'newDriver.current_location' => 'nullable|string|max:255',
                'newDriver.vehicle_types_allowed' => 'nullable|array',
            ], [
                'newDriver.license_expiry.after_or_equal' => 'License expiry must be a future date',
                'newDriver.employee_id.unique' => 'This employee ID is already registered as a driver',
                'newDriver.employee_id.exists' => 'Employee ID not found in employee records',
                'newDriver.license_number.unique' => 'This license number is already registered',
            ]);

            // Check if employee is active
            $employee = DB::table('employees')
                ->where('employee_id', $this->newDriver['employee_id'])
                ->where('status', 'active')
                ->first();

            if (!$employee) {
                throw new \Exception('Employee not found or not active. Only active employees can be registered as drivers.');
            }

            // Prepare driver data
            $driverData = [
                'employee_id' => $this->newDriver['employee_id'],
                'license_number' => $this->newDriver['license_number'],
                'license_type' => $this->newDriver['license_type'],
                'license_expiry' => $this->newDriver['license_expiry'],
                'years_experience' => $this->newDriver['years_experience'],
                'current_location' => $this->newDriver['current_location'] ?: 'Warehouse',
                'status' => 'available',
                'rating' => 5.00,
                'vehicle_types_allowed' => json_encode($this->newDriver['vehicle_types_allowed'] ?: ['box_truck', 'van']),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Log::info('Inserting driver data', $driverData);

            // Insert driver
            $result = DB::table('logistics_drivers')->insert($driverData);

            if ($result) {
                Log::info('Driver created successfully', ['license_number' => $this->newDriver['license_number']]);
                
                $this->showCreateDriverModal = false;
                $this->reset('newDriver');
                $this->newDriver['license_expiry'] = now()->addYear()->format('Y-m-d');
                $this->loadDriversAndVehicles();
                
                session()->flash('driver-success', 'Driver created successfully!');
            } else {
                Log::error('Driver insert failed');
                session()->flash('driver-error', 'Failed to create driver');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessages = [];
            foreach ($e->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $errorMessages[] = $error;
                }
            }
            
            session()->flash('driver-error', implode('<br>', $errorMessages));
            Log::error('Driver creation validation failed', ['errors' => $e->errors()]);
            
        } catch (\Exception $e) {
            Log::error('Driver creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            session()->flash('driver-error', 'Failed to create driver: ' . $e->getMessage());
        }
    }

    // Create Vehicle Method
    public function createVehicle()
    {
        try {
            Log::info('Starting vehicle creation', ['data' => $this->newVehicle]);

            // Validate vehicle data
            $this->validate([
                'newVehicle.vehicle_number' => 'required|unique:logistics_vehicles,vehicle_number|max:255',
                'newVehicle.vehicle_type' => 'required|in:box_truck,van,refrigerated_truck,flatbed,tractor_trailer',
                'newVehicle.make' => 'required|string|max:100',
                'newVehicle.model' => 'required|string|max:100',
                'newVehicle.capacity_kg' => 'required|integer|min:100|max:50000',
                'newVehicle.capacity_m3' => 'nullable|integer|min:1|max:100',
                'newVehicle.current_location' => 'nullable|string|max:255',
                'newVehicle.fuel_efficiency' => 'nullable|numeric|min:1|max:50',
                'newVehicle.driver_id' => 'nullable|exists:logistics_drivers,driver_id',
                'newVehicle.features' => 'nullable|array',
            ], [
                'newVehicle.vehicle_number.unique' => 'This vehicle number is already registered',
                'newVehicle.capacity_kg.min' => 'Capacity must be at least 100kg',
            ]);

            // Prepare vehicle data
            $vehicleData = [
                'vehicle_number' => $this->newVehicle['vehicle_number'],
                'vehicle_type' => $this->newVehicle['vehicle_type'],
                'make' => $this->newVehicle['make'],
                'model' => $this->newVehicle['model'],
                'capacity_kg' => $this->newVehicle['capacity_kg'],
                'capacity_m3' => $this->newVehicle['capacity_m3'] ?: null,
                'current_location' => $this->newVehicle['current_location'] ?: 'Warehouse',
                'status' => 'available',
                'driver_id' => $this->newVehicle['driver_id'] ?: null,
                'fuel_efficiency' => $this->newVehicle['fuel_efficiency'] ?: null,
                'features' => json_encode($this->newVehicle['features'] ?: []),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Log::info('Inserting vehicle data', $vehicleData);

            // Insert vehicle
            $result = DB::table('logistics_vehicles')->insert($vehicleData);

            if ($result) {
                Log::info('Vehicle created successfully', ['vehicle_number' => $this->newVehicle['vehicle_number']]);
                
                $this->showCreateVehicleModal = false;
                $this->reset('newVehicle');
                $this->newVehicle['capacity_kg'] = 1000;
                $this->newVehicle['capacity_m3'] = 10;
                $this->loadDriversAndVehicles();
                
                session()->flash('vehicle-success', 'Vehicle created successfully!');
            } else {
                Log::error('Vehicle insert failed');
                session()->flash('vehicle-error', 'Failed to create vehicle');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessages = [];
            foreach ($e->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $errorMessages[] = $error;
                }
            }
            
            session()->flash('vehicle-error', implode('<br>', $errorMessages));
            Log::error('Vehicle creation validation failed', ['errors' => $e->errors()]);
            
        } catch (\Exception $e) {
            Log::error('Vehicle creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            session()->flash('vehicle-error', 'Failed to create vehicle: ' . $e->getMessage());
        }
    }

    // Shipment Creation Methods
    public function addItem()
    {
        $this->validate([
            'newItem.inventory_id' => 'required|exists:inventories,inventory_id',
            'newItem.quantity' => 'required|integer|min:1',
            'newItem.unit_price' => 'required|numeric|min:0',
        ]);

        // Get item details
        $item = DB::table('inventories')
            ->where('inventory_id', $this->newItem['inventory_id'])
            ->first();

        if ($item) {
            // Check if item already exists in items array
            $existingIndex = collect($this->items)->search(function ($i) use ($item) {
                return $i['inventory_id'] == $item->inventory_id;
            });

            if ($existingIndex !== false) {
                // Update quantity if item already exists
                $this->items[$existingIndex]['quantity'] += $this->newItem['quantity'];
                $this->items[$existingIndex]['total'] = $this->items[$existingIndex]['quantity'] * $this->items[$existingIndex]['unit_price'];
            } else {
                // Add new item
                $this->items[] = [
                    'inventory_id' => $item->inventory_id,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'quantity' => $this->newItem['quantity'],
                    'unit_price' => $this->newItem['unit_price'] ?: $item->unit_price,
                    'total' => $this->newItem['quantity'] * ($this->newItem['unit_price'] ?: $item->unit_price),
                    'unit_of_measure' => $item->unit_of_measure ?? 'each'
                ];
            }

            $this->reset('newItem');
            $this->showAddItemModal = false;
        }
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items); // Reindex array
    }

    public function createShipment()
{
    try {
        Log::info('Starting shipment creation', [
            'store_id' => $this->store_id,
            'shipment_type' => $this->shipment_type,
            'items_count' => count($this->items),
            'items' => $this->items
        ]);

        // Basic validation rules
        $validationRules = [
            'store_id' => 'required|exists:tgif_stores,store_id',
            'shipment_type' => 'required|in:scheduled_replenishment,emergency_replenishment,new_store_setup,promotional_material,equipment_delivery',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'delivery_window_start' => 'nullable|date_format:H:i',
            'delivery_window_end' => 'nullable|date_format:H:i',
            'delivery_notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
        ];

        // Conditional validation messages
        $validationMessages = [
            'items.required' => 'Please add at least one item to the shipment',
            'items.min' => 'Please add at least one item to the shipment',
            'scheduled_date.after_or_equal' => 'Scheduled date cannot be in the past',
        ];

        // Check if this shipment type requires items
        $itemRequiredTypes = ['scheduled_replenishment', 'emergency_replenishment', 'new_store_setup'];
        if (in_array($this->shipment_type, $itemRequiredTypes) && empty($this->items)) {
            throw new \Exception("This shipment type requires at least one item. Please add items to proceed.");
        }

        // Check if this shipment type requires a vehicle
        $vehicleRequiredTypes = ['scheduled_replenishment', 'emergency_replenishment', 'new_store_setup', 'equipment_delivery'];
        if (in_array($this->shipment_type, $vehicleRequiredTypes)) {
            $validationRules['vehicle_id'] = 'required|exists:logistics_vehicles,vehicle_id';
            $validationMessages['vehicle_id.required'] = 'Vehicle is required for this shipment type';
            
            // If vehicle is assigned, validate driver details
            if ($this->vehicle_id) {
                $validationRules['driver_name'] = 'required|string|max:100';
                $validationRules['driver_phone'] = 'required|string|max:20';
                $validationMessages['driver_name.required'] = 'Driver name is required when vehicle is assigned';
                $validationMessages['driver_phone.required'] = 'Driver phone is required when vehicle is assigned';
            }
        } else {
            $validationRules['vehicle_id'] = 'nullable|exists:logistics_vehicles,vehicle_id';
            $validationRules['driver_name'] = 'nullable|string|max:100';
            $validationRules['driver_phone'] = 'nullable|string|max:20';
        }

        // Validate delivery window
        if ($this->delivery_window_start || $this->delivery_window_end) {
            $validationRules['delivery_window_start'] = 'required|date_format:H:i';
            $validationRules['delivery_window_end'] = 'required|date_format:H:i';
            $validationMessages['delivery_window_start.required'] = 'Both start and end times are required for delivery window';
            $validationMessages['delivery_window_end.required'] = 'Both start and end times are required for delivery window';
        }

        // Validate basic shipment data
        $this->validate($validationRules, $validationMessages);

        // Additional custom validation for delivery window
        if ($this->delivery_window_start && $this->delivery_window_end) {
            if (strtotime($this->delivery_window_start) >= strtotime($this->delivery_window_end)) {
                throw new \Exception('Delivery window start must be before delivery window end');
            }
            
            // Check if delivery window is reasonable (at least 1 hour)
            $startTime = strtotime($this->delivery_window_start);
            $endTime = strtotime($this->delivery_window_end);
            $windowHours = ($endTime - $startTime) / 3600;
            
            if ($windowHours < 1) {
                throw new \Exception('Delivery window must be at least 1 hour');
            }
        }

        // Validate each item in the items array
        foreach ($this->items as $index => $item) {
            $this->validate([
                "items.$index.inventory_id" => 'required|exists:inventories,inventory_id',
                "items.$index.quantity" => 'required|integer|min:1',
                "items.$index.unit_price" => 'required|numeric|min:0',
            ], [
                "items.$index.inventory_id.required" => "Item #" . ($index + 1) . " inventory is required",
                "items.$index.inventory_id.exists" => "Item #" . ($index + 1) . " inventory does not exist",
                "items.$index.quantity.required" => "Item #" . ($index + 1) . " quantity is required",
                "items.$index.quantity.min" => "Item #" . ($index + 1) . " quantity must be at least 1",
                "items.$index.unit_price.required" => "Item #" . ($index + 1) . " unit price is required",
                "items.$index.unit_price.min" => "Item #" . ($index + 1) . " unit price must be positive",
            ]);
        }

        Log::info('Shipment validation passed');

        DB::beginTransaction();

        // Get store details
        $store = DB::table('tgif_stores')
            ->where('store_id', $this->store_id)
            ->first();

        if (!$store) {
            throw new \Exception('Store not found');
        }

        Log::info('Store found', ['store_name' => $store->store_name]);

        // Check inventory availability for each item (only for replenishment shipments)
        if (in_array($this->shipment_type, ['scheduled_replenishment', 'emergency_replenishment'])) {
            foreach ($this->items as $item) {
                $inventory = DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->first();

                if (!$inventory) {
                    throw new \Exception("Item with SKU {$item['sku']} not found in inventory");
                }

                if ($inventory->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$item['product_name']}. Available: {$inventory->quantity}, Requested: {$item['quantity']}");
                }

                // Check if unit price is reasonable (within 50% of inventory price)
                $inventoryPrice = $inventory->unit_price ?? 0;
                $requestedPrice = $item['unit_price'];
                
                if ($inventoryPrice > 0) {
                    $priceDifference = abs($requestedPrice - $inventoryPrice) / $inventoryPrice;
                    if ($priceDifference > 0.5) {
                        Log::warning('Price discrepancy detected', [
                            'item' => $item['product_name'],
                            'inventory_price' => $inventoryPrice,
                            'requested_price' => $requestedPrice,
                            'difference' => $priceDifference
                        ]);
                        // Don't throw exception, just log warning
                    }
                }
            }
        }

        // Check vehicle availability
     if ($this->vehicle_id) {
    $vehicle = DB::table('logistics_vehicles')
        ->where('vehicle_id', $this->vehicle_id)
        ->first();

    if (!$vehicle) {
        throw new \Exception('Selected vehicle not found');
    }

    if ($vehicle->status == 'in_transit') {
        throw new \Exception('Selected vehicle is currently in transit. Please choose another vehicle.');
    }

    // Check if vehicle has enough capacity - USING CORRECT FIELD NAME
    $totalWeight = collect($this->items)->sum('quantity') * 0.5; // Simple estimation
    if ($vehicle->capacity_kg && $totalWeight > $vehicle->capacity_kg) {
        throw new \Exception("Vehicle weight capacity exceeded. Max: {$vehicle->capacity_kg}kg, Estimated: {$totalWeight}kg");
    }
}

        // Calculate totals
        $totalQuantity = collect($this->items)->sum('quantity');
        $totalValue = collect($this->items)->sum('total');
        
        // Simple weight/volume estimation
        $totalWeight = $totalQuantity * 0.5;
        $totalVolume = $totalQuantity * 0.01;

        // Generate shipment number
        $todayCount = DB::table('outbound_shipments')
            ->whereDate('created_at', today())
            ->count();
        
        $shipmentNumber = 'SHIP-' . date('Ymd') . '-' . str_pad($todayCount + 1, 4, '0', STR_PAD_LEFT);

        // Calculate ETA based on store distance (simplified)
        $estimatedArrival = now()->addHours(rand(1, 4)); // Random ETA for demo

        // Create the shipment
        $shipmentData = [
            'shipment_number' => $shipmentNumber,
            'store_id' => $this->store_id,
            'warehouse_name' => 'Main Warehouse',
            'shipment_type' => $this->shipment_type,
            'scheduled_date' => $this->scheduled_date,
            'preferred_delivery_window_start' => $this->delivery_window_start,
            'preferred_delivery_window_end' => $this->delivery_window_end,
            'estimated_departure_time' => now(),
            'estimated_arrival_time' => $estimatedArrival,
            'shipment_items' => json_encode($this->items),
            'total_quantity' => $totalQuantity,
            'total_weight_kg' => $totalWeight,
            'total_volume_m3' => $totalVolume,
            'total_value' => $totalValue,
            'assigned_vehicle_id' => $this->vehicle_id,
            'assigned_driver_name' => $this->driver_name,
            'assigned_driver_phone' => $this->driver_phone,
            'status' => 'pending',
            'delivery_notes' => $this->delivery_notes,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Log::info('Creating shipment with data', $shipmentData);

        $shipmentId = DB::table('outbound_shipments')->insertGetId($shipmentData);

        if (!$shipmentId) {
            throw new \Exception('Failed to create shipment record');
        }

        Log::info('Shipment created', ['shipment_id' => $shipmentId, 'shipment_number' => $shipmentNumber]);

        // If vehicle assigned, update its status
        if ($this->vehicle_id) {
    $vehicleUpdated = DB::table('logistics_vehicles')
        ->where('vehicle_id', $this->vehicle_id)
        ->update([
            'status' => 'in_transit',
            'updated_at' => now(),
        ]);

    Log::info('Vehicle status updated', [
        'vehicle_id' => $this->vehicle_id,
        'updated' => $vehicleUpdated
    ]);
}

        // Process items only for replenishment shipments
        if (in_array($this->shipment_type, ['scheduled_replenishment', 'emergency_replenishment'])) {
            foreach ($this->items as $item) {
                Log::info('Processing inventory item', $item);

                // Create stock transaction
                $transactionId = DB::table('stock_transactions')->insertGetId([
    'inventory_id' => $item['inventory_id'],
    'type' => 'out',
    'quantity' => $item['quantity'],
    'unit_cost' => $item['unit_price'],
    'reference_type' => 'outbound_shipment',
    'reference_id' => $shipmentId,
    'remarks' => 'Allocated for shipment #' . $shipmentNumber . ' to ' . $store->store_name,
    'transaction_date' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

                if (!$transactionId) {
                    throw new \Exception("Failed to create stock transaction for item: {$item['product_name']}");
                }

                // Update inventory quantity
                $inventoryUpdated = DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->decrement('quantity', $item['quantity'], [
                        'updated_at' => now(),
                    ]);

                if (!$inventoryUpdated) {
                    throw new \Exception("Failed to update inventory for item: {$item['product_name']}");
                }
            }
        }


        DB::commit();

        Log::info('Shipment creation completed successfully', [
            'shipment_id' => $shipmentId,
            'shipment_number' => $shipmentNumber,
            'items_processed' => count($this->items),
            'total_value' => $totalValue
        ]);

        // Set success state
        $this->shipment_number = $shipmentNumber;
        $this->showSuccess = true;

        // Reset form
        $this->resetForm();

        // Flash success message
        session()->flash('shipment-success', 'Shipment created successfully! Shipment Number: ' . $shipmentNumber);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Validation errors - display them to user
        $errorMessages = [];
        foreach ($e->errors() as $field => $errors) {
            foreach ($errors as $error) {
                $errorMessages[] = $error;
            }
        }
        
        session()->flash('shipment-error', implode('<br>', $errorMessages));
        
        Log::error('Shipment validation failed', ['errors' => $e->errors()]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Shipment creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => [
                'store_id' => $this->store_id,
                'shipment_type' => $this->shipment_type,
                'items' => $this->items
            ]
        ]);
        
        session()->flash('shipment-error', 'Failed to create shipment: ' . $e->getMessage());
    }
}

    private function resetForm()
    {
        $this->reset([
            'store_id',
            'shipment_type',
            'scheduled_date',
            'delivery_window_start',
            'delivery_window_end',
            'vehicle_id',
            'driver_name',
            'driver_phone',
            'delivery_notes',
            'items',
        ]);
        
        $this->scheduled_date = now()->addDay()->format('Y-m-d');
        $this->delivery_window_start = '09:00';
        $this->delivery_window_end = '17:00';
    }

    public function loadMoreStores()
    {
        $this->perPage += 10;
    }
}
?>
<div>
    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 rounded-r-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-truck text-blue-600 mr-2"></i>
                    Outbound Logistics
                </h1>
                <p class="text-gray-600 mt-1">Manage stores and create shipments</p>
            </div>
            <div class="flex items-center space-x-2">
                <button wire:click="$set('showCreateStoreModal', true)"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Create New Store
                </button>
                <button wire:click="$set('showCreateDriverModal', true)"
                        class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-user-tie mr-2"></i>
                    Add Driver
                </button>
                <button wire:click="$set('showCreateVehicleModal', true)"
                        class="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-truck mr-2"></i>
                    Add Vehicle
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('store-success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 mr-3"></i>
            <span class="text-green-800">{{ session('store-success') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('store-error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
            <span class="text-red-800">{{ session('store-error') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('driver-success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 mr-3"></i>
            <span class="text-green-800">{{ session('driver-success') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('driver-error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
            <span class="text-red-800">{{ session('driver-error') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('vehicle-success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 mr-3"></i>
            <span class="text-green-800">{{ session('vehicle-success') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('vehicle-error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
            <span class="text-red-800">{{ session('vehicle-error') }}</span>
        </div>
    </div>
    @endif

    <!-- Store List Section -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-100 mb-6">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-store text-blue-600 mr-2"></i>
                        Available Stores
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Select a store to create shipment</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search stores..."
                               wire:model.debounce.300ms="searchStores">
                    </div>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        @php
                            $storeCount = DB::table('tgif_stores')
                                ->when($this->searchStores, function ($query) {
                                    $query->where(function ($q) {
                                        $q->where('store_name', 'like', '%' . $this->searchStores . '%')
                                          ->orWhere('store_code', 'like', '%' . $this->searchStores . '%')
                                          ->orWhere('city', 'like', '%' . $this->searchStores . '%');
                                    });
                                })
                                ->where('status', 'active')
                                ->count();
                        @endphp
                        {{ $storeCount }} stores
                    </span>
                </div>
            </div>

            @php
                $stores = DB::table('tgif_stores')
                    ->when($this->searchStores, function ($query) {
                        $query->where(function ($q) {
                            $q->where('store_name', 'like', '%' . $this->searchStores . '%')
                              ->orWhere('store_code', 'like', '%' . $this->searchStores . '%')
                              ->orWhere('city', 'like', '%' . $this->searchStores . '%');
                        });
                    })
                    ->where('status', 'active')
                    ->orderBy('store_name')
                    ->limit($this->perPage)
                    ->get();
            @endphp

            @if($stores->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($stores as $store)
                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-sm transition-all cursor-pointer
                            {{ $store_id == $store->store_id ? 'border-blue-500 bg-blue-50' : '' }}"
                     wire:click="$set('store_id', '{{ $store->store_id }}')">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-bold text-gray-900">
                                {{ $store->store_code }}
                            </h4>
                            <p class="text-sm text-gray-700">{{ $store->store_name }}</p>
                        </div>
                        <span class="px-2 py-1 rounded text-xs font-medium 
                                    {{ $store->store_type == 'restaurant' ? 'bg-blue-100 text-blue-800' : 
                                       ($store->store_type == 'kiosk' ? 'bg-green-100 text-green-800' : 
                                       ($store->store_type == 'food_truck' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800')) }}">
                            {{ ucfirst($store->store_type) }}
                        </span>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-map-marker-alt mr-2 text-blue-500"></i>
                            <span class="truncate">{{ $store->city }}, {{ $store->state }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-user mr-2 text-green-500"></i>
                            <span>{{ $store->contact_person }}</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-phone mr-2 text-purple-500"></i>
                            <span>{{ $store->contact_phone }}</span>
                        </div>
                    </div>
                    @if($store_id == $store->store_id)
                    <div class="mt-4 pt-3 border-t border-blue-200">
                        <div class="flex items-center text-sm text-blue-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>Selected for shipment</span>
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            @if($stores->count() >= $this->perPage)
            <div class="mt-6 text-center">
                <button wire:click="loadMoreStores"
                        class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-arrow-down mr-2"></i>
                    Load More Stores
                </button>
            </div>
            @endif
            @else
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-store text-blue-500 text-2xl"></i>
                </div>
                <h4 class="text-lg font-medium text-gray-900 mb-2">No Stores Found</h4>
                <p class="text-gray-500 mb-6">
                    @if($searchStores)
                    No stores match your search criteria.
                    @else
                    Create your first store to start shipping.
                    @endif
                </p>
                <button wire:click="$set('showCreateStoreModal', true)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Create New Store
                </button>
            </div>
            @endif
        </div>
    </div>

    <!-- Shipment Creation Section (only shown if store is selected) -->
    @if($store_id)
    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 rounded-r-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-truck-loading text-blue-600 mr-2"></i>
                    Create Shipment for Selected Store
                </h2>
                <p class="text-gray-600 mt-1">Add items and configure delivery details</p>
            </div>
            <div class="flex items-center space-x-2">
                @php
                    $selectedStore = DB::table('tgif_stores')->where('store_id', $store_id)->first();
                @endphp
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    <i class="fas fa-store mr-1"></i>
                    {{ $selectedStore->store_code }}
                </span>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    @if($showSuccess)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Shipment Created Successfully!</h3>
                <p class="text-gray-600 mb-4">Shipment Number: <span class="font-bold text-blue-600">{{ $shipment_number }}</span></p>
                <p class="text-sm text-gray-500 mb-6">The shipment has been created and items have been allocated from inventory.</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Shipment Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Shipment Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Shipment Details
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1"></i>
                            Shipment Type *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="shipment_type"
                                required>
                            <option value="scheduled_replenishment">Scheduled Replenishment</option>
                            <option value="emergency_replenishment">Emergency Replenishment</option>
                            <option value="new_store_setup">New Store Setup</option>
                            <option value="promotional_material">Promotional Material</option>
                            <option value="equipment_delivery">Equipment Delivery</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-day mr-1"></i>
                            Scheduled Date *
                        </label>
                        <input type="date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="scheduled_date"
                               min="{{ date('Y-m-d') }}"
                               required>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1"></i>
                                Delivery Window Start
                            </label>
                            <input type="time" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="delivery_window_start">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1"></i>
                                Delivery Window End
                            </label>
                            <input type="time" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="delivery_window_end">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-truck text-blue-600 mr-2"></i>
                    Delivery Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1"></i>
                            Assign Vehicle
                        </label>
                        <div class="flex space-x-2">
                            <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    wire:model="vehicle_id">
                                <option value="">-- Select Vehicle --</option>
                                @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->vehicle_id }}">
                                    {{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})
                                </option>
                                @endforeach
                            </select>
                            <button type="button"
                                    class="px-3 py-2.5 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 transition-colors"
                                    wire:click="$set('showCreateVehicleModal', true)"
                                    title="Add new vehicle">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-tie mr-1"></i>
                                Driver Name
                            </label>
                            <input type="text" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="driver_name"
                                   placeholder="Driver name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone mr-1"></i>
                                Driver Phone
                            </label>
                            <input type="tel" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="driver_phone"
                                   placeholder="Driver phone">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Delivery Notes
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="delivery_notes"
                                  rows="3"
                                  placeholder="Special instructions, access codes, etc."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Items Summary -->
        <div class="space-y-6">
            <!-- Items Card -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-boxes text-blue-600 mr-2"></i>
                        Shipment Items
                    </h2>
                    <button type="button"
                            class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                            wire:click="$set('showAddItemModal', true)">
                        <i class="fas fa-plus mr-1"></i>
                        Add Item
                    </button>
                </div>

                @if(count($items) > 0)
                <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                    @foreach($items as $index => $item)
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-blue-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $item['product_name'] }}</div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="inline-flex items-center mr-3">
                                        <i class="fas fa-barcode mr-1 text-blue-600"></i>
                                        {{ $item['sku'] }}
                                    </span>
                                    @if($item['unit_of_measure'])
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-weight mr-1 text-blue-600"></i>
                                        {{ $item['unit_of_measure'] }}
                                    </span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-600 mt-2">
                                    <span class="inline-flex items-center mr-4">
                                        <i class="fas fa-boxes mr-1 text-blue-600"></i>
                                        Qty: {{ $item['quantity'] }}
                                    </span>
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-dollar-sign mr-1 text-blue-600"></i>
                                        ${{ number_format($item['unit_price'], 2) }} each
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-blue-700 text-lg">${{ number_format($item['total'], 2) }}</div>
                                <button type="button"
                                        class="mt-2 px-2 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors"
                                        wire:click="removeItem({{ $index }})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Summary -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Items:</span>
                            <span class="font-medium text-gray-900">{{ collect($items)->sum('quantity') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Unique Products:</span>
                            <span class="font-medium text-gray-900">{{ count($items) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t">
                            <span class="text-lg font-bold text-gray-900">Total Value:</span>
                            <span class="text-lg font-bold text-blue-700">${{ number_format(collect($items)->sum('total'), 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-6">
                    <button type="button"
                            class="w-full px-4 py-3 text-base font-medium text-white bg-gradient-to-r from-blue-600 to-cyan-600 rounded-lg hover:from-blue-700 hover:to-cyan-700 transition-all shadow-md"
                            wire:click="createShipment"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed">
                        <span wire:loading.remove>
                            <i class="fas fa-paper-plane mr-2"></i>
                            Create Shipment
                        </span>
                        <span wire:loading>
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            Creating...
                        </span>
                    </button>
                </div>
                @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-box-open text-blue-500 text-xl"></i>
                    </div>
                    <h4 class="text-base font-medium text-gray-900 mb-2">No Items Added</h4>
                    <p class="text-sm text-gray-500 mb-4">Add items to create a shipment</p>
                </div>
                @endif
            </div>

            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
                <h3 class="text-md font-bold text-gray-900 mb-3">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Quick Stats
                </h3>
                <div class="space-y-3">
                    @php
                        $activeStores = DB::table('tgif_stores')
                            ->where('status', 'active')
                            ->count();
                        
                        $availableVehicles = DB::table('logistics_vehicles')
                            ->where('status', 'available')
                            ->count();
                        
                        $availableDrivers = DB::table('logistics_drivers')
                            ->where('status', 'available')
                            ->count();
                        
                        $pendingShipments = DB::table('outbound_shipments')
                            ->where('status', 'pending')
                            ->count();
                    @endphp
                    
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-store text-blue-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Active Stores</p>
                            <p class="font-bold text-gray-900">{{ $activeStores }}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-truck text-green-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Available Vehicles</p>
                            <p class="font-bold text-gray-900">{{ $availableVehicles }}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-user-tie text-purple-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Available Drivers</p>
                            <p class="font-bold text-gray-900">{{ $availableDrivers }}</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-amber-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pending Shipments</p>
                            <p class="font-bold text-gray-900">{{ $pendingShipments }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- No Store Selected Message -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-8 text-center">
        <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-hand-pointer text-blue-500 text-3xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Select a Store to Begin</h3>
        <p class="text-gray-600 mb-6">Click on any store from the list above to start creating a shipment.</p>
        <div class="flex justify-center space-x-4">
            <button wire:click="$set('showCreateStoreModal', true)"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-plus-circle mr-2"></i>
                Create New Store
            </button>
        </div>
    </div>
    @endif

    <!-- Create Store Modal -->
    @if($showCreateStoreModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-store text-green-600 mr-2"></i>
                        Create New Store
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Add a new TGIF store location</p>
                </div>
                <button wire:click="$set('showCreateStoreModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="createStore">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-96 overflow-y-auto pr-2">
                    <!-- Store Basic Information -->
                    <div class="md:col-span-2">
                        <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            Basic Information
                        </h4>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1"></i>
                            Store Code *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.store_code"
                               placeholder="e.g., TGIF001"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-store mr-1"></i>
                            Store Name *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.store_name"
                               placeholder="e.g., TGIF Times Square"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1"></i>
                            Store Type *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newStore.store_type"
                                required>
                            <option value="restaurant">Restaurant</option>
                            <option value="kiosk">Kiosk</option>
                            <option value="food_truck">Food Truck</option>
                            <option value="franchise">Franchise</option>
                        </select>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="md:col-span-2 mt-4">
                        <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-address-card text-green-500 mr-2"></i>
                            Contact Information
                        </h4>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tie mr-1"></i>
                            Contact Person *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.contact_person"
                               placeholder="e.g., John Smith"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>
                            Contact Phone *
                        </label>
                        <input type="tel" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.contact_phone"
                               placeholder="e.g., (123) 456-7890"
                               required>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1"></i>
                            Contact Email
                        </label>
                        <input type="email" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.contact_email"
                               placeholder="e.g., manager@tgif.com">
                    </div>
                    
                    <!-- Address Information -->
                    <div class="md:col-span-2 mt-4">
                        <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-map-marked-alt text-red-500 mr-2"></i>
                            Address Information
                        </h4>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-pin mr-1"></i>
                            Address *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.address"
                               placeholder="e.g., 123 Main Street"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-city mr-1"></i>
                            City *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.city"
                               placeholder="e.g., New York"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag-usa mr-1"></i>
                            State *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.state"
                               placeholder="e.g., NY"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-mail-bulk mr-1"></i>
                            ZIP Code *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.zip_code"
                               placeholder="e.g., 10001"
                               required>
                    </div>
                    
                    <!-- Delivery Information -->
                    <div class="md:col-span-2 mt-4">
                        <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-truck text-purple-500 mr-2"></i>
                            Delivery Information
                        </h4>
                    </div>
                    
                    <!-- In the Create Store Modal form -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-map-marker-alt mr-1"></i>
        Latitude
        <span class="text-xs text-gray-500 ml-1">(-90 to 90)</span>
    </label>
    <input type="number" step="0.000001" min="-90" max="90"
           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
           wire:model="newStore.latitude"
           placeholder="e.g., 40.7128 (between -90 and 90)">
    @error('newStore.latitude')
        <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
    @enderror
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-map-marker-alt mr-1"></i>
        Longitude
        <span class="text-xs text-gray-500 ml-1">(-90 and 90)</span>
    </label>
    <input type="number" step="0.000001" min="-180" max="180"
           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
           wire:model="newStore.longitude"
           placeholder="e.g., -74.0060 (between -180 and 180)">
    @error('newStore.longitude')
        <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
    @enderror
</div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1"></i>
                            Delivery Window
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newStore.delivery_window"
                               placeholder="e.g., 9:00 AM - 5:00 PM">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Delivery Instructions
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="newStore.delivery_instructions"
                                  rows="2"
                                  placeholder="Special instructions for delivery drivers"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showCreateStoreModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Create Store
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Create Driver Modal -->
    @if($showCreateDriverModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-user-tie text-purple-600 mr-2"></i>
                        Register New Driver
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Add a new driver to the logistics team</p>
                </div>
                <button wire:click="$set('showCreateDriverModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="createDriver">
                <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-badge mr-1"></i>
                            Employee ID *
                            <span class="text-xs text-gray-500">(Must exist in employee records)</span>
                        </label>
                        <input type="number" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newDriver.employee_id"
                               placeholder="e.g., 1001"
                               required>
                        @php
                            $employeeExists = $this->newDriver['employee_id'] ? 
                                DB::table('employees')
                                    ->where('employee_id', $this->newDriver['employee_id'])
                                    ->where('status', 'active')
                                    ->exists() : false;
                        @endphp
                        @if($this->newDriver['employee_id'] && !$employeeExists)
                            <p class="text-xs text-red-600 mt-1">
                                <i class="fas fa-exclamation-circle"></i>
                                Employee ID not found or employee is not active
                            </p>
                        @endif
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1"></i>
                            License Number *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newDriver.license_number"
                               placeholder="e.g., DL123456789"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-certificate mr-1"></i>
                            License Type *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newDriver.license_type"
                                required>
                            <option value="commercial">Commercial</option>
                            <option value="non_commercial">Non-Commercial</option>
                            <option value="learner">Learner's Permit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            License Expiry Date *
                        </label>
                        <input type="date" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newDriver.license_expiry"
                               min="{{ date('Y-m-d') }}"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-history mr-1"></i>
                            Years of Experience *
                        </label>
                        <input type="number" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newDriver.years_experience"
                               min="0"
                               max="50"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            Current Location
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newDriver.current_location"
                               placeholder="e.g., Main Warehouse">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1"></i>
                            Vehicle Types Allowed
                        </label>
                        <div class="space-y-2">
                            @php
                                $vehicleTypes = ['box_truck', 'van', 'refrigerated_truck', 'flatbed', 'tractor_trailer'];
                            @endphp
                            @foreach($vehicleTypes as $type)
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="vehicle_type_{{ $type }}"
                                       value="{{ $type }}"
                                       wire:model="newDriver.vehicle_types_allowed"
                                       class="h-4 w-4 text-blue-600 rounded focus:ring-blue-500">
                                <label for="vehicle_type_{{ $type }}" class="ml-2 text-sm text-gray-700 capitalize">
                                    {{ str_replace('_', ' ', $type) }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showCreateDriverModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Register Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Create Vehicle Modal -->
    @if($showCreateVehicleModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-truck text-orange-600 mr-2"></i>
                        Register New Vehicle
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Add a new vehicle to the logistics fleet</p>
                </div>
                <button wire:click="$set('showCreateVehicleModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="createVehicle">
                <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-hashtag mr-1"></i>
                            Vehicle Number *
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newVehicle.vehicle_number"
                               placeholder="e.g., TGIF-VAN-001"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-truck mr-1"></i>
                            Vehicle Type *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newVehicle.vehicle_type"
                                required>
                            <option value="box_truck">Box Truck</option>
                            <option value="van">Van</option>
                            <option value="refrigerated_truck">Refrigerated Truck</option>
                            <option value="flatbed">Flatbed Truck</option>
                            <option value="tractor_trailer">Tractor Trailer</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-industry mr-1"></i>
                                Make *
                            </label>
                            <input type="text" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="newVehicle.make"
                                   placeholder="e.g., Ford"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-car mr-1"></i>
                                Model *
                            </label>
                            <input type="text" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="newVehicle.model"
                                   placeholder="e.g., Transit"
                                   required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-weight mr-1"></i>
                                Capacity (kg) *
                            </label>
                            <input type="number" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="newVehicle.capacity_kg"
                                   min="100"
                                   max="50000"
                                   placeholder="e.g., 1000"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-cube mr-1"></i>
                                Capacity (m³)
                            </label>
                            <input type="number" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="newVehicle.capacity_m3"
                                   min="1"
                                   max="100"
                                   placeholder="e.g., 10">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-gas-pump mr-1"></i>
                            Fuel Efficiency (km/l)
                        </label>
                        <input type="number" 
                               step="0.1"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newVehicle.fuel_efficiency"
                               placeholder="e.g., 8.5">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            Current Location
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newVehicle.current_location"
                               placeholder="e.g., Main Warehouse">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tie mr-1"></i>
                            Assign Driver (Optional)
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newVehicle.driver_id">
                            <option value="">-- No Driver Assigned --</option>
                            @foreach($drivers as $driver)
                            <option value="{{ $driver->driver_id }}">
                                {{ $driver->license_number }} 
                                @php
                                    $employee = DB::table('employees')
                                        ->where('employee_id', $driver->employee_id)
                                        ->first();
                                @endphp
                                @if($employee)
                                (Employee #{{ $employee->employee_id }})
                                @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tools mr-1"></i>
                            Vehicle Features
                        </label>
                        <div class="space-y-2">
                            @php
                                $vehicleFeatures = [
                                    'gps' => 'GPS Tracking',
                                    'refrigeration' => 'Refrigeration System',
                                    'lift_gate' => 'Lift Gate',
                                    'pallet_jack' => 'Pallet Jack',
                                    'safety_camera' => 'Safety Camera',
                                    'temperature_monitor' => 'Temperature Monitor'
                                ];
                            @endphp
                            @foreach($vehicleFeatures as $value => $label)
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="feature_{{ $value }}"
                                       value="{{ $value }}"
                                       wire:model="newVehicle.features"
                                       class="h-4 w-4 text-blue-600 rounded focus:ring-blue-500">
                                <label for="feature_{{ $value }}" class="ml-2 text-sm text-gray-700">
                                    {{ $label }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showCreateVehicleModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Register Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Add Item Modal -->
    @if($showAddItemModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Add Item to Shipment
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Select inventory item to ship</p>
                </div>
                <button wire:click="$set('showAddItemModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="addItem">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-box mr-1"></i>
                            Select Inventory Item *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newItem.inventory_id"
                                required>
                            <option value="">-- Select Item --</option>
                            @php
                                $inventoryItems = DB::table('inventories')
                                    ->where('status', 'active')
                                    ->where('quantity', '>', 0)
                                    ->orderBy('product_name')
                                    ->get();
                            @endphp
                            @foreach($inventoryItems as $item)
                            <option value="{{ $item->inventory_id }}">
                                {{ $item->sku }} - {{ $item->product_name }} 
                                (Stock: {{ $item->quantity }} @ ${{ number_format($item->unit_price, 2) }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-boxes mr-1"></i>
                            Quantity *
                        </label>
                        <input type="number" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newItem.quantity"
                               min="1"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign mr-1"></i>
                            Unit Price *
                        </label>
                        <input type="number" 
                               step="0.01"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="newItem.unit_price"
                               min="0"
                               required>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showAddItemModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>