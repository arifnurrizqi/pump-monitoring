<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_latest', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // process data
            $table->decimal('ph', 5, 2)->nullable();
            $table->decimal('turbidity', 8, 2)->nullable();
            $table->decimal('flow', 8, 2)->nullable();
            $table->decimal('flow_total', 12, 2)->nullable();

            $table->decimal('level_percent', 5, 2)->nullable();
            $table->decimal('level_cm', 8, 2)->nullable();

            // voltage
            $table->decimal('device_voltage_mcu', 6, 2)->nullable();
            $table->decimal('device_voltage_5v', 6, 2)->nullable();
            $table->decimal('device_voltage_input', 6, 2)->nullable();

            // status
            $table->integer('signal_strength')->nullable();
            $table->string('status_code')->nullable();
            $table->string('alarm_code')->nullable();

            // waktu data
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_latest');
    }
};
