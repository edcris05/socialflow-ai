<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Services\Publishing\PublicMediaPreflight;
use Illuminate\Http\RedirectResponse;

class PublicationMediaPreflightController extends Controller
{
    public function store(
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
        PublicMediaPreflight $preflight,
    ): RedirectResponse {
        $brand = request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
        $draft = $brand->drafts()
            ->where('user_id', request()->user()->getKey())
            ->whereKey($draft->getKey())
            ->firstOrFail();
        $media = $draft->publicationMedia()
            ->where('brand_id', $brand->getKey())
            ->whereKey($publicationMedia->getKey())
            ->whereNull('superseded_at')
            ->firstOrFail();

        abort_unless($media->status === PublicationMedia::STATUS_APPROVED && filled($media->public_url), 422);

        $result = $preflight->verifyAndRecord($media);
        $flash = $result->passed
            ? ['status' => 'La imagen pública fue verificada correctamente.']
            : ['error' => $result->errorMessage];

        return to_route('marcas.borradores.edit', [$brand, $draft])->with($flash);
    }
}
