<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 120)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('key', 80);
            $table->json('value');
            $table->timestamps();
            $table->unique(['branch_id', 'key']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'branch_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'branch_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
        });

        // Legacy tickets remain untouched. New tickets explicitly identify their
        // numbering period, making the branch/period sequence enforceable.
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->string('numbering_period', 10)->nullable();
            $table->unique(['branch_id', 'numbering_period', 'kot_number'], 'kot_branch_period_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kot_tickets', function (Blueprint $table) {
            $table->dropUnique('kot_branch_period_number_unique');
            $table->dropColumn('numbering_period');
        });
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('document_sequences');
    }
};
