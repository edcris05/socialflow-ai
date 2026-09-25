<?php

namespace App\Services\Knowledge;

use App\Models\Brand;
use Illuminate\Support\Collection;

interface KnowledgeRetrieverInterface
{
    /** @return Collection<int, RetrievalMatch> */
    public function retrieve(Brand $brand, string $query): Collection;
}