<?php

namespace App\Models;

use Database\Factories\BrandAutopublishingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'brand_id',
    'enabled',
    'enabled_by',
    'enabled_at',
    'disabled_by',
    'disabled_at',
])]
class BrandAutopublishingSetting extends Model
{
    /** @use HasFactory<BrandAutopublishingSettingFactory> */
    use HasFactory, HasUlids;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function allowsAutomaticPublishing(): bool
    {
        return $this->enabled === true
            && $this->enabled_by !== null
            && $this->enabled_at !== null;
    }

    public function enable(User $actor): bool
    {
        if ($this->allowsAutomaticPublishing()) {
            return false;
        }

        $this->fill([
            'enabled' => true,
            'enabled_by' => $actor->getKey(),
            'enabled_at' => now(),
        ])->save();

        return true;
    }

    public function disable(User $actor): bool
    {
        if ($this->enabled !== true) {
            return false;
        }

        $this->fill([
            'enabled' => false,
            'disabled_by' => $actor->getKey(),
            'disabled_at' => now(),
        ])->save();

        return true;
    }

    #[Scope]
    protected function enabledForAutomaticPublishing(Builder $query): Builder
    {
        return $query
            ->where('enabled', true)
            ->whereNotNull('enabled_by')
            ->whereNotNull('enabled_at');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }
}
