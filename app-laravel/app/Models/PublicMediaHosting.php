<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'publication_media_id',
    'status',
    'provider',
    'disk',
    'object_key',
    'public_url',
    'content_type',
    'size_bytes',
    'checksum_sha256',
    'hosted_at',
    'last_error_code',
    'last_error_message',
])]
class PublicMediaHosting extends Model
{
    use HasUlids;

    public const STATUS_NOT_HOSTED = 'not_hosted';

    public const STATUS_HOSTING = 'hosting';

    public const STATUS_HOSTED = 'hosted';

    public const STATUS_FAILED = 'failed';

    public function publicationMedia(): BelongsTo
    {
        return $this->belongsTo(PublicationMedia::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_HOSTING => 'ALOJANDO',
            self::STATUS_HOSTED => 'ALOJADA',
            self::STATUS_FAILED => 'ERROR',
            default => 'NO ALOJADA',
        };
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'hosted_at' => 'datetime',
        ];
    }
}
