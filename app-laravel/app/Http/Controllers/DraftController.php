<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftRequest;
use App\Models\Brand;
use App\Models\Draft;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DraftController extends Controller
{
    public function index(Brand $brand): View
    {
        $brand = $this->ownedBrand($brand);
        $drafts = $brand->drafts()->where('user_id', auth()->id())->latest()->paginate(10);

        return view('drafts.index', compact('brand', 'drafts'));
    }

    public function create(Brand $brand): View
    {
        return view('drafts.create', ['brand' => $this->ownedBrand($brand)]);
    }

    public function store(DraftRequest $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = new Draft($request->validated());
        $draft->brand()->associate($brand);
        $draft->user_id = $request->user()->id;
        $draft->save();

        return to_route('marcas.borradores.index', $brand)->with('status', 'El borrador fue guardado.');
    }

    public function edit(Brand $brand, Draft $draft): View
    {
        $brand = $this->ownedBrand($brand);
        $draft = $brand->drafts()->where('user_id', auth()->id())->whereKey($draft)->firstOrFail();

        return view('drafts.edit', compact('brand', 'draft'));
    }

    public function update(DraftRequest $request, Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $brand->drafts()->where('user_id', auth()->id())->whereKey($draft)->firstOrFail();
        $draft->update($request->validated());

        return to_route('marcas.borradores.index', $brand)->with('status', 'El borrador fue actualizado.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}