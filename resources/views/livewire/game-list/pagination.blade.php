<div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
    <x-filters.select
        wire:model.live="perPage"
        :value="$perPage"
        :options="[
            9 => '9 per page',
            18 => '18 per page',
            27 => '27 per page'
        ]"
        placeholder=""
        class="w-full sm:w-auto"
    />
    {{ $games->links(data: ['scrollTo' => false]) }}
</div>
