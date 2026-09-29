<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->string('evaluation_status')->default('not_evaluated')->after('status');
            $table->json('evaluation_violations')->nullable()->after('error');
            $table->json('evaluation_warnings')->nullable()->after('evaluation_violations');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation_warnings');
        });
    }

    public function down(): void
    {
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->dropColumn(['evaluation_status', 'evaluation_violations', 'evaluation_warnings', 'evaluated_at']);
        });
    }
};
