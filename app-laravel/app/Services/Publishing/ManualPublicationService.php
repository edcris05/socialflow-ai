<?php

namespace App\Services\Publishing;

use App\Models\Draft;
use App\Models\MetaConnection;
use App\Models\PublicationAttempt;
use App\Models\PublicationMedia;
use App\Models\ScheduledPublication;
use App\Models\User;

class ManualPublicationService
{
    private const PROVIDER = 'meta';

    public function __construct(
        private PublicMediaPreflight $preflight,
        private PublicationOrchestrator $orchestrator,
    ) {}

    public function publish(User $actor, ScheduledPublication $scheduledPublication): PublicationResult
    {
        $publication = ScheduledPublication::query()
            ->whereKey($scheduledPublication->getKey())
            ->whereHas('brand.users', fn ($query) => $query->whereKey($actor->getKey()))
            ->with(['draft.currentPublicationMedia', 'brand.metaConnection'])
            ->firstOrFail();

        if (! config('services.meta.publishing_enabled', false)) {
            return PublicationResult::failed('PUBLISHING_DISABLED', 'La publicación en Meta está deshabilitada.');
        }

        $existingAttempt = PublicationAttempt::query()
            ->where('scheduled_publication_id', $publication->getKey())
            ->where('provider', self::PROVIDER)
            ->latest('id')
            ->first();

        if ($existingAttempt !== null) {
            return $this->existingResult($existingAttempt);
        }

        $failure = $this->localPreconditionFailure($publication);

        if ($failure !== null) {
            return $failure;
        }

        /** @var PublicationMedia $media */
        $media = $publication->draft->currentPublicationMedia;
        $preflightResult = $this->preflight->verifyAndRecord($media);

        if (! $preflightResult->passed) {
            return PublicationResult::failed(
                $preflightResult->errorCode ?? 'MEDIA_PREFLIGHT_FAILED',
                $preflightResult->errorMessage ?? 'No se pudo verificar la imagen pública.',
            );
        }

        return $this->orchestrator->publish($actor, $publication);
    }

    private function localPreconditionFailure(ScheduledPublication $publication): ?PublicationResult
    {
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

        /** @var MetaConnection|null $connection */
        $connection = $publication->brand->metaConnection;

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
                $attempt->external_container_id,
                $attempt->external_media_id,
                alreadyPublished: true,
            );
        }

        if ($attempt->status === PublicationAttempt::STATUS_PUBLISHING) {
            return PublicationResult::failed('PUBLICATION_IN_PROGRESS', 'Ya existe una publicación en curso para esta programación.');
        }

        if ($attempt->status === PublicationAttempt::STATUS_OUTCOME_UNKNOWN) {
            return PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                $attempt->last_error_message ?? 'Meta recibió la publicación, pero no fue posible confirmar el resultado.',
                outcomeUncertain: true,
                externalContainerId: $attempt->external_container_id,
            );
        }

        return PublicationResult::failed(
            $attempt->last_error_code ?? 'PUBLICATION_ALREADY_FAILED',
            $attempt->last_error_message ?? 'El intento anterior falló y no se reintentará automáticamente.',
            externalContainerId: $attempt->external_container_id,
        );
    }
}
