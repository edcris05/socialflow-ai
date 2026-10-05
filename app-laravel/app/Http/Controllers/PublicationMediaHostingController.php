<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Services\Publishing\PublicMediaHostingService;
use Illuminate\Http\RedirectResponse;

class PublicationMediaHostingController extends Controller
{
    public function store(
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
        PublicMediaHostingService $hostingService,
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
        $result = $hostingService->host(request()->user(), $media);
        $flash = $result->successful
            ? ['status' => 'La copia pública fue preparada. Todavía requiere preflight antes de publicar.']
            : ['error' => $result->errorMessage];

        return to_route('marcas.borradores.edit', [$brand, $draft])->with($flash);
    }
}
