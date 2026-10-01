<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->timestamp('period_start');
            $table->timestamp('period_end');

            $table->decimal('avg_ph', 6, 2)->nullable();
            $table->decimal('min_ph', 6, 2)->nullable();
            $table->decimal('max_ph', 6, 2)->nullable();

            $table->decimal('avg_turbidity', 10, 2)->nullable();
            $table->decimal('min_turbidity', 10, 2)->nullable();
            $table->decimal('max_turbidity', 10, 2)->nullable();

            $table->decimal('avg_flow', 12, 2)->nullable();
            $table->decimal('min_flow', 12, 2)->nullable();
            $table->decimal('max_flow', 12, 2)->nullable();

            $table->decimal('start_flow_total', 14, 2)->nullable();
            $table->decimal('end_flow_total', 14, 2)->nullable();

            $table->decimal('avg_level_percent', 6, 2)->nullable();
            $table->decimal('min_level_percent', 6, 2)->nullable();
            $table->decimal('max_level_percent', 6, 2)->nullable();

            $table->decimal('avg_level_cm', 10, 2)->nullable();
            $table->decimal('min_level_cm', 10, 2)->nullable();
            $table->decimal('max_level_cm', 10, 2)->nullable();

            $table->decimal('avg_device_voltage_mcu', 6, 2)->nullable();
            $table->decimal('min_device_voltage_mcu', 6, 2)->nullable();
            $table->decimal('max_device_voltage_mcu', 6, 2)->nullable();

            $table->decimal('avg_device_voltage_5v', 6, 2)->nullable();
            $table->decimal('min_device_voltage_5v', 6, 2)->nullable();
            $table->decimal('max_device_voltage_5v', 6, 2)->nullable();

            $table->decimal('avg_device_voltage_input', 6, 2)->nullable();
            $table->decimal('min_device_voltage_input', 6, 2)->nullable();
            $table->decimal('max_device_voltage_input', 6, 2)->nullable();

            $table->decimal('avg_signal_strength', 8, 2)->nullable();
            $table->integer('min_signal_strength')->nullable();
            $table->integer('max_signal_strength')->nullable();

            $table->unsignedInteger('sample_count')->default(0);

            $table->timestamps();

            $table->unique(['device_id', 'period_start']);
            $table->index('period_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_monthly');
    }
};