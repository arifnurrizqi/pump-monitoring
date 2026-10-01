<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mqtt_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('mode', 20)->default('external');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(1883);
            $table->string('client_id_prefix')->default('hydroflow-dashboard');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('clean_session')->default(true);
            $table->unsignedSmallInteger('connect_timeout')->default(5);
            $table->unsignedSmallInteger('socket_timeout')->default(5);
            $table->unsignedSmallInteger('keep_alive')->default(60);
            $table->boolean('tls_enabled')->default(false);
            $table->boolean('tls_verify_peer')->default(true);
            $table->boolean('tls_verify_peer_name')->default(true);
            $table->boolean('tls_allow_self_signed')->default(false);
            $table->string('tls_ca_file')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mqtt_settings');
    }
};
