<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new #[Layout('components.layouts.supplychain')] class extends Component
{
    use WithPagination;

    // Filter properties
    public $search = '';
    public $status = 'all';
    public $shipment_type = 'all';
    public $date_from = '';
    public $date_to = '';
    
    // View details
    public $showDetailsModal = false;
    public $selectedShipment = null;
    public $shipmentItems = [];
    
    // Update status
    public $showUpdateModal = false;
    public $newStatus = '';
    public $updateNotes = '';
    
    // Tracking
    public $showTrackingModal = false;
    public $trackingLocation = '';
    public $trackingNotes = '';
    
    // Cancel shipment
    public $showCancelModal = false;
    public $cancelReason = '';

    // Pagination
    public $perPage = 15;

    public function mount()
    {
        // $this->date_from = now()->subDays(30)->format('Y-m-d');
        // $this->date_to = now()->format('Y-m-d');
    }

    // Get all shipments with filters
    public function getShipmentsProperty()
{
    try {
        Log::info('Fetching shipments...');
        
        $query = DB::table('outbound_shipments')
            ->select(
                'outbound_shipments.*',
                'tgif_stores.store_name',
                'tgif_stores.store_code',
                'tgif_stores.city',
                'tgif_stores.state',
                'tgif_stores.contact_person',
                'tgif_stores.contact_phone',
                'logistics_vehicles.vehicle_number',
                'logistics_vehicles.vehicle_type'
            )
            ->leftJoin('tgif_stores', 'outbound_shipments.store_id', '=', 'tgif_stores.store_id')
            ->leftJoin('logistics_vehicles', 'outbound_shipments.assigned_vehicle_id', '=', 'logistics_vehicles.vehicle_id');
        
        // Apply filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('outbound_shipments.shipment_number', 'like', '%' . $this->search . '%')
                  ->orWhere('tgif_stores.store_name', 'like', '%' . $this->search . '%')
                  ->orWhere('tgif_stores.store_code', 'like', '%' . $this->search . '%')
                  ->orWhere('outbound_shipments.assigned_driver_name', 'like', '%' . $this->search . '%');
            });
        }
        
        if ($this->status !== 'all') {
            $query->where('outbound_shipments.status', $this->status);
        }
        
        if ($this->shipment_type !== 'all') {
            $query->where('outbound_shipments.shipment_type', $this->shipment_type);
        }
        
        if ($this->date_from) {
            $query->whereDate('outbound_shipments.scheduled_date', '>=', $this->date_from);
        }
        
        if ($this->date_to) {
            $query->whereDate('outbound_shipments.scheduled_date', '<=', $this->date_to);
        }
        
        $query->orderBy('outbound_shipments.scheduled_date', 'desc')
              ->orderBy('outbound_shipments.created_at', 'desc');
        
        Log::info('Shipments query built', ['query' => $query->toSql()]);
        
        $results = $query->paginate($this->perPage);
        
        Log::info('Shipments fetched', ['count' => $results->count()]);
        
        return $results;
        
    } catch (\Exception $e) {
        Log::error('Error fetching shipments', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Return empty collection on error
        return collect([])->paginate($this->perPage);
    }
}

    // Get status statistics
public function getStatusStatsProperty()
{
    return DB::table('outbound_shipments')
        ->select(
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN status = "in_transit" THEN 1 ELSE 0 END) as in_transit'),
            DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
            DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            DB::raw('SUM(CASE WHEN status = "delayed" THEN 1 ELSE 0 END) as `delayed`'),
            DB::raw('SUM(total_value) as total_value')
        )
        ->when($this->date_from, function ($query) {
            $query->whereDate('scheduled_date', '>=', $this->date_from);
        })
        ->when($this->date_to, function ($query) {
            $query->whereDate('scheduled_date', '<=', $this->date_to);
        })
        ->first();
}

    // View shipment details
    public function viewDetails($shipmentId)
    {
        $this->selectedShipment = DB::table('outbound_shipments')
            ->select(
                'outbound_shipments.*',
                'tgif_stores.*',
                'logistics_vehicles.vehicle_number',
                'logistics_vehicles.vehicle_type',
                'logistics_vehicles.make',
                'logistics_vehicles.model',
                'logistics_vehicles.capacity_kg',
                'logistics_drivers.license_number'
            )
            ->leftJoin('tgif_stores', 'outbound_shipments.store_id', '=', 'tgif_stores.store_id')
            ->leftJoin('logistics_vehicles', 'outbound_shipments.assigned_vehicle_id', '=', 'logistics_vehicles.vehicle_id')
            ->leftJoin('logistics_drivers', 'logistics_vehicles.driver_id', '=', 'logistics_drivers.driver_id')
            ->where('outbound_shipments.shipment_id', $shipmentId)
            ->first();

        if ($this->selectedShipment) {
            $this->shipmentItems = json_decode($this->selectedShipment->shipment_items, true) ?? [];
            $this->showDetailsModal = true;
        }
    }

    // Update shipment status
    public function updateStatus($shipmentId)
    {
        $this->selectedShipment = DB::table('outbound_shipments')
            ->where('shipment_id', $shipmentId)
            ->first();
        
        $this->newStatus = $this->selectedShipment->status;
        $this->showUpdateModal = true;
    }

    public function saveStatusUpdate()
    {
        $this->validate([
            'newStatus' => 'required|in:pending,in_transit,delivered,cancelled,delayed',
            'updateNotes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Update shipment status
            DB::table('outbound_shipments')
                ->where('shipment_id', $this->selectedShipment->shipment_id)
                ->update([
                    'status' => $this->newStatus,
                    'updated_at' => now(),
                    'delivery_notes' => $this->updateNotes ?: $this->selectedShipment->delivery_notes
                ]);

            // If cancelled, restore inventory
            if ($this->newStatus === 'cancelled' && $this->selectedShipment->status !== 'cancelled') {
                $items = json_decode($this->selectedShipment->shipment_items, true) ?? [];
                
                foreach ($items as $item) {
                    // Restore inventory quantity
                    DB::table('inventories')
                        ->where('inventory_id', $item['inventory_id'])
                        ->increment('quantity', $item['quantity']);
                    
                    // Update stock transactions
                    DB::table('stock_transactions')
                        ->where('reference_id', $this->selectedShipment->shipment_id)
                        ->where('reference_type', 'outbound_shipment')
                        ->update([
                            'status' => 'cancelled',
                            'remarks' => 'Cancelled: ' . $this->updateNotes
                        ]);
                }
            }

            DB::commit();

            $this->showUpdateModal = false;
            $this->reset(['newStatus', 'updateNotes', 'selectedShipment']);
            
            session()->flash('success', 'Shipment status updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    // Add tracking update
public function addTracking($shipmentId)
{
    try {
        Log::info('Starting addTracking method', [
            'shipment_id' => $shipmentId,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        $this->selectedShipment = DB::table('outbound_shipments')
            ->where('shipment_id', $shipmentId)
            ->first();
        
        Log::info('Shipment fetched for tracking', [
            'shipment_exists' => !is_null($this->selectedShipment),
            'shipment_id' => $shipmentId,
            'shipment_number' => $this->selectedShipment->shipment_number ?? 'N/A',
            'current_status' => $this->selectedShipment->status ?? 'N/A'
        ]);
        
        if (!$this->selectedShipment) {
            Log::warning('Shipment not found for tracking', ['shipment_id' => $shipmentId]);
            session()->flash('error', 'Shipment not found!');
            return;
        }
        
        // Check if shipment is eligible for tracking
        $eligibleStatuses = ['in_transit', 'pending', 'loading', 'arrived', 'unloading'];
        if (!in_array($this->selectedShipment->status, $eligibleStatuses)) {
            Log::warning('Shipment not eligible for tracking update', [
                'shipment_id' => $shipmentId,
                'current_status' => $this->selectedShipment->status,
                'eligible_statuses' => $eligibleStatuses
            ]);
            session()->flash('error', 'Tracking can only be added to shipments that are in transit or pending delivery');
            return;
        }
        
        // Pre-fill location if there's existing GPS data
        if ($this->selectedShipment->current_latitude && $this->selectedShipment->current_longitude) {
            $this->trackingLocation = "Lat: {$this->selectedShipment->current_latitude}, Long: {$this->selectedShipment->current_longitude}";
        } else {
            $this->trackingLocation = ''; // Reset
        }
        
        $this->showTrackingModal = true;
        
        Log::info('Tracking modal opened', [
            'shipment_id' => $shipmentId,
            'shipment_number' => $this->selectedShipment->shipment_number,
            'prefilled_location' => !empty($this->trackingLocation)
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error opening tracking modal', [
            'shipment_id' => $shipmentId,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString()
        ]);
        
        session()->flash('error', 'Failed to open tracking modal: ' . $e->getMessage());
    }
}

    public function saveTracking()
{
    try {
        Log::info('Starting saveTracking method', [
            'shipment_id' => $this->selectedShipment->shipment_id ?? 'N/A',
            'shipment_number' => $this->selectedShipment->shipment_number ?? 'N/A',
            'tracking_location' => $this->trackingLocation,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        $this->validate([
            'trackingLocation' => 'required|string|max:255',
            'trackingNotes' => 'nullable|string|max:500'
        ]);
        
        Log::info('Validation passed for tracking data');
        
        // Parse location for GPS coordinates (if provided in format "Lat: X.XXXX, Long: X.XXXX")
        $latitude = null;
        $longitude = null;
        
        if (preg_match('/Lat:\s*([-+]?\d*\.?\d+),\s*Long:\s*([-+]?\d*\.?\d+)/i', $this->trackingLocation, $matches)) {
            $latitude = floatval($matches[1]);
            $longitude = floatval($matches[2]);
            Log::info('GPS coordinates parsed from location', [
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
        }
        
        // Get existing ETA updates or initialize empty array
        $etaUpdates = [];
        if (!empty($this->selectedShipment->eta_updates)) {
            try {
                $etaUpdates = json_decode($this->selectedShipment->eta_updates, true) ?? [];
            } catch (\Exception $e) {
                Log::warning('Failed to decode existing ETA updates', [
                    'eta_updates' => $this->selectedShipment->eta_updates,
                    'error' => $e->getMessage()
                ]);
                $etaUpdates = [];
            }
        }
        
        // Add new ETA update if we have current_eta_minutes
        if ($this->selectedShipment->current_eta_minutes) {
            $etaUpdates[] = [
                'timestamp' => now()->toDateTimeString(),
                'eta_minutes' => $this->selectedShipment->current_eta_minutes,
                'location' => $this->trackingLocation,
                'notes' => $this->trackingNotes
            ];
            
            // Keep only last 10 ETA updates
            if (count($etaUpdates) > 10) {
                $etaUpdates = array_slice($etaUpdates, -10);
            }
        }
        
        // Get existing GPS tracking data or initialize empty array
        $gpsTrackingData = [];
        if (!empty($this->selectedShipment->gps_tracking_data)) {
            try {
                $gpsTrackingData = json_decode($this->selectedShipment->gps_tracking_data, true) ?? [];
            } catch (\Exception $e) {
                Log::warning('Failed to decode existing GPS tracking data', [
                    'gps_data' => $this->selectedShipment->gps_tracking_data,
                    'error' => $e->getMessage()
                ]);
                $gpsTrackingData = [];
            }
        }
        
        // Add new GPS tracking point if coordinates were parsed
        if ($latitude !== null && $longitude !== null) {
            $gpsTrackingData[] = [
                'timestamp' => now()->toDateTimeString(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location' => $this->trackingLocation,
                'speed_kmh' => $this->selectedShipment->current_speed_kmh,
                'notes' => $this->trackingNotes
            ];
            
            // Keep only last 50 GPS points
            if (count($gpsTrackingData) > 50) {
                $gpsTrackingData = array_slice($gpsTrackingData, -50);
            }
        }
        
        // Update the shipment with tracking data
        $updateData = [
            'current_latitude' => $latitude ?? $this->selectedShipment->current_latitude,
            'current_longitude' => $longitude ?? $this->selectedShipment->current_longitude,
            'last_gps_update' => now(),
            'eta_updates' => !empty($etaUpdates) ? json_encode($etaUpdates) : null,
            'gps_tracking_data' => !empty($gpsTrackingData) ? json_encode($gpsTrackingData) : null,
            'delivery_notes' => $this->selectedShipment->delivery_notes . "\n\n[Tracking Update " . now()->format('Y-m-d H:i:s') . "]:\n" . 
                               "Location: " . $this->trackingLocation . "\n" .
                               ($this->trackingNotes ? "Notes: " . $this->trackingNotes . "\n" : ""),
            'updated_at' => now()
        ];
        
        Log::info('Updating shipment with tracking data', [
            'update_fields' => array_keys($updateData),
            'has_gps_coordinates' => ($latitude !== null && $longitude !== null),
            'eta_updates_count' => count($etaUpdates),
            'gps_points_count' => count($gpsTrackingData)
        ]);
        
        $updated = DB::table('outbound_shipments')
            ->where('shipment_id', $this->selectedShipment->shipment_id)
            ->update($updateData);
        
        if ($updated) {
            Log::info('Tracking update saved successfully', [
                'shipment_id' => $this->selectedShipment->shipment_id,
                'rows_affected' => $updated,
                'location' => $this->trackingLocation
            ]);
            
            // Send notifications if enabled
            if ($this->selectedShipment->notifications_enabled) {
                $this->sendTrackingNotification($this->selectedShipment->shipment_id, $this->trackingLocation, $this->trackingNotes);
            }
            
            $this->showTrackingModal = false;
            $this->reset(['trackingLocation', 'trackingNotes', 'selectedShipment']);
            
            session()->flash('success', 'Tracking update added successfully!');
            
        } else {
            Log::warning('No rows affected when updating tracking', [
                'shipment_id' => $this->selectedShipment->shipment_id
            ]);
            
            session()->flash('error', 'Failed to save tracking update - no changes made.');
        }

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Tracking validation failed', ['errors' => $e->errors()]);
        session()->flash('error', 'Please check your input: ' . implode(', ', array_flatten($e->errors())));
        
    } catch (\Exception $e) {
        Log::error('Error saving tracking update', [
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'shipment_id' => $this->selectedShipment->shipment_id ?? 'N/A'
        ]);
        
        session()->flash('error', 'Failed to add tracking: ' . $e->getMessage());
    }
}

// Optional: Add notification method
private function sendTrackingNotification($shipmentId, $location, $notes)
{
    try {
        // Get store contact info
        $shipment = DB::table('outbound_shipments')
            ->join('tgif_stores', 'outbound_shipments.store_id', '=', 'tgif_stores.store_id')
            ->where('outbound_shipments.shipment_id', $shipmentId)
            ->select('tgif_stores.store_name', 'tgif_stores.contact_person', 'tgif_stores.contact_email', 'tgif_stores.contact_phone', 'outbound_shipments.shipment_number')
            ->first();
        
        if ($shipment && ($shipment->contact_email || $shipment->contact_phone)) {
            Log::info('Sending tracking notification', [
                'shipment_id' => $shipmentId,
                'store' => $shipment->store_name,
                'email' => $shipment->contact_email,
                'phone' => $shipment->contact_phone
            ]);
            
            // Here you would implement actual notification logic
            // For email: Mail::to($shipment->contact_email)->send(new TrackingUpdate($shipment, $location, $notes));
            // For SMS: Use Twilio or similar service
        }
        
    } catch (\Exception $e) {
        Log::error('Failed to send tracking notification', [
            'shipment_id' => $shipmentId,
            'error' => $e->getMessage()
        ]);
    }
}

    // Get tracking history
  public function getTrackingHistory($shipmentId)
{
    try {
        $shipment = DB::table('outbound_shipments')
            ->where('shipment_id', $shipmentId)
            ->select('eta_updates', 'gps_tracking_data', 'last_gps_update', 'current_latitude', 'current_longitude')
            ->first();
        
        if (!$shipment) {
            return collect();
        }
        
        $trackingHistory = [];
        
        // Parse ETA updates
        if (!empty($shipment->eta_updates)) {
            try {
                $etaUpdates = json_decode($shipment->eta_updates, true);
                if (is_array($etaUpdates)) {
                    foreach ($etaUpdates as $update) {
                        $trackingHistory[] = (object)[
                            'type' => 'eta_update',
                            'timestamp' => $update['timestamp'] ?? now()->toDateTimeString(),
                            'location' => $update['location'] ?? 'Unknown',
                            'notes' => $update['notes'] ?? null,
                            'eta_minutes' => $update['eta_minutes'] ?? null
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse ETA updates', ['error' => $e->getMessage()]);
            }
        }
        
        // Parse GPS tracking data
        if (!empty($shipment->gps_tracking_data)) {
            try {
                $gpsData = json_decode($shipment->gps_tracking_data, true);
                if (is_array($gpsData)) {
                    foreach ($gpsData as $point) {
                        $trackingHistory[] = (object)[
                            'type' => 'gps_tracking',
                            'timestamp' => $point['timestamp'] ?? now()->toDateTimeString(),
                            'location' => $point['location'] ?? 'Unknown',
                            'notes' => $point['notes'] ?? null,
                            'latitude' => $point['latitude'] ?? null,
                            'longitude' => $point['longitude'] ?? null,
                            'speed_kmh' => $point['speed_kmh'] ?? null
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse GPS tracking data', ['error' => $e->getMessage()]);
            }
        }
        
        // Sort by timestamp descending
        usort($trackingHistory, function($a, $b) {
            return strtotime($b->timestamp) - strtotime($a->timestamp);
        });
        
        return collect($trackingHistory);
        
    } catch (\Exception $e) {
        Log::error('Error getting tracking history', [
            'shipment_id' => $shipmentId,
            'error' => $e->getMessage()
        ]);
        return collect();
    }
}

    // Cancel shipment
    public function cancelShipment($shipmentId)
    {
        $this->selectedShipment = DB::table('outbound_shipments')
            ->where('shipment_id', $shipmentId)
            ->first();
        
        $this->showCancelModal = true;
    }

    public function confirmCancel()
    {
        $this->validate([
            'cancelReason' => 'required|string|min:10|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Cancel shipment
            DB::table('outbound_shipments')
                ->where('shipment_id', $this->selectedShipment->shipment_id)
                ->update([
                    'status' => 'cancelled',
                    'delivery_notes' => $this->cancelReason,
                    'updated_at' => now()
                ]);

            // Restore inventory items
            $items = json_decode($this->selectedShipment->shipment_items, true) ?? [];
            
            foreach ($items as $item) {
                // Restore inventory quantity
                DB::table('inventories')
                    ->where('inventory_id', $item['inventory_id'])
                    ->increment('quantity', $item['quantity']);
                
                // Update stock transaction status
                DB::table('stock_transactions')
                    ->where('reference_id', $this->selectedShipment->shipment_id)
                    ->where('reference_type', 'outbound_shipment')
                    ->update([
                        'status' => 'cancelled',
                        'remarks' => 'Cancelled: ' . $this->cancelReason
                    ]);
            }

            // Release vehicle if assigned
            if ($this->selectedShipment->assigned_vehicle_id) {
                DB::table('logistics_vehicles')
                    ->where('vehicle_id', $this->selectedShipment->assigned_vehicle_id)
                    ->update([
                        'status' => 'available',
                        'updated_at' => now()
                    ]);
            }

            DB::commit();

            $this->showCancelModal = false;
            $this->reset(['cancelReason', 'selectedShipment']);
            
            session()->flash('success', 'Shipment cancelled successfully and inventory restored!');

        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to cancel shipment: ' . $e->getMessage());
        }
    }

    // Export shipments (simple CSV for now)
    public function exportShipments()
    {
        $shipments = DB::table('outbound_shipments')
            ->select(
                'outbound_shipments.*',
                'tgif_stores.store_name',
                'tgif_stores.store_code',
                'tgif_stores.city',
                'tgif_stores.state'
            )
            ->leftJoin('tgif_stores', 'outbound_shipments.store_id', '=', 'tgif_stores.store_id')
            ->when($this->status !== 'all', function ($query) {
                $query->where('outbound_shipments.status', $this->status);
            })
            ->when($this->date_from, function ($query) {
                $query->whereDate('scheduled_date', '>=', $this->date_from);
            })
            ->when($this->date_to, function ($query) {
                $query->whereDate('scheduled_date', '<=', $this->date_to);
            })
            ->orderBy('scheduled_date', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="outbound_shipments_' . date('Y-m-d_H-i') . '.csv"',
        ];

        $callback = function () use ($shipments) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'Shipment Number',
                'Store Code',
                'Store Name',
                'City',
                'State',
                'Shipment Type',
                'Scheduled Date',
                'Status',
                'Total Quantity',
                'Total Value',
                'Assigned Driver',
                'Assigned Vehicle',
                'Delivery Notes',
                'Created At'
            ]);

            // Data
            foreach ($shipments as $shipment) {
                fputcsv($file, [
                    $shipment->shipment_number,
                    $shipment->store_code,
                    $shipment->store_name,
                    $shipment->city,
                    $shipment->state,
                    ucfirst(str_replace('_', ' ', $shipment->shipment_type)),
                    $shipment->scheduled_date,
                    ucfirst($shipment->status),
                    $shipment->total_quantity,
                    $shipment->total_value,
                    $shipment->assigned_driver_name,
                    $shipment->assigned_vehicle_id,
                    $shipment->delivery_notes,
                    $shipment->created_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Reset all filters
    public function resetFilters()
    {
        $this->reset(['search', 'status', 'shipment_type', 'date_from', 'date_to']);
        $this->date_from = now()->subDays(30)->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }
}
?>

<div>
    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 rounded-r-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-truck-moving text-blue-600 mr-2"></i>
                    Outbound Shipments
                </h1>
                <p class="text-gray-600 mt-1">Monitor and manage all outgoing shipments</p>
            </div>
            <div class="flex items-center space-x-2">
                <button wire:click="exportShipments"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-file-export mr-2"></i>
                    Export CSV
                </button>
                <a href="{{ route('supplychain.logistic') }}"
                   class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i>
                    New Shipment
                </a>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-green-500 mr-3"></i>
            <span class="text-green-800">{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
            <span class="text-red-800">{{ session('error') }}</span>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-truck-loading text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Shipments</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $this->statusStats->total ?? 0 }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-clock text-amber-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $this->statusStats->pending ?? 0 }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Delivered</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $this->statusStats->delivered ?? 0 }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Value</p>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($this->statusStats->total_value ?? 0, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">
                <i class="fas fa-filter text-blue-600 mr-2"></i>
                Filters
            </h2>
            <button wire:click="resetFilters"
                    class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-redo mr-1"></i>
                Reset
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-search mr-1"></i>
                    Search
                </label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" 
                           class="pl-10 pr-4 py-2.5 w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="Shipment, Store, Driver..."
                           wire:model.debounce.300ms="search">
                </div>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-tag mr-1"></i>
                    Status
                </label>
                <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        wire:model="status">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_transit">In Transit</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="delayed">Delayed</option>
                </select>
            </div>
            
            <!-- Shipment Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-truck mr-1"></i>
                    Shipment Type
                </label>
                <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        wire:model="shipment_type">
                    <option value="all">All Types</option>
                    <option value="scheduled_replenishment">Scheduled Replenishment</option>
                    <option value="emergency_replenishment">Emergency Replenishment</option>
                    <option value="new_store_setup">New Store Setup</option>
                    <option value="promotional_material">Promotional Material</option>
                    <option value="equipment_delivery">Equipment Delivery</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    Date Range
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" 
                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           wire:model="date_from">
                    <input type="date" 
                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           wire:model="date_to">
                </div>
            </div>
        </div>
    </div>

    <!-- Shipments Table -->
    <div class="bg-white rounded-xl shadow-sm border border-blue-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-blue-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                            Shipment Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                            Store
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                            Schedule & Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                            Items & Value
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->shipments as $shipment)
                    <tr class="hover:bg-blue-50 transition-colors">
                        <!-- Shipment Details -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="font-bold text-blue-700">{{ $shipment->shipment_number }}</div>
                                <div class="text-sm text-gray-600 capitalize">
                                    {{ str_replace('_', ' ', $shipment->shipment_type) }}
                                </div>

                            </div>
                        </td>
                        
                        <!-- Store Information -->
                        <td class="px-6 py-4">
                            <div>
                                <div class="font-medium text-gray-900">{{ $shipment->store_code }}</div>
                                <div class="text-sm text-gray-600">{{ $shipment->store_name }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    {{ $shipment->city }}, {{ $shipment->state }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ $shipment->contact_person }}
                                </div>
                            </div>
                        </td>
                        
                        <!-- Schedule & Status -->
                        <td class="px-6 py-4">
                            <div>
                                @if($shipment->preferred_delivery_window_start && $shipment->preferred_delivery_window_end)
                                <div class="text-xs text-gray-600 mt-1">
                                    <i class="far fa-clock mr-1"></i>
                                    {{ date('h:i A', strtotime($shipment->preferred_delivery_window_start)) }} - 
                                    {{ date('h:i A', strtotime($shipment->preferred_delivery_window_end)) }}
                                </div>
                                @endif
                                <div class="mt-2">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'in_transit' => 'bg-blue-100 text-blue-800',
                                            'delivered' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'delayed' => 'bg-orange-100 text-orange-800'
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$shipment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        <i class="fas fa-circle mr-1 text-xs"></i>
                                        {{ ucfirst($shipment->status) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Items & Value -->
                        <td class="px-6 py-4">
                            <div>
                                <div class="text-sm">
                                    <span class="font-medium text-gray-900">{{ $shipment->total_quantity ?? 0 }}</span>
                                    <span class="text-gray-600"> items</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $shipment->transaction_count ?? 0 }} transactions
                                </div>
                                <div class="font-bold text-blue-700 mt-2">
                                    ${{ number_format($shipment->total_value ?? 0, 2) }}
                                </div>
                            </div>
                        </td>
                        
                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-2">
                                <!-- View Details -->
                                <button wire:click="viewDetails({{ $shipment->shipment_id }})"
                                        class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Update Status -->
                                <button wire:click="updateStatus({{ $shipment->shipment_id }})"
                                        class="p-2 text-green-600 hover:text-green-800 hover:bg-green-50 rounded transition-colors"
                                        title="Update Status">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Tracking -->
                                @if(in_array($shipment->status, ['in_transit', 'pending']))
                                <button wire:click="addTracking({{ $shipment->shipment_id }})"
                                        class="p-2 text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded transition-colors"
                                        title="Add Tracking">
                                    <i class="fas fa-map-marked-alt"></i>
                                </button>
                                @endif
                                
                                <!-- Cancel -->
                                @if(in_array($shipment->status, ['pending', 'in_transit']))
                                <button wire:click="cancelShipment({{ $shipment->shipment_id }})"
                                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors"
                                        title="Cancel Shipment">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                                @endif
                            </div>
                            
                           <!-- Driver/Vehicle Info -->
@if($shipment->assigned_driver_name || $shipment->vehicle_number)
<div class="mt-3 pt-3 border-t border-gray-100">
    @if($shipment->assigned_driver_name)
    <div class="text-xs text-gray-600">
        <i class="fas fa-user-tie mr-1"></i>
        {{ $shipment->assigned_driver_name }} <!-- Changed from driver_name -->
    </div>
    @endif
    @if($shipment->vehicle_number)
    <div class="text-xs text-gray-600 mt-1">
        <i class="fas fa-truck mr-1"></i>
        {{ $shipment->vehicle_number }}
    </div>
    @endif
</div>
@endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-truck text-blue-500 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No Shipments Found</h4>
                            <p class="text-gray-600 mb-4">No shipments match your current filters.</p>
                            <button wire:click="resetFilters"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-redo mr-2"></i>
                                Reset Filters
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($this->shipments->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->shipments->links() }}
        </div>
        @endif
    </div>

    <!-- View Details Modal -->
    @if($showDetailsModal && $selectedShipment)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-shipping-fast text-blue-600 mr-2"></i>
                        Shipment Details: {{ $selectedShipment->shipment_number }}
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Complete shipment information</p>
                </div>
                <button wire:click="$set('showDetailsModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <div class="space-y-6 max-h-96 overflow-y-auto pr-2">
                <!-- Shipment Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <!-- Status & Type -->
                        <div class="flex items-center justify-between">
                            <div>
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_transit' => 'bg-blue-100 text-blue-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'delayed' => 'bg-orange-100 text-orange-800'
                                    ];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$selectedShipment->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    <i class="fas fa-circle mr-1 text-xs"></i>
                                    {{ ucfirst($selectedShipment->status) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600 capitalize">
                                {{ str_replace('_', ' ', $selectedShipment->shipment_type) }}
                            </div>
                        </div>
                        
                        <!-- Dates -->
                        <div class="space-y-2">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="far fa-calendar text-blue-500 mr-2 w-5"></i>
                                <span class="font-medium mr-2">Scheduled:</span>
                                {{ $selectedShipment->scheduled_date->format('F d, Y') }}
                            </div>
                            @if($selectedShipment->preferred_delivery_window_start && $selectedShipment->preferred_delivery_window_end)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="far fa-clock text-blue-500 mr-2 w-5"></i>
                                <span class="font-medium mr-2">Delivery Window:</span>
                                {{ date('h:i A', strtotime($selectedShipment->preferred_delivery_window_start)) }} - 
                                {{ date('h:i A', strtotime($selectedShipment->preferred_delivery_window_end)) }}
                            </div>
                            @endif
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-clock text-blue-500 mr-2 w-5"></i>
                                <span class="font-medium mr-2">Created:</span>
                                {{ $selectedShipment->created_at->format('F d, Y h:i A') }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-4">
                        <!-- Store Info -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-store text-green-600 mr-2"></i>
                                Store Information
                            </h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-hashtag mr-2 w-4"></i>
                                    {{ $selectedShipment->store_code }}
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-store mr-2 w-4"></i>
                                    {{ $selectedShipment->store_name }}
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-map-marker-alt mr-2 w-4"></i>
                                    {{ $selectedShipment->address }}, {{ $selectedShipment->city }}, {{ $selectedShipment->state }} {{ $selectedShipment->zip_code }}
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-phone mr-2 w-4"></i>
                                    {{ $selectedShipment->contact_phone }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items Section -->
                <div>
                    <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-boxes text-purple-600 mr-2"></i>
                        Shipment Items
                    </h4>
                    
                    @if(count($shipmentItems) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">SKU</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Quantity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Unit Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($shipmentItems as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item['sku'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $item['product_name'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $item['quantity'] ?? 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${{ number_format($item['unit_price'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-blue-700">${{ number_format($item['total'] ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Total:</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-sm font-bold text-blue-700">${{ number_format($selectedShipment->total_value ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-gray-500 text-sm">No items found in this shipment.</p>
                    @endif
                </div>
                
                <!-- Delivery Information -->
                @if($selectedShipment->assigned_driver_name || $selectedShipment->assigned_driver_phone || $selectedShipment->vehicle_number)
                <div>
                    <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-truck text-orange-600 mr-2"></i>
                        Delivery Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($selectedShipment->assigned_driver_name)
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-user-tie text-blue-500 mr-2"></i>
                            <span class="font-medium mr-2">Driver:</span>
                            {{ $selectedShipment->assigned_driver_name }}
                            @if($selectedShipment->assigned_driver_phone)
                            <span class="ml-2">({{ $selectedShipment->assigned_driver_phone }})</span>
                            @endif
                        </div>
                        @endif
                        
                        @if($selectedShipment->vehicle_number)
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-truck text-blue-500 mr-2"></i>
                            <span class="font-medium mr-2">Vehicle:</span>
                            {{ $selectedShipment->vehicle_number }}
                            @if($selectedShipment->vehicle_type)
                            <span class="ml-2 capitalize">({{ str_replace('_', ' ', $selectedShipment->vehicle_type) }})</span>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                
                <!-- Delivery Notes -->
                @if($selectedShipment->delivery_notes)
                <div>
                    <h4 class="font-medium text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-sticky-note text-green-600 mr-2"></i>
                        Delivery Notes
                    </h4>
                    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">{{ $selectedShipment->delivery_notes }}</p>
                </div>
                @endif
                
                <!-- Tracking History -->
                @php
                    $trackingHistory = $this->getTrackingHistory($selectedShipment->shipment_id);
                @endphp
                @if($trackingHistory->count() > 0)
                <div>
                    <h4 class="font-medium text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-map-marked-alt text-purple-600 mr-2"></i>
                        Tracking History
                    </h4>
                    <div class="space-y-3">
                        @foreach($trackingHistory as $tracking)
                        <div class="border-l-4 border-blue-500 pl-4 py-2">
                            <div class="flex justify-between items-start">
                                <div class="text-sm font-medium text-gray-900">{{ $tracking->location }}</div>
                                <div class="text-xs text-gray-500">{{ $tracking->tracked_at->format('M d, h:i A') }}</div>
                            </div>
                            @if($tracking->notes)
                            <p class="text-sm text-gray-600 mt-1">{{ $tracking->notes }}</p>
                            @endif
                            <div class="mt-1">
                                @php
                                    $trackingStatusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_transit' => 'bg-blue-100 text-blue-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'delayed' => 'bg-orange-100 text-orange-800'
                                    ];
                                @endphp
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $trackingStatusColors[$tracking->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($tracking->status) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-end">
                <button type="button" 
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        wire:click="$set('showDetailsModal', false)">
                    <i class="fas fa-times mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Update Status Modal -->
    @if($showUpdateModal && $selectedShipment)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-edit text-green-600 mr-2"></i>
                        Update Shipment Status
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $selectedShipment->shipment_number }}</p>
                </div>
                <button wire:click="$set('showUpdateModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="saveStatusUpdate">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tag mr-1"></i>
                            New Status *
                        </label>
                        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                wire:model="newStatus"
                                required>
                            <option value="pending">Pending</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-1"></i>
                            Update Notes
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="updateNotes"
                                  rows="3"
                                  placeholder="Add notes about this status update..."></textarea>
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
                            class="px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

   <!-- Add Tracking Modal -->
@if($showTrackingModal && $selectedShipment)
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900">
                    <i class="fas fa-map-marked-alt text-purple-600 mr-2"></i>
                    Add Tracking Update
                </h3>
                <p class="text-sm text-gray-600 mt-1">{{ $selectedShipment->shipment_number }}</p>
            </div>
            <button wire:click="$set('showTrackingModal', false)" 
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <form wire:submit.prevent="saveTracking">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        Current Location *
                        <span class="text-xs text-gray-500 ml-1">(Format: "Lat: 40.7128, Long: -74.0060" or descriptive location)</span>
                    </label>
                    <input type="text" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           wire:model="trackingLocation"
                           placeholder="e.g., Lat: 40.7128, Long: -74.0060 or 'En route to Downtown Store'"
                           required>
                    @if($selectedShipment->current_latitude && $selectedShipment->current_longitude)
                    <p class="text-xs text-gray-500 mt-1">
                        Current GPS: {{ $selectedShipment->current_latitude }}, {{ $selectedShipment->current_longitude }}
                    </p>
                    @endif
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sticky-note mr-1"></i>
                        Tracking Notes
                        <span class="text-xs text-gray-500 ml-1">(Optional - any updates or issues)</span>
                    </label>
                    <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                              wire:model="trackingNotes"
                              rows="3"
                              placeholder="Add notes about the current status..."></textarea>
                </div>
                
                <!-- Current Shipment Info -->
                <div class="p-3 bg-blue-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Shipment Info:</h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium ml-1">{{ ucfirst(str_replace('_', ' ', $selectedShipment->status)) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Driver:</span>
                            <span class="font-medium ml-1">{{ $selectedShipment->assigned_driver_name ?? 'Not assigned' }}</span>
                        </div>
                        @if($selectedShipment->current_eta_minutes)
                        <div>
                            <span class="text-gray-600">Current ETA:</span>
                            <span class="font-medium ml-1">{{ $selectedShipment->current_eta_minutes }} minutes</span>
                        </div>
                        @endif
                        @if($selectedShipment->last_gps_update)
                        <div>
                            <span class="text-gray-600">Last Update:</span>
                            <span class="font-medium ml-1">{{ \Carbon\Carbon::parse($selectedShipment->last_gps_update)->format('M d, H:i') }}</span>
                        </div>
                        @endif
                    </div>
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
                        class="px-4 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Save Tracking Update
                </button>
            </div>
        </form>
    </div>
</div>
@endif

    <!-- Cancel Shipment Modal -->
    @if($showCancelModal && $selectedShipment)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 transition-opacity">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-lg bg-white">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-times-circle text-red-600 mr-2"></i>
                        Cancel Shipment
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $selectedShipment->shipment_number }}</p>
                </div>
                <button wire:click="$set('showCancelModal', false)" 
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form wire:submit.prevent="confirmCancel">
                <div class="space-y-4">
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center text-red-700 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="font-medium">Warning</span>
                        </div>
                        <p class="text-sm text-red-600">
                            Cancelling this shipment will:
                            <ul class="list-disc list-inside mt-2 text-sm text-red-600">
                                <li>Change status to "Cancelled"</li>
                                <li>Restore all items to inventory</li>
                                <li>Release assigned vehicle (if any)</li>
                                <li>Update all related stock transactions</li>
                            </ul>
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-comment-alt mr-1"></i>
                            Cancellation Reason *
                        </label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  wire:model="cancelReason"
                                  rows="4"
                                  placeholder="Please provide a reason for cancellation..."
                                  required></textarea>
                        <p class="text-xs text-gray-500 mt-1">Minimum 10 characters required.</p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:click="$set('showCancelModal', false)">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-ban mr-2"></i>
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>