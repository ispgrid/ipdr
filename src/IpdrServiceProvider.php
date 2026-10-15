<?php

namespace Ispgrid\Ipdr;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Ispgrid\Ipdr\Console\CopyCommand;

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

        if ($this->app->runningInConsole()) {
            $this->commands([CopyCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ipdr:copy')->everyFifteenMinutes()->withoutOverlapping();
        });
    }
}
