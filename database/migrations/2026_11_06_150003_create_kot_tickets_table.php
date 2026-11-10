<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kot_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('kot_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained('kitchens')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('ticket_status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kitchen_id', 'ticket_status']);
            $table->index(['company_id', 'branch_id', 'ticket_status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kot_tickets');
    }
};

