<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use Illuminate\Database\Seeder;

class ConsolidateArtMadeKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::query()->where('slug', 'art-made-to-print')->firstOrFail();
        $user = $brand->users()->firstOrFail();

        $this->mergeEntry(
            $brand,
            'Identidad de Art Made to Print',
            'Identidad y propósito de la marca',
            'Art Made to Print es un emprendimiento de impresiones y productos personalizados. El proyecto fue iniciado meses atrás y posteriormente pausado debido principalmente a falta de tiempo y otras complicaciones. Actualmente se busca retomarlo de una manera organizada y sostenible.\n\nEl objetivo hasta diciembre es recuperar la inversión realizada y generar un ingreso extra.',
            'Información proporcionada por la dueña',
            $user->id,
        );

        $this->mergeEntry(
            $brand,
            'Regla de información confirmada',
            'Regla para información comercial no confirmada',
            'No inventar ni asumir información comercial de Art Made to Print.\n\nSi un dato no se encuentra en la base de conocimiento verificada, debe considerarse desconocido.\n\nParticularmente, no inventar precios, promociones, descuentos, stock, medios de pago, métodos de envío, zonas de entrega, tiempos de producción, cantidades mínimas, materiales, características de productos, horarios, dirección, datos de contacto ni disponibilidad.\n\nSi alguno de estos datos es necesario para generar contenido, debe solicitarse confirmación antes de utilizarlo.',
            'Regla interna de SocialFlow AI',
            $user->id,
        );

        $instagram = $brand->knowledgeEntries()->where('title', 'Instagram oficial')->firstOrFail();
        $before = $instagram->only(['title', 'content', 'source', 'status']);
        $instagram->update([
            'content' => 'Cuenta oficial: @artmadetoprint\nhttps://www.instagram.com/artmadetoprint/',
            'updated_by' => $user->id,
            'verified_by' => $user->id,
            'verified_at' => $instagram->verified_at ?? now(),
            'status' => 'verified',
        ]);
        $this->audit($instagram, $user->id, $before, $instagram->fresh()->only(['title', 'content', 'source', 'status']), 'updated');
    }

    private function mergeEntry(Brand $brand, string $keepTitle, string $duplicateTitle, string $content, string $source, int $userId): void
    {
        $entry = $brand->knowledgeEntries()->where('title', $keepTitle)->firstOrFail();
        $duplicate = $brand->knowledgeEntries()->where('title', $duplicateTitle)->first();
        $before = $entry->only(['title', 'content', 'source', 'status']);

        $entry->update([
            'content' => $content,
            'source' => $source,
            'status' => 'verified',
            'updated_by' => $userId,
            'verified_by' => $userId,
            'verified_at' => $entry->verified_at ?? now(),
        ]);

        if ($before !== $entry->fresh()->only(['title', 'content', 'source', 'status'])) {
            $this->audit($entry, $userId, $before, $entry->fresh()->only(['title', 'content', 'source', 'status']), 'updated');
        }

        $duplicate?->delete();
    }

    private function audit(KnowledgeEntry $entry, int $userId, array $before, array $after, string $action): void
    {
        $entry->audits()->create([
            'brand_id' => $entry->brand_id,
            'user_id' => $userId,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}