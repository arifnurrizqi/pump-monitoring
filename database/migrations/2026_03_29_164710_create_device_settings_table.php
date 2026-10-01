<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->decimal('tank_height_cm', 10, 2)->nullable();
            $table->decimal('tank_volume_liter', 12, 2)->nullable();

            $table->decimal('ph_min', 6, 2)->nullable();
            $table->decimal('ph_max', 6, 2)->nullable();

            $table->decimal('turbidity_max', 10, 2)->nullable();

            $table->decimal('flow_min', 10, 2)->nullable();
            $table->decimal('flow_max', 10, 2)->nullable();

            $table->decimal('level_min_percent', 6, 2)->nullable();
            $table->decimal('level_max_percent', 6, 2)->nullable();

            $table->decimal('device_voltage_mcu_min', 6, 2)->nullable();
            $table->decimal('device_voltage_5v_min', 6, 2)->nullable();
            $table->decimal('device_voltage_input_min', 6, 2)->nullable();

            $table->unsignedInteger('sampling_interval_sec')->nullable();
            $table->unsignedInteger('send_interval_sec')->nullable();

            $table->boolean('alarm_enabled')->default(true);
            $table->string('timezone', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_settings');
    }
};