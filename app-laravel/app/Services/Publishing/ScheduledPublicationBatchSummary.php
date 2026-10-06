<?php

namespace App\Services\Publishing;

class ScheduledPublicationBatchSummary
{
    public int $examined = 0;

    public int $published = 0;

    public int $skipped = 0;

    public int $blocked = 0;

    public int $failed = 0;

    public int $outcomeUnknown = 0;

    public function record(ScheduledPublishingResult $result): void
    {
        $this->examined++;

        match ($result->status) {
            ScheduledPublishingResult::STATUS_PUBLISHED => $this->published++,
            ScheduledPublishingResult::STATUS_SKIPPED => $this->skipped++,
            ScheduledPublishingResult::STATUS_BLOCKED => $this->blocked++,
            ScheduledPublishingResult::STATUS_FAILED => $this->failed++,
            ScheduledPublishingResult::STATUS_OUTCOME_UNKNOWN => $this->outcomeUnknown++,
        };
    }
}
