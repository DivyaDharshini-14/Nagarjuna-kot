<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slugs = ['report.view', 'report.export', 'audit-log.view', 'audit-log.export', 'settings.view', 'settings.edit'];
        foreach ($slugs as $slug) {
            [$module, $action] = explode('.', $slug);
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug, 'module' => $module, 'name' => ucfirst($action), 'description' => ucfirst($action).' '.$module,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('roles')->whereIn('slug', ['admin', 'super-admin', 'company-admin', 'manager', 'branch-manager', 'restaurant-manager'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve role configuration and historical access decisions on rollback.
    }
};
