<?php

namespace App\Contracts;

use App\Models\ContextSnapshot;
use App\Services\Generation\EvaluationResult;

interface GenerationEvaluatorInterface
{
    public function evaluate(string $content, ContextSnapshot $snapshot): EvaluationResult;
}
