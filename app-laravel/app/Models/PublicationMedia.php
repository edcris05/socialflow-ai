<?php

namespace App\Models;

use App\Services\Publishing\PublicMediaUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'brand_id',
    'draft_id',
    'uploaded_by',
    'type',
    'original_filename',
    'storage_disk',
    'storage_path',
    'public_url',
    'mime_type',
    'size_bytes',
    'width',
    'height',
    'status',
    'approved_by',
    'approved_at',
    'rejected_by',
    'rejected_at',
    'rejection_reason',
    'superseded_at',
    'preflight_status',
    'preflight_checked_at',
    'preflight_final_url',
    'preflight_content_type',
    'preflight_content_length',
    'preflight_error_code',
    'preflight_error_message',
])]
class PublicationMedia extends Model
{
    use HasUlids;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_IMAGE = 'image';

    public const PREFLIGHT_NOT_CHECKED = 'not_checked';

    public const PREFLIGHT_PASSED = 'passed';

    public const PREFLIGHT_FAILED = 'failed';

    protected $table = 'publication_media';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function publicHosting(): HasOne
    {
        return $this->hasOne(PublicMediaHosting::class);
    }

    public function hasValidImage(): bool
    {
        return $this->type === self::TYPE_IMAGE && $this->mime_type === 'image/jpeg';
    }

    public function hasValidPublicUrl(): bool
    {
        return PublicMediaUrl::isValid($this->effectivePublicUrl());
    }

    /**
     * A successfully managed copy takes precedence; the manual URL remains a
     * transitional fallback and never becomes the identity of the asset.
     */
    public function effectivePublicUrl(): ?string
    {
        /** @var PublicMediaHosting|null $hosting */
        $hosting = $this->relationLoaded('publicHosting')
            ? $this->getRelation('publicHosting')
            : $this->publicHosting()->first();

        if ($hosting?->status === PublicMediaHosting::STATUS_HOSTED && filled($hosting->public_url)) {
            return $hosting->public_url;
        }

        return $this->public_url;
    }

    public function publicHostingStatus(): string
    {
        /** @var PublicMediaHosting|null $hosting */
        $hosting = $this->relationLoaded('publicHosting')
            ? $this->getRelation('publicHosting')
            : $this->publicHosting()->first();

        return $hosting?->status ?? PublicMediaHosting::STATUS_NOT_HOSTED;
    }

    public function isPublishable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->superseded_at === null
            && $this->hasValidImage()
            && $this->hasValidPublicUrl()
            && $this->hasFreshPreflight();
    }

    public function hasFreshPreflight(): bool
    {
        $freshMinutes = max(1, (int) config('services.publication_media.preflight_fresh_minutes', 15));

        return $this->preflight_status === self::PREFLIGHT_PASSED
            && $this->preflight_checked_at !== null
            && $this->preflight_checked_at->gte(now()->subMinutes($freshMinutes));
    }

    public function preflightStatusLabel(): string
    {
        return match ($this->preflight_status) {
            self::PREFLIGHT_PASSED => 'VERIFICADA',
            self::PREFLIGHT_FAILED => 'ERROR DE PREFLIGHT',
            default => 'SIN VERIFICAR',
        };
    }

    /** @return array<string, null> */
    public static function resetPreflightAttributes(): array
    {
        return [
            'preflight_status' => self::PREFLIGHT_NOT_CHECKED,
            'preflight_checked_at' => null,
            'preflight_final_url' => null,
            'preflight_content_type' => null,
            'preflight_content_length' => null,
            'preflight_error_code' => null,
            'preflight_error_message' => null,
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'APROBADA',
            self::STATUS_REJECTED => 'RECHAZADA',
            default => 'CARGADA — SIN APROBAR',
        };
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'superseded_at' => 'datetime',
            'preflight_checked_at' => 'datetime',
            'preflight_content_length' => 'integer',
        ];
    }
}
