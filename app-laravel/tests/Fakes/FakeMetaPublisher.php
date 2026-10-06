<?php

namespace Tests\Fakes;

use App\Contracts\MetaPublisherInterface;
use App\Models\MetaConnection;
use App\Services\Publishing\InstagramPublicationPayload;
use App\Services\Publishing\PublicationResult;

class FakeMetaPublisher implements MetaPublisherInterface
{
    public int $createCalls = 0;

    public int $publishCalls = 0;

    /** @var list<string> */
    public array $captions = [];

    public PublicationResult $createResult;

    public PublicationResult $publishResult;

    public function __construct()
    {
        $this->createResult = PublicationResult::succeeded(externalContainerId: 'fake_container');
        $this->publishResult = PublicationResult::succeeded(
            externalContainerId: 'fake_container',
            externalMediaId: 'fake_media',
        );
    }

    public function createContainer(
        MetaConnection $connection,
        InstagramPublicationPayload $payload,
    ): PublicationResult {
        $this->createCalls++;
        $this->captions[] = $payload->caption;

        return $this->createResult;
    }

    public function publishContainer(
        MetaConnection $connection,
        string $containerId,
    ): PublicationResult {
        $this->publishCalls++;

        return $this->publishResult;
    }
}
