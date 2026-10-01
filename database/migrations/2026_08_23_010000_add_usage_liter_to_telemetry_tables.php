<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->decimal('pulse_per_liter_used', 12, 4)->nullable()->after('delta_pulse');
            $table->decimal('usage_liter', 18, 6)->default(0)->after('pulse_per_liter_used');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->decimal('last_usage_liter', 18, 6)->default(0)->after('last_delta_pulse');
            $table->decimal('total_usage_liter', 20, 6)->default(0)->after('last_usage_liter');
        });

        foreach (['telemetry_hourly', 'telemetry_daily', 'telemetry_monthly', 'telemetry_yearly'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('usage_liter', 20, 6)->default(0)->after('max_sample_flow');
            });
        }

        $settings = DB::table('device_settings')
            ->where('pulse_per_liter', '>', 0)
            ->pluck('pulse_per_liter', 'device_id');

        foreach ($settings as $deviceId => $pulsePerLiter) {
            $calibration = (float) $pulsePerLiter;

            DB::table('telemetry_raw')
                ->where('device_id', $deviceId)
                ->update([
                    'pulse_per_liter_used' => $calibration,
                    'usage_liter' => DB::raw('delta_pulse / '.$calibration),
                ]);

            $totalUsage = (float) DB::table('telemetry_raw')
                ->where('device_id', $deviceId)
                ->sum('usage_liter');

            DB::table('telemetry_latest')
                ->where('device_id', $deviceId)
                ->update([
                    'last_usage_liter' => DB::raw('last_delta_pulse / '.$calibration),
                    'total_usage_liter' => $totalUsage,
                ]);
        }

        foreach (['telemetry_hourly', 'telemetry_daily', 'telemetry_monthly', 'telemetry_yearly'] as $tableName) {
            DB::table($tableName)
                ->orderBy('id')
                ->each(function ($aggregate) use ($tableName) {
                    $usage = DB::table('telemetry_raw')
                        ->where('device_id', $aggregate->device_id)
                        ->whereBetween('recorded_at', [$aggregate->period_start, $aggregate->period_end])
                        ->sum('usage_liter');

                    DB::table($tableName)
                        ->where('id', $aggregate->id)
                        ->update(['usage_liter' => $usage]);
                });
        }
    }

    public function down(): void
    {
        foreach (['telemetry_hourly', 'telemetry_daily', 'telemetry_monthly', 'telemetry_yearly'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('usage_liter');
            });
        }

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->dropColumn(['last_usage_liter', 'total_usage_liter']);
        });

        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropColumn(['pulse_per_liter_used', 'usage_liter']);
        });
    }
};
