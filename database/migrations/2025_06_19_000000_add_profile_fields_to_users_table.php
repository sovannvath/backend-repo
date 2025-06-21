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
        Schema::table('users', function (Blueprint $table) {
            // Add phone field
            $table->string('phone')->nullable()->after('email');
            
            // Add avatar field
            $table->string('avatar')->nullable()->after('phone');
            
            // Add address fields
            $table->string('street')->nullable()->after('avatar');
            $table->string('city')->nullable()->after('street');
            $table->string('state')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('zip_code');
            
            // Add preferences as JSON
            $table->json('preferences')->nullable()->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'avatar',
                'street',
                'city',
                'state',
                'zip_code',
                'country',
                'preferences'
            ]);
        });
    }
};

