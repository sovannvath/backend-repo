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
        Schema::table('reorder_requests', function (Blueprint $table) {
            $table->foreignId('warehouse_staff_id')->nullable()->constrained('users')->onDelete('set null')->after('admin_id');
            $table->integer('quantity_approved')->nullable()->after('quantity_requested');
            $table->text('warehouse_notes')->nullable()->after('notes');
            $table->timestamp('warehouse_approved_at')->nullable()->after('approved_at');
            $table->timestamp('warehouse_rejected_at')->nullable()->after('warehouse_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reorder_requests', function (Blueprint $table) {
            $table->dropColumn([
                'warehouse_staff_id',
                'quantity_approved',
                'warehouse_notes',
                'warehouse_approved_at',
                'warehouse_rejected_at'
            ]);
        });
    }
};

