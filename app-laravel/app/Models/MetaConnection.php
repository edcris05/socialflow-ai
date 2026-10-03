<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'brand_id',
    'configured_by',
    'facebook_page_id',
    'instagram_account_id',
    'access_token',
    'token_expires_at',
    'scopes',
    'status',
    'last_verified_at',
    'last_error',
])]
#[Hidden(['access_token'])]
class MetaConnection extends Model
{
    use HasUlids;

    public const STATUS_CONFIGURED_UNVERIFIED = 'configured_unverified';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_ERROR = 'error';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function isConfigured(): bool
    {
        return filled($this->access_token);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIGURED_UNVERIFIED => 'CONFIGURADO — SIN VERIFICAR',
            self::STATUS_VERIFIED => 'VERIFICADO',
            self::STATUS_ERROR => 'ERROR',
            default => 'NO CONFIGURADO',
        };
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'last_verified_at' => 'datetime',
        ];
    }
}
