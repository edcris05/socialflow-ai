<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArtMadeKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::query()->where('slug', 'art-made-to-print')->firstOrFail();
        $user = $brand->users()->first() ?? User::query()->firstOrFail();

        $entries = [
            [
                'title' => 'Identidad y propósito de la marca',
                'content' => 'Art Made to Print es un emprendimiento de impresiones y productos personalizados. El proyecto fue iniciado meses atrás y posteriormente pausado debido principalmente a falta de tiempo y otras complicaciones. Actualmente se busca retomarlo de una manera organizada y sostenible.\n\nEl objetivo hasta diciembre es recuperar la inversión realizada y generar un ingreso extra.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'brand_identity',
            ],
            [
                'title' => 'Productos y servicios ofrecidos',
                'content' => 'Art Made to Print busca ofrecer inicialmente:\n\n- Impresiones.\n- Anillados.\n- Stickers.\n- Etiquetas.\n- Diseños personalizados, sujetos a disponibilidad de tiempo.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'product',
            ],
            [
                'title' => 'Servicios personalizados',
                'content' => 'La personalización forma parte de la propuesta de Art Made to Print. Está confirmada específicamente para stickers, etiquetas y trabajos de diseño personalizado.\n\nLa dueña disfruta especialmente realizar diseños personalizados, aunque actualmente este servicio depende de su disponibilidad de tiempo.\n\nTodavía faltan definir cantidad mínima, revisiones, tiempos, formatos y condiciones de estos trabajos.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'product',
            ],
            [
                'title' => 'Equipamiento de producción disponible',
                'content' => 'Actualmente Art Made to Print dispone de:\n\n- Impresora de tinta continua.\n- Anilladora.\n- Abrochadora.\n- Perforadora.\n- Anillos de distintos tamaños.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'fact',
            ],
            [
                'title' => 'Materiales de impresión disponibles',
                'content' => 'Actualmente se dispone de:\n\n- Hojas A4 de 75 g/m².\n- Algunas hojas de 110 g/m².\n- Hojas adhesivas resistentes al agua.\n- Hojas adhesivas no resistentes al agua.\n- Transfer.\n\nEl transfer todavía no fue probado, por lo que no debe promocionarse como un servicio disponible hasta confirmar su funcionamiento.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'fact',
            ],
            [
                'title' => 'Identidad visual de la marca',
                'content' => 'La identidad visual de Art Made to Print utiliza como colores principales rosa pastel y celeste pastel, acompañados por colores pastel que combinen con blanco.\n\nSe busca una estética limpia, visual, legible, creativa y adecuada para Instagram, evitando el exceso de texto y manteniendo coherencia entre el logo, publicaciones, historias y demás piezas de comunicación.\n\nNo hay códigos HEX aprobados todavía.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'brand_identity',
            ],
            [
                'title' => 'Slogan de la marca',
                'content' => 'Más que impresiones, creamos soluciones para tus proyectos.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'brand_identity',
            ],
            [
                'title' => 'Tono de voz',
                'content' => 'Art Made to Print busca utilizar una comunicación simple, cercana, natural y humana, evitando un lenguaje excesivamente formal.\n\nLa comunicación debe transmitir cercanía con el cliente y estar orientada a ayudarlo con sus proyectos.',
                'source' => 'Preferencias expresadas por la dueña',
                'category' => 'brand_identity',
            ],
            [
                'title' => 'Criterios de tipografía',
                'content' => 'La tipografía utilizada para contenidos debe ser legible y mantener coherencia visual con el logo.\n\nA la dueña le gusta Baloo para títulos, pero considera que su grosor contrasta demasiado con las letras del logo o membrete.\n\nActualmente no existe una tipografía definitiva aprobada.',
                'source' => 'Preferencias expresadas por la dueña',
                'category' => 'brand_identity',
            ],
            [
                'title' => 'Objetivo de organización de Instagram',
                'content' => 'Instagram es uno de los principales canales que Art Made to Print necesita organizar. La dificultad actual es mantener una actividad constante debido al tiempo disponible.\n\nSe busca preparar contenido, historias y estructura reutilizable con anticipación para poder mantener activa la presencia de la marca incluso durante períodos de menor disponibilidad.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'fact',
            ],
            [
                'title' => 'Instagram oficial',
                'content' => 'Cuenta oficial: @artmadetoprint',
                'source' => 'Instagram oficial / proporcionado por responsable',
                'category' => 'contact',
            ],
            [
                'title' => 'Volante publicitario',
                'content' => 'Art Made to Print ya cuenta con un volante publicitario creado para repartir por la zona. El objetivo es generar presencia local y no depender exclusivamente de Instagram para conseguir clientes.\n\nNo están confirmados todavía los lugares de reparto, promociones, QR ni WhatsApp.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'fact',
            ],
            [
                'title' => 'Disponibilidad y continuidad del negocio',
                'content' => 'La disponibilidad de tiempo de la dueña es una restricción importante del emprendimiento.\n\nLa organización y comunicación de Art Made to Print deben priorizar simplicidad, bajo mantenimiento y reutilización de contenido.\n\nLos diseños personalizados están sujetos a disponibilidad.\n\nLa estrategia de contenido no debe depender de que la dueña pueda dedicar una gran cantidad de tiempo diariamente al emprendimiento.',
                'source' => 'Información proporcionada por la dueña',
                'category' => 'restriction',
            ],
            [
                'title' => 'Idea futura - Cuadernillo universitario con QR',
                'content' => 'Existe como idea futura desarrollar un cuadernillo universitario personalizado que permita almacenar o digitalizar apuntes mediante un QR.\n\nActualmente es solamente una idea conceptual.\n\nNO es un producto disponible. No tiene diseño, precio, proceso de producción ni funcionamiento técnico definidos y no debe promocionarse como un producto actualmente ofrecido.',
                'source' => 'Idea proporcionada por la dueña',
                'category' => 'future_idea',
            ],
            [
                'title' => 'Regla para información comercial no confirmada',
                'content' => 'No inventar ni asumir información comercial de Art Made to Print.\n\nSi un dato no se encuentra en la base de conocimiento verificada, debe considerarse desconocido.\n\nParticularmente, no inventar precios, promociones, descuentos, stock, medios de pago, métodos de envío, zonas de entrega, tiempos de producción, cantidades mínimas, materiales, características de productos, horarios, dirección, datos de contacto ni disponibilidad.\n\nSi alguno de estos datos es necesario para generar contenido, debe solicitarse confirmación antes de utilizarlo.',
                'source' => 'Regla interna de SocialFlow AI',
                'category' => 'restriction',
            ],
        ];

        foreach ($entries as $data) {
            $entry = KnowledgeEntry::query()->firstOrCreate(
                ['brand_id' => $brand->id, 'title' => $data['title']],
                [
                    ...$data,
                    'status' => 'verified',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                ],
            );

            if ($entry->wasRecentlyCreated) {
                $entry->audits()->create([
                    'brand_id' => $brand->id,
                    'user_id' => $user->id,
                    'action' => 'created',
                    'after' => $entry->only(['title', 'content', 'source', 'status']),
                ]);
            }
        }
    }
}