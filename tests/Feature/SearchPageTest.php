<?php

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Radacct;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

function closedSessionWithIp(string $username): void
{
    Radacct::factory()->create([
        'username' => $username, 'framedipaddress' => '100.64.10.7',
        'acctstarttime' => '2026-10-15 09:00:00', 'acctstoptime' => '2026-10-15 11:00:00',
    ]);
}

it('is in the menu after Login log for Admin and closed to a Technician', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSeeInOrder([__('Login log'), __('Session records'), __('Plans')]);

    $this->actingAs(User::factory()->role(Role::TECHNICIAN)->create())
        ->get(route('session-records.index'))
        ->assertForbidden();
});

it('stops an open page once its permission is taken away', function () {
    $role = Role::create(['name' => 'Records desk', 'guard_name' => 'web']);
    $role->givePermissionTo('search-session-records');
    $staff = User::factory()->role('Records desk')->create();
    $this->actingAs($staff);
    $page = Livewire::test('ipdr::session-records')->assertOk();

    $role->revokePermissionTo('search-session-records');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $staff->unsetRelation('roles')->unsetRelation('permissions');

    $page->set('value', '100.64.10.7')->assertForbidden();
});

it('needs a reference and a reason before it searches', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('ipdr::session-records')
        ->set('by', 'ip')->set('value', '100.64.10.7')->set('at', '2026-10-15T10:00')
        ->call('search')
        ->assertHasErrors(['reference' => 'required', 'reason' => 'required']);
});

it('shows who had the IP, with their details, and logs the search', function () {
    $this->actingAs(User::factory()->create());
    Account::factory()->create(['username' => 'asha', 'fullname' => 'Asha Rao', 'mobile' => '9415226838']);
    closedSessionWithIp('asha');

    Livewire::test('ipdr::session-records')
        ->set('by', 'ip')->set('value', '100.64.10.7')->set('at', '2026-10-15T10:00')
        ->set('reference', 'DoT/123')->set('reason', 'Police request')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSee('Asha Rao')
        ->assertSee('9415226838');

    $log = Activity::query()->where('event', 'session-records-searched')->sole();
    expect($log->attribute_changes['attributes']['reference'])->toBe('DoT/123')
        ->and($log->attribute_changes['attributes']['results'])->toBe(1);
});

it('says Deleted customer for a session of a removed customer', function () {
    $this->actingAs(User::factory()->create());
    closedSessionWithIp('gone');

    Livewire::test('ipdr::session-records')
        ->set('by', 'ip')->set('value', '100.64.10.7')->set('at', '2026-10-15T10:00')
        ->set('reference', 'R1')->set('reason', 'Test')
        ->call('search')
        ->assertSee(__('Deleted customer'));
});

it('exports the results as CSV and logs the export', function () {
    $this->actingAs(User::factory()->create());
    closedSessionWithIp('asha');

    Livewire::test('ipdr::session-records')
        ->set('by', 'ip')->set('value', '100.64.10.7')->set('at', '2026-10-15T10:00')
        ->set('reference', 'R1')->set('reason', 'Test')
        ->call('export')
        ->assertFileDownloaded();

    expect(Activity::query()->where('event', 'session-records-exported')->count())->toBe(1);
});

it('lets App settings staff set how long records are kept, never under two years', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('ipdr::session-records')
        ->assertSet('keepDays', '730')
        ->set('keepDays', '500')->call('saveKeepDays')->assertHasErrors(['keepDays'])
        ->set('keepDays', '1095')->call('saveKeepDays')->assertHasNoErrors();

    expect(AppSetting::valueOf('ipdr_keep_days'))->toBe('1095');
});
