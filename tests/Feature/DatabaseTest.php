<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ispgrid\Ipdr\IpdrServiceProvider;

it('creates the archive tables in their own database', function () {
    expect(DB::connection('ipdr')->getDatabaseName())->toBe('ispgrid_ipdr_test')
        ->and(Schema::connection('ipdr')->hasTable('ipdr_sessions'))->toBeTrue()
        ->and(Schema::connection('ipdr')->hasTable('ipdr_pending'))->toBeTrue()
        ->and(Schema::connection('ipdr')->hasTable('ipdr_state'))->toBeTrue();
});

it('takes its database settings from its config file, so they survive config:cache', function () {
    config(['ipdr.database.database' => 'ispgrid_ipdr_other', 'ipdr.database.host' => '']);

    (new IpdrServiceProvider(app()))->register();

    expect(config('database.connections.ipdr.database'))->toBe('ispgrid_ipdr_other')
        ->and(config('database.connections.ipdr.host'))->toBe(config('database.connections.mysql.host'));
});

it('adds the Search session records permission', function () {
    expect(DB::table('permissions')->where('name', 'search-session-records')->exists())->toBeTrue();
});
