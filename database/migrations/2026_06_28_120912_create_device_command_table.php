<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->string('command_type'); // contoh: valve
            $table->string('command_key');  // contoh: valve_state
            $table->string('command_value'); // 0 / 1

            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('ack_at')->nullable(); // kalau device kasih feedback

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
