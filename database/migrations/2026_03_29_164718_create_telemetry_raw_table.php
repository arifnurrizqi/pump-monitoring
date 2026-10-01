<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_raw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->decimal('ph', 6, 2)->nullable();
            $table->decimal('turbidity', 10, 2)->nullable();
            $table->decimal('flow', 12, 2)->nullable();
            $table->decimal('flow_total', 14, 2)->nullable();

            $table->decimal('level_percent', 6, 2)->nullable();
            $table->decimal('level_cm', 10, 2)->nullable();

            $table->decimal('device_voltage_mcu', 6, 2)->nullable();
            $table->decimal('device_voltage_5v', 6, 2)->nullable();
            $table->decimal('device_voltage_input', 6, 2)->nullable();

            $table->integer('signal_strength')->nullable();
            $table->string('status_code', 50)->nullable();
            $table->string('alarm_code', 50)->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('recorded_at');

            $table->timestamps();

            $table->index('device_id');
            $table->index('recorded_at');
            $table->index(['device_id', 'recorded_at']);
            $table->index('status_code');
            $table->index('alarm_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_raw');
    }
};