<?php

namespace App\Services\Meta;

use App\Contracts\MetaPublisherInterface;
use App\Models\MetaConnection;
use App\Services\Publishing\InstagramPublicationPayload;
use App\Services\Publishing\PublicationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InstagramMetaPublisher implements MetaPublisherInterface
{
    public function createContainer(
        MetaConnection $connection,
        InstagramPublicationPayload $payload,
    ): PublicationResult {
        try {
            $response = $this->request($connection)->post(
                '/'.config('services.meta.version').'/'.$connection->instagram_account_id.'/media',
                [
                    'image_url' => $payload->imageUrl,
                    'caption' => $payload->caption,
                ],
            );
        } catch (ConnectionException) {
            return PublicationResult::failed(
                'NETWORK_ERROR',
                'No se pudo conectar con Instagram para crear el contenedor.',
            );
        }

        if (in_array($response->status(), [401, 403], true)) {
            return PublicationResult::failed(
                'META_AUTH_FAILED',
                'Instagram rechazó la autenticación o los permisos de publicación.',
            );
        }

        if ($response->serverError()) {
            return PublicationResult::failed(
                'META_UNAVAILABLE',
                'Instagram no está disponible temporalmente.',
            );
        }

        if ($response->failed()) {
            return PublicationResult::failed(
                'META_CREATE_FAILED',
                'Instagram rechazó la creación del contenedor.',
            );
        }

        $containerId = $this->responseId($response);
        if ($containerId === null) {
            return PublicationResult::failed(
                'META_RESPONSE_INVALID',
                'Instagram devolvió una respuesta inválida al crear el contenedor.',
            );
        }

        return PublicationResult::succeeded(externalContainerId: $containerId);
    }

    public function publishContainer(
        MetaConnection $connection,
        string $containerId,
    ): PublicationResult {
        try {
            $response = $this->request($connection)->post(
                '/'.config('services.meta.version').'/'.$connection->instagram_account_id.'/media_publish',
                ['creation_id' => $containerId],
            );
        } catch (ConnectionException) {
            return PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                'No se pudo confirmar si Instagram completó la publicación.',
                outcomeUncertain: true,
                externalContainerId: $containerId,
            );
        }

        if (in_array($response->status(), [401, 403], true)) {
            return PublicationResult::failed(
                'META_AUTH_FAILED',
                'Instagram rechazó la autenticación o los permisos de publicación.',
                externalContainerId: $containerId,
            );
        }

        if ($response->serverError()) {
            return PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                'Instagram no confirmó si completó la publicación.',
                outcomeUncertain: true,
                externalContainerId: $containerId,
            );
        }

        if ($response->failed()) {
            return PublicationResult::failed(
                'META_PUBLISH_FAILED',
                'Instagram rechazó la publicación del contenedor.',
                externalContainerId: $containerId,
            );
        }

        $mediaId = $this->responseId($response);
        if ($mediaId === null) {
            return PublicationResult::failed(
                'META_PUBLISH_OUTCOME_UNKNOWN',
                'Instagram no devolvió un identificador de publicación válido.',
                outcomeUncertain: true,
                externalContainerId: $containerId,
            );
        }

        return PublicationResult::succeeded(
            externalContainerId: $containerId,
            externalMediaId: $mediaId,
        );
    }

    private function request(MetaConnection $connection): PendingRequest
    {
        return Http::baseUrl((string) config('services.meta.base_url'))
            ->asForm()
            ->acceptJson()
            ->withToken((string) $connection->access_token)
            ->connectTimeout((int) config('services.meta.connect_timeout'))
            ->timeout((int) config('services.meta.timeout'));
    }

    private function responseId(Response $response): ?string
    {
        $data = $response->json();
        $id = is_array($data) ? ($data['id'] ?? null) : null;

        if ((! is_string($id) && ! is_int($id)) || blank((string) $id)) {
            return null;
        }

        return (string) $id;
    }
}
