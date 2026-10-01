<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. device_settings
        Schema::table('device_settings', function (Blueprint $table) {
            $table->float('pulse_per_liter')
                  ->default(450)
                  ->after('ph_max'); // sesuaikan posisi kolom
        });

        // 2. telemetry_raw
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->unsignedInteger('delta_pulse')
                  ->default(0)
                  ->after('turbidity'); // sesuaikan
        });

        // 3. telemetry_latest
        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->unsignedBigInteger('total_pulse')
                  ->default(0)
                  ->after('turbidity');

            $table->unsignedInteger('last_delta_pulse')
                  ->default(0)
                  ->after('total_pulse');
        });
    }

    public function down(): void
    {
        // rollback

        Schema::table('device_settings', function (Blueprint $table) {
            $table->dropColumn('pulse_per_liter');
        });

        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropColumn('delta_pulse');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->dropColumn(['total_pulse', 'last_delta_pulse']);
        });
    }
};