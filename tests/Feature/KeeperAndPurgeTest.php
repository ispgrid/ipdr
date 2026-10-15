<?php

use App\Models\AppSetting;
use App\Models\Radacct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ispgrid\Ipdr\Archive\ArchiveKeeper;
use Ispgrid\Ipdr\Archive\CopySessions;

function archiveRow(string $username, string $stoppedAgo): void
{
    DB::connection('ipdr')->table('ipdr_sessions')->insert([
        'radacctid' => random_int(1, 1_000_000),
        'acctuniqueid' => Str::random(32),
        'username' => $username,
        'acctstarttime' => DB::raw("NOW() - INTERVAL {$stoppedAgo}"),
        'acctstoptime' => DB::raw("NOW() - INTERVAL {$stoppedAgo}"),
    ]);
}

it('tells core which sessions it already holds', function () {
    $kept = Radacct::factory()->closed()->create();
    $notYet = Radacct::factory()->closed()->create();
    app(CopySessions::class)->run();
    DB::connection('ipdr')->table('ipdr_sessions')->where('acctuniqueid', $notYet->acctuniqueid)->delete();

    expect(app(ArchiveKeeper::class)->kept([$kept->acctuniqueid, $notYet->acctuniqueid]))->toBe([$kept->acctuniqueid]);
});

it('stops core deleting sessions it has not archived yet', function () {
    DB::table('radacct')->insert([
        'acctsessionid' => 'a', 'acctuniqueid' => Str::random(32), 'username' => 'old', 'nasipaddress' => '10.0.0.1',
        'acctstarttime' => DB::raw('NOW() - INTERVAL 400 DAY'), 'acctstoptime' => DB::raw('NOW() - INTERVAL 400 DAY'),
    ]);

    $this->artisan('radius:prune')->assertSuccessful();
    expect(DB::table('radacct')->count())->toBe(1);

    app(CopySessions::class)->run();
    $this->artisan('radius:prune')->assertSuccessful();
    expect(DB::table('radacct')->count())->toBe(0)
        ->and(DB::connection('ipdr')->table('ipdr_sessions')->count())->toBe(1);
});

it('removes archived sessions older than two years and keeps the rest', function () {
    archiveRow('old', '731 DAY');
    archiveRow('recent', '729 DAY');

    $this->artisan('ipdr:purge')->expectsOutput('Removed 1 sessions older than 730 days.')->assertSuccessful();

    expect(DB::connection('ipdr')->table('ipdr_sessions')->pluck('username')->all())->toBe(['recent']);
});

it('never keeps less than two years, whatever the setting says', function (string $value, int $days) {
    AppSetting::put('ipdr_keep_days', $value);

    $this->artisan('ipdr:purge')->expectsOutput("Removed 0 sessions older than {$days} days.")->assertSuccessful();
})->with([
    'a year' => ['365', 730],
    'three years' => ['1095', 1095],
    'not a number' => ['abc', 730],
]);
