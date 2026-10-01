<?php

namespace App\Contracts;

use App\Services\Strategy\StrategyContext;
use App\Services\Strategy\StrategyResult;

interface StrategyProviderInterface
{
    public function generate(StrategyContext $context): StrategyResult;
}
