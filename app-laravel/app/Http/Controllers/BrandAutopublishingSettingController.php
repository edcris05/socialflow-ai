<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandAutopublishingSettingController extends Controller
{
    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($request, $brand);
        $request->validate([
            'confirm_enable' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request, $brand): void {
            $brand = $this->ownedBrandForUpdate($request, $brand);
            $setting = BrandAutopublishingSetting::query()
                ->where('brand_id', $brand->getKey())
                ->lockForUpdate()
                ->first() ?? new BrandAutopublishingSetting(['brand_id' => $brand->getKey()]);

            $setting->enable($request->user());
        });

        return to_route('marcas.show', $brand)
            ->with('status', 'La autopublicación fue activada para esta marca.');
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($request, $brand);

        DB::transaction(function () use ($request, $brand): void {
            $brand = $this->ownedBrandForUpdate($request, $brand);
            $setting = BrandAutopublishingSetting::query()
                ->where('brand_id', $brand->getKey())
                ->lockForUpdate()
                ->first();

            $setting?->disable($request->user());
        });

        return to_route('marcas.show', $brand)
            ->with('status', 'La autopublicación fue desactivada para esta marca.');
    }

    private function ownedBrand(Request $request, Brand $brand): Brand
    {
        return $request->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }

    private function ownedBrandForUpdate(Request $request, Brand $brand): Brand
    {
        return Brand::query()
            ->whereKey($brand->getKey())
            ->whereHas('users', fn (Builder $users): Builder => $users->whereKey($request->user()->getKey()))
            ->lockForUpdate()
            ->firstOrFail();
    }
}
