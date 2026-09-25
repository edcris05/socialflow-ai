<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContextRequest;
use App\Models\Brand;
use App\Models\ContextSnapshot;
use App\Models\Draft;
use App\Services\Knowledge\ContextBuilder;
use App\Services\Knowledge\ContextPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContextController extends Controller
{
    public function create(Brand $brand): View
    {
        return view('context.create', [
            'brand' => $this->ownedBrand($brand),
            'package' => null,
            'contextToken' => null,
        ]);
    }

    public function preview(ContextRequest $request, Brand $brand, ContextBuilder $builder): View
    {
        $brand = $this->ownedBrand($brand);
        $package = $builder->build($brand, $request->validated('query'));
        $contextToken = Str::random(40);

        $request->session()->put('prepared-contexts.'.$contextToken, [
            'brand_id' => (string) $brand->getKey(),
            'user_id' => (string) $request->user()->getKey(),
            'data' => $package->toSessionData(),
        ]);

        return view('context.create', [
            'brand' => $brand,
            'package' => $package,
            'contextToken' => $contextToken,
        ]);
    }

    public function storeDraft(Request $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $validated = $request->validate([
            'context_token' => ['required', 'string', 'size:40'],
        ]);
        $prepared = $request->session()->pull('prepared-contexts.'.$validated['context_token']);

        abort_unless(
            is_array($prepared)
            && ($prepared['brand_id'] ?? null) === (string) $brand->getKey()
            && ($prepared['user_id'] ?? null) === (string) $request->user()->getKey()
            && is_array($prepared['data'] ?? null),
            422,
        );

        $package = ContextPackage::fromSessionData($brand, $prepared['data']);

        $draft = DB::transaction(function () use ($brand, $package, $request): Draft {
            $draft = new Draft([
                'title' => 'Borrador: '.Str::limit(trim($package->query), 80, '...'),
                'content' => '',
                'status' => 'draft',
            ]);
            $draft->brand()->associate($brand);
            $draft->user()->associate($request->user());
            $draft->save();

            ContextSnapshot::fromPackage($draft, $request->user(), $package)->save();

            return $draft;
        });

        return redirect()->route('marcas.borradores.edit', [$brand, $draft])
            ->with('status', 'Se preparó el borrador con el contexto actual.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }
}
