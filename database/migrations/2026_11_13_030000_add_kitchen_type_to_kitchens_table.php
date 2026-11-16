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
            $table->string('kitchen_type', 100)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        // The companion migration may have created or still depend on this column.
        if (DB::table(config('database.migrations.table', 'migrations'))
            ->where('migration', '2026_11_13_100200_add_kitchen_type')->exists()
            || ! Schema::hasColumn('kitchens', 'kitchen_type')) {
            return;
        }

        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn('kitchen_type');
        });
    }
};

