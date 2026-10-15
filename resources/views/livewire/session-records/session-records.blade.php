<section class="grid gap-6">
    <div>
        <x-ui.heading level="1" size="xl">{{ __('Session records') }}</x-ui.heading>
        <x-ui.text muted class="mt-1">{{ __('Every session is kept for :days days. Each search is recorded in the Activity log with its reference and reason.', ['days' => $keepDays]) }}</x-ui.text>
    </div>

    <form wire:submit="search" class="grid gap-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select wire:model.live="by" :label="__('Search by')" :options="$this->searchTypes" />
            @if ($by === 'nas')
                <x-ui.select wire:model="value" :label="__('NAS')" :options="$this->routers" :placeholder="__('Choose a NAS')" />
            @else
                <x-ui.input wire:model="value" :label="$this->valueLabel" autocomplete="off" />
            @endif
            @if ($by === 'ip')
                <x-ui.input wire:model="at" type="datetime-local" step="1" :label="__('Date and time')" />
            @else
                <x-ui.input wire:model="from" type="datetime-local" :label="__('From')" />
                <x-ui.input wire:model="to" type="datetime-local" :label="__('To')" />
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.input wire:model="reference" :label="__('Reference number')" :description="__('The number on the request you are answering.')" />
            <x-ui.input wire:model="reason" :label="__('Reason')" />
        </div>
        <x-ui.form-actions>
            <x-ui.button type="submit">{{ __('Search') }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" wire:click="export">{{ __('Export CSV') }}</x-ui.button>
        </x-ui.form-actions>
    </form>

    @if ($searched)
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Username') }}</x-ui.th>
                <x-ui.th>{{ __('Customer') }}</x-ui.th>
                <x-ui.th>{{ __('Device (MAC)') }}</x-ui.th>
                <x-ui.th>{{ __('IP') }}</x-ui.th>
                <x-ui.th>{{ __('NAS') }}</x-ui.th>
                <x-ui.th>{{ __('Start') }}</x-ui.th>
                <x-ui.th>{{ __('End') }}</x-ui.th>
                <x-ui.th>{{ __('Duration') }}</x-ui.th>
                <x-ui.th>{{ __('Data (down / up)') }}</x-ui.th>
            </x-slot:head>

            @forelse ($this->rows as $row)
                <tr wire:key="session-{{ $loop->index }}">
                    <x-ui.td>
                        @if ($row['account'])
                            <x-ui.link href="{{ route('customers.show', $row['account']) }}" wire:navigate class="font-medium">{{ $row['username'] }}</x-ui.link>
                        @else
                            <span class="font-medium">{{ $row['username'] }}</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td>
                        <div>{{ $row['customer'] }}</div>
                        <x-ui.text muted class="text-xs">{{ $row['mobile'] }} {{ $row['address'] }}</x-ui.text>
                    </x-ui.td>
                    <x-ui.td class="font-mono">{{ $row['mac'] }}</x-ui.td>
                    <x-ui.td class="font-mono">{{ $row['ip'] }}</x-ui.td>
                    <x-ui.td>{{ $row['nas'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums whitespace-nowrap">{{ $row['start'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums whitespace-nowrap">{{ $row['end'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums whitespace-nowrap">{{ $row['duration'] }}</x-ui.td>
                    <x-ui.td class="tabular-nums whitespace-nowrap">{{ $row['data'] }}</x-ui.td>
                </tr>
            @empty
                <tr>
                    <x-ui.td colspan="9" class="py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No session matches this search.') }}</x-ui.td>
                </tr>
            @endforelse
        </x-ui.table>

        {{ $this->results->links() }}
    @endif

    @if ($this->canManageKeepDays)
        <x-ui.card class="grid content-start gap-4">
            <x-ui.heading>{{ __('Keeping') }}</x-ui.heading>
            <form wire:submit="saveKeepDays" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                <x-ui.input wire:model="keepDays" type="number" min="730" max="3650" :label="__('Keep session records (days)')" :description="__('Older records are removed every night. 730 to 3650 days.')" />
                <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</section>
