<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['knowledge_entry_id', 'brand_id', 'user_id', 'action', 'before', 'after'])]
class KnowledgeAudit extends Model
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'knowledge_entry_id');
    }

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
            'before' => 'array',
            'after' => 'array',
        ];
    }
}