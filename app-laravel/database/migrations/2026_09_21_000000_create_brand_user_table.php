<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_user', function (Blueprint $table) {
            $table->foreignUlid('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();

            $table->primary(['brand_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_user');
    }
};