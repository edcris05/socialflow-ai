<?php

namespace App\Console\Commands;

use App\Services\Publishing\DueScheduledPublicationFinder;
use App\Services\Publishing\ScheduledPublicationBatchProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('socialflow:publish-due {--limit= : Maximum number of due candidates to process}')]
#[Description('Process a bounded batch of due scheduled publications')]
class PublishDueScheduledPublications extends Command
{
    public function handle(
        ScheduledPublicationBatchProcessor $batchProcessor,
        DueScheduledPublicationFinder $finder,
    ): int {
        $limit = $this->requestedLimit();

        if ($limit === null) {
            $this->error('The --limit option must be a positive integer.');

            return self::INVALID;
        }

        $boundedLimit = $finder->boundedLimit($limit);
        $summary = $batchProcessor->process($boundedLimit);

        $this->line(implode(' ', [
            'examined='.$summary->examined,
            'published='.$summary->published,
            'skipped='.$summary->skipped,
            'blocked='.$summary->blocked,
            'failed='.$summary->failed,
            'outcome_unknown='.$summary->outcomeUnknown,
        ]));

        return self::SUCCESS;
    }

    private function requestedLimit(): ?int
    {
        $rawLimit = $this->option('limit');

        if ($rawLimit === null) {
            return max(1, (int) config('services.scheduled_publishing.default_limit', 25));
        }

        if ((! is_int($rawLimit) && ! is_string($rawLimit))
            || filter_var($rawLimit, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $limit = (int) $rawLimit;

        return $limit > 0 ? $limit : null;
    }
}
