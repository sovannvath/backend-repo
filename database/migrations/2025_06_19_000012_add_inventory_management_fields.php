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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(10)->after('quantity');
            $table->integer('reorder_quantity')->default(50)->after('low_stock_threshold');
            $table->boolean('auto_reorder')->default(false)->after('reorder_quantity');
            $table->decimal('reorder_cost', 10, 2)->nullable()->after('auto_reorder');
        });

        Schema::create('reorder_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->integer('quantity_requested');
            $table->decimal('estimated_cost', 10, 2);
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('alert_type', ['low_stock', 'out_of_stock', 'reorder_needed']);
            $table->text('message');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_alerts');
        Schema::dropIfExists('reorder_requests');
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'low_stock_threshold',
                'reorder_quantity', 
                'auto_reorder',
                'reorder_cost'
            ]);
        });
    }
};

