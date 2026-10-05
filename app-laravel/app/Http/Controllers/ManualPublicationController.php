<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ScheduledPublication;
use App\Services\Publishing\ManualPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualPublicationController extends Controller
{
    public function store(
        Request $request,
        Brand $brand,
        ScheduledPublication $scheduledPublication,
        ManualPublicationService $publisher,
    ): RedirectResponse {
        $brand = $request->user()->brands()->whereKey($brand->getKey())->firstOrFail();
        $publication = $brand->scheduledPublications()->whereKey($scheduledPublication->getKey())->firstOrFail();
        $request->validate([
            'confirm_publish' => ['required', 'in:true'],
        ]);
        $result = $publisher->publish($request->user(), $publication);
        $flash = $result->successful
            ? ['status' => 'La publicación fue completada en Instagram.']
            : ['error' => $result->errorMessage];

        return to_route('marcas.programacion.index', $brand)->with($flash);
    }
}
