<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('context_snapshots', function (Blueprint $table): void {$table->ulid('id')->primary();$table->foreignUlid('draft_id')->unique()->constrained()->cascadeOnDelete();$table->foreignUlid('brand_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->text('query');$table->json('relevant_knowledge');$table->json('brand_context');$table->json('policies');$table->json('restrictions');$table->json('pending_knowledge');$table->json('sources');$table->json('warnings');$table->json('missing_information');$table->json('matches');$table->timestamps();}); }
 public function down(): void { Schema::dropIfExists('context_snapshots'); }
};