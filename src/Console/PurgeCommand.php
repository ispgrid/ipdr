<?php

namespace Ispgrid\Ipdr\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Ispgrid\Ipdr\Archive\KeepDays;
use Ispgrid\Ipdr\Archive\PurgeSessions;

#[Signature('ipdr:purge')]
#[Description('Remove archived sessions older than the session records period')]
class PurgeCommand extends Command
{
    public function handle(PurgeSessions $purge): int
    {
        $days = KeepDays::get();
        $this->info("Removed {$purge->run($days)} sessions older than {$days} days.");

        return self::SUCCESS;
    }
}
