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
            $table->string('applicability')->default('global')->after('category')->index();
        });

        $scopes = [
            'Política de publicación de precios' => 'price',
            'Precios de impresiones' => 'price',
            'Precio de anillados' => 'price',
            'Precios de stickers' => 'price',
            'Precios de etiquetas' => 'price',
            'Precio de diseños personalizados' => 'price',
            'Cambios, reclamos y problemas con pedidos' => 'customer_service',
            'Condiciones de pago en pedidos personalizados' => 'order',
            'Proceso de pedidos personalizados' => 'order',
            'Modalidades de entrega' => 'delivery',
            'Tiempos de producción' => 'order',
            'Ubicación pública' => 'location',
            'Ubicación de Art Made to Print' => 'location',
            'Regla para información comercial no confirmada' => 'content',
            'Disponibilidad y continuidad del negocio' => 'content',
        ];

        foreach ($scopes as $title => $applicability) {
            DB::table('knowledge_entries')->where('title', $title)->update(['applicability' => $applicability]);
        }
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table) {
            $table->dropIndex(['applicability']);
            $table->dropColumn('applicability');
        });
    }
};