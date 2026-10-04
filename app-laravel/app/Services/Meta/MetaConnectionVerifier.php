<?php

namespace App\Services\Meta;

use App\Models\MetaConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MetaConnectionVerifier
{
    public function verify(MetaConnection $connection): bool
    {
        if (! $connection->isConfigured() || blank($connection->instagram_account_id)) {
            return false;
        }

        try {
            $response = Http::baseUrl((string) config('services.meta.base_url'))
                ->acceptJson()
                ->withToken($connection->access_token)
                ->connectTimeout((int) config('services.meta.connect_timeout'))
                ->timeout((int) config('services.meta.timeout'))
                ->get('/'.config('services.meta.version').'/me', [
                    'fields' => 'user_id,username',
                ]);
        } catch (ConnectionException) {
            return $this->fail($connection, 'No se pudo conectar con Instagram. Intentá verificar nuevamente.');
        }

        $errorCode = $response->json('error.code');

        if (in_array($response->status(), [401, 403], true) || (int) $errorCode === 190) {
            return $this->fail($connection, 'El token fue rechazado o no tiene permisos para consultar la cuenta de Instagram.');
        }

        if ($response->serverError()) {
            return $this->fail($connection, 'Instagram no está disponible temporalmente. Intentá verificar nuevamente más tarde.');
        }

        if ($response->failed()) {
            return $this->fail($connection, 'No se pudo verificar la conexión con Instagram.');
        }

        $data = $response->json();
        $userId = is_array($data) ? ($data['user_id'] ?? null) : null;

        if (! is_string($userId) && ! is_int($userId)) {
            return $this->fail($connection, 'Instagram devolvió una respuesta inválida.');
        }

        if ((string) $userId !== (string) $connection->instagram_account_id) {
            return $this->fail($connection, 'La cuenta autenticada no coincide con el Instagram Account ID configurado.');
        }

        $connection->update([
            'status' => MetaConnection::STATUS_VERIFIED,
            'last_verified_at' => now(),
            'last_error' => null,
        ]);

        return true;
    }

    private function fail(MetaConnection $connection, string $message): bool
    {
        $connection->update([
            'status' => MetaConnection::STATUS_ERROR,
            'last_error' => $message,
        ]);

        return false;
    }
}
