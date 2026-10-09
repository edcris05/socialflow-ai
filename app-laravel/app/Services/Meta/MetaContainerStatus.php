<?php

namespace App\Services\Meta;

enum MetaContainerStatus: string
{
    case FINISHED = 'FINISHED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case ERROR = 'ERROR';
    case EXPIRED = 'EXPIRED';
    case PUBLISHED = 'PUBLISHED';
}
