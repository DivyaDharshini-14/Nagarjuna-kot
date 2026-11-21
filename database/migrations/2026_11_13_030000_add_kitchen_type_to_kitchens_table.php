<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingColumn = collect(Schema::getColumns('kitchens'))->firstWhere('name', 'kitchen_type');

        if ($existingColumn && $existingColumn['nullable']) {
            return;
        }

        Schema::table('kitchens', function (Blueprint $table) use ($existingColumn) {
            $column = $table->string('kitchen_type', 100)->nullable()->after('code');

            if ($existingColumn) {
                $column->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kitchens', function (Blueprint $table) {
            $table->dropColumn('kitchen_type');
        });
    }
};

