<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMqttSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in(['save', 'test'])],
            'is_active' => ['nullable', 'boolean'],
            'host' => ['required', 'string', 'max:255', 'not_regex:/\s/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'client_id_prefix' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'clean_session' => ['nullable', 'boolean'],
            'connect_timeout' => ['required', 'integer', 'between:1,60'],
            'socket_timeout' => ['required', 'integer', 'between:1,120'],
            'keep_alive' => ['required', 'integer', 'between:5,3600'],
            'tls_enabled' => ['nullable', 'boolean'],
            'tls_verify_peer' => ['nullable', 'boolean'],
            'tls_verify_peer_name' => ['nullable', 'boolean'],
            'tls_allow_self_signed' => ['nullable', 'boolean'],
            'tls_ca_file' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
