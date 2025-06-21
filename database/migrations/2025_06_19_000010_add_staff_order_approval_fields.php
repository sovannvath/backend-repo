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
        // Add staff-specific fields to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('staff_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('staff_notes')->nullable()->after('rejected_at');
            $table->string('approval_status')->default('pending')->after('staff_notes'); // pending, approved, rejected
        });

        // Add staff-specific fields to users table if not already present
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'hire_date')) {
                $table->date('hire_date')->nullable()->after('preferences');
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('hire_date');
            }
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id')->unique()->nullable()->after('department');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('employee_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rejected_at', 'staff_notes', 'approval_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'department', 'employee_id', 'is_active']);
        });
    }
};

