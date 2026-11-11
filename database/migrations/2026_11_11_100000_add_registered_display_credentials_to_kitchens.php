<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->char('display_token_hash', 64)->nullable()->unique();
            $table->timestamp('display_token_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropUnique(['display_token_hash']);
            $table->dropColumn(['display_token_hash', 'display_token_created_at']);
        });
    }
};
