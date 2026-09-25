<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('brands.index', [
            'brands' => auth()->user()->brands()->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('brands.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $brand = Brand::query()->create($request->safe()->only([
            'name',
            'slug',
            'description',
            'tone_of_voice',
            'target_audience',
            'social_channels',
            'restrictions',
        ]));

        $request->user()->brands()->attach($brand, ['role' => 'owner']);

        return to_route('marcas.show', $brand)
            ->with('status', 'La marca fue creada correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Brand $brand): View
    {
        return view('brands.show', ['brand' => $this->ownedBrand($brand)]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Brand $brand): View
    {
        return view('brands.edit', ['brand' => $this->ownedBrand($brand)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $brand->update($request->safe()->only([
            'name',
            'slug',
            'description',
            'tone_of_voice',
            'target_audience',
            'social_channels',
            'restrictions',
        ]));

        return to_route('marcas.show', $brand)
            ->with('status', 'La marca fue actualizada correctamente.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return auth()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}
