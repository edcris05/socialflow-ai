<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->string('category')->default('fact')->after('source')->index();
        });

        $categories = [
            'Productos y servicios ofrecidos' => 'product',
            'Servicios personalizados' => 'product',
            'Equipamiento de producción disponible' => 'fact',
            'Materiales de impresión disponibles' => 'fact',
            'Identidad visual de la marca' => 'brand_identity',
            'Slogan de la marca' => 'brand_identity',
            'Tono de voz' => 'brand_identity',
            'Criterios de tipografía' => 'brand_identity',
            'Instagram oficial' => 'contact',
            'Canales oficiales de contacto' => 'contact',
            'Precios de impresiones' => 'price',
            'Precio de anillados' => 'price',
            'Precios de stickers' => 'price',
            'Precios de etiquetas' => 'price',
            'Precio de diseños personalizados' => 'price',
            'Política de publicación de precios' => 'policy',
            'Regla para información comercial no confirmada' => 'restriction',
            'Disponibilidad y continuidad del negocio' => 'restriction',
            'Ubicación pública' => 'policy',
            'Ubicación de Art Made to Print' => 'contact',
            'Horarios de atención' => 'contact',
            'Público objetivo' => 'fact',
            'Stock disponible' => 'fact',
            'Tiempos de producción' => 'policy',
            'Proceso de pedidos personalizados' => 'policy',
            'Cambios, reclamos y problemas con pedidos' => 'policy',
            'Condiciones de pago en pedidos personalizados' => 'policy',
            'Medios de pago aceptados' => 'contact',
            'Modalidades de entrega' => 'policy',
            'Idea futura - Cuadernillo universitario con QR' => 'future_idea',
        ];

        foreach ($categories as $title => $category) {
            DB::table('knowledge_entries')->where('title', $title)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};