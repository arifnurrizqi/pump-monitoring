<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_parameters', function (Blueprint $table) {
            $table->string('chart_group', 60)->nullable();
        });

        DB::table('source_parameters')->whereIn('key', ['voltage_l1', 'voltage_l2', 'voltage_l3'])
            ->update(['chart_group' => 'tegangan']);
        DB::table('source_parameters')->where('key', 'motor_temp')
            ->update(['chart_group' => 'temperatur']);
        DB::table('source_parameters')->where('key', 'pressure')
            ->update(['chart_group' => 'tekanan']);
        DB::table('source_parameters')->where('key', 'flow_rate')
            ->update(['chart_group' => 'debit']);
    }

    public function down(): void
    {
        Schema::table('source_parameters', function (Blueprint $table) {
            $table->dropColumn('chart_group');
        });
    }
};
