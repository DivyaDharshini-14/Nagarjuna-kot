<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('operation', 64);
            $table->char('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['order_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_api_idempotency_keys');
    }
};
