<?php

namespace Ispgrid\Ipdr\Archive;

use App\Models\AppSetting;

/**
 * How long the archive keeps a session: App setting ipdr_keep_days, never under two years.
 */
final class KeepDays
{
    public const DEFAULT = 730;

    public const MIN = 730;

    public const MAX = 3650;

    public static function get(): int
    {
        $days = filter_var(AppSetting::valueOf('ipdr_keep_days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MIN, 'max_range' => self::MAX],
        ]);

        return $days === false ? self::DEFAULT : $days;
    }
}
