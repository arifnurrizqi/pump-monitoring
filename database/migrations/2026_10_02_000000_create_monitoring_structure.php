<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('production_site_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('monitored_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->default('pump');
            $table->unsignedTinyInteger('phase_count')->nullable();
            $table->timestamps();
        });

        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 60);
            $table->string('name');
            $table->string('type', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['device_id', 'source_key']);
        });

        Schema::create('source_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label');
            $table->string('unit', 24)->nullable();
            $table->decimal('alarm_min', 18, 6)->nullable();
            $table->decimal('alarm_max', 18, 6)->nullable();
            $table->boolean('alarm_enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['data_source_id', 'key']);
        });

        Schema::create('source_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->timestamp('last_recorded_at');
            $table->unsignedInteger('sample_count');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['data_source_id', 'bucket_start']);
            $table->index('last_recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_intervals');
        Schema::dropIfExists('source_parameters');
        Schema::dropIfExists('data_sources');
        Schema::dropIfExists('monitored_assets');
        Schema::table('devices', fn (Blueprint $table) => $table->dropConstrainedForeignId('production_site_id'));
        Schema::dropIfExists('production_sites');
    }
};
