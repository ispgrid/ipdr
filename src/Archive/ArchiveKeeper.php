<?php

namespace Ispgrid\Ipdr\Archive;

use App\Contracts\SessionKeeper;
use Illuminate\Support\Facades\DB;

/**
 * Answers core's nightly cleanup: only sessions already in the archive may leave radacct.
 */
final class ArchiveKeeper implements SessionKeeper
{
    public function kept(array $acctUniqueIds): array
    {
        if ($acctUniqueIds === []) {
            return [];
        }

        $held = DB::connection('ipdr')->table('ipdr_sessions')->whereIn('acctuniqueid', $acctUniqueIds)->pluck('acctuniqueid')->all();

        return array_values(array_intersect($acctUniqueIds, $held));
    }
}
