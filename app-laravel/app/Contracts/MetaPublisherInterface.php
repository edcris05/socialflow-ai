<?php

namespace App\Contracts;

use App\Models\MetaConnection;
use App\Services\Publishing\InstagramPublicationPayload;
use App\Services\Publishing\PublicationResult;

interface MetaPublisherInterface
{
    public function createContainer(
        MetaConnection $connection,
        InstagramPublicationPayload $payload,
    ): PublicationResult;

    public function publishContainer(
        MetaConnection $connection,
        string $containerId,
    ): PublicationResult;
}
