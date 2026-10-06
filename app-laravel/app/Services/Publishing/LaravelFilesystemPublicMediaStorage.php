<?php

namespace App\Services\Publishing;

use App\Contracts\PublicMediaStorageInterface;
use App\Models\PublicationMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LaravelFilesystemPublicMediaStorage implements PublicMediaStorageInterface
{
    public function store(PublicationMedia $media): PublicMediaStorageResult
    {
        $diskName = trim((string) config('filesystems.public_media_disk'));

        if ($diskName === '') {
            return $this->failure(
                'PUBLIC_MEDIA_DISK_NOT_CONFIGURED',
                'El almacenamiento público no está configurado.',
            );
        }

        if ($diskName === $media->storage_disk) {
            return $this->failure(
                'PUBLIC_MEDIA_DISK_NOT_DISTINCT',
                'El almacenamiento público debe ser distinto del almacenamiento privado.',
            );
        }

        $diskConfig = config('filesystems.disks.'.$diskName);

        if (! is_array($diskConfig) || blank($diskConfig['driver'] ?? null)) {
            return $this->failure(
                'PUBLIC_MEDIA_DISK_NOT_CONFIGURED',
                'El almacenamiento público no está configurado.',
            );
        }

        $constraints = config('filesystems.public_media_disk_constraints.'.$diskName, []);
        $configurationFailure = $this->configurationFailure(
            $diskConfig,
            is_array($constraints) ? $constraints : [],
        );

        if ($configurationFailure !== null) {
            return $configurationFailure;
        }

        try {
            $sourceDisk = Storage::disk($media->storage_disk);

            if (! $sourceDisk->exists($media->storage_path)) {
                return $this->failure(
                    'PRIVATE_MEDIA_MISSING',
                    'No se encontró la imagen privada aprobada.',
                );
            }

            $checksumStream = $sourceDisk->readStream($media->storage_path);

            if (! is_resource($checksumStream)) {
                return $this->failure(
                    'PRIVATE_MEDIA_UNREADABLE',
                    'No se pudo leer la imagen privada aprobada.',
                );
            }

            try {
                $checksumContext = hash_init('sha256');
                hash_update_stream($checksumContext, $checksumStream);
                $checksum = hash_final($checksumContext);
            } finally {
                if (is_resource($checksumStream)) {
                    fclose($checksumStream);
                }
            }

            $copyStream = $sourceDisk->readStream($media->storage_path);

            if (! is_resource($copyStream)) {
                return $this->failure(
                    'PRIVATE_MEDIA_UNREADABLE',
                    'No se pudo leer la imagen privada aprobada.',
                );
            }

            $objectKey = $this->objectKey($media);
            $publicDisk = Storage::disk($diskName);

            try {
                $stored = $publicDisk->put($objectKey, $copyStream, [
                    'ContentType' => $media->mime_type,
                ]);
            } finally {
                if (is_resource($copyStream)) {
                    fclose($copyStream);
                }
            }

            if (! $stored) {
                return $this->failure(
                    'PUBLIC_MEDIA_WRITE_FAILED',
                    'No se pudo guardar la copia pública de la imagen.',
                );
            }

            $publicUrl = $publicDisk->url($objectKey);

            if (! PublicMediaUrl::isValid($publicUrl)) {
                $publicDisk->delete($objectKey);

                return $this->failure(
                    'PUBLIC_MEDIA_URL_INVALID',
                    'El almacenamiento no generó una URL pública HTTPS válida.',
                );
            }

            return PublicMediaStorageResult::succeeded(
                provider: (string) $diskConfig['driver'],
                disk: $diskName,
                objectKey: $objectKey,
                publicUrl: $publicUrl,
                contentType: $media->mime_type,
                sizeBytes: (int) $sourceDisk->size($media->storage_path),
                checksumSha256: $checksum,
            );
        } catch (Throwable) {
            return $this->failure(
                'PUBLIC_MEDIA_STORAGE_FAILED',
                'No se pudo preparar la copia pública de la imagen.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $diskConfig
     * @param  array<string, mixed>  $constraints
     */
    private function configurationFailure(array $diskConfig, array $constraints): ?PublicMediaStorageResult
    {
        foreach ($constraints['required'] ?? [] as $requiredKey) {
            if (blank($diskConfig[$requiredKey] ?? null)) {
                return $this->failure(
                    'PUBLIC_MEDIA_DISK_NOT_CONFIGURED',
                    'El almacenamiento público no está configurado.',
                );
            }
        }

        $publicUrl = (string) ($diskConfig['url'] ?? '');

        if (! PublicMediaUrl::isValid($publicUrl) || $this->hasQueryOrFragment($publicUrl)) {
            return $this->failure(
                'PUBLIC_MEDIA_URL_INVALID',
                'El almacenamiento no generó una URL pública HTTPS válida.',
            );
        }

        $endpoint = $diskConfig['endpoint'] ?? null;

        if (filled($endpoint)
            && (! PublicMediaUrl::isValid((string) $endpoint) || $this->hasQueryOrFragment((string) $endpoint))) {
            return $this->failure(
                'PUBLIC_MEDIA_STORAGE_CONFIG_INVALID',
                'La configuración del almacenamiento público no es válida.',
            );
        }

        foreach ($constraints['distinct_host_pairs'] ?? [] as $pair) {
            if (! is_array($pair) || count($pair) !== 2) {
                continue;
            }

            [$firstKey, $secondKey] = array_values($pair);
            $firstUrl = (string) ($diskConfig[$firstKey] ?? '');
            $secondUrl = (string) ($diskConfig[$secondKey] ?? '');

            if ($this->host($firstUrl) === $this->host($secondUrl)) {
                return $this->failure(
                    'PUBLIC_MEDIA_URL_INVALID',
                    'El almacenamiento no generó una URL pública HTTPS válida.',
                );
            }
        }

        return null;
    }

    private function hasQueryOrFragment(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) && (isset($parts['query']) || isset($parts['fragment']));
    }

    private function host(string $url): string
    {
        return Str::lower((string) parse_url($url, PHP_URL_HOST));
    }

    private function objectKey(PublicationMedia $media): string
    {
        return implode('/', [
            'publication-media',
            (string) $media->brand_id,
            (string) $media->draft_id,
            (string) $media->getKey(),
            Str::ulid().'.jpg',
        ]);
    }

    private function failure(string $code, string $message): PublicMediaStorageResult
    {
        return PublicMediaStorageResult::failed($code, $message);
    }
}
