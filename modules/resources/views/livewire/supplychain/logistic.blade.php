<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.supplychain')] class extends Component
{
    use WithPagination;

    public $tab = 'pending'; // pending, loading, in_transit, delivered
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $storeFilter = '';
    public $vehicleFilter = '';

    // For updating shipment details
    public $showUpdateModal = false;
    public $updateData = [
        'shipment_id' => null,
        'new_estimated_arrival' => '',
        'new_delivery_window_start' => '',
        'new_delivery_window_end' => '',
    ];

    // For showing shipment items
    public $showItemsModal = false;
    public $selectedShipmentItems = [];
    public $selectedShipmentNumber = '';
    public $selectedShipmentStore = '';

    // For tracking updates
    public $showTrackingModal = false;
    public $trackingData = [
        'shipment_id' => null,
        'latitude' => '',
        'longitude' => '',
        'status' => 'in_transit',
        'notes' => '',
    ];

    // For delivery confirmation
    public $showDeliveryConfirmModal = false;
    public $deliveryData = [
        'shipment_id' => null,
        'store_manager_name' => '',
        'store_manager_phone' => '',
        'delivery_notes' => '',
    ];

    protected $queryString = ['tab', 'search', 'dateFrom', 'dateTo', 'storeFilter', 'vehicleFilter'];

    public function updateShipmentDetails($shipmentId)
    {
        $this->validate([
            'updateData.new_estimated_arrival' => 'required|date',
            'updateData.new_delivery_window_start' => 'nullable|date_format:H:i',
            'updateData.new_delivery_window_end' => 'nullable|date_format:H:i',
        ]);

        try {
            DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->update([
                    'estimated_arrival_time' => $this->updateData['new_estimated_arrival'],
                    'preferred_delivery_window_start' => $this->updateData['new_delivery_window_start'],
                    'preferred_delivery_window_end' => $this->updateData['new_delivery_window_end'],
                    'eta_last_updated' => now(),
                    'updated_at' => now(),
                ]);

            $this->showUpdateModal = false;
            $this->updateData = [
                'shipment_id' => null,
                'new_estimated_arrival' => '',
                'new_delivery_window_start' => '',
                'new_delivery_window_end' => '',
            ];

            session()->flash('success', 'Shipment details updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update shipment: ' . $e->getMessage());
        }
    }

    public function updateShipmentStatus($shipmentId, $status)
    {
        try {
            $updates = ['status' => $status, 'updated_at' => now()];
            
            // Add timestamps based on status
            switch ($status) {
                case 'loading':
                    $updates['loading_notified_at'] = now();
                    break;
                case 'in_transit':
                    $updates['actual_departure_time'] = now();
                    $updates['departure_notified_at'] = now();
                    break;
                case 'arrived':
                    $updates['actual_arrival_time'] = now();
                    $updates['arrival_notified_at'] = now();
                    break;
            }

            DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->update($updates);

            session()->flash('success', 'Shipment status updated to ' . str_replace('_', ' ', $status) . '!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    public function updateTracking($shipmentId)
    {
        $this->validate([
            'trackingData.latitude' => 'required|numeric|between:-90,90',
            'trackingData.longitude' => 'required|numeric|between:-180,180',
            'trackingData.notes' => 'nullable|string|max:500',
        ]);

        try {
            // Get existing GPS data
            $shipment = DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->first();

            $gpsData = $shipment->gps_tracking_data ? json_decode($shipment->gps_tracking_data, true) : [];
            
            // Add new tracking point
            $gpsData[] = [
                'latitude' => $this->trackingData['latitude'],
                'longitude' => $this->trackingData['longitude'],
                'timestamp' => now()->toDateTimeString(),
                'speed' => $this->trackingData['speed'] ?? null,
                'notes' => $this->trackingData['notes'],
            ];

            // Calculate ETA if in transit
            $currentEta = null;
            if ($this->trackingData['status'] === 'in_transit') {
                $store = DB::table('tgif_stores')
                    ->where('store_id', $shipment->store_id)
                    ->first();
                
                if ($store && $store->latitude && $store->longitude) {
                    // Simple ETA calculation (in practice use distance matrix API)
                    $distance = $this->calculateDistance(
                        $this->trackingData['latitude'],
                        $this->trackingData['longitude'],
                        $store->latitude,
                        $store->longitude
                    );
                    $currentEta = max(10, ceil($distance / 50 * 60)); // Assuming 50km/h average
                }
            }

            DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->update([
                    'current_latitude' => $this->trackingData['latitude'],
                    'current_longitude' => $this->trackingData['longitude'],
                    'status' => $this->trackingData['status'],
                    'gps_tracking_data' => json_encode($gpsData),
                    'last_gps_update' => now(),
                    'current_eta_minutes' => $currentEta,
                    'eta_last_updated' => now(),
                    'eta_notified_at' => $currentEta ? now() : null,
                    'updated_at' => now(),
                ]);

            $this->showTrackingModal = false;
            $this->trackingData = [
                'shipment_id' => null,
                'latitude' => '',
                'longitude' => '',
                'status' => 'in_transit',
                'notes' => '',
            ];

            session()->flash('success', 'Tracking updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update tracking: ' . $e->getMessage());
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    public function confirmDelivery($shipmentId)
    {
        $this->validate([
            'deliveryData.store_manager_name' => 'required|string|max:100',
            'deliveryData.store_manager_phone' => 'required|string|max:20',
            'deliveryData.delivery_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Get shipment details
            $shipment = DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->first();

            // Update shipment status
            DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->update([
                    'status' => 'delivered',
                    'actual_completion_time' => now(),
                    'completed_notified_at' => now(),
                    'delivery_notes' => $this->deliveryData['delivery_notes'],
                    'received_by_store_manager' => auth()->id(),
                    'store_received_at' => now(),
                    'store_receiving_checklist' => json_encode([
                        'checked_by' => $this->deliveryData['store_manager_name'],
                        'checked_at' => now()->toDateTimeString(),
                        'quantity_verified' => true,
                        'quality_checked' => true,
                        'temperature_verified' => $shipment->requires_refrigeration ? true : null,
                    ]),
                    'proof_of_delivery' => json_encode([
                        'confirmed_by' => $this->deliveryData['store_manager_name'],
                        'confirmed_at' => now()->toDateTimeString(),
                        'contact_phone' => $this->deliveryData['store_manager_phone'],
                    ]),
                    'updated_at' => now(),
                ]);

            // Process items - update store stock levels
            $shipmentItems = json_decode($shipment->shipment_items, true);
            
            foreach ($shipmentItems as $item) {
                // Check if stock level exists for this store and inventory item
                $stockLevel = DB::table('store_stock_levels')
                    ->where('store_id', $shipment->store_id)
                    ->where('inventory_id', $item['inventory_id'])
                    ->first();

                if ($stockLevel) {
                    // Update existing stock
                    DB::table('store_stock_levels')
                        ->where('store_stock_id', $stockLevel->store_stock_id)
                        ->update([
                            'current_quantity' => DB::raw('current_quantity + ' . $item['quantity']),
                            'last_delivery_date' => now()->toDateString(),
                            'last_delivery_quantity' => $item['quantity'],
                            'updated_at' => now(),
                        ]);
                } else {
                    // Create new stock level entry
                    DB::table('store_stock_levels')->insert([
                        'store_id' => $shipment->store_id,
                        'inventory_id' => $item['inventory_id'],
                        'current_quantity' => $item['quantity'],
                        'min_quantity' => 10, // Default min quantity
                        'max_quantity' => 100, // Default max quantity
                        'last_delivery_date' => now()->toDateString(),
                        'last_delivery_quantity' => $item['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Update inventory quantity (reduce warehouse stock)
                DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->update([
                        'quantity' => DB::raw('quantity - ' . $item['quantity']),
                        'updated_at' => now(),
                    ]);

                // Create stock transaction
                DB::table('stock_transactions')->insert([
                    'inventory_id' => $item['inventory_id'],
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_price'],
                    'reference_type' => 'outbound_shipment',
                    'reference_id' => $shipmentId,
                    'created_by' => auth()->id(),
                    'remarks' => 'Shipped to store via shipment ' . $shipment->shipment_number,
                    'transaction_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update vehicle status back to available
            if ($shipment->assigned_vehicle_id) {
                DB::table('logistics_vehicles')
                    ->where('vehicle_id', $shipment->assigned_vehicle_id)
                    ->update([
                        'status' => 'available',
                        'current_location' => $shipment->store_id ? DB::table('tgif_stores')->where('store_id', $shipment->store_id)->value('address') : null,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();

            $this->showDeliveryConfirmModal = false;
            $this->deliveryData = [
                'shipment_id' => null,
                'store_manager_name' => '',
                'store_manager_phone' => '',
                'delivery_notes' => '',
            ];

            session()->flash('success', 'Delivery confirmed successfully! Store inventory updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to confirm delivery: ' . $e->getMessage());
        }
    }

    public function showShipmentItems($shipmentId)
    {
        $shipment = DB::table('outbound_shipments')
            ->where('shipment_id', $shipmentId)
            ->first();

        if ($shipment) {
            $this->selectedShipmentItems = json_decode($shipment->shipment_items, true);
            $this->selectedShipmentNumber = $shipment->shipment_number;
            
            $store = DB::table('tgif_stores')
                ->where('store_id', $shipment->store_id)
                ->first();
            
            $this->selectedShipmentStore = $store ? $store->store_name : 'Unknown Store';
        }

        $this->showItemsModal = true;
    }

    public function markAsPartiallyDelivered($shipmentId)
    {
        try {
            DB::table('outbound_shipments')
                ->where('shipment_id', $shipmentId)
                ->update([
                    'status' => 'partially_delivered',
                    'updated_at' => now(),
                ]);

            session()->flash('success', 'Shipment marked as partially delivered!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
}
?>
<div>
    <!-- Page Header with Blue Theme -->
    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 rounded-r-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-truck text-blue-600 mr-2"></i>
                    Outbound Logistics
                </h1>
                <p class="text-gray-600 mt-1">Track, manage and deliver shipments to stores</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    <i class="fas fa-store-alt mr-1"></i>
                    Store Deliveries
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Cards with Blue Theme -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @php
            // Count shipments by status
            $pending = DB::table('outbound_shipments')
                ->where('status', 'pending')
                ->count();
                
            $loading = DB::table('outbound_shipments')
                ->where('status', 'loading')
                ->count();
                
            $in_transit = DB::table('outbound_shipments')
                ->where('status', 'in_transit')
                ->count();
                
            $delivered_today = DB::table('outbound_shipments')
                ->whereDate('actual_completion_time', Carbon::today())
                ->where('status', 'delivered')
                ->count();
        @endphp
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $pending }}</p>
                    <p class="text-xs text-blue-600 mt-2">
                        <i class="fas fa-clock mr-1"></i> Awaiting dispatch
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clipboard-list text-blue-500 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Loading</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $loading }}</p>
                    <p class="text-xs text-amber-600 mt-2">
                        <i class="fas fa-boxes mr-1"></i> Being loaded
                    </p>
                </div>
                <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-truck-loading text-amber-500 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">In Transit</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $in_transit }}</p>
                    <p class="text-xs text-purple-600 mt-2">
                        <i class="fas fa-shipping-fast mr-1"></i> On the way
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-road text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Delivered Today</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $delivered_today }}</p>
                    <p class="text-xs text-green-600 mt-2">
                        <i class="fas fa-check-circle mr-1"></i> Successfully delivered
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-double text-green-500 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="space-y-6">
        <!-- Tabs Section -->
        <div class="bg-white rounded-xl shadow-sm border border-blue-100">
            <!-- Tabs Header -->
            <div class="border-b border-gray-200">
                <div class="flex space-x-1 px-6 pt-4">
                    <button class="px-4 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 
                                {{ $tab == 'pending' ? 'bg-blue-50 text-blue-700 border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}" 
                            wire:click="$set('tab', 'pending')">
                        <i class="fas fa-clock mr-2"></i>
                        Pending
                        @if($pending > 0)
                        <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                            {{ $pending }}
                        </span>
                        @endif
                    </button>
                    
                    <button class="px-4 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 
                                {{ $tab == 'loading' ? 'bg-blue-50 text-blue-700 border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}" 
                            wire:click="$set('tab', 'loading')">
                        <i class="fas fa-truck-loading mr-2"></i>
                        Loading
                        @if($loading > 0)
                        <span class="ml-2 px-2 py-1 text-xs bg-amber-100 text-amber-800 rounded-full">
                            {{ $loading }}
                        </span>
                        @endif
                    </button>
                    
                    <button class="px-4 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 
                                {{ $tab == 'in_transit' ? 'bg-blue-50 text-blue-700 border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}" 
                            wire:click="$set('tab', 'in_transit')">
                        <i class="fas fa-shipping-fast mr-2"></i>
                        In Transit
                        @if($in_transit > 0)
                        <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">
                            {{ $in_transit }}
                        </span>
                        @endif
                    </button>
                    
                    <button class="px-4 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 
                                {{ $tab == 'delivered' ? 'bg-blue-50 text-blue-700 border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}" 
                            wire:click="$set('tab', 'delivered')">
                        <i class="fas fa-check-circle mr-2"></i>
                        Delivered
                        @if($delivered_today > 0)
                        <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                            {{ $delivered_today }}
                        </span>
                        @endif
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex-1 flex flex-col md:flex-row gap-4">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" 
                                   class="pl-10 pr-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="Search Shipment, Store..." 
                                   wire:model.debounce.300ms="search">
                        </div>
                        
                        @php
                            $stores = DB::table('tgif_stores')
                                ->select('store_id', 'store_name', 'store_code')
                                ->where('status', 'active')
                                ->orderBy('store_name')
                                ->get();
                            
                            $vehicles = DB::table('logistics_vehicles')
                                ->select('vehicle_id', 'vehicle_number', 'vehicle_type')
                                ->where('status', '!=', 'out_of_service')
                                ->orderBy('vehicle_number')
                                ->get();
                        @endphp
                        
                        <div class="flex gap-4">
                            <select class="border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    wire:model="storeFilter">
                                <option value="">All Stores</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->store_id }}">{{ $store->store_code }} - {{ $store->store_name }}</option>
                                @endforeach
                            </select>
                            
                            <select class="border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    wire:model="vehicleFilter">
                                <option value="">All Vehicles</option>
                                @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->vehicle_id }}">{{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})</option>
                                @endforeach
                            </select>
                            
                            <input type="date" 
                                   class="border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="From Date"
                                   wire:model="dateFrom">
                            
                            <input type="date" 
                                   class="border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="To Date"
                                   wire:model="dateTo">
                        </div>
                    </div>
                    
                    @if($search || $storeFilter || $vehicleFilter || $dateFrom || $dateTo)
                    <button class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set(['search' => '', 'storeFilter' => '', 'vehicleFilter' => '', 'dateFrom' => '', 'dateTo' => ''])">
                        <i class="fas fa-times mr-2"></i>
                        Clear Filters
                    </button>
                    @endif
                </div>
            </div>

            <!-- Shipments List -->
            <div class="p-6">
                @php
                    $shipmentsQuery = DB::table('outbound_shipments as os')
                        ->join('tgif_stores as ts', 'os.store_id', '=', 'ts.store_id')
                        ->leftJoin('logistics_vehicles as lv', 'os.assigned_vehicle_id', '=', 'lv.vehicle_id')
                        ->select(
                            'os.shipment_id',
                            'os.shipment_number',
                            'os.status',
                            'os.estimated_arrival_time',
                            'os.preferred_delivery_window_start',
                            'os.preferred_delivery_window_end',
                            'os.total_quantity',
                            'os.total_value',
                            'os.current_eta_minutes',
                            'os.current_latitude',
                            'os.current_longitude',
                            'os.requires_refrigeration',
                            'os.assigned_vehicle_id',
                            'os.assigned_driver_name',
                            'ts.store_name',
                            'ts.store_code',
                            'ts.address as store_address',
                            'ts.contact_person as store_contact',
                            'lv.vehicle_number',
                            'lv.vehicle_type'
                        )
                        ->when($tab == 'pending', function ($query) {
                            $query->where('os.status', 'pending');
                        })
                        ->when($tab == 'loading', function ($query) {
                            $query->where('os.status', 'loading');
                        })
                        ->when($tab == 'in_transit', function ($query) {
                            $query->where('os.status', 'in_transit');
                        })
                        ->when($tab == 'delivered', function ($query) {
                            $query->where('os.status', 'delivered');
                        })
                        ->when($dateFrom, function ($query) use ($dateFrom) {
                            $query->whereDate('os.estimated_arrival_time', '>=', $dateFrom);
                        })
                        ->when($dateTo, function ($query) use ($dateTo) {
                            $query->whereDate('os.estimated_arrival_time', '<=', $dateTo);
                        })
                        ->when($storeFilter, function ($query) use ($storeFilter) {
                            $query->where('os.store_id', '=', $storeFilter);
                        })
                        ->when($vehicleFilter, function ($query) use ($vehicleFilter) {
                            $query->where('os.assigned_vehicle_id', '=', $vehicleFilter);
                        })
                        ->when($search, function ($query) use ($search) {
                            $query->where(function ($q) use ($search) {
                                $q->where('os.shipment_number', 'like', '%' . $search . '%')
                                  ->orWhere('ts.store_name', 'like', '%' . $search . '%')
                                  ->orWhere('ts.store_code', 'like', '%' . $search . '%');
                            });
                        })
                        ->orderBy('os.estimated_arrival_time', 'asc')
                        ->orderBy('os.created_at', 'desc');

                    $shipments = $shipmentsQuery->paginate(15);
                @endphp
                
                @if($shipments->count() > 0)
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-hashtag mr-1"></i> Shipment
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-store mr-1"></i> Store
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-truck mr-1"></i> Vehicle
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-clock mr-1"></i> ETA
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-boxes mr-1"></i> Details
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-map-marker-alt mr-1"></i> Tracking
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-blue-800 uppercase tracking-wider">
                                    <i class="fas fa-cogs mr-1"></i> Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($shipments as $shipment)
                            @php
                                $etaDate = \Carbon\Carbon::parse($shipment->estimated_arrival_time);
                                $isOverdue = $etaDate->lt(Carbon::now()) && !in_array($shipment->status, ['delivered', 'arrived']);
                                $isToday = $etaDate->isToday();
                                $statusColors = [
                                    'pending' => 'bg-blue-100 text-blue-800',
                                    'planned' => 'bg-indigo-100 text-indigo-800',
                                    'loading' => 'bg-amber-100 text-amber-800',
                                    'in_transit' => 'bg-purple-100 text-purple-800',
                                    'arrived' => 'bg-green-100 text-green-800',
                                    'unloading' => 'bg-yellow-100 text-yellow-800',
                                    'delivered' => 'bg-green-100 text-green-800',
                                    'partially_delivered' => 'bg-orange-100 text-orange-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-blue-700">{{ $shipment->shipment_number }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$shipment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ str_replace('_', ' ', ucfirst($shipment->status)) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        <i class="fas fa-store text-blue-500 mr-1"></i>
                                        {{ $shipment->store_code }} - {{ $shipment->store_name }}
                                    </div>
                                    <div class="text-sm text-gray-500 truncate max-w-xs">{{ $shipment->store_address }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($shipment->vehicle_number)
                                    <div class="font-medium text-gray-900">
                                        <i class="fas fa-truck text-gray-500 mr-1"></i>
                                        {{ $shipment->vehicle_number }}
                                    </div>
                                    <div class="text-sm text-gray-500">{{ $shipment->assigned_driver_name }}</div>
                                    @else
                                    <span class="text-gray-400 italic">Not assigned</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="{{ $isOverdue ? 'text-red-600 font-semibold' : ($isToday ? 'text-blue-600 font-semibold' : 'text-gray-900') }}">
                                        <i class="fas fa-calendar-day mr-1"></i>
                                        {{ $etaDate->format('M d, Y H:i') }}
                                        @if($isToday)
                                        <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Today</span>
                                        @elseif($isOverdue)
                                        <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Late</span>
                                        @endif
                                    </div>
                                    @if($shipment->current_eta_minutes && $shipment->status == 'in_transit')
                                    <div class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        {{ $shipment->current_eta_minutes }} min ETA
                                    </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <span class="inline-flex items-center mr-3">
                                            <i class="fas fa-box mr-1 text-blue-500"></i>
                                            {{ $shipment->total_quantity }} items
                                        </span>
                                        <span class="inline-flex items-center">
                                            <i class="fas fa-money-bill-wave mr-1 text-green-500"></i>
                                            ${{ number_format($shipment->total_value, 2) }}
                                        </span>
                                    </div>
                                    @if($shipment->requires_refrigeration)
                                    <div class="text-xs text-cyan-600 mt-1">
                                        <i class="fas fa-snowflake mr-1"></i>
                                        Refrigerated shipment
                                    </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($shipment->current_latitude && $shipment->current_longitude)
                                    <div class="text-sm text-gray-900">
                                        <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                        Tracking active
                                    </div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs">
                                        {{ $shipment->current_latitude }}, {{ $shipment->current_longitude }}
                                    </div>
                                    @else
                                    <span class="text-gray-400 italic">No tracking</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <!-- Status update buttons based on current status -->
                                        @if($shipment->status == 'pending')
                                        <button class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                wire:click="$set('updateData.shipment_id', {{ $shipment->shipment_id }})"
                                                wire:click="$toggle('showUpdateModal')"
                                                title="Update Details">
                                            <i class="fas fa-edit mr-1.5"></i>
                                            <span class="hidden md:inline">Update</span>
                                        </button>
                                        
                                        <button class="inline-flex items-center px-3 py-1.5 border border-amber-300 text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors"
                                                wire:click="updateShipmentStatus({{ $shipment->shipment_id }}, 'loading')"
                                                title="Mark as Loading">
                                            <i class="fas fa-truck-loading mr-1.5"></i>
                                            <span class="hidden md:inline">Loading</span>
                                        </button>
                                        @endif
                                        
                                        @if($shipment->status == 'loading')
                                        <button class="inline-flex items-center px-3 py-1.5 border border-purple-300 text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors"
                                                wire:click="updateShipmentStatus({{ $shipment->shipment_id }}, 'in_transit')"
                                                title="Mark as In Transit">
                                            <i class="fas fa-play mr-1.5"></i>
                                            <span class="hidden md:inline">Depart</span>
                                        </button>
                                        @endif
                                        
                                        @if($shipment->status == 'in_transit')
                                        <button class="inline-flex items-center px-3 py-1.5 border border-green-300 text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors"
                                                wire:click="updateShipmentStatus({{ $shipment->shipment_id }}, 'arrived')"
                                                title="Mark as Arrived">
                                            <i class="fas fa-flag-checkered mr-1.5"></i>
                                            <span class="hidden md:inline">Arrived</span>
                                        </button>
                                        
                                        <button class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                                                wire:click="$set('trackingData.shipment_id', {{ $shipment->shipment_id }})"
                                                wire:click="$toggle('showTrackingModal')"
                                                title="Update Tracking">
                                            <i class="fas fa-satellite-dish mr-1.5"></i>
                                            <span class="hidden md:inline">Track</span>
                                        </button>
                                        @endif
                                        
                                        @if($shipment->status == 'arrived')
                                        <button class="inline-flex items-center px-3 py-1.5 border border-green-600 text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors"
                                                wire:click="$set('deliveryData.shipment_id', {{ $shipment->shipment_id }})"
                                                wire:click="$toggle('showDeliveryConfirmModal')"
                                                title="Confirm Delivery">
                                            <i class="fas fa-check-double mr-1.5"></i>
                                            <span class="hidden md:inline">Deliver</span>
                                        </button>
                                        
                                        <button class="inline-flex items-center px-3 py-1.5 border border-orange-300 text-orange-700 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors"
                                                wire:click="markAsPartiallyDelivered({{ $shipment->shipment_id }})"
                                                title="Partial Delivery">
                                            <i class="fas fa-box-open mr-1.5"></i>
                                            <span class="hidden md:inline">Partial</span>
                                        </button>
                                        @endif
                                        
                                        <!-- View Items Button -->
                                        <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                                wire:click="showShipmentItems({{ $shipment->shipment_id }})"
                                                title="View Items">
                                            <i class="fas fa-list-ul mr-1.5"></i>
                                            <span class="hidden md:inline">Items</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="mt-6">
                    {{ $shipments->links() }}
                </div>
                @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-truck text-blue-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Shipments Found</h3>
                    <p class="text-gray-500 mb-6">
                        @if($tab == 'pending')
                        No pending shipments awaiting dispatch.
                        @elseif($tab == 'loading')
                        No shipments currently loading.
                        @elseif($tab == 'in_transit')
                        No shipments currently in transit.
                        @else
                        No delivered shipments.
                        @endif
                    </p>
                    @if($search || $storeFilter || $vehicleFilter || $dateFrom || $dateTo)
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                            wire:click="$set(['search' => '', 'storeFilter' => '', 'vehicleFilter' => '', 'dateFrom' => '', 'dateTo' => ''])">
                        <i class="fas fa-times mr-2"></i>
                        Clear Filters
                    </button>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <!-- Today's Scheduled Deliveries -->
        <div class="bg-white rounded-xl shadow-sm border border-blue-100">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            <i class="fas fa-calendar-day text-blue-600 mr-2"></i>
                            Today's Scheduled Deliveries
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Shipments scheduled for delivery today</p>
                    </div>
                    @php
                        $todayCount = DB::table('outbound_shipments')
                            ->whereDate('estimated_arrival_time', Carbon::today())
                            ->whereIn('status', ['pending', 'loading', 'in_transit', 'arrived'])
                            ->count();
                    @endphp
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        {{ $todayCount }} shipments
                    </span>
                </div>
                
                @php
                    $todayShipments = DB::table('outbound_shipments as os')
                        ->join('tgif_stores as ts', 'os.store_id', '=', 'ts.store_id')
                        ->leftJoin('logistics_vehicles as lv', 'os.assigned_vehicle_id', '=', 'lv.vehicle_id')
                        ->select(
                            'os.shipment_id',
                            'os.shipment_number',
                            'os.status',
                            'os.estimated_arrival_time',
                            'os.total_quantity',
                            'os.total_value',
                            'ts.store_name',
                            'ts.store_code',
                            'lv.vehicle_number'
                        )
                        ->whereDate('os.estimated_arrival_time', Carbon::today())
                        ->whereIn('os.status', ['pending', 'loading', 'in_transit', 'arrived'])
                        ->orderBy('os.status')
                        ->orderBy('os.estimated_arrival_time')
                        ->limit(12)
                        ->get();
                @endphp
                
                @if($todayShipments->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($todayShipments as $shipment)
                    @php
                        $etaTime = \Carbon\Carbon::parse($shipment->estimated_arrival_time);
                        $statusColors = [
                            'pending' => 'border-blue-200 bg-blue-50',
                            'loading' => 'border-amber-200 bg-amber-50',
                            'in_transit' => 'border-purple-200 bg-purple-50',
                            'arrived' => 'border-green-200 bg-green-50',
                        ];
                        $cardClass = $statusColors[$shipment->status] ?? 'border-gray-200 bg-gray-50';
                    @endphp
                    <div class="border rounded-lg p-4 hover:shadow-sm transition-all {{ $cardClass }}">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-bold text-gray-800">{{ $shipment->shipment_number }}</h4>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-store mr-1"></i>
                                    {{ $shipment->store_code }}
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium 
                                        {{ $statusColors[$shipment->status] ? str_replace('bg-', 'text-', str_replace(' border-', ' ', $cardClass)) : 'bg-gray-100 text-gray-800' }}">
                                {{ str_replace('_', ' ', ucfirst($shipment->status)) }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-clock mr-2 text-blue-500"></i>
                                <span>{{ $etaTime->format('H:i') }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-boxes mr-2 text-green-500"></i>
                                <span>{{ $shipment->total_quantity }} items</span>
                            </div>
                            @if($shipment->vehicle_number)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-truck mr-2 text-gray-500"></i>
                                <span>{{ $shipment->vehicle_number }}</span>
                            </div>
                            @endif
                        </div>
                        <div class="flex items-center justify-between mt-4">
                            <div class="font-semibold text-gray-900">
                                ${{ number_format($shipment->total_value, 2) }}
                            </div>
                            <div class="flex space-x-2">
                                <button class="px-2 py-1 text-xs bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
                                        wire:click="showShipmentItems({{ $shipment->shipment_id }})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @if($shipment->status == 'in_transit')
                                <button class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors"
                                        wire:click="$set('trackingData.shipment_id', {{ $shipment->shipment_id }})"
                                        wire:click="$toggle('showTrackingModal')">
                                    <i class="fas fa-satellite"></i>
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-check text-blue-500"></i>
                    </div>
                    <h4 class="text-base font-medium text-gray-900 mb-1">No Deliveries Scheduled Today</h4>
                    <p class="text-sm text-gray-500">No shipments are scheduled for delivery today.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Update Shipment Modal -->
    @if($showUpdateModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-edit text-blue-600 mr-2"></i>
                        Update Shipment Details
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Update delivery schedule</p>
                </div>
                <button wire:click="$set('showUpdateModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="updateShipmentDetails({{ $updateData['shipment_id'] }})">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            New Estimated Arrival
                        </label>
                        <input type="datetime-local" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="updateData.new_estimated_arrival" 
                               required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1"></i>
                                Delivery Window Start
                            </label>
                            <input type="time" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="updateData.new_delivery_window_start">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1"></i>
                                Delivery Window End
                            </label>
                            <input type="time" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="updateData.new_delivery_window_end">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showUpdateModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Update Details
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Tracking Update Modal -->
    @if($showTrackingModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-satellite-dish text-blue-600 mr-2"></i>
                        Update Tracking
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Update shipment location and status</p>
                </div>
                <button wire:click="$set('showTrackingModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="updateTracking({{ $trackingData['shipment_id'] }})">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                Latitude
                            </label>
                            <input type="number" step="0.00000001"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="trackingData.latitude" 
                                   placeholder="e.g., 40.7128"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                Longitude
                            </label>
                            <input type="number" step="0.00000001"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   wire:model="trackingData.longitude" 
                                   placeholder="e.g., -74.0060"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Status
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="trackingData.status">
                            <option value="in_transit">In Transit</option>
                            <option value="arrived">Arrived at Store</option>
                            <option value="unloading">Unloading</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Notes
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="trackingData.notes" 
                                  placeholder="Enter tracking notes..."
                                  rows="3"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showTrackingModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Update Tracking
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delivery Confirmation Modal -->
    @if($showDeliveryConfirmModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-check-double text-green-600 mr-2"></i>
                        Confirm Delivery
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Complete shipment delivery to store</p>
                </div>
                <button wire:click="$set('showDeliveryConfirmModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="confirmDelivery({{ $deliveryData['shipment_id'] }})">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tie mr-1"></i>
                            Store Manager Name
                        </label>
                        <input type="text" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="deliveryData.store_manager_name" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1"></i>
                            Store Manager Phone
                        </label>
                        <input type="tel" 
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               wire:model="deliveryData.store_manager_phone" 
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Delivery Notes
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="deliveryData.delivery_notes" 
                                  placeholder="Enter delivery notes..."
                                  rows="3"></textarea>
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2 mt-0.5"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-semibold">Important:</p>
                                <p class="mt-1">Confirming delivery will:</p>
                                <ul class="list-disc pl-5 mt-1 space-y-1">
                                    <li>Update shipment status to "Delivered"</li>
                                    <li>Update store stock levels</li>
                                    <li>Reduce warehouse inventory</li>
                                    <li>Mark vehicle as available</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showDeliveryConfirmModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-check-double mr-2"></i>
                        Confirm Delivery
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Show Items Modal -->
    @if($showItemsModal)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-list-ul text-blue-600 mr-2"></i>
                        Shipment Items - {{ $selectedShipmentNumber }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Items being shipped to: {{ $selectedShipmentStore }}</p>
                </div>
                <button wire:click="$set('showItemsModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            @if(count($selectedShipmentItems) > 0)
            <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                @php
                    $totalValue = 0;
                    $totalQuantity = 0;
                @endphp
                
                @foreach($selectedShipmentItems as $item)
                    @php
                        $itemTotal = $item['quantity'] * $item['unit_price'];
                        $totalValue += $itemTotal;
                        $totalQuantity += $item['quantity'];
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4 hover:bg-blue-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $item['product_name'] ?? 'Unknown Product' }}</div>
                                @if($item['sku'])
                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="inline-flex items-center mr-4">
                                        <i class="fas fa-barcode mr-1 text-blue-600"></i>
                                        SKU: {{ $item['sku'] }}
                                    </span>
                                    @if($item['unit_of_measure'])
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-weight mr-1 text-blue-600"></i>
                                        UOM: {{ $item['unit_of_measure'] }}
                                    </span>
                                    @endif
                                </div>
                                @endif
                                <div class="text-sm text-gray-600 mt-2">
                                    <span class="inline-flex items-center mr-4">
                                        <i class="fas fa-boxes mr-1 text-blue-600"></i>
                                        Quantity: {{ $item['quantity'] }}
                                    </span>
                                    <span class="inline-flex items-center">
                                        <i class="fas fa-dollar-sign mr-1 text-blue-600"></i>
                                        Unit Price: ${{ number_format($item['unit_price'], 2) }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Line Total</div>
                                <div class="font-bold text-blue-700 text-lg">${{ number_format($itemTotal, 2) }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-bold text-gray-900">Total Items:</span>
                        <span class="ml-2 text-gray-700">{{ $totalQuantity }}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Total Shipment Value</div>
                        <div class="text-2xl font-bold text-blue-700">${{ number_format($totalValue, 2) }}</div>
                    </div>
                </div>
            </div>
            @else
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-box-open text-blue-500 text-2xl"></i>
                </div>
                <h4 class="text-lg font-medium text-gray-900 mb-2">No Items Found</h4>
                <p class="text-gray-500">This shipment doesn't contain any items.</p>
            </div>
            @endif
            
            <div class="mt-6 flex justify-end">
                <button wire:click="$set('showItemsModal', false)" 
                        class="px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif
</div>