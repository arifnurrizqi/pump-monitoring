<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->string('message_id', 100)->nullable();
            $table->unique(['device_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_raw', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'message_id']);
            $table->dropColumn('message_id');
        });
    }
};
