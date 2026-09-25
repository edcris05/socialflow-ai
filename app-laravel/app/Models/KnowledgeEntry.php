<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'content',
    'source',
    'status',
    'category',
    'applicability',
    'brand_id',
    'created_by',
    'updated_by',
    'verified_by',
    'verified_at',
])]
#[Hidden([])]
class KnowledgeEntry extends Model
{
    use HasFactory, HasUlids;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(KnowledgeAudit::class);
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }
}