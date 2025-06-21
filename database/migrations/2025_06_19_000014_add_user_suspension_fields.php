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
        // Add suspension fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('suspension_reason')->nullable();
            $table->text('suspension_notes')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->unsignedBigInteger('reactivated_by')->nullable();
            $table->text('reactivation_notes')->nullable();
            
            $table->foreign('suspended_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reactivated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create user_suspensions table for tracking suspension history
        Schema::create('user_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->unsignedBigInteger('reactivated_by')->nullable();
            $table->string('action_type')->default('suspended'); // suspended, reactivated
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('suspended_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reactivated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['user_id', 'created_at']);
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_suspensions');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropForeign(['reactivated_by']);
            $table->dropColumn([
                'suspension_reason',
                'suspension_notes',
                'suspended_at',
                'suspended_by',
                'reactivated_at',
                'reactivated_by',
                'reactivation_notes'
            ]);
        });
    }
};

