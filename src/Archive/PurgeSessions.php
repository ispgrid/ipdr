<?php

namespace Ispgrid\Ipdr\Archive;

use Illuminate\Support\Facades\DB;

/**
 * Removes archived sessions that ended more than $days ago, in batches (database clock, like FreeRADIUS).
 */
final class PurgeSessions
{
    private const BATCH = 5000;

    public function run(int $days): int
    {
        $removed = 0;

        do {
            $deleted = DB::connection('ipdr')->table('ipdr_sessions')
                ->whereRaw('acctstoptime < NOW() - INTERVAL ? DAY', [$days])
                ->orderBy('acctstoptime')
                ->limit(self::BATCH)
                ->delete();
            $removed += $deleted;
        } while ($deleted === self::BATCH);

        return $removed;
    }
}
