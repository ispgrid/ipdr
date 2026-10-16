<form wire:submit="save" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
    <x-ui.input wire:model="keepDays" type="number" min="730" max="3650" :label="__('Keep session records (days)')" :description="__('Older records are removed every night. 730 to 3650 days.')" />
    <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>
</form>
