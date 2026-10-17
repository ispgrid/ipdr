<?php

use App\Livewire\Concerns\AuthorizesEveryRequest;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\AppSetting;
use Ispgrid\Ipdr\Archive\KeepDays;
use Livewire\Component;

/**
 * The add-on's section on System, then Add-ons: how long the archive keeps a session.
 */
new class extends Component
{
    use AuthorizesEveryRequest, ShowsToasts;

    public string $keepDays = '';

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->keepDays = (string) KeepDays::get();
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('manage-session-records');
    }

    public function save(): void
    {
        $this->authorize('manage-session-records');
        $this->validate(['keepDays' => ['required', 'integer', 'between:'.KeepDays::MIN.','.KeepDays::MAX]]);
        AppSetting::put(KeepDays::KEY, $this->keepDays);
        $this->toast(__('Settings saved.'));
    }
};
