<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'is_active',
    'mode',
    'host',
    'port',
    'client_id_prefix',
    'username',
    'password',
    'clean_session',
    'connect_timeout',
    'socket_timeout',
    'keep_alive',
    'tls_enabled',
    'tls_verify_peer',
    'tls_verify_peer_name',
    'tls_allow_self_signed',
    'tls_ca_file',
    'updated_by',
])]
class MqttSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
            'clean_session' => 'boolean',
            'connect_timeout' => 'integer',
            'socket_timeout' => 'integer',
            'keep_alive' => 'integer',
            'tls_enabled' => 'boolean',
            'tls_verify_peer' => 'boolean',
            'tls_verify_peer_name' => 'boolean',
            'tls_allow_self_signed' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
