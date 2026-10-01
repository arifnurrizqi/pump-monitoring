<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['device_commands', 'telemetry_latest', 'telemetry_yearly', 'telemetry_monthly',
            'telemetry_daily', 'telemetry_hourly', 'telemetry_raw'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('alarm_logs')->whereNull('source_parameter_id')->delete();

        $obsoleteSettings = [
            'tank_height_cm', 'tank_volume_liter', 'ph_min', 'ph_max', 'pulse_per_liter',
            'turbidity_max', 'sample_flow_min', 'sample_flow_max', 'flow_min', 'flow_max',
            'level_min_percent', 'level_max_percent', 'device_voltage_mcu_min',
            'device_voltage_5v_min', 'device_voltage_input_min', 'sampling_interval_sec',
            'alarm_enabled', 'timezone',
        ];
        foreach ($obsoleteSettings as $column) {
            if (Schema::hasColumn('device_settings', $column)) {
                Schema::table('device_settings', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasColumn('devices', 'api_token')) {
            Schema::table('devices', fn (Blueprint $table) => $table->dropColumn('api_token'));
        }
    }

    public function down(): void
    {
        // Removed water-analyzer data cannot be reconstructed by rolling back.
    }
};
