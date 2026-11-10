<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('table_id')->constrained('restaurant_tables')->restrictOnDelete();
            $table->foreignId('waiter_id')->constrained('users')->restrictOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->unsignedInteger('guest_count')->default(1);
            $table->string('status', 30)->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->text('special_instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['table_id', 'status']);
            $table->index('waiter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

