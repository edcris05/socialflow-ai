<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use Illuminate\Database\Seeder;

class ArtMadeCommercialKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::query()->where('slug', 'art-made-to-print')->firstOrFail();
        $user = $brand->users()->firstOrFail();

        $entries = [
            [
                'title' => 'Precios de impresiones',
                'content' => 'Impresión blanco y negro en hoja A4: $150.\n\nImpresión color en hoja A4: $600.\n\nEl precio no cambia si la impresión es doble faz.\n\nRegla comercial: los precios deben consultarse antes de ser publicados en redes sociales u otro contenido promocional, aunque existan precios registrados en la base de conocimiento.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Precio de anillados',
                'content' => 'El precio base informado para anillados es de $4.000.\n\nEl precio puede variar según la cantidad de hojas. La variación habitual es de aproximadamente $500.\n\nEl precio definitivo debe confirmarse según el trabajo solicitado.\n\nRegla comercial: consultar antes de publicar precios en redes sociales.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Precios de stickers',
                'content' => 'Stickers en hoja A4:\n\n- Adhesivo normal: $3.500 por hoja A4.\n- Adhesivo resistente al agua: $4.500 por hoja A4.\n- Sticker individual: $500 cada uno.\n\nActualmente no existe un tamaño estándar o máximo confirmado para los stickers individuales.\n\nRegla comercial: consultar antes de publicar precios en redes sociales.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Precios de etiquetas',
                'content' => 'Precio informado para etiquetas: $3.500 / $4.500.\n\nExisten opciones de material adhesivo normal y resistente al agua.\n\nAntes de proporcionar un precio definitivo de etiquetas debe confirmarse cuál corresponde al pedido.\n\nRegla comercial: consultar antes de publicar precios en redes sociales.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Precio de diseños personalizados',
                'content' => 'Actualmente no existe un precio establecido para el servicio de diseño personalizado.\n\nNo se debe informar ni estimar automáticamente un precio para este servicio. Debe consultarse para cada caso hasta que exista una política de precios definida.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Proceso de pedidos personalizados',
                'content' => 'Para los pedidos personalizados se establece el diseño y el tiempo correspondiente al trabajo.\n\nAntes de realizar la impresión o entrega se consulta al cliente para obtener su conformidad.\n\nEl trabajo no debe considerarse aprobado para impresión o entrega hasta contar con la conformidad del cliente.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Medios de pago aceptados',
                'content' => 'Art Made to Print acepta actualmente:\n\n- Efectivo.\n- Transferencia.\n- Mercado Pago.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Modalidades de entrega',
                'content' => 'Los pedidos pueden retirarse o enviarse dentro de Corrientes Capital.\n\nEl costo de envío depende de la distancia y comienza desde $1.500.\n\nNo debe informarse un costo definitivo de envío sin conocer previamente la ubicación de entrega.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Tiempos de producción',
                'content' => 'El tiempo de producción depende del producto solicitado.\n\nActualmente no existen tiempos generales de producción documentados para cada producto.\n\nNo se debe prometer automáticamente un plazo de producción o entrega sin confirmar previamente el producto y las condiciones del pedido.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Canales oficiales de contacto',
                'content' => 'WhatsApp: 3795000095\n\nInstagram: @artmadetoprint',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Ubicación de Art Made to Print',
                'content' => 'Ubicación informada: Barrio Dr. Nicolini, Casa 127, Manzana F.\n\nEsta información no implica autorización automática para publicar la dirección completa en redes sociales. Antes de utilizarla en contenido público debe confirmarse si la responsable desea comunicar la dirección completa o solamente la zona.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Horarios de atención',
                'content' => 'Actualmente Art Made to Print no tiene horarios de atención establecidos.\n\nNo se deben inventar ni publicar horarios específicos hasta que sean definidos.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Público objetivo',
                'content' => 'Art Made to Print está dirigido a público general y comercios.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Stock disponible',
                'content' => 'El stock operativo de Art Made to Print está compuesto principalmente por hojas y tinta.\n\nActualmente no se encuentran documentadas las cantidades disponibles de cada insumo.\n\nLa disponibilidad específica debe verificarse antes de realizar afirmaciones sobre stock.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Cambios, reclamos y problemas con pedidos',
                'content' => 'Ante un reclamo o problema con un pedido, Art Made to Print evalúa cada caso.\n\nDependiendo de la situación, el producto puede realizarse nuevamente o puede devolverse el dinero al cliente.\n\nLa solución se determina según el caso particular.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Condiciones de pago en pedidos personalizados',
                'content' => 'En los pedidos personalizados, las condiciones de pago se acuerdan con el cliente según el pedido.\n\nNo existe actualmente una regla fija sobre seña o momento del pago.\n\nNo se debe afirmar que se requiere una seña determinada ni un pago anticipado sin haberlo acordado previamente.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Ubicación pública',
                'content' => 'Art Made to Print se encuentra en Barrio Dr. Nicolini, Corrientes Capital.\n\nPara contenido público puede mencionarse Barrio Dr. Nicolini.\n\nNo debe publicarse la dirección particular de la vivienda.\n\nLa dirección exacta solamente debe proporcionarse de manera privada cuando sea necesario coordinar el retiro de un pedido.',
                'source' => 'Información proporcionada por la dueña',
            ],
            [
                'title' => 'Política de publicación de precios',
                'content' => 'Los precios registrados en la base de conocimiento son valores de referencia actuales para uso interno.\n\nAntes de publicar un precio en Instagram, Facebook, publicidad u otro contenido público, debe consultarse y confirmarse que el precio continúa vigente.\n\nSocialFlow AI no debe asumir automáticamente que un precio almacenado está autorizado para publicación.',
                'source' => 'Regla definida por la dueña',
            ],
        ];

        $categories = [
            'Precios de impresiones' => 'price',
            'Precio de anillados' => 'price',
            'Precios de stickers' => 'price',
            'Precios de etiquetas' => 'price',
            'Precio de diseños personalizados' => 'price',
            'Proceso de pedidos personalizados' => 'policy',
            'Medios de pago aceptados' => 'contact',
            'Modalidades de entrega' => 'policy',
            'Tiempos de producción' => 'policy',
            'Canales oficiales de contacto' => 'contact',
            'Ubicación de Art Made to Print' => 'contact',
            'Horarios de atención' => 'contact',
            'Público objetivo' => 'fact',
            'Stock disponible' => 'fact',
            'Cambios, reclamos y problemas con pedidos' => 'policy',
            'Condiciones de pago en pedidos personalizados' => 'policy',
            'Ubicación pública' => 'policy',
            'Política de publicación de precios' => 'policy',
        ];

        $applicability = [
            'Precios de impresiones' => 'price',
            'Precio de anillados' => 'price',
            'Precios de stickers' => 'price',
            'Precios de etiquetas' => 'price',
            'Precio de diseños personalizados' => 'price',
            'Proceso de pedidos personalizados' => 'order',
            'Medios de pago aceptados' => 'order',
            'Modalidades de entrega' => 'delivery',
            'Tiempos de producción' => 'order',
            'Canales oficiales de contacto' => 'global',
            'Ubicación de Art Made to Print' => 'location',
            'Horarios de atención' => 'global',
            'Público objetivo' => 'global',
            'Stock disponible' => 'global',
            'Cambios, reclamos y problemas con pedidos' => 'customer_service',
            'Condiciones de pago en pedidos personalizados' => 'order',
            'Ubicación pública' => 'location',
            'Política de publicación de precios' => 'price',
        ];

        foreach ($entries as $data) {
            $entry = KnowledgeEntry::query()->firstOrNew([
                'brand_id' => $brand->id,
                'title' => $data['title'],
            ]);

            $before = $entry->exists
                ? $entry->only(['title', 'content', 'source', 'status'])
                : null;

            $entry->fill([
                ...$data,
                'category' => $categories[$data['title']] ?? 'fact',
                'applicability' => $applicability[$data['title']] ?? 'global',
                'status' => 'verified',
                'updated_by' => $user->id,
                'verified_by' => $user->id,
                'verified_at' => $entry->verified_at ?? now(),
            ]);

            if (! $entry->exists) {
                $entry->created_by = $user->id;
            }

            if (! $entry->exists || $entry->isDirty()) {
                $entry->save();
                $entry->audits()->create([
                    'brand_id' => $brand->id,
                    'user_id' => $user->id,
                    'action' => $before === null ? 'created' : 'updated',
                    'before' => $before,
                    'after' => $entry->fresh()->only(['title', 'content', 'source', 'status']),
                ]);
            }
        }
    }
}