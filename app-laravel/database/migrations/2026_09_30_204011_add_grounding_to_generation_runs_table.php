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
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->string('grounding_status')->default('not_evaluated')->after('evaluation_status');
            $table->json('grounding_results')->nullable()->after('grounding_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->dropColumn(['grounding_status', 'grounding_results']);
        });
    }
};
