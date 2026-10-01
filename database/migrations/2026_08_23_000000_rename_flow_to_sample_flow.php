<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_settings', function (Blueprint $table) {
            $table->renameColumn('flow_min', 'sample_flow_min');
            $table->renameColumn('flow_max', 'sample_flow_max');
        });

        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->renameColumn('flow', 'sample_flow');
            $table->dropColumn('flow_total');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->renameColumn('flow', 'sample_flow');
            $table->dropColumn('flow_total');
        });

        foreach (['telemetry_hourly', 'telemetry_daily', 'telemetry_monthly', 'telemetry_yearly'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('avg_flow', 'avg_sample_flow');
                $table->renameColumn('min_flow', 'min_sample_flow');
                $table->renameColumn('max_flow', 'max_sample_flow');
                $table->dropColumn(['start_flow_total', 'end_flow_total']);
            });
        }
    }

    public function down(): void
    {
        foreach (['telemetry_hourly', 'telemetry_daily', 'telemetry_monthly', 'telemetry_yearly'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('avg_sample_flow', 'avg_flow');
                $table->renameColumn('min_sample_flow', 'min_flow');
                $table->renameColumn('max_sample_flow', 'max_flow');
                $table->decimal('start_flow_total', 14, 2)->nullable();
                $table->decimal('end_flow_total', 14, 2)->nullable();
            });
        }

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->renameColumn('sample_flow', 'flow');
            $table->decimal('flow_total', 12, 2)->nullable()->after('flow');
        });

        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->renameColumn('sample_flow', 'flow');
            $table->decimal('flow_total', 14, 2)->nullable()->after('flow');
        });

        Schema::table('device_settings', function (Blueprint $table) {
            $table->renameColumn('sample_flow_min', 'flow_min');
            $table->renameColumn('sample_flow_max', 'flow_max');
        });
    }
};
