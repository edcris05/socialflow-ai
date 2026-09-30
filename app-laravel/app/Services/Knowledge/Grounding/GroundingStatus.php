<?php

namespace App\Services\Knowledge\Grounding;

enum GroundingStatus: string
{
    case Supported = 'SUPPORTED';
    case Unknown = 'UNKNOWN';
    case Unsupported = 'UNSUPPORTED';
    case Conflict = 'CONFLICT';
}
