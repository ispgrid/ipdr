<?php

namespace Ispgrid\Ipdr\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Ispgrid\Ipdr\Archive\CopySessions;

#[Signature('ipdr:copy')]
#[Description('Copy closed sessions into the session records archive')]
class CopyCommand extends Command
{
    public function handle(CopySessions $copy): int
    {
        $result = $copy->run();
        $this->info("Archived {$result['archived']} sessions; {$result['open']} still open.");

        return self::SUCCESS;
    }
}
