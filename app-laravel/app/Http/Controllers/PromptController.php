<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Services\Prompting\PromptComposer;
use Illuminate\View\View;

class PromptController extends Controller
{
    public function preview(Brand $brand, Draft $draft, PromptComposer $composer): View
    {
        $brand = $this->ownedBrand($brand);
        $draft = $brand->drafts()->where('user_id', auth()->id())->whereKey($draft)->firstOrFail();
        $snapshot = $draft->contextSnapshot()->firstOrFail();

        return view('drafts.prompt-preview', [
            'brand' => $brand,
            'draft' => $draft,
            'prompt' => $composer->compose($snapshot),
        ]);
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}
