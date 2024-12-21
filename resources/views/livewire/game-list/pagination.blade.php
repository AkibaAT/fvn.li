<div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
    <x-filters.select
        wire:model.live="perPage"
        :value="$perPage"
        :options="[
            12 => '12 per page',
            24 => '24 per page',
            36 => '36 per page'
        ]"
        class="w-full sm:w-auto"
    />
    {{ $games->links(data: ['scrollTo' => false]) }}
</div>
