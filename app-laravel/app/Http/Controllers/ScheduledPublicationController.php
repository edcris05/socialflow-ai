<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\ScheduledPublication;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduledPublicationController extends Controller
{
    public function index(Brand $brand): View
    {
        $brand = $this->ownedBrand($brand);
        $publications = $brand->scheduledPublications()
            ->whereHas('draft', fn ($query) => $query
                ->where('brand_id', $brand->getKey()))
            ->with([
                'draft' => fn ($query) => $query->with([
                    'currentPublicationMedia' => fn ($mediaQuery) => $mediaQuery
                        ->where('brand_id', $brand->getKey())
                        ->with('publicHosting'),
                ]),
                'scheduledBy',
                'cancelledBy',
                'publicationAttempts' => fn ($query) => $query
                    ->where('provider', 'meta')
                    ->latest('id'),
            ])
            ->orderByRaw("CASE WHEN status = 'scheduled' AND scheduled_for <= ? THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END", [now()])
            ->orderBy('scheduled_for')
            ->orderByDesc('created_at')
            ->paginate(20);

        $brand->load(['metaConnection', 'autopublishingSetting']);

        return view('scheduled-publications.index', compact('brand', 'publications'));
    }

    public function store(Request $request, Brand $brand, Draft $draft): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $draft = $this->ownedDraft($brand, $draft);
        $scheduledFor = $this->validatedDate($request);
        $this->ensureApproved($draft);

        DB::transaction(function () use ($brand, $draft, $request, $scheduledFor): void {
            $lockedDraft = Draft::query()->lockForUpdate()->findOrFail($draft->getKey());
            $this->ensureApproved($lockedDraft);
            /** @var ScheduledPublication|null $active */
            $active = $lockedDraft->scheduledPublications()
                ->where('status', ScheduledPublication::STATUS_SCHEDULED)
                ->lockForUpdate()
                ->first();

            if ($active) {
                $active->update(['scheduled_for' => $scheduledFor]);

                return;
            }

            $lockedDraft->scheduledPublications()->create([
                'brand_id' => $brand->getKey(),
                'scheduled_by' => $request->user()->getKey(),
                'scheduled_for' => $scheduledFor,
                'status' => ScheduledPublication::STATUS_SCHEDULED,
            ]);
        });

        return to_route('marcas.borradores.edit', [$brand, $draft])
            ->with('status', 'El borrador fue programado. No se realizó ninguna publicación.');
    }

    public function update(Request $request, Brand $brand, ScheduledPublication $scheduledPublication): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $scheduledPublication = $this->ownedPublication($brand, $scheduledPublication);
        $this->ensureScheduled($scheduledPublication);
        $scheduledFor = $this->validatedDate($request);
        $scheduledPublication->update(['scheduled_for' => $scheduledFor]);

        return to_route('marcas.programacion.index', $brand)->with('status', 'La programación fue actualizada.');
    }

    public function cancel(Request $request, Brand $brand, ScheduledPublication $scheduledPublication): RedirectResponse
    {
        $brand = $this->ownedBrand($brand);
        $scheduledPublication = $this->ownedPublication($brand, $scheduledPublication);
        $this->ensureScheduled($scheduledPublication);
        $scheduledPublication->update([
            'status' => ScheduledPublication::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->getKey(),
        ]);

        return to_route('marcas.programacion.index', $brand)->with('status', 'La programación fue cancelada.');
    }

    private function validatedDate(Request $request): Carbon
    {
        $validated = $request->validate([
            'scheduled_for' => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);
        $scheduledFor = Carbon::createFromFormat('Y-m-d\\TH:i', $validated['scheduled_for'], config('app.timezone'));

        if (! $scheduledFor || $scheduledFor->lte(now())) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'La fecha y hora deben estar en el futuro.',
            ]);
        }

        return $scheduledFor;
    }

    private function ensureApproved(Draft $draft): void
    {
        if ($draft->status !== Draft::STATUS_APPROVED) {
            abort(422, 'Sólo un borrador aprobado puede programarse.');
        }
    }

    private function ensureScheduled(ScheduledPublication $scheduledPublication): void
    {
        if ($scheduledPublication->status !== ScheduledPublication::STATUS_SCHEDULED) {
            abort(422, 'La programación ya está cancelada.');
        }
    }

    private function ownedBrand(Brand $brand): Brand
    {
        return request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();
    }

    private function ownedDraft(Brand $brand, Draft $draft): Draft
    {
        return $brand->drafts()->where('user_id', request()->user()->getKey())->whereKey($draft)->firstOrFail();
    }

    private function ownedPublication(Brand $brand, ScheduledPublication $scheduledPublication): ScheduledPublication
    {
        return $brand->scheduledPublications()->whereKey($scheduledPublication)->firstOrFail();
    }
}
