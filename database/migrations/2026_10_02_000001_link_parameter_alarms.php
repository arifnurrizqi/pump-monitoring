<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alarm_logs', function (Blueprint $table) {
            $table->foreignId('source_parameter_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['source_parameter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('alarm_logs', function (Blueprint $table) {
            $table->dropIndex(['source_parameter_id', 'status']);
            $table->dropConstrainedForeignId('source_parameter_id');
        });
    }
};
