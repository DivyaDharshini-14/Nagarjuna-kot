<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_cancellations', function (Blueprint $table) {
            $table->unsignedInteger('stock_reversed_quantity')->default(0)->after('stock_reversed');
        });
    }

    public function down(): void
    {
        Schema::table('item_cancellations', function (Blueprint $table) {
            $table->dropColumn('stock_reversed_quantity');
        });
    }
};
