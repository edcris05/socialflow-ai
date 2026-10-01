<?php

namespace App\Services\Generation;

enum GroundingSummaryStatus: string
{
    case NotEvaluated = 'not_evaluated';
    case Passed = 'passed';
    case RequiresReview = 'requires_review';
}
