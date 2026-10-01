<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'draft_id', 'context_snapshot_id', 'user_id', 'provider', 'model', 'operation', 'status', 'evaluation_status', 'grounding_status', 'grounding_results', 'generated_content', 'input_tokens', 'cached_input_tokens', 'output_tokens', 'estimated_cost_usd', 'provider_request_id', 'finish_reason', 'error', 'evaluation_violations', 'evaluation_warnings', 'evaluated_at'])]
class GenerationRun extends Model
{
    use HasUlids;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    public function contextSnapshot(): BelongsTo
    {
        return $this->belongsTo(ContextSnapshot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'estimated_cost_usd' => 'decimal:8',
            'evaluation_violations' => 'array',
            'evaluation_warnings' => 'array',
            'evaluated_at' => 'datetime',
            'grounding_results' => 'array',
        ];
    }
}
