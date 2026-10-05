<?php

namespace App\Services\Publishing;

use App\Contracts\PublicHostResolverInterface;
use App\Models\PublicationMedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The controlled-host allowlist is the primary SSRF boundary. DNS is checked
 * immediately before HTTP, but application-level resolution cannot eliminate
 * every DNS-rebinding race; production egress controls remain recommended.
 */
class PublicMediaPreflight
{
    public function __construct(private PublicHostResolverInterface $resolver) {}

    public function verifyAndRecord(PublicationMedia $media): PublicMediaPreflightResult
    {
        $result = $this->verify($media);

        $media->update([
            'preflight_status' => $result->passed
                ? PublicationMedia::PREFLIGHT_PASSED
                : PublicationMedia::PREFLIGHT_FAILED,
            'preflight_checked_at' => $result->checkedAt,
            'preflight_final_url' => $result->finalUrl,
            'preflight_content_type' => $result->contentType,
            'preflight_content_length' => $result->contentLength,
            'preflight_error_code' => $result->errorCode,
            'preflight_error_message' => $result->errorMessage,
        ]);

        return $result;
    }

    public function verify(PublicationMedia $media): PublicMediaPreflightResult
    {
        if ($media->superseded_at !== null || $media->status !== PublicationMedia::STATUS_APPROVED) {
            return $this->failure('MEDIA_NOT_APPROVED', 'La imagen vigente requiere aprobación explícita.');
        }

        $url = $media->public_url;

        if (! PublicMediaUrl::isValid($url)) {
            return $this->failure('MEDIA_URL_UNSAFE', 'La URL pública no cumple la política de seguridad.');
        }

        $parts = parse_url((string) $url);
        $host = Str::lower((string) ($parts['host'] ?? ''));
        $allowedHosts = config('services.publication_media.allowed_hosts', []);

        if (! is_array($allowedHosts) || ! in_array($host, $allowedHosts, true)) {
            return $this->failure('MEDIA_HOST_NOT_ALLOWED', 'El host de la imagen no está habilitado para publicación.');
        }

        try {
            $addresses = $this->resolver->resolve($host);
        } catch (Throwable) {
            return $this->failure('MEDIA_UNREACHABLE', 'No se pudo resolver el host de la imagen pública.');
        }

        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! $this->isPublicAddress($address))) {
            return $this->failure('MEDIA_URL_UNSAFE', 'El destino de la imagen no es una dirección pública segura.');
        }

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])->connectTimeout((int) config('services.publication_media.connect_timeout', 5))
                ->timeout((int) config('services.publication_media.timeout', 10))
                ->accept('image/jpeg')
                ->withHeaders(['Range' => 'bytes=0-'.(int) config('services.publication_media.max_bytes', 8388608)])
                ->get((string) $url);
        } catch (ConnectionException) {
            return $this->failure('MEDIA_UNREACHABLE', 'No se pudo acceder a la imagen pública.');
        } catch (Throwable) {
            return $this->failure('MEDIA_PREFLIGHT_FAILED', 'No se pudo verificar la imagen pública.');
        }

        return $this->validateResponse($response, (string) $url);
    }

    private function validateResponse(Response $response, string $url): PublicMediaPreflightResult
    {
        if ($response->redirect()) {
            $this->close($response);

            return $this->failure('MEDIA_REDIRECT_REJECTED', 'La URL de la imagen no puede redirigir.');
        }

        if (! $response->successful()) {
            $this->close($response);

            return $this->failure('MEDIA_UNREACHABLE', 'La imagen pública no respondió correctamente.');
        }

        $contentType = Str::lower(trim(Str::before((string) $response->header('Content-Type'), ';')));

        if ($contentType !== 'image/jpeg') {
            $this->close($response);

            return $this->failure('MEDIA_CONTENT_TYPE_INVALID', 'La URL pública debe devolver Content-Type image/jpeg.');
        }

        $maxBytes = max(1, (int) config('services.publication_media.max_bytes', 8388608));
        $declaredLength = $this->declaredLength($response);

        if ($declaredLength !== null && $declaredLength > $maxBytes) {
            $this->close($response);

            return $this->failure('MEDIA_TOO_LARGE', 'La imagen pública supera el tamaño máximo permitido.');
        }

        $stream = $response->toPsrResponse()->getBody();
        $prefix = '';
        $bytesRead = 0;

        try {
            while (! $stream->eof() && $bytesRead <= $maxBytes) {
                $chunk = $stream->read(min(8192, $maxBytes + 1 - $bytesRead));

                if ($chunk === '') {
                    break;
                }

                if (strlen($prefix) < 3) {
                    $prefix .= substr($chunk, 0, 3 - strlen($prefix));
                }

                $bytesRead += strlen($chunk);
            }
        } finally {
            $stream->close();
        }

        if ($bytesRead > $maxBytes) {
            return $this->failure('MEDIA_TOO_LARGE', 'La imagen pública supera el tamaño máximo permitido.');
        }

        if (! str_starts_with($prefix, "\xFF\xD8\xFF")) {
            return $this->failure('MEDIA_CONTENT_INVALID', 'El contenido remoto no es una imagen JPEG válida.');
        }

        return PublicMediaPreflightResult::passed(
            finalUrl: $this->safeUrlForMetadata($url),
            contentType: $contentType,
            contentLength: $declaredLength ?? $bytesRead,
        );
    }

    private function declaredLength(Response $response): ?int
    {
        $contentRange = (string) $response->header('Content-Range');

        if (preg_match('/\/([0-9]+)$/', $contentRange, $matches) === 1) {
            return (int) $matches[1];
        }

        $contentLength = $response->header('Content-Length');

        return is_string($contentLength) && ctype_digit($contentLength)
            ? (int) $contentLength
            : null;
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function close(Response $response): void
    {
        $response->toPsrResponse()->getBody()->close();
    }

    private function safeUrlForMetadata(string $url): string
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '/');

        return 'https://'.(string) ($parts['host'] ?? '').$path;
    }

    private function failure(string $code, string $message): PublicMediaPreflightResult
    {
        return PublicMediaPreflightResult::failed($code, $message);
    }
}
