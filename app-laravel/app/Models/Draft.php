<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'content', 'status', 'brand_id', 'user_id', 'manually_edited_at'])]
class Draft extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REJECTED = 'rejected';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function generationRuns(): HasMany
    {
        return $this->hasMany(GenerationRun::class);
    }

    public function contextSnapshot(): HasOne
    {
        return $this->hasOne(ContextSnapshot::class);
    }

    public function scheduledPublications(): HasMany
    {
        return $this->hasMany(ScheduledPublication::class);
    }

    public function publicationMedia(): HasMany
    {
        return $this->hasMany(PublicationMedia::class);
    }

    public function currentPublicationMedia(): HasOne
    {
        return $this->hasOne(PublicationMedia::class)
            ->whereNull('superseded_at')
            ->latestOfMany();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'APROBADO',
            self::STATUS_REJECTED => 'RECHAZADO',
            default => 'BORRADOR',
        };
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'manually_edited_at' => 'datetime',
        ];
    }
}
