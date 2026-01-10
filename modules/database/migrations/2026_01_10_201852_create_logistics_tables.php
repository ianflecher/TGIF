<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Stores/Restaurants table (Your TGIF locations)
        if (!Schema::hasTable('tgif_stores')) {
            Schema::create('tgif_stores', function (Blueprint $table) {
                $table->id('store_id');
                $table->string('store_code')->unique(); // TGIF001, TGIF002, etc.
                $table->string('store_name');
                $table->string('store_type'); // restaurant, kiosk, food_truck, franchise
                $table->string('address');
                $table->string('city');
                $table->string('state');
                $table->string('zip_code');
                $table->string('contact_person');
                $table->string('contact_phone');
                $table->string('contact_email')->nullable();
                
                // Delivery information
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 10, 8)->nullable();
                $table->string('delivery_window')->nullable(); // Preferred delivery hours
                $table->text('delivery_instructions')->nullable();
                
                // Storage capacity
                $table->integer('freezer_capacity_cu_ft')->nullable(); // For frozen fries
                $table->integer('dry_storage_capacity_cu_ft')->nullable(); // For packaging, oil
                $table->integer('refrigerator_capacity_cu_ft')->nullable(); // For fresh ingredients
                
                // Status
                $table->enum('status', ['active', 'inactive', 'under_renovation', 'closed'])->default('active');
                
                $table->timestamps();
                
                // Indexes
                $table->index(['store_code', 'store_type']);
                $table->index(['city', 'state']);
            });
        }

        // 2. Store Stock Levels table
        if (!Schema::hasTable('store_stock_levels')) {
            Schema::create('store_stock_levels', function (Blueprint $table) {
                $table->id('store_stock_id');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('inventory_id');
                
                // Current stock
                $table->integer('current_quantity')->default(0);
                $table->integer('min_quantity')->default(10); // Reorder point
                $table->integer('max_quantity')->default(100); // Storage limit
                
                // Last delivery information
                $table->date('last_delivery_date')->nullable();
                $table->integer('last_delivery_quantity')->nullable();
                
                // Reorder status
                $table->boolean('needs_reorder')->default(false);
                $table->integer('reorder_quantity')->nullable();
                $table->date('reorder_requested_date')->nullable();
                
                // Temperature monitoring (for perishables)
                $table->decimal('current_temperature_c', 5, 2)->nullable();
                $table->decimal('min_safe_temperature_c', 5, 2)->nullable();
                $table->decimal('max_safe_temperature_c', 5, 2)->nullable();
                
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('store_id')->references('store_id')->on('tgif_stores')->onDelete('cascade');
                $table->foreign('inventory_id')->references('inventory_id')->on('inventories')->onDelete('cascade');
                
                // Indexes
                $table->unique(['store_id', 'inventory_id']);
                $table->index(['needs_reorder', 'store_id']);
            });
        }

        // 3. Logistics Vehicles table
        if (!Schema::hasTable('logistics_vehicles')) {
            Schema::create('logistics_vehicles', function (Blueprint $table) {
                $table->id('vehicle_id');
                
                // Vehicle information
                $table->string('vehicle_number')->unique(); // License plate
                $table->string('vehicle_type'); // refrigerated_truck, dry_van, box_truck, pickup
                $table->string('make');
                $table->string('model');
                $table->integer('year')->nullable();
                
                // Capacity for fries business
                $table->integer('capacity_weight_kg')->default(1000);
                $table->integer('capacity_volume_m3')->default(20);
                $table->integer('pallet_capacity')->default(10);
                
                // Refrigeration for fries
                $table->boolean('is_refrigerated')->default(false);
                $table->decimal('min_temperature_c', 5, 2)->nullable();
                $table->decimal('max_temperature_c', 5, 2)->nullable();
                
                // Features
                $table->json('special_features')->nullable(); // lift_gate, tailgate, ramp
                $table->boolean('has_gps_tracking')->default(true);
                
                // Maintenance
                $table->integer('odometer_km')->default(0);
                $table->date('last_maintenance_date')->nullable();
                $table->date('next_maintenance_date')->nullable();
                $table->decimal('fuel_efficiency_kmpl', 5, 2)->nullable();
                
                // Status
                $table->enum('status', ['available', 'loading', 'in_transit', 'unloading', 'maintenance', 'out_of_service'])->default('available');
                $table->string('current_location')->nullable();
                $table->decimal('current_latitude', 10, 8)->nullable();
                $table->decimal('current_longitude', 10, 8)->nullable();
                
                // Driver assignment
                $table->unsignedBigInteger('current_driver_id')->nullable();
                
                $table->timestamps();
                
                // Indexes
                $table->index(['vehicle_number', 'status']);
                $table->index(['vehicle_type', 'is_refrigerated']);
            });
        }

        // 4. Outbound Shipments table (Warehouse to Stores)
        if (!Schema::hasTable('outbound_shipments')) {
            Schema::create('outbound_shipments', function (Blueprint $table) {
                $table->id('shipment_id');
                $table->string('shipment_number')->unique();
                
                // Store information
                $table->unsignedBigInteger('store_id');
                $table->foreign('store_id')->references('store_id')->on('tgif_stores')->onDelete('cascade');
                
                // Warehouse information
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('warehouse_name');
                
                // Shipment details
                $table->enum('shipment_type', [
                    'scheduled_replenishment', // Regular restocking
                    'emergency_replenishment',  // Urgent restocking
                    'new_store_setup',          // Initial stock for new store
                    'promotional_material',     // Marketing materials
                    'equipment_delivery'        // Fryers, freezers, etc.
                ])->default('scheduled_replenishment');
                
                // Scheduling
                $table->date('scheduled_date');
                $table->time('preferred_delivery_window_start')->nullable();
                $table->time('preferred_delivery_window_end')->nullable();
                $table->timestamp('estimated_departure_time')->nullable();
                $table->timestamp('estimated_arrival_time');
                
                // Goods information
                $table->json('shipment_items'); // Array of {inventory_id, quantity, unit_price, total}
                $table->integer('total_quantity')->default(0);
                $table->decimal('total_weight_kg', 10, 2)->default(0);
                $table->decimal('total_volume_m3', 10, 2)->default(0);
                $table->decimal('total_value', 12, 2)->default(0);
                
                // Temperature requirements
                $table->boolean('requires_refrigeration')->default(false);
                $table->decimal('required_temperature_c', 5, 2)->nullable();
                
                // Assignment
                $table->unsignedBigInteger('assigned_vehicle_id')->nullable();
                $table->foreign('assigned_vehicle_id')->references('vehicle_id')->on('logistics_vehicles')->onDelete('set null');
                $table->unsignedBigInteger('assigned_driver_id')->nullable();
                $table->string('assigned_driver_name')->nullable();
                $table->string('assigned_driver_phone')->nullable();
                
                // Status tracking
                $table->enum('status', [
                    'pending',          // Created, not yet processed
                    'planned',          // Added to delivery plan
                    'loading',          // Being loaded at warehouse
                    'in_transit',       // On the way to store
                    'arrived',          // Arrived at store
                    'unloading',        // Being unloaded
                    'delivered',        // Unloaded and confirmed
                    'partially_delivered', // Some items delivered
                    'failed',           // Delivery failed
                    'cancelled'         // Cancelled
                ])->default('pending');
                
                // Route optimization
                $table->json('optimized_route')->nullable();
                $table->decimal('route_distance_km', 10, 2)->default(0);
                $table->integer('estimated_travel_minutes')->default(0);
                
                // GPS Tracking
                $table->json('gps_tracking_data')->nullable(); // Array of {lat, lng, timestamp, speed}
                $table->decimal('current_latitude', 10, 8)->nullable();
                $table->decimal('current_longitude', 10, 8)->nullable();
                $table->decimal('current_speed_kmh', 5, 2)->nullable();
                $table->timestamp('last_gps_update')->nullable();
                
                // ETA Updates
                $table->json('eta_updates')->nullable(); // Array of {timestamp, eta_minutes, reason}
                $table->integer('current_eta_minutes')->nullable();
                $table->timestamp('eta_last_updated')->nullable();
                
                // Delivery confirmation
                $table->json('proof_of_delivery')->nullable(); // {signature, photos, notes}
                $table->timestamp('actual_departure_time')->nullable();
                $table->timestamp('actual_arrival_time')->nullable();
                $table->timestamp('actual_completion_time')->nullable();
                $table->text('delivery_notes')->nullable();
                
                // Store receiving
                $table->unsignedBigInteger('received_by_store_manager')->nullable();
                $table->timestamp('store_received_at')->nullable();
                $table->json('store_receiving_checklist')->nullable();
                
                // Performance metrics
                $table->boolean('on_time_delivery')->default(true);
                $table->integer('delay_minutes')->default(0);
                $table->decimal('shipment_cost', 10, 2)->default(0);
                $table->json('quality_checks')->nullable(); // Temperature checks, damage reports
                
                // Notifications
                $table->boolean('notifications_enabled')->default(true);
                $table->timestamp('loading_notified_at')->nullable();
                $table->timestamp('departure_notified_at')->nullable();
                $table->timestamp('eta_notified_at')->nullable();
                $table->timestamp('arrival_notified_at')->nullable();
                $table->timestamp('completed_notified_at')->nullable();
                
                // Stock transaction reference
                $table->unsignedBigInteger('stock_transaction_id')->nullable();
                $table->foreign('stock_transaction_id')->references('transaction_id')->on('stock_transactions')->onDelete('set null');
                
                $table->timestamps();
                
                // Indexes
                $table->index(['shipment_number', 'status']);
                $table->index(['store_id', 'scheduled_date']);
                $table->index(['status', 'estimated_arrival_time']);
                $table->index(['assigned_vehicle_id', 'assigned_driver_id']);
                $table->index('current_eta_minutes');
            });
        }

        // 5. Delivery Route Optimizations table
        if (!Schema::hasTable('delivery_route_optimizations')) {
            Schema::create('delivery_route_optimizations', function (Blueprint $table) {
                $table->id('optimization_id');
                $table->date('optimization_date');
                $table->string('optimization_name');
                
                // Input shipments
                $table->json('shipment_ids'); // Array of shipment IDs to optimize
                $table->json('available_vehicles'); // Available vehicles for assignment
                
                // Constraints
                $table->json('constraints')->nullable(); // {max_distance_km, max_time_minutes, vehicle_capacities}
                $table->json('traffic_data')->nullable();
                $table->json('weather_data')->nullable();
                $table->json('store_time_windows')->nullable(); // Store delivery time preferences
                
                // Optimized routes
                $table->json('optimized_routes'); // Array of optimized routes with shipments
                $table->json('vehicle_assignments'); // Vehicle assignments per route
                $table->json('driver_assignments'); // Driver assignments per route
                
                // Performance improvements
                $table->decimal('total_distance_before', 10, 2)->default(0);
                $table->decimal('total_distance_after', 10, 2)->default(0);
                $table->decimal('distance_saved_km', 10, 2)->default(0);
                $table->integer('time_saved_minutes')->default(0);
                $table->decimal('fuel_saved_liters', 10, 2)->default(0);
                $table->decimal('cost_saving', 10, 2)->default(0);
                
                // Route execution
                $table->enum('execution_status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
                $table->date('execution_date')->nullable();
                
                // Created by
                $table->unsignedBigInteger('optimized_by')->nullable();
                $table->foreign('optimized_by')->references('user_id')->on('users')->onDelete('set null');
                
                $table->timestamps();
                
                // Indexes
                $table->index(['optimization_date', 'execution_status']);
                $table->index(['distance_saved_km', 'cost_saving']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_route_optimizations');
        Schema::dropIfExists('outbound_shipments');
        Schema::dropIfExists('logistics_vehicles');
        Schema::dropIfExists('store_stock_levels');
        Schema::dropIfExists('tgif_stores');
    }
};