<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the archive tables in their own database', function () {
    expect(DB::connection('ipdr')->getDatabaseName())->toBe('ispgrid_ipdr_test')
        ->and(Schema::connection('ipdr')->hasTable('ipdr_sessions'))->toBeTrue()
        ->and(Schema::connection('ipdr')->hasTable('ipdr_pending'))->toBeTrue()
        ->and(Schema::connection('ipdr')->hasTable('ipdr_state'))->toBeTrue();
});

it('adds the Search session records permission', function () {
    expect(DB::table('permissions')->where('name', 'search-session-records')->exists())->toBeTrue();
});
