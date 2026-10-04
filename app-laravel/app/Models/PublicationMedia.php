<?php

namespace App\Models;

use App\Services\Publishing\PublicMediaUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
])]
class PublicationMedia extends Model
{
    use HasUlids;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_IMAGE = 'image';

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

    public function hasValidImage(): bool
    {
        return $this->type === self::TYPE_IMAGE && $this->mime_type === 'image/jpeg';
    }

    public function hasValidPublicUrl(): bool
    {
        return PublicMediaUrl::isValid($this->public_url);
    }

    public function isPublishable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->superseded_at === null
            && $this->hasValidImage()
            && $this->hasValidPublicUrl();
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
        ];
    }
}
