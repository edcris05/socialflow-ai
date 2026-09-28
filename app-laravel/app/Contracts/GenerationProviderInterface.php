<?php

namespace App\Contracts;

use App\Services\Generation\GenerationResult;
use App\Services\Prompting\GenerationPrompt;

interface GenerationProviderInterface
{
    public function generate(GenerationPrompt $prompt): GenerationResult;
}
