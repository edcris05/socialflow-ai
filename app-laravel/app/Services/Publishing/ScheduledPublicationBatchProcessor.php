<?php

namespace App\Services\Publishing;

class ScheduledPublicationBatchProcessor
{
    public function __construct(
        private DueScheduledPublicationFinder $finder,
        private ScheduledPublishingProcessor $processor,
    ) {}

    public function process(int $limit): ScheduledPublicationBatchSummary
    {
        $summary = new ScheduledPublicationBatchSummary;

        foreach ($this->finder->find($limit) as $publication) {
            $summary->record($this->processor->process($publication));
        }

        return $summary;
    }
}
