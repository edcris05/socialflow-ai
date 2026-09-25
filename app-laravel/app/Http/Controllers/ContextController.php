<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContextRequest;
use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Services\Knowledge\ContextBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContextController extends Controller
{
    public function create(Brand $brand): View
    {
        return view('context.create', [
            'brand' => $this->ownedBrand($brand),
            'package' => null,
        ]);
    }

    public function preview(ContextRequest $request, Brand $brand, ContextBuilder $builder): View
    {
        $brand = $this->ownedBrand($brand);

        return view('context.create', [
            'brand' => $brand,
            'package' => $builder->build($brand, $request->validated('query')),
        ]);
    }

    public function storeDraft(ContextRequest $request, Brand $brand, ContextBuilder $builder): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $package = $builder->build($brand, $request->validated('query'));

        $draft = new Draft([
            'title' => 'Borrador: '.Str::limit(trim($package->query), 80, '...'),
            'content' => '',
            'status' => 'draft',
        ]);
        $draft->brand()->associate($brand);
        $draft->user()->associate($request->user());
        $draft->save();

        $snapshot = ContextSnapshot::fromPackage($draft, $request->user(), $package);
        $snapshot->save();

        return redirect()->route('marcas.borradores.edit', [$brand, $draft])
            ->with('status', 'Se preparó el borrador con el contexto actual.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}