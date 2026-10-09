<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'slug',
    'description',
    'tone_of_voice',
    'target_audience',
    'social_channels',
    'restrictions',
])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, HasUlids;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function knowledgeEntries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function strategyRuns(): HasMany
    {
        return $this->hasMany(StrategyRun::class);
    }

    public function scheduledPublications(): HasMany
    {
        return $this->hasMany(ScheduledPublication::class);
    }

    public function publicationMedia(): HasMany
    {
        return $this->hasMany(PublicationMedia::class);
    }

    public function metaConnection(): HasOne
    {
        return $this->hasOne(MetaConnection::class);
    }

    public function autopublishingSetting(): HasOne
    {
        return $this->hasOne(BrandAutopublishingSetting::class);
    }

    public function autopublishingEnabled(): bool
    {
        return $this->autopublishingSetting?->allowsAutomaticPublishing() ?? false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_channels' => 'array',
            'restrictions' => 'array',
        ];
    }
}
