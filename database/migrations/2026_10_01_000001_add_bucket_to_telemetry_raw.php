<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->timestamp('bucket_start')->nullable();
            $table->unsignedInteger('sample_count')->nullable();
            $table->unique(['device_id', 'bucket_start']);
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'bucket_start']);
            $table->dropColumn(['bucket_start', 'sample_count']);
        });
    }
};
