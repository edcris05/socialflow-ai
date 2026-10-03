<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('configured_by')->constrained('users')->restrictOnDelete();
            $table->string('facebook_page_id')->nullable();
            $table->string('instagram_account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_connections');
    }
};
