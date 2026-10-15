<?php

namespace Ispgrid\Ipdr\Archive;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copies closed sessions from radacct into the archive. New rows are read by radacctid from where the last run
 * stopped; rows seen open wait in ipdr_pending until they close. insertOrIgnore on acctuniqueid makes a repeat
 * harmless. Reads radacct only: add-ons never write rad* tables.
 */
final class CopySessions
{
    private const BATCH = 1000;

    private const COLUMNS = [
        'radacctid', 'acctuniqueid', 'acctsessionid', 'username', 'nasipaddress', 'nasportid', 'callingstationid',
        'calledstationid', 'framedipaddress', 'framedipv6address', 'delegatedipv6prefix', 'acctstarttime',
        'acctstoptime', 'acctsessiontime', 'acctinputoctets', 'acctoutputoctets', 'acctterminatecause',
    ];

    /**
     * Archive columns that hold '' rather than NULL (old radacct rows may have NULL in them).
     */
    private const TEXT_COLUMNS = [
        'acctsessionid', 'username', 'nasipaddress', 'callingstationid', 'calledstationid', 'framedipaddress',
        'framedipv6address', 'delegatedipv6prefix', 'acctterminatecause',
    ];

    /**
     * @return array{archived: int, open: int}
     */
    public function run(): array
    {
        $archived = $this->copyNew() + $this->copyPending();

        return ['archived' => $archived, 'open' => DB::connection('ipdr')->table('ipdr_pending')->count()];
    }

    private function copyNew(): int
    {
        $archived = 0;
        $last = (int) (DB::connection('ipdr')->table('ipdr_state')->where('key', 'last_radacctid')->value('value') ?? 0);

        do {
            $rows = DB::table('radacct')->select(self::COLUMNS)->where('radacctid', '>', $last)->orderBy('radacctid')->limit(self::BATCH)->get();

            if ($rows->isEmpty()) {
                break;
            }

            [$closed, $open] = $rows->partition(fn (object $row): bool => $row->acctstoptime !== null);
            $archived += $this->archive($closed);
            DB::connection('ipdr')->table('ipdr_pending')->insertOrIgnore(
                $open->map(fn (object $row): array => ['radacctid' => $row->radacctid])->values()->all(),
            );
            $last = (int) $rows->last()->radacctid;
            DB::connection('ipdr')->table('ipdr_state')->upsert([['key' => 'last_radacctid', 'value' => (string) $last]], ['key'], ['value']);
        } while ($rows->count() === self::BATCH);

        return $archived;
    }

    private function copyPending(): int
    {
        $archived = 0;

        DB::connection('ipdr')->table('ipdr_pending')->orderBy('radacctid')->chunkById(self::BATCH, function (Collection $pending) use (&$archived): void {
            $ids = $pending->pluck('radacctid')->map(fn (mixed $id): int => (int) $id)->all();
            $rows = DB::table('radacct')->select(self::COLUMNS)->whereIn('radacctid', $ids)->get()->keyBy('radacctid');
            $closed = $rows->filter(fn (object $row): bool => $row->acctstoptime !== null);
            $archived += $this->archive($closed);
            $gone = array_values(array_diff($ids, $rows->keys()->map(fn (mixed $id): int => (int) $id)->all()));

            if ($gone !== []) {
                Log::warning('Session records: '.count($gone).' waiting sessions are no longer in radacct.');
            }

            DB::connection('ipdr')->table('ipdr_pending')->whereIn('radacctid', [...$closed->keys()->all(), ...$gone])->delete();
        }, 'radacctid');

        return $archived;
    }

    /**
     * @param  Collection<array-key, object>  $rows
     */
    private function archive(Collection $rows): int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        $now = now();

        return DB::connection('ipdr')->table('ipdr_sessions')->insertOrIgnore($rows->map(function (object $row) use ($now): array {
            $values = (array) $row;

            foreach (self::TEXT_COLUMNS as $column) {
                $values[$column] ??= '';
            }

            return $values + ['archived_at' => $now];
        })->values()->all());
    }
}
