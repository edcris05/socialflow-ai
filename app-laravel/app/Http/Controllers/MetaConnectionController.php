<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetaConnectionRequest;
use App\Models\Brand;
use App\Models\MetaConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MetaConnectionController extends Controller
{
    public function show(Brand $brand): View
    {
        $brand = $this->ownedBrand($brand);
        $connection = $brand->metaConnection;

        return view('meta-connections.show', compact('brand', 'connection'));
    }

    public function store(MetaConnectionRequest $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $connection = $brand->metaConnection ?? new MetaConnection(['brand_id' => $brand->getKey()]);
        $validated = $request->validated();
        $newToken = trim((string) ($validated['access_token'] ?? ''));
        $hadToken = $connection->exists && $connection->isConfigured();

        $connection->brand()->associate($brand);
        $connection->configured_by = $request->user()->getKey();
        $connection->facebook_page_id = $this->nullableString($validated['facebook_page_id'] ?? null);
        $connection->instagram_account_id = $this->nullableString($validated['instagram_account_id'] ?? null);
        $connection->token_expires_at = $validated['token_expires_at'] ?? null;
        $connection->status = MetaConnection::STATUS_CONFIGURED_UNVERIFIED;
        $connection->last_verified_at = null;
        $connection->last_error = null;

        if ($newToken !== '') {
            $connection->access_token = $newToken;
        }

        $connection->save();

        $message = $newToken !== ''
            ? 'Se guardaron los cambios. El token fue actualizado.'
            : ($hadToken
                ? 'Se guardaron los cambios. El token existente se conservó.'
                : 'Se guardaron los cambios. Quedó sin verificar.');

        return to_route('marcas.meta.show', $brand)->with('status', $message);
    }

    public function deleteToken(Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $connection = $brand->metaConnection;

        if ($connection) {
            $connection->update([
                'access_token' => null,
                'status' => MetaConnection::STATUS_CONFIGURED_UNVERIFIED,
                'last_verified_at' => null,
                'last_error' => null,
            ]);
        }

        return to_route('marcas.meta.show', $brand)->with('status', 'El token fue eliminado. La conexión y sus identificadores se conservaron.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
