<?php

namespace Tests\Fakes;

use App\Contracts\MetaPublisherInterface;
use App\Models\MetaConnection;
use App\Services\Meta\MetaContainerStatus;
use App\Services\Meta\MetaContainerStatusResult;
use App\Services\Publishing\InstagramPublicationPayload;
use App\Services\Publishing\PublicationResult;

class FakeMetaPublisher implements MetaPublisherInterface
{
    public int $createCalls = 0;

    public int $publishCalls = 0;

    public int $statusCalls = 0;

    /** @var list<string> */
    public array $captions = [];

    /** @var list<string> */
    public array $calls = [];

    public PublicationResult $createResult;

    public PublicationResult $publishResult;

    /** @var list<MetaContainerStatusResult> */
    public array $statusResults;

    public function __construct()
    {
        $this->createResult = PublicationResult::succeeded(externalContainerId: 'fake_container');
        $this->statusResults = [MetaContainerStatusResult::succeeded(MetaContainerStatus::FINISHED)];
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
        $this->calls[] = 'create';

        return $this->createResult;
    }

    public function getContainerStatus(
        MetaConnection $connection,
        string $containerId,
    ): MetaContainerStatusResult {
        $result = $this->statusResults[min($this->statusCalls, count($this->statusResults) - 1)];
        $this->statusCalls++;
        $this->calls[] = 'status';

        return $result;
    }

    public function publishContainer(
        MetaConnection $connection,
        string $containerId,
    ): PublicationResult {
        $this->publishCalls++;
        $this->calls[] = 'publish';

        return $this->publishResult;
    }
}
