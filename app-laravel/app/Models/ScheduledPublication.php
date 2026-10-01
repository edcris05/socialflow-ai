<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'draft_id', 'scheduled_by', 'scheduled_for', 'status', 'cancelled_at', 'cancelled_by'])]
class ScheduledPublication extends Model
{
    use HasUlids;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_for->lte(now());
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'CANCELADO';
        }

        return $this->isReady() ? 'LISTO' : 'PROGRAMADO';
    }

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
