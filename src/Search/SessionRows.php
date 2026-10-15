<?php

namespace Ispgrid\Ipdr\Search;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Nas;
use App\Support\Units;
use Illuminate\Support\Collection;

/**
 * Session rows with the customer's current details: the archive keeps only the username.
 */
final class SessionRows
{
    /**
     * @param  Collection<int, object>  $sessions
     * @return list<array{username: string, account: ?int, customer: string, mobile: string, address: string, mac: string, ip: string, nas: string, start: string, end: string, duration: string, data: string, down: int, up: int}>
     */
    public static function from(Collection $sessions): array
    {
        $format = AppSetting::valueOf('date_format', 'd/m/Y').' H:i:s';
        $routers = Nas::names();
        $accounts = Account::withTrashed()->with('address')->whereIn('username', $sessions->pluck('username')->unique())->get()->keyBy('username');

        return $sessions->map(function (object $session) use ($format, $routers, $accounts): array {
            $account = $accounts[$session->username] ?? null;
            $live = $account !== null && ! $account->trashed();
            $address = $live ? $account->address : null;
            $down = (int) $session->acctoutputoctets;
            $up = (int) $session->acctinputoctets;

            return [
                'username' => (string) $session->username,
                'account' => $live ? $account->id : null,
                'customer' => $live ? $account->fullname : __('Deleted customer'),
                'mobile' => $live ? (string) $account->mobile : '',
                'address' => $address === null ? '' : collect([$address->line1, $address->line2, $address->area, $address->city, $address->state, $address->postal_code])->filter()->implode(', '),
                'mac' => (string) $session->callingstationid,
                'ip' => trim($session->framedipaddress.' '.$session->framedipv6address),
                'nas' => $routers[$session->nasipaddress] ?? (string) $session->nasipaddress,
                'start' => $session->acctstarttime ? date($format, (int) strtotime((string) $session->acctstarttime)) : '',
                'end' => $session->acctstoptime ? date($format, (int) strtotime((string) $session->acctstoptime)) : __('Online'),
                'duration' => Units::duration((int) $session->acctsessiontime),
                'data' => Units::bytes($down).' / '.Units::bytes($up),
                'down' => $down,
                'up' => $up,
            ];
        })->values()->all();
    }
}
