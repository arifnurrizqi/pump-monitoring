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
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->boolean('valve_state')->nullable()->after('alarm_code');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->boolean('valve_state')->nullable()->after('alarm_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropColumn('valve_state');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->dropColumn('valve_state');
        });
    }
};
