<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['kitchen-manager', 'kitchen-staff', 'manager'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['menu-item.view', 'menu-item.stock-adjustment'])
            ->pluck('id');

        $now = now();
        $assignments = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $assignments[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($assignments !== []) {
            DB::table('role_permissions')->insertOrIgnore($assignments);
        }
    }

    public function down(): void
    {
        // Safe additive migration
    }
};

