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
        Schema::table('publication_media', function (Blueprint $table): void {
            $table->string('preflight_status')->default('not_checked')->after('superseded_at');
            $table->timestamp('preflight_checked_at')->nullable()->after('preflight_status');
            $table->text('preflight_final_url')->nullable()->after('preflight_checked_at');
            $table->string('preflight_content_type')->nullable()->after('preflight_final_url');
            $table->unsignedBigInteger('preflight_content_length')->nullable()->after('preflight_content_type');
            $table->string('preflight_error_code')->nullable()->after('preflight_content_length');
            $table->string('preflight_error_message', 500)->nullable()->after('preflight_error_code');

            $table->index(['preflight_status', 'preflight_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publication_media', function (Blueprint $table): void {
            $table->dropIndex(['preflight_status', 'preflight_checked_at']);
            $table->dropColumn([
                'preflight_status',
                'preflight_checked_at',
                'preflight_final_url',
                'preflight_content_type',
                'preflight_content_length',
                'preflight_error_code',
                'preflight_error_message',
            ]);
        });
    }
};
