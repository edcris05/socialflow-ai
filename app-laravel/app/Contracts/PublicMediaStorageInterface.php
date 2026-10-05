<?php

namespace App\Contracts;

use App\Models\PublicationMedia;
use App\Services\Publishing\PublicMediaStorageResult;

/**
 * Stores a derived public copy for publication. Creative source assets and
 * libraries such as Google Drive remain outside this delivery boundary.
 */
interface PublicMediaStorageInterface
{
    public function store(PublicationMedia $media): PublicMediaStorageResult;
}
