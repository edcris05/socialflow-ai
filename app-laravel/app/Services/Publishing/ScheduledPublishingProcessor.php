<?php

namespace App\Services\Publishing;

use App\Models\PublicationAttempt;
use App\Models\ScheduledPublication;

class ScheduledPublishingProcessor
{
    private const PROVIDER = 'meta';

    public function __construct(private PublicationOrchestrator $orchestrator) {}

    public function process(ScheduledPublication $scheduledPublication): ScheduledPublishingResult
    {
        $publication = ScheduledPublication::query()
            ->with('brand.autopublishingSetting')
            ->findOrFail($scheduledPublication->getKey());

        if ($publication->status !== ScheduledPublication::STATUS_SCHEDULED) {
            return ScheduledPublishingResult::skipped('SCHEDULE_CANCELLED', 'La programación está cancelada.');
        }

        if (! $publication->isReady()) {
            return ScheduledPublishingResult::skipped('SCHEDULE_NOT_READY', 'La fecha programada todavía no llegó.');
        }

        $existingAttempt = $this->existingAttempt($publication);

        if ($existingAttempt !== null) {
            return $this->existingAttemptResult($existingAttempt);
        }

        if (! config('services.scheduled_publishing.enabled', false)) {
            return ScheduledPublishingResult::blocked(
                'SCHEDULED_PUBLISHING_DISABLED',
                'La publicación programada automática está deshabilitada.',
            );
        }

        if (! $publication->brand->autopublishingEnabled()) {
            return ScheduledPublishingResult::blocked(
                'BRAND_AUTOPUBLISH_DISABLED',
                'La autopublicación está deshabilitada para esta marca.',
            );
        }

        $publicationResult = $this->orchestrator->publishAutomatically($publication);

        if ($publicationResult->successful) {
            if ($publicationResult->alreadyPublished) {
                return ScheduledPublishingResult::skipped(
                    'ALREADY_PUBLISHED',
                    'La programación ya fue publicada.',
                );
            }

            return ScheduledPublishingResult::published();
        }

        if ($publicationResult->outcomeUncertain) {
            return ScheduledPublishingResult::outcomeUnknown(
                $publicationResult->errorCode ?? 'META_PUBLISH_OUTCOME_UNKNOWN',
                $publicationResult->errorMessage ?? 'No fue posible confirmar el resultado de publicación.',
            );
        }

        $attempt = $this->existingAttempt($publication);

        if ($attempt !== null) {
            return $this->existingAttemptResult($attempt);
        }

        return ScheduledPublishingResult::blocked(
            $publicationResult->errorCode ?? 'PUBLICATION_PRECONDITION_FAILED',
            $publicationResult->errorMessage ?? 'La publicación no cumple las precondiciones requeridas.',
        );
    }

    private function existingAttempt(ScheduledPublication $publication): ?PublicationAttempt
    {
        return PublicationAttempt::query()
            ->where('scheduled_publication_id', $publication->getKey())
            ->where('provider', self::PROVIDER)
            ->first();
    }

    private function existingAttemptResult(PublicationAttempt $attempt): ScheduledPublishingResult
    {
        return match ($attempt->status) {
            PublicationAttempt::STATUS_PUBLISHED => ScheduledPublishingResult::skipped(
                'ALREADY_PUBLISHED',
                'La programación ya fue publicada.',
            ),
            PublicationAttempt::STATUS_OUTCOME_UNKNOWN => ScheduledPublishingResult::outcomeUnknown(
                $attempt->last_error_code ?? 'META_PUBLISH_OUTCOME_UNKNOWN',
                $attempt->last_error_message ?? 'No fue posible confirmar el resultado de publicación.',
            ),
            PublicationAttempt::STATUS_FAILED => ScheduledPublishingResult::failed(
                $attempt->last_error_code ?? 'PUBLICATION_ALREADY_FAILED',
                $attempt->last_error_message ?? 'El intento anterior falló y no se reintentará automáticamente.',
            ),
            default => ScheduledPublishingResult::blocked(
                'PUBLICATION_IN_PROGRESS',
                'Ya existe una publicación en curso para esta programación.',
            ),
        };
    }
}
