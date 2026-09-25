<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeEntryRequest;
use App\Models\Brand;
use App\Models\KnowledgeAudit;
use App\Models\KnowledgeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeEntryController extends Controller
{
    public function index(Request $request, Brand $brand): View
    {
        $brand = $this->ownedBrand($brand);
        $search = trim((string) $request->query('q', ''));
        $searchTerm = '%'.mb_strtolower($search).'%';

        $entries = $brand->knowledgeEntries()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $query->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(content) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(source) LIKE ?', [$term]);
            }))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('knowledge.index', compact('brand', 'entries', 'search'));
    }

    public function create(Brand $brand): View
    {
        return view('knowledge.create', ['brand' => $this->ownedBrand($brand)]);
    }

    public function store(KnowledgeEntryRequest $request, Brand $brand): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $entry = new KnowledgeEntry($request->validated());
        $entry->brand()->associate($brand);
        $entry->created_by = $request->user()->id;
        $entry->updated_by = $request->user()->id;
        $entry->save();

        $this->audit($entry, $request, 'created', null, $entry->fresh()->only(['title', 'content', 'source', 'status']));

        return to_route('marcas.conocimiento.show', [$brand, $entry])
            ->with('status', 'El conocimiento fue creado.');
    }

    public function show(Brand $brand, KnowledgeEntry $knowledgeEntry): View
    {
        $brand = $this->ownedBrand($brand);
        $entry = $this->ownedEntry($brand, $knowledgeEntry);

        return view('knowledge.show', compact('brand', 'entry'));
    }

    public function edit(Brand $brand, KnowledgeEntry $knowledgeEntry): View
    {
        $brand = $this->ownedBrand($brand);
        $entry = $this->ownedEntry($brand, $knowledgeEntry);

        return view('knowledge.edit', compact('brand', 'entry'));
    }

    public function update(KnowledgeEntryRequest $request, Brand $brand, KnowledgeEntry $knowledgeEntry): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $entry = $this->ownedEntry($brand, $knowledgeEntry);
        $before = $entry->only(['title', 'content', 'source', 'status']);
        $entry->fill($request->validated());
        $entry->updated_by = $request->user()->id;
        $entry->save();

        $this->audit($entry, $request, 'updated', $before, $entry->fresh()->only(['title', 'content', 'source', 'status']));

        return to_route('marcas.conocimiento.show', [$brand, $entry])
            ->with('status', 'El conocimiento fue actualizado.');
    }

    public function verify(Request $request, Brand $brand, KnowledgeEntry $knowledgeEntry): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $entry = $this->ownedEntry($brand, $knowledgeEntry);
        $before = $entry->only(['status', 'verified_at', 'verified_by']);
        $entry->status = 'verified';
        $entry->verified_at = now();
        $entry->verified_by = $request->user()->id;
        $entry->updated_by = $request->user()->id;
        $entry->save();

        $this->audit($entry, $request, 'verified', $before, $entry->fresh()->only(['status', 'verified_at', 'verified_by']));

        return back()->with('status', 'El conocimiento fue marcado como verificado.');
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }

    private function ownedEntry(Brand $brand, KnowledgeEntry $entry): KnowledgeEntry
    {
        return $brand->knowledgeEntries()->whereKey($entry->getKey())->firstOrFail();
    }

    private function audit(KnowledgeEntry $entry, Request $request, string $action, ?array $before, ?array $after): void
    {
        $entry->audits()->create([
            'brand_id' => $entry->brand_id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}