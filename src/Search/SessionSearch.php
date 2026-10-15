<?php

namespace Ispgrid\Ipdr\Search;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One search: an IP at a moment (who had it then), or a username, MAC or NAS over a range (sessions that
 * overlap it). Archived sessions come from the archive; sessions still open come from radacct.
 */
final class SessionSearch
{
    public const BY = ['ip', 'username', 'mac', 'nas'];

    private const COLUMNS = ['username' => 'username', 'mac' => 'callingstationid', 'nas' => 'nasipaddress'];

    public function __construct(
        public readonly string $by,
        public readonly string $value,
        public readonly ?CarbonImmutable $at,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
    ) {}

    /**
     * MikroTik sends AA:BB:CC:DD:EE:FF; staff may type it in lower case, with dashes or dots.
     */
    public static function normaliseMac(string $mac): string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9a-fA-F]/', '', $mac));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : strtoupper(trim($mac));
    }

    public function archived(): Builder
    {
        return $this->filter(DB::connection('ipdr')->table('ipdr_sessions'), closed: true)
            ->orderByDesc('acctstarttime')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, object>
     */
    public function open(): Collection
    {
        return $this->filter(DB::table('radacct')->whereNull('acctstoptime'), closed: false)
            ->orderByDesc('acctstarttime')
            ->get();
    }

    /**
     * The search in words, for the Activity log.
     */
    public function describe(): string
    {
        $format = 'Y-m-d H:i:s';

        return match ($this->by) {
            'ip' => "IP {$this->value} at {$this->at?->format($format)}",
            default => ['username' => 'Username', 'mac' => 'MAC', 'nas' => 'NAS'][$this->by]." {$this->value} from {$this->from?->format($format)} to {$this->to?->format($format)}",
        };
    }

    private function filter(Builder $query, bool $closed): Builder
    {
        $value = $this->by === 'mac' ? self::normaliseMac($this->value) : trim($this->value);

        if ($this->by === 'ip') {
            $query->where(fn (Builder $q) => $q->where('framedipaddress', $value)->orWhere('framedipv6address', $value))
                ->where('acctstarttime', '<=', $this->at);

            return $closed ? $query->where('acctstoptime', '>=', $this->at) : $query;
        }

        $query->where(self::COLUMNS[$this->by], $value)->where('acctstarttime', '<=', $this->to);

        return $closed ? $query->where('acctstoptime', '>=', $this->from) : $query;
    }
}
