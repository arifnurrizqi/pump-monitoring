<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->decimal('level_distance_cm', 10, 2)->nullable()->after('sample_flow');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->decimal('level_distance_cm', 10, 2)->nullable()->after('sample_flow');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropColumn('level_distance_cm');
        });

        Schema::table('telemetry_latest', function (Blueprint $table) {
            $table->dropColumn('level_distance_cm');
        });
    }
};
