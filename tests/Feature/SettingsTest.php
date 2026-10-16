<?php

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Ispgrid\Ipdr\Archive\KeepDays;
use Livewire\Livewire;

function settingsMigration(): object
{
    return require __DIR__.'/../../database/migrations/2026_10_16_000001_add_session_records_settings.php';
}

it('sets how long records are kept on System, then Add-ons, never under two years', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('addons.index'))->assertOk()->assertSee(__('Keep session records (days)'));

    Livewire::test('ipdr::settings')
        ->assertSet('keepDays', '730')
        ->set('keepDays', '500')->call('save')->assertHasErrors(['keepDays'])
        ->set('keepDays', '1095')->call('save')->assertHasNoErrors();

    expect(AppSetting::valueOf(KeepDays::KEY))->toBe('1095');
});

it('needs the Session records settings permission, not App settings', function () {
    Role::create(['name' => 'Settings desk', 'guard_name' => 'web'])->givePermissionTo('manage-app-settings');
    $this->actingAs(User::factory()->role('Settings desk')->create());

    $this->get(route('addons.index'))->assertOk()->assertDontSee(__('Keep session records (days)'));
    Livewire::test('ipdr::settings')->assertForbidden();
});

it('no longer sets keep-days on the search page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('session-records.index'))->assertOk()->assertDontSee(__('Keep session records (days)'));
});

it('gives the new permission to roles with App settings and moves the setting to its new key', function () {
    $role = Role::create(['name' => 'Settings desk', 'guard_name' => 'web']);
    $role->givePermissionTo('manage-app-settings');
    $role->revokePermissionTo('manage-session-records');
    DB::table('app_settings')->insert(['key' => 'ipdr_keep_days', 'value' => '900']);

    settingsMigration()->up();

    expect($role->fresh()->hasPermissionTo('manage-session-records'))->toBeTrue()
        ->and(Role::findByName(Role::TECHNICIAN)->hasPermissionTo('manage-session-records'))->toBeFalse()
        ->and(AppSetting::valueOf('ipdr_keep_days'))->toBeNull()
        ->and(AppSetting::valueOf(KeepDays::KEY))->toBe('900');
});

it('keeps a value already under the new key when moving the setting', function () {
    AppSetting::put(KeepDays::KEY, '1200');
    DB::table('app_settings')->insert(['key' => 'ipdr_keep_days', 'value' => '900']);

    settingsMigration()->up();

    expect(AppSetting::valueOf('ipdr_keep_days'))->toBeNull()
        ->and(AppSetting::valueOf(KeepDays::KEY))->toBe('1200');
});
