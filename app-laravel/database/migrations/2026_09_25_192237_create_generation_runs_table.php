<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('draft_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('context_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('operation');
            $table->string('status');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 12, 8)->nullable();
            $table->string('provider_request_id')->nullable();
            $table->string('finish_reason')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['brand_id', 'created_at']);
            $table->index(['draft_id', 'operation', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_runs');
    }
};
