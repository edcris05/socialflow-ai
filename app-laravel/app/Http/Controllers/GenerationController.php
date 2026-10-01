<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Services\Generation\GenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GenerationController extends Controller
{
    public function store(Request $request, Brand $brand, Draft $draft, GenerationService $service): RedirectResponse
    {
        $brand = request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
        $draft = $brand->drafts()->where('user_id', $request->user()->id)->whereKey($draft)->firstOrFail();
        if ($draft->status !== Draft::STATUS_DRAFT) {
            abort(422, 'Sólo un borrador puede generar contenido.');
        }
        $validated = $request->validate(['generation_token' => ['required', 'string', 'size:40']]);
        $token = $request->session()->pull('generation-tokens.'.$validated['generation_token']);
        abort_unless(is_array($token) && ($token['brand_id'] ?? null) === (string) $brand->getKey() && ($token['draft_id'] ?? null) === (string) $draft->getKey() && ($token['user_id'] ?? null) === (string) $request->user()->getKey(), 422);
        $run = $service->generate($draft, $request->user(), $request->boolean('regenerate'));

        return to_route('marcas.borradores.edit', [$brand, $draft])->with($run->status === 'succeeded' ? 'status' : 'error', $run->status === 'succeeded' ? 'Contenido generado.' : 'No se pudo generar contenido.');
    }
}
