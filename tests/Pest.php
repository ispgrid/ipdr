<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
| Run from the core checkout:
|   ISPGRID_ADDONS=on php vendor/bin/pest --test-directory=vendor/ispgrid/ipdr/tests vendor/ispgrid/ipdr/tests
| The archive lives on its own connection, outside RefreshDatabase's transaction: it is emptied before each test.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => ipdrReset())
    ->in(__DIR__);

function ipdrReset(): void
{
    foreach (['ipdr_sessions', 'ipdr_pending', 'ipdr_state'] as $table) {
        DB::connection('ipdr')->table($table)->truncate();
    }
}
