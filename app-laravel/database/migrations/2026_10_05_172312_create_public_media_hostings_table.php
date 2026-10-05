<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('public_media_hostings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('publication_media_id')
                ->unique()
                ->constrained('publication_media')
                ->cascadeOnDelete();
            $table->string('status')->default('not_hosted');
            $table->string('provider')->nullable();
            $table->string('disk')->nullable();
            $table->string('object_key')->nullable();
            $table->text('public_url')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->timestamp('hosted_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'disk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_media_hostings');
    }
};
