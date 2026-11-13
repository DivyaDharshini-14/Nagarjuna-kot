<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->dropUnique('kot_tickets_kot_number_unique');
            $table->index('kot_number', 'kot_tickets_kot_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->dropIndex('kot_tickets_kot_number_index');
            $table->unique('kot_number', 'kot_tickets_kot_number_unique');
        });
    }
};

