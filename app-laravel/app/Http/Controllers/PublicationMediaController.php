<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Draft;
use App\Models\PublicationMedia;
use App\Models\PublicMediaHosting;
use App\Services\Publishing\PublicMediaUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PublicationMediaController extends Controller
{
    private const STORAGE_DISK = 'local';

    public function store(Request $request, Brand $brand, Draft $draft): RedirectResponse
    {
        $draft = $this->ownedApprovedDraft($brand, $draft);
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg', 'max:8192'],
            'public_url' => ['nullable', 'string', 'max:2048', $this->publicUrlRule()],
        ]);
        $file = $request->file('image');
        $path = $file->store('publication-media/'.$brand->getKey(), self::STORAGE_DISK);

        if (! is_string($path)) {
            return back()->with('error', 'No se pudo guardar la imagen.');
        }

        $dimensions = getimagesize($file->getRealPath());

        try {
            DB::transaction(function () use ($request, $brand, $draft, $file, $path, $dimensions, $validated): void {
                $lockedDraft = Draft::query()
                    ->whereKey($draft->getKey())
                    ->where('brand_id', $brand->getKey())
                    ->where('user_id', $request->user()->getKey())
                    ->where('status', Draft::STATUS_APPROVED)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedDraft->publicationMedia()
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => now()]);

                PublicationMedia::create([
                    'brand_id' => $brand->getKey(),
                    'draft_id' => $lockedDraft->getKey(),
                    'uploaded_by' => $request->user()->getKey(),
                    'type' => PublicationMedia::TYPE_IMAGE,
                    'original_filename' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
                    'storage_disk' => self::STORAGE_DISK,
                    'storage_path' => $path,
                    'public_url' => $validated['public_url'] ?? null,
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'width' => is_array($dimensions) ? $dimensions[0] : null,
                    'height' => is_array($dimensions) ? $dimensions[1] : null,
                    'status' => PublicationMedia::STATUS_UPLOADED,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk(self::STORAGE_DISK)->delete($path);

            throw $exception;
        }

        return $this->redirectToDraft($brand, $draft, 'Imagen cargada. Requiere aprobación explícita.');
    }

    public function show(Brand $brand, Draft $draft, PublicationMedia $publicationMedia): StreamedResponse
    {
        $media = $this->ownedMedia($brand, $draft, $publicationMedia);
        $disk = Storage::disk($media->storage_disk);

        abort_unless($disk->exists($media->storage_path), 404);

        return $disk->response($media->storage_path, null, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatePublicUrl(
        Request $request,
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
    ): RedirectResponse {
        $media = $this->ownedCurrentMedia($brand, $draft, $publicationMedia);
        $validated = $request->validate([
            'public_url' => ['nullable', 'string', 'max:2048', $this->publicUrlRule()],
        ]);

        $attributes = [
            'public_url' => $validated['public_url'] ?? null,
        ];
        $hasManagedHosting = $media->publicHosting()
            ->where('status', PublicMediaHosting::STATUS_HOSTED)
            ->exists();

        if (! $hasManagedHosting) {
            $attributes = [
                ...$attributes,
                'status' => PublicationMedia::STATUS_UPLOADED,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                ...PublicationMedia::resetPreflightAttributes(),
            ];
        }

        $media->update($attributes);

        $message = $hasManagedHosting
            ? 'URL pública manual actualizada. La copia administrada continúa siendo la URL efectiva.'
            : 'URL pública actualizada. La imagen requiere una nueva aprobación.';

        return $this->redirectToDraft($brand, $draft, $message);
    }

    public function approve(Brand $brand, Draft $draft, PublicationMedia $publicationMedia): RedirectResponse
    {
        $media = $this->ownedCurrentMedia($brand, $draft, $publicationMedia);
        $media->update([
            'status' => PublicationMedia::STATUS_APPROVED,
            'approved_by' => request()->user()->getKey(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return $this->redirectToDraft($brand, $draft, 'Imagen aprobada para publicación. No se realizó ninguna publicación.');
    }

    public function reject(
        Request $request,
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
    ): RedirectResponse {
        $media = $this->ownedCurrentMedia($brand, $draft, $publicationMedia);
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $media->update([
            'status' => PublicationMedia::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $request->user()->getKey(),
            'rejected_at' => now(),
            'rejection_reason' => filled($validated['rejection_reason'] ?? null)
                ? trim((string) $validated['rejection_reason'])
                : null,
        ]);

        return $this->redirectToDraft($brand, $draft, 'Imagen rechazada.');
    }

    private function ownedApprovedDraft(Brand $brand, Draft $draft): Draft
    {
        $draft = $this->ownedDraft($brand, $draft);

        abort_unless($draft->status === Draft::STATUS_APPROVED, 404);

        return $draft;
    }

    private function ownedDraft(Brand $brand, Draft $draft): Draft
    {
        $brand = request()->user()->brands()->whereKey($brand->getKey())->firstOrFail();

        return $brand->drafts()
            ->where('user_id', request()->user()->getKey())
            ->whereKey($draft->getKey())
            ->firstOrFail();
    }

    private function ownedMedia(
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
    ): PublicationMedia {
        $draft = $this->ownedDraft($brand, $draft);

        return $draft->publicationMedia()
            ->where('brand_id', $brand->getKey())
            ->whereKey($publicationMedia->getKey())
            ->firstOrFail();
    }

    private function ownedCurrentMedia(
        Brand $brand,
        Draft $draft,
        PublicationMedia $publicationMedia,
    ): PublicationMedia {
        $media = $this->ownedMedia($brand, $draft, $publicationMedia);

        abort_if($media->superseded_at !== null, 404);

        return $media;
    }

    private function publicUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! PublicMediaUrl::isValid(is_string($value) ? $value : null)) {
                $fail('La URL pública debe usar HTTPS, puerto 443, sin credenciales ni destinos locales o internos.');
            }
        };
    }

    private function redirectToDraft(Brand $brand, Draft $draft, string $message): RedirectResponse
    {
        return to_route('marcas.borradores.edit', [$brand, $draft])->with('status', $message);
    }
}
