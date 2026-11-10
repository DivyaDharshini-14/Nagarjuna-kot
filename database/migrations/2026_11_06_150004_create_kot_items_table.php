<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kot_ticket_id')->constrained('kot_tickets')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('special_instructions')->nullable();
            $table->string('item_status', 30)->default('new');
            $table->timestamps();

            $table->index(['kot_ticket_id', 'item_status']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kot_items');
    }
};

