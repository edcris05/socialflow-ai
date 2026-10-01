<?php

namespace App\Http\Controllers;

use App\Http\Requests\DraftRequest;
use App\Models\Brand;
use App\Models\Draft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $draft = $this->ownedDraft($brand, $draft)->load(['approvedBy', 'contextSnapshot', 'rejectedBy']);

        $generationToken = null;
        if ($draft->status === Draft::STATUS_DRAFT && $draft->contextSnapshot !== null) {
            $generationToken = Str::random(40);
            request()->session()->put('generation-tokens.'.$generationToken, [
                'brand_id' => (string) $brand->getKey(),
                'draft_id' => (string) $draft->getKey(),
                'user_id' => (string) request()->user()->getKey(),
            ]);
        }
        $latestGenerationRun = $draft->generationRuns()
            ->where('status', 'succeeded')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return view('drafts.edit', compact('brand', 'draft', 'generationToken', 'latestGenerationRun'));
    }

    public function update(DraftRequest $request, Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $this->ownedDraft($brand, $draft);
        $this->ensureStatus($draft, Draft::STATUS_DRAFT, 'Sólo un borrador puede editarse.');

        $attributes = $request->validated();
        if ($draft->content !== $attributes['content']) {
            $generatedContent = $draft->generationRuns()
                ->where('status', 'succeeded')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('generated_content');

            if (is_string($generatedContent)) {
                $attributes['manually_edited_at'] = $attributes['content'] === $generatedContent ? null : now();
            }
        }
        $this->transition($draft, Draft::STATUS_DRAFT, $attributes, 'Sólo un borrador puede editarse.');

        return to_route('marcas.borradores.edit', [$brand, $draft])->with('status', 'El borrador fue actualizado.');
    }

    public function approve(Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $this->ownedDraft($brand, $draft);

        $this->transition($draft, Draft::STATUS_DRAFT, [
            'status' => Draft::STATUS_APPROVED,
            'approved_by' => request()->user()->getKey(),
            'approved_at' => now(),
        ], 'Sólo un borrador puede aprobarse.');

        return to_route('marcas.borradores.edit', [$brand, $draft])
            ->with('status', 'Borrador aprobado para publicación. No se realizó ninguna publicación.');
    }

    public function reject(Request $request, Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $this->ownedDraft($brand, $draft);
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = trim((string) ($validated['rejection_reason'] ?? ''));

        $this->transition($draft, Draft::STATUS_DRAFT, [
            'status' => Draft::STATUS_REJECTED,
            'rejected_by' => $request->user()->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => $reason === '' ? null : $reason,
        ], 'Sólo un borrador puede rechazarse.');

        return to_route('marcas.borradores.edit', [$brand, $draft])->with('status', 'Borrador rechazado.');
    }

    public function reopen(Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $this->ownedDraft($brand, $draft);

        $this->transition($draft, Draft::STATUS_REJECTED, [
            'status' => Draft::STATUS_DRAFT,
        ], 'Sólo un borrador rechazado puede volver a borrador.');

        return to_route('marcas.borradores.edit', [$brand, $draft])->with('status', 'Borrador reabierto para edición.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }

    private function ownedDraft(Brand $brand, Draft $draft): Draft
    {
        return $brand->drafts()->where('user_id', auth()->id())->whereKey($draft)->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function transition(Draft $draft, string $from, array $attributes, string $message): void
    {
        $updated = Draft::query()
            ->whereKey($draft->getKey())
            ->where('status', $from)
            ->update($attributes);

        if ($updated !== 1) {
            abort(422, $message);
        }
    }

    private function ensureStatus(Draft $draft, string $status, string $message): void
    {
        if ($draft->status !== $status) {
            abort(422, $message);
        }
    }
}
