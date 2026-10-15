<?php

use App\Models\Radacct;
use Illuminate\Support\Facades\DB;
use Ispgrid\Ipdr\Archive\CopySessions;

function archived(): array
{
    return DB::connection('ipdr')->table('ipdr_sessions')->orderBy('username')->pluck('username')->all();
}

it('archives closed sessions and remembers open ones', function () {
    Radacct::factory()->closed()->create(['username' => 'closed']);
    Radacct::factory()->create(['username' => 'open']);

    $this->artisan('ipdr:copy')->expectsOutput('Archived 1 sessions; 1 still open.')->assertSuccessful();

    expect(archived())->toBe(['closed'])
        ->and(DB::connection('ipdr')->table('ipdr_pending')->count())->toBe(1);
});

it('archives a long session once when it finally closes', function () {
    $session = Radacct::factory()->create(['username' => 'long']);
    app(CopySessions::class)->run();
    app(CopySessions::class)->run();

    DB::table('radacct')->where('radacctid', $session->radacctid)->update(['acctstoptime' => now(), 'acctterminatecause' => 'Lost-Carrier']);
    app(CopySessions::class)->run();
    app(CopySessions::class)->run();

    expect(archived())->toBe(['long'])
        ->and(DB::connection('ipdr')->table('ipdr_pending')->count())->toBe(0);
});

it('copies the fields a records request needs', function () {
    $session = Radacct::factory()->closed()->create([
        'username' => 'asha', 'callingstationid' => 'AA:BB:CC:DD:EE:01', 'framedipaddress' => '100.64.10.7',
        'nasipaddress' => '10.0.0.9', 'acctinputoctets' => 100, 'acctoutputoctets' => 200,
    ]);

    app(CopySessions::class)->run();

    $row = DB::connection('ipdr')->table('ipdr_sessions')->first();
    expect($row->acctuniqueid)->toBe($session->acctuniqueid)
        ->and($row->callingstationid)->toBe('AA:BB:CC:DD:EE:01')
        ->and($row->framedipaddress)->toBe('100.64.10.7')
        ->and($row->nasipaddress)->toBe('10.0.0.9')
        ->and((int) $row->acctinputoctets)->toBe(100)
        ->and($row->acctstoptime)->not->toBeNull();
});

it('never archives a session twice', function () {
    Radacct::factory()->closed()->create();
    app(CopySessions::class)->run();
    DB::connection('ipdr')->table('ipdr_state')->delete();

    app(CopySessions::class)->run();

    expect(DB::connection('ipdr')->table('ipdr_sessions')->count())->toBe(1);
});

it('forgets a waiting session whose row is gone', function () {
    $session = Radacct::factory()->create();
    app(CopySessions::class)->run();
    DB::table('radacct')->where('radacctid', $session->radacctid)->delete();

    app(CopySessions::class)->run();

    expect(DB::connection('ipdr')->table('ipdr_pending')->count())->toBe(0);
});
