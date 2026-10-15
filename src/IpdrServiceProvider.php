<?php

namespace Ispgrid\Ipdr;

use App\Models\User;
use App\Support\ActivityFormatter;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use App\Support\Permissions;
use App\Support\SessionKeepers;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Ispgrid\Ipdr\Archive\ArchiveKeeper;
use Ispgrid\Ipdr\Console\CopyCommand;
use Ispgrid\Ipdr\Console\PurgeCommand;
use Livewire\Livewire;

/**
 * Session records (IPDR): every closed session copied to its own database (connection "ipdr") and kept for two
 * years, searchable by IP and time.
 */
class IpdrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var array<string, mixed> $base */
        $base = config('database.connections.mysql');
        $default = $this->app->environment('testing') ? 'ispgrid_ipdr_test' : 'ispgrid_ipdr';

        config(['database.connections.ipdr' => array_merge($base, [
            'host' => env('IPDR_DB_HOST', $base['host']),
            'port' => env('IPDR_DB_PORT', $base['port']),
            'database' => env('IPDR_DB_DATABASE', $default),
            'username' => env('IPDR_DB_USERNAME', $base['username']),
            'password' => env('IPDR_DB_PASSWORD', $base['password']),
        ])]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        Livewire::addNamespace('ipdr', viewPath: __DIR__.'/../resources/views/livewire');

        $this->app->make(SessionKeepers::class)->register(ArchiveKeeper::class);

        $this->app->make(Permissions::class)->register(
            'search-session-records',
            fn (): string => __('Search session records'),
            fn (): string => __('Sessions'),
        );

        $this->app->make(Navigation::class)->add(new NavItem(
            group: 'main',
            label: fn (): string => __('Session records'),
            route: 'session-records.index',
            icon: 'document-magnifying-glass',
            active: ['session-records.*'],
            visible: fn (User $user): bool => $user->can('search-session-records'),
            order: 45,
        ));

        ActivityFormatter::addEventLabel('session-records-searched', fn (): string => __('Session records searched'));
        ActivityFormatter::addEventLabel('session-records-exported', fn (): string => __('Session records exported'));

        if ($this->app->runningInConsole()) {
            $this->commands([CopyCommand::class, PurgeCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ipdr:copy')->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command('ipdr:purge')->dailyAt('04:00')->withoutOverlapping();
        });
    }
}
