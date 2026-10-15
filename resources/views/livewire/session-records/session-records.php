<?php

use App\Models\AppSetting;
use App\Models\Nas;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Validation\Rule;
use Ispgrid\Ipdr\Archive\CopySessions;
use Ispgrid\Ipdr\Archive\KeepDays;
use Ispgrid\Ipdr\Search\SessionRows;
use Ispgrid\Ipdr\Search\SessionSearch;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Session records')] class extends Component
{
    use WithPagination;

    private const EXPORT_LIMIT = 100000;

    public string $by = 'ip';

    public string $value = '';

    public string $at = '';

    public string $from = '';

    public string $to = '';

    public string $reference = '';

    public string $reason = '';

    public string $keepDays = '';

    /**
     * Set once a search passed validation; the table shows results only then.
     */
    public bool $searched = false;

    public function mount(): void
    {
        $this->authorize('search-session-records');
        $this->keepDays = (string) KeepDays::get();
    }

    /**
     * Copies the newest closed sessions first, so a session that ended minutes ago is found too.
     */
    public function search(): void
    {
        $this->authorize('search-session-records');
        $this->validate();
        app(CopySessions::class)->run();
        $this->searched = true;
        $this->resetPage();
        $search = $this->query();
        $this->log('session-records-searched', $search->archived()->count() + $search->open()->count());
    }

    public function export(): StreamedResponse
    {
        $this->authorize('search-session-records');
        $this->validate();
        app(CopySessions::class)->run();
        $search = $this->query();
        $sessions = $search->open()->concat($search->archived()->limit(self::EXPORT_LIMIT)->get());
        $this->log('session-records-exported', $sessions->count());
        $rows = SessionRows::from($sessions);
        $header = [__('Username'), __('Customer'), __('Mobile'), __('Address'), __('Device (MAC)'), __('IP'), __('NAS'), __('Start'), __('End'), __('Duration'), __('Download (bytes)'), __('Upload (bytes)')];

        return response()->streamDownload(function () use ($rows, $header): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $header, escape: '');

            foreach ($rows as $row) {
                fputcsv($output, [$row['username'], $row['customer'], $row['mobile'], $row['address'], $row['mac'], $row['ip'], $row['nas'], $row['start'], $row['end'], $row['duration'], $row['down'], $row['up']], escape: '');
            }

            fclose($output);
        }, 'session-records-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function saveKeepDays(): void
    {
        $this->authorize('manage-app-settings');
        $this->validateOnly('keepDays', ['keepDays' => ['required', 'integer', 'between:'.KeepDays::MIN.','.KeepDays::MAX]]);
        AppSetting::put('ipdr_keep_days', $this->keepDays);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $range = $this->by !== 'ip';

        return [
            'by' => ['required', Rule::in(SessionSearch::BY)],
            'value' => ['required', 'string', 'max:64'],
            'at' => [Rule::requiredIf(! $range), 'nullable', 'date'],
            'from' => [Rule::requiredIf($range), 'nullable', 'date'],
            'to' => [Rule::requiredIf($range), 'nullable', 'date', 'after_or_equal:from'],
            'reference' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    #[Computed]
    public function results(): ?Paginator
    {
        return $this->searched ? $this->query()->archived()->simplePaginate(50) : null;
    }

    /**
     * Open sessions first (on the first page only), then archived ones.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        if (! $this->searched || $this->results === null) {
            return [];
        }

        $open = $this->getPage() === 1 ? $this->query()->open() : collect();

        return SessionRows::from($open->concat($this->results->items()));
    }

    #[Computed]
    public function canManageKeepDays(): bool
    {
        return auth()->user()->can('manage-app-settings');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function searchTypes(): array
    {
        return ['ip' => __('IP address at a time'), 'username' => __('Username'), 'mac' => __('Device MAC'), 'nas' => __('NAS')];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function routers(): array
    {
        return Nas::names();
    }

    #[Computed]
    public function valueLabel(): string
    {
        return $this->searchTypes[$this->by] ?? __('Search');
    }

    private function query(): SessionSearch
    {
        $parse = fn (string $value): ?CarbonImmutable => $value === '' ? null : CarbonImmutable::parse($value);

        return new SessionSearch($this->by, $this->value, $parse($this->at), $parse($this->from), $parse($this->to));
    }

    private function log(string $event, int $results): void
    {
        activity()
            ->event($event)
            ->withChanges(['attributes' => [
                'reference' => $this->reference,
                'reason' => $this->reason,
                'search' => $this->query()->describe(),
                'results' => $results,
            ]])
            ->log($event);
    }
};
