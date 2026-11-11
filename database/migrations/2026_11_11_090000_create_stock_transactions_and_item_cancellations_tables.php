<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('quantity');
        });

        Schema::table('kot_items', function (Blueprint $table) {
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('quantity');
        });

        Schema::create('item_cancellations', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_batch')->index();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->boolean('stock_reversed')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'created_at'], 'item_cancellations_scope_status_idx');
            $table->index(['order_id', 'order_item_id', 'status'], 'item_cancellations_order_item_status_idx');
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_cancellation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type', 40);
            $table->unsignedInteger('quantity');
            $table->integer('quantity_delta')->default(0);
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'transaction_type', 'created_at'], 'stock_transactions_scope_type_idx');
            $table->index(['menu_item_id', 'created_at']);
            $table->index('order_id');
        });

        $now = now();
        DB::table('menu_items')
            ->where('stock_tracking_enabled', true)
            ->whereNotNull('prepared_stock_quantity')
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($now): void {
                foreach ($items as $item) {
                    DB::table('stock_transactions')->insert([
                        'company_id' => $item->company_id,
                        'branch_id' => $item->branch_id,
                        'menu_item_id' => $item->id,
                        'transaction_type' => 'opening_stock',
                        'quantity' => $item->prepared_stock_quantity,
                        'quantity_delta' => $item->prepared_stock_quantity,
                        'stock_before' => 0,
                        'stock_after' => $item->prepared_stock_quantity,
                        'reason' => 'Opening balance imported when stock history was introduced.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('item_cancellations');
        Schema::table('kot_items', function (Blueprint $table) {
            $table->dropColumn('cancelled_quantity');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cancelled_quantity');
        });
    }
};
