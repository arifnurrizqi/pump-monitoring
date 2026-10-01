<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            $table->string('alarm_code', 50);
            $table->string('parameter_name', 100)->nullable();
            $table->string('alarm_message');
            $table->decimal('current_value', 14, 2)->nullable();
            $table->decimal('threshold_value', 14, 2)->nullable();
            $table->string('severity', 20)->default('warning');
            $table->string('status', 20)->default('active');

            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            $table->index('device_id');
            $table->index('alarm_code');
            $table->index('status');
            $table->index('severity');
            $table->index('started_at');
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_logs');
    }
};