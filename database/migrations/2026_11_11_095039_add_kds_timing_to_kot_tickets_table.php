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
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('ticket_status');
            $table->timestamp('preparing_at')->nullable()->after('accepted_at');
            $table->timestamp('ready_at')->nullable()->after('preparing_at');
            $table->unsignedInteger('duration_seconds')->nullable()->after('ready_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'preparing_at', 'ready_at', 'duration_seconds']);
        });
    }
};
