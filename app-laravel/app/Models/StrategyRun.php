<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'brand_id',
    'user_id',
    'provider',
    'model',
    'status',
    'context',
    'suggestions',
    'input_tokens',
    'cached_input_tokens',
    'output_tokens',
    'estimated_cost_usd',
    'provider_request_id',
    'finish_reason',
    'error',
])]
class StrategyRun extends Model
{
    use HasUlids;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'suggestions' => 'array',
            'estimated_cost_usd' => 'decimal:8',
        ];
    }
}
