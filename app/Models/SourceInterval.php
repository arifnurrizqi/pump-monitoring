<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceInterval extends Model
{
    protected $fillable = ['data_source_id', 'bucket_start', 'last_recorded_at', 'sample_count', 'metrics'];

    protected $casts = [
        'bucket_start' => 'datetime',
        'last_recorded_at' => 'datetime',
        'sample_count' => 'integer',
        'metrics' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
