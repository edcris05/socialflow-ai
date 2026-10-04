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
        Schema::create('publication_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('scheduled_publication_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('meta_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('status');
            $table->unsignedInteger('attempt_count')->default(1);
            $table->string('idempotency_key', 64)->unique();
            $table->string('target_account_id_snapshot');
            $table->text('caption_snapshot');
            $table->text('media_url_snapshot')->nullable();
            $table->string('external_container_id')->nullable();
            $table->string('external_media_id')->nullable();
            $table->string('last_error_code')->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['scheduled_publication_id', 'provider', 'target_account_id_snapshot'],
                'publication_attempts_target_unique',
            );
            $table->index(['scheduled_publication_id', 'status']);
            $table->index(['meta_connection_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_attempts');
    }
};
