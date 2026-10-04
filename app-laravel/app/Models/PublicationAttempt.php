<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scheduled_publication_id',
    'meta_connection_id',
    'initiated_by',
    'provider',
    'status',
    'attempt_count',
    'idempotency_key',
    'target_account_id_snapshot',
    'caption_snapshot',
    'media_url_snapshot',
    'external_container_id',
    'external_media_id',
    'last_error_code',
    'last_error_message',
    'started_at',
    'completed_at',
    'published_at',
])]
class PublicationAttempt extends Model
{
    use HasUlids;

    public const STATUS_PUBLISHING = 'publishing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OUTCOME_UNKNOWN = 'outcome_unknown';

    public function scheduledPublication(): BelongsTo
    {
        return $this->belongsTo(ScheduledPublication::class);
    }

    public function metaConnection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
