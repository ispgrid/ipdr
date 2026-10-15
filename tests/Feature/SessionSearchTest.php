<?php

use App\Models\Radacct;
use Carbon\CarbonImmutable;
use Ispgrid\Ipdr\Archive\CopySessions;
use Ispgrid\Ipdr\Search\SessionSearch;

function sessionAt(string $username, string $ip, string $start, ?string $stop, string $mac = 'AA:BB:CC:DD:EE:01'): void
{
    Radacct::factory()->create([
        'username' => $username, 'framedipaddress' => $ip, 'callingstationid' => $mac,
        'acctstarttime' => $start, 'acctstoptime' => $stop,
    ]);
}

it('finds who had an IP at a moment, archived and online', function () {
    sessionAt('before', '100.64.10.7', '2026-10-15 08:00:00', '2026-10-15 09:00:00');
    sessionAt('during', '100.64.10.7', '2026-10-15 09:30:00', '2026-10-15 12:00:00');
    sessionAt('other-ip', '100.64.10.8', '2026-10-15 09:30:00', '2026-10-15 12:00:00');
    sessionAt('online', '100.64.10.9', '2026-10-15 10:00:00', null);
    app(CopySessions::class)->run();

    $search = new SessionSearch('ip', '100.64.10.7', CarbonImmutable::parse('2026-10-15 10:00:00'), null, null);
    expect($search->archived()->pluck('username')->all())->toBe(['during'])
        ->and($search->open()->pluck('username')->all())->toBe([]);

    $online = new SessionSearch('ip', '100.64.10.9', CarbonImmutable::parse('2026-10-15 11:00:00'), null, null);
    expect($online->open()->pluck('username')->all())->toBe(['online']);
});

it('counts the start and end second as inside the session', function () {
    sessionAt('edge', '100.64.10.7', '2026-10-15 09:00:00', '2026-10-15 10:00:00');
    app(CopySessions::class)->run();

    foreach (['2026-10-15 09:00:00', '2026-10-15 10:00:00'] as $moment) {
        expect((new SessionSearch('ip', '100.64.10.7', CarbonImmutable::parse($moment), null, null))->archived()->count())->toBe(1);
    }
});

it('finds the sessions of a customer that overlap a range', function () {
    sessionAt('asha', '100.64.10.7', '2026-10-01 10:00:00', '2026-10-02 10:00:00');
    sessionAt('asha', '100.64.10.7', '2026-10-09 23:00:00', '2026-10-10 01:00:00');
    sessionAt('asha', '100.64.10.7', '2026-10-20 10:00:00', '2026-10-20 11:00:00');
    app(CopySessions::class)->run();

    $search = new SessionSearch('username', 'asha', null, CarbonImmutable::parse('2026-10-10 00:00:00'), CarbonImmutable::parse('2026-10-19 23:59:59'));

    expect($search->archived()->count())->toBe(1);
});

it('matches a MAC typed in lower case with dashes', function () {
    sessionAt('asha', '100.64.10.7', '2026-10-01 10:00:00', '2026-10-01 11:00:00', 'AA:BB:CC:DD:EE:0F');
    app(CopySessions::class)->run();

    $search = new SessionSearch('mac', 'aa-bb-cc-dd-ee-0f', null, CarbonImmutable::parse('2026-10-01 00:00:00'), CarbonImmutable::parse('2026-10-02 00:00:00'));

    expect($search->archived()->count())->toBe(1)
        ->and(SessionSearch::normaliseMac('aabb.ccdd.ee0f'))->toBe('AA:BB:CC:DD:EE:0F');
});
