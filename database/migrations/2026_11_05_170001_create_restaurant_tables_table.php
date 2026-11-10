<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('floor_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('seating_capacity');
            $table->string('table_status', 30)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['floor_id', 'name']);
            $table->index(
                ['company_id', 'branch_id', 'table_status', 'is_active'],
                'rest_tables_scope_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
