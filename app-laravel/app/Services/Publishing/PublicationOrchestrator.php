<?php

namespace App\Services\Publishing;

use App\Contracts\MetaPublisherInterface;
use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublicationOrchestrator
{
    private const PROVIDER = 'meta';

    public function __construct(private MetaPublisherInterface $publisher) {}

    public function publish(
        User $actor,
        ScheduledPublication $scheduledPublication,
    ): PublicationResult {
        $publication = $this->ownedPublication($actor, $scheduledPublication);

        if (! config('services.meta.publishing_enabled', false)) {
            return PublicationResult::failed(
                'PUBLISHING_DISABLED',
                'La publicación en Meta está deshabilitada.',
            );
        }

        $claim = DB::transaction(function () use ($actor, $publication): array {
            $lockedPublication = ScheduledPublication::query()
                ->whereKey($publication->getKey())
                ->whereHas('brand.users', fn ($query) => $query->whereKey($actor->getKey()))
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDraft = Draft::query()
                ->whereKey($lockedPublication->draft_id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentMedia = PublicationMedia::query()
                ->where('draft_id', $lockedDraft->getKey())
                ->whereNull('superseded_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $lockedDraft->setRelation('currentPublicationMedia', $currentMedia);
            $lockedPublication->setRelation('draft', $lockedDraft);

            $connection = MetaConnection::query()
                ->where('brand_id', $lockedPublication->brand_id)
                ->lockForUpdate()
                ->first();

            $existingAttempt = PublicationAttempt::query()
                ->where('scheduled_publication_id', $lockedPublication->getKey())
                ->where('provider', self::PROVIDER)
                ->first();

            if ($existingAttempt !== null) {
                return ['attempt' => null, 'result' => $this->existingResult($existingAttempt)];
            }

            $failure = $this->preconditionFailure($lockedPublication, $connection);
            if ($failure !== null) {
                return ['attempt' => null, 'result' => $failure];
            }

            /** @var MetaConnection $connection */
            /** @var PublicationMedia $media */
            $media = $lockedPublication->draft->currentPublicationMedia;
            $targetAccountId = (string) $connection->instagram_account_id;
            $idempotencyKey = $this->idempotencyKey($lockedPublication, $targetAccountId);

            $attempt = PublicationAttempt::create([
                'scheduled_publication_id' => $lockedPublication->getKey(),
                'meta_connection_id' => $connection->getKey(),
                'initiated_by' => $actor->getKey(),
                'provider' => self::PROVIDER,
                'status' => PublicationAttempt::STATUS_PUBLISHING,
                'attempt_count' => 1,
                'idempotency_key' => $idempotencyKey,
                'target_account_id_snapshot' => $targetAccountId,
                'caption_snapshot' => $lockedPublication->draft->content,
                'media_url_snapshot' => $media->effectivePublicUrl(),
                'started_at' => now(),
            ]);

            return ['attempt' => $attempt, 'result' => null];
        });

        if ($claim['result'] instanceof PublicationResult) {
            return $claim['result'];
        }

        /** @var PublicationAttempt $attempt */
        $attempt = $claim['attempt'];
        /** @var MetaConnection $connection */
        $connection = $attempt->metaConnection()->firstOrFail();
        $snapshotPayload = new InstagramPublicationPayload(
            $attempt->caption_snapshot,
            $attempt->media_url_snapshot,
        );

        try {
            $createResult = $this->publisher->createContainer($connection, $snapshotPayload);
        } catch (Throwable) {
            $createResult = PublicationResult::failed(
                'META_CREATE_FAILED',
                'No se pudo completar la creación del contenedor de Instagram.',
            );
        }

        if (! $createResult->successful) {
            return $this->failAttempt($attempt, $createResult);
        }

        if ($createResult->externalContainerId === null) {
            return $this->failAttempt($attempt, PublicationResult::failed(
                'META_RESPONSE_INVALID',
                'Instagram no devolvió un identificador de contenedor válido.',
            ));
        }

        $attempt->update(['external_container_id' => $createResult->externalContainerId]);

        try {
            $publishResult = $this->publisher->publishContainer(
                $connection,
                $createResult->externalContainerId,
            );
        } catch (Throwable) {
            $publishResult = PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                'No se pudo confirmar si Instagram completó la publicación.',
                outcomeUncertain: true,
                externalContainerId: $createResult->externalContainerId,
            );
        }

        if (! $publishResult->successful) {
            return $this->failAttempt($attempt, $publishResult);
        }

        if ($publishResult->externalMediaId === null) {
            return $this->failAttempt($attempt, PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                'Instagram no devolvió un identificador de publicación válido.',
                outcomeUncertain: true,
                externalContainerId: $createResult->externalContainerId,
            ));
        }

        $attempt->update([
            'status' => PublicationAttempt::STATUS_PUBLISHED,
            'external_media_id' => $publishResult->externalMediaId,
            'last_error_code' => null,
            'last_error_message' => null,
            'completed_at' => now(),
            'published_at' => now(),
        ]);

        return PublicationResult::succeeded(
            externalContainerId: $createResult->externalContainerId,
            externalMediaId: $publishResult->externalMediaId,
        );
    }

    private function ownedPublication(
        User $actor,
        ScheduledPublication $scheduledPublication,
    ): ScheduledPublication {
        return ScheduledPublication::query()
            ->whereKey($scheduledPublication->getKey())
            ->whereHas('brand.users', fn ($query) => $query->whereKey($actor->getKey()))
            ->firstOrFail();
    }

    private function preconditionFailure(
        ScheduledPublication $publication,
        ?MetaConnection $connection,
    ): ?PublicationResult {
        if ($publication->status !== ScheduledPublication::STATUS_SCHEDULED) {
            return PublicationResult::failed('SCHEDULE_CANCELLED', 'La programación está cancelada.');
        }

        if (! $publication->isReady()) {
            return PublicationResult::failed('SCHEDULE_NOT_READY', 'La fecha programada todavía no llegó.');
        }

        $draft = $publication->draft;
        if ($draft->brand_id !== $publication->brand_id) {
            return PublicationResult::failed('BRAND_MISMATCH', 'La programación y el borrador no pertenecen a la misma marca.');
        }

        if ($draft->status !== Draft::STATUS_APPROVED) {
            return PublicationResult::failed('DRAFT_NOT_APPROVED', 'El borrador ya no está aprobado.');
        }

        $media = $draft->currentPublicationMedia;
        if ($media === null) {
            return PublicationResult::failed('MISSING_MEDIA_ASSET', 'El borrador no tiene una imagen vigente.');
        }

        if ($media->brand_id !== $publication->brand_id || $media->draft_id !== $draft->getKey()) {
            return PublicationResult::failed('MEDIA_OWNERSHIP_MISMATCH', 'La imagen no pertenece a este borrador y marca.');
        }

        if ($media->status !== PublicationMedia::STATUS_APPROVED) {
            return PublicationResult::failed('MEDIA_NOT_APPROVED', 'La imagen vigente requiere aprobación explícita.');
        }

        if (! $media->hasValidImage()) {
            return PublicationResult::failed('MEDIA_INVALID', 'La imagen vigente no tiene un formato admitido para Meta.');
        }

        if (! $media->hasValidPublicUrl()) {
            return PublicationResult::failed('MEDIA_NOT_PUBLICLY_ACCESSIBLE', 'La imagen no tiene una URL pública válida para Meta.');
        }

        if (! $media->hasFreshPreflight()) {
            return PublicationResult::failed('MEDIA_PREFLIGHT_REQUIRED', 'La imagen pública requiere un preflight reciente.');
        }

        if ($connection === null) {
            return PublicationResult::failed('META_CONNECTION_MISSING', 'La marca no tiene una conexión de Meta.');
        }

        if ($connection->status !== MetaConnection::STATUS_VERIFIED) {
            return PublicationResult::failed('META_CONNECTION_UNVERIFIED', 'La conexión de Meta no está verificada.');
        }

        if (! $connection->isConfigured()) {
            return PublicationResult::failed('META_TOKEN_MISSING', 'La conexión de Meta no tiene access token.');
        }

        if (blank($connection->instagram_account_id)) {
            return PublicationResult::failed('META_ACCOUNT_MISSING', 'La conexión de Meta no tiene Instagram Account ID.');
        }

        return null;
    }

    private function existingResult(PublicationAttempt $attempt): PublicationResult
    {
        if ($attempt->status === PublicationAttempt::STATUS_PUBLISHED) {
            return PublicationResult::succeeded(
                externalContainerId: $attempt->external_container_id,
                externalMediaId: $attempt->external_media_id,
            );
        }

        if ($attempt->status === PublicationAttempt::STATUS_PUBLISHING) {
            return PublicationResult::failed(
                'PUBLICATION_IN_PROGRESS',
                'Ya existe una publicación en curso para esta programación.',
                externalContainerId: $attempt->external_container_id,
            );
        }

        if ($attempt->status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN) {
            return PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                $attempt->last_error_message ?? 'Meta recibió la publicación, pero no fue posible confirmar el resultado.',
                outcomeUncertain: true,
                externalContainerId: $attempt->external_container_id,
            );
        }

        $errorCode = $attempt->last_error_code ?? 'PUBLICATION_ALREADY_FAILED';

        return PublicationResult::failed(
            $errorCode,
            $attempt->last_error_message ?? 'El intento anterior falló y no se reintentará automáticamente.',
            outcomeUncertain: false,
            externalContainerId: $attempt->external_container_id,
        );
    }

    private function failAttempt(
        PublicationAttempt $attempt,
        PublicationResult $result,
    ): PublicationResult {
        $attempt->update([
            'status' => $result->outcomeUncertain
                ? PublicationAttempt::STATUS_OUTCOME_UNKNOWN
                : PublicationAttempt::STATUS_FAILED,
            'external_container_id' => $result->externalContainerId ?? $attempt->external_container_id,
            'external_media_id' => null,
            'last_error_code' => $result->errorCode,
            'last_error_message' => $result->errorMessage,
            'completed_at' => now(),
            'published_at' => null,
        ]);

        return $result;
    }

    private function idempotencyKey(
        ScheduledPublication $publication,
        string $targetAccountId,
    ): string {
        return hash('sha256', implode('|', [
            self::PROVIDER,
            (string) $publication->getKey(),
            $targetAccountId,
        ]));
    }
}
