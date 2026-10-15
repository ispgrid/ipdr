<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * The archive database and its tables, created only when missing: the archive is a legal record, so nothing here
 * ever drops it (down() does nothing). Also the "Search session records" permission row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $database = config('database.connections.ipdr.database');
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::purge('ipdr');
        $schema = Schema::connection('ipdr');

        if (! $schema->hasTable('ipdr_sessions')) {
            $schema->create('ipdr_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('radacctid')->index();
                $table->string('acctuniqueid', 32)->unique();
                $table->string('acctsessionid', 64)->default('');
                $table->string('username', 64)->default('');
                $table->string('nasipaddress', 15)->default('');
                $table->string('nasportid', 32)->nullable();
                $table->string('callingstationid', 50)->default('');
                $table->string('calledstationid', 50)->default('');
                $table->string('framedipaddress', 15)->default('');
                $table->string('framedipv6address', 45)->default('');
                $table->string('delegatedipv6prefix', 45)->default('');
                $table->dateTime('acctstarttime')->nullable();
                $table->dateTime('acctstoptime');
                $table->unsignedInteger('acctsessiontime')->nullable();
                $table->bigInteger('acctinputoctets')->nullable();
                $table->bigInteger('acctoutputoctets')->nullable();
                $table->string('acctterminatecause', 32)->default('');
                $table->timestamp('archived_at')->useCurrent();
                $table->index(['framedipaddress', 'acctstarttime']);
                $table->index(['framedipv6address', 'acctstarttime']);
                $table->index(['username', 'acctstarttime']);
                $table->index(['callingstationid', 'acctstarttime']);
                $table->index(['nasipaddress', 'acctstarttime']);
                $table->index('acctstoptime');
            });
        }

        if (! $schema->hasTable('ipdr_pending')) {
            $schema->create('ipdr_pending', function (Blueprint $table) {
                $table->unsignedBigInteger('radacctid')->primary();
            });
        }

        if (! $schema->hasTable('ipdr_state')) {
            $schema->create('ipdr_state', function (Blueprint $table) {
                $table->string('key', 50)->primary();
                $table->string('value', 255);
            });
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore(['name' => 'search-session-records', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never: the archive is a legal record.
    }
};
