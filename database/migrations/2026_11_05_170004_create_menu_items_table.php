<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->decimal('selling_price', 10, 2);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->text('preparation_instructions')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('stock_tracking_enabled')->default(false);
            $table->string('availability', 40)->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->index(['company_id', 'branch_id', 'availability', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
