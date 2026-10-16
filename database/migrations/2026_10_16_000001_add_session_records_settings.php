<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keep-days moves to the add-on's own section on System, then Add-ons, behind its own permission, "Session records
 * settings". Roles that could set it through App settings get the new permission, and the setting takes the
 * add-on's key, ipdr.keep_days (a value already under the new key wins).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore(['name' => 'manage-session-records', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        $new = DB::table('permissions')->where('name', 'manage-session-records')->where('guard_name', 'web')->value('id');
        $appSettings = DB::table('permissions')->where('name', 'manage-app-settings')->where('guard_name', 'web')->value('id');

        if ($new !== null && $appSettings !== null) {
            $roles = DB::table('role_has_permissions')->where('permission_id', $appSettings)->pluck('role_id');
            DB::table('role_has_permissions')->insertOrIgnore($roles->map(fn (int $role): array => ['permission_id' => $new, 'role_id' => $role])->all());
        }

        if (DB::table('app_settings')->where('key', 'ipdr.keep_days')->exists()) {
            DB::table('app_settings')->where('key', 'ipdr_keep_days')->delete();
        } else {
            DB::table('app_settings')->where('key', 'ipdr_keep_days')->update(['key' => 'ipdr.keep_days']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing: the setting and the permission stay.
    }
};
