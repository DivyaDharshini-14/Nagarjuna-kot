<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kitchens', 'kitchen_type')) {
            return;
        }

        Schema::table('kitchens', function (Blueprint $table) {
            $table->string('kitchen_type', 100)->default('General');
        });
    }

    public function down(): void
    {
        // Drop only after neither migration requires the shared column.
        if (DB::table(config('database.migrations.table', 'migrations'))
            ->where('migration', '2026_11_13_030000_add_kitchen_type_to_kitchens_table')->exists()
            || ! Schema::hasColumn('kitchens', 'kitchen_type')) {
            return;
        }

        Schema::table('kitchens', fn (Blueprint $table) => $table->dropColumn('kitchen_type'));
    }
};
