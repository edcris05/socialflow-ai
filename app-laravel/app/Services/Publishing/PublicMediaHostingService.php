<?php

namespace App\Services\Publishing;

use App\Contracts\PublicMediaStorageInterface;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublicMediaHostingService
{
    public function __construct(private PublicMediaStorageInterface $storage) {}

    public function host(User $actor, PublicationMedia $publicationMedia): PublicMediaHostingResult
    {
        $claim = DB::transaction(function () use ($actor, $publicationMedia): array {
            $media = PublicationMedia::query()
                ->whereKey($publicationMedia->getKey())
                ->whereHas('brand.users', fn ($query) => $query->whereKey($actor->getKey()))
                ->whereHas('draft', fn ($query) => $query
                    ->where('user_id', $actor->getKey())
                    ->whereColumn('drafts.brand_id', 'publication_media.brand_id'))
                ->lockForUpdate()
                ->firstOrFail();
            $hosting = PublicMediaHosting::query()
                ->where('publication_media_id', $media->getKey())
                ->lockForUpdate()
                ->first();

            if ($hosting?->status === PublicMediaHosting::STATUS_HOSTED) {
                return ['media' => null, 'hosting' => null, 'result' => PublicMediaHostingResult::succeeded($hosting)];
            }

            if ($hosting?->status === PublicMediaHosting::STATUS_HOSTING) {
                return ['media' => null, 'hosting' => null, 'result' => PublicMediaHostingResult::failed(
                    'PUBLIC_MEDIA_HOSTING_IN_PROGRESS',
                    'La copia pública ya se está preparando.',
                )];
            }

            $failure = $this->preconditionFailure($media);

            if ($failure !== null) {
                return ['media' => null, 'hosting' => null, 'result' => $failure];
            }

            $previousEffectiveUrl = $media->effectivePublicUrl();
            $hosting ??= new PublicMediaHosting(['publication_media_id' => $media->getKey()]);
            $hosting->fill([
                'status' => PublicMediaHosting::STATUS_HOSTING,
                'provider' => null,
                'disk' => null,
                'object_key' => null,
                'public_url' => null,
                'content_type' => null,
                'size_bytes' => null,
                'checksum_sha256' => null,
                'hosted_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            return [
                'media' => $media,
                'hosting' => $hosting,
                'previous_effective_url' => $previousEffectiveUrl,
                'result' => null,
            ];
        });

        if ($claim['result'] instanceof PublicMediaHostingResult) {
            return $claim['result'];
        }

        /** @var PublicationMedia $media */
        $media = $claim['media'];
        /** @var PublicMediaHosting $hosting */
        $hosting = $claim['hosting'];

        try {
            $storageResult = $this->storage->store($media);
        } catch (Throwable) {
            $storageResult = PublicMediaStorageResult::failed(
                'PUBLIC_MEDIA_STORAGE_FAILED',
                'No se pudo preparar la copia pública de la imagen.',
            );
        }

        if (! $storageResult->successful) {
            $hosting->update([
                'status' => PublicMediaHosting::STATUS_FAILED,
                'last_error_code' => $storageResult->errorCode,
                'last_error_message' => $storageResult->errorMessage,
            ]);

            return PublicMediaHostingResult::failed(
                $storageResult->errorCode ?? 'PUBLIC_MEDIA_STORAGE_FAILED',
                $storageResult->errorMessage ?? 'No se pudo preparar la copia pública de la imagen.',
            );
        }

        return DB::transaction(function () use ($claim, $hosting, $media, $storageResult): PublicMediaHostingResult {
            $lockedMedia = PublicationMedia::query()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();
            $lockedHosting = PublicMediaHosting::query()->whereKey($hosting->getKey())->lockForUpdate()->firstOrFail();
            $lockedHosting->update([
                'status' => PublicMediaHosting::STATUS_HOSTED,
                'provider' => $storageResult->provider,
                'disk' => $storageResult->disk,
                'object_key' => $storageResult->objectKey,
                'public_url' => $storageResult->publicUrl,
                'content_type' => $storageResult->contentType,
                'size_bytes' => $storageResult->sizeBytes,
                'checksum_sha256' => $storageResult->checksumSha256,
                'hosted_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            if ($claim['previous_effective_url'] !== $storageResult->publicUrl) {
                $lockedMedia->update(PublicationMedia::resetPreflightAttributes());
            }

            return PublicMediaHostingResult::succeeded($lockedHosting->fresh());
        });
    }

    private function preconditionFailure(PublicationMedia $media): ?PublicMediaHostingResult
    {
        if ($media->superseded_at !== null) {
            return PublicMediaHostingResult::failed(
                'PUBLIC_MEDIA_SUPERSEDED',
                'La imagen ya no es la versión vigente.',
            );
        }

        if ($media->status !== PublicationMedia::STATUS_APPROVED) {
            return PublicMediaHostingResult::failed(
                'PUBLIC_MEDIA_NOT_APPROVED',
                'La imagen requiere aprobación antes de alojarse.',
            );
        }

        if (! $media->hasValidImage()) {
            return PublicMediaHostingResult::failed(
                'PUBLIC_MEDIA_INVALID',
                'La imagen no tiene un formato admitido para publicación.',
            );
        }

        return null;
    }
}
