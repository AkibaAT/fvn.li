<script lang="ts" setup>
import {computed} from 'vue';
import {Link} from '@inertiajs/vue3';
import PlatformIcons from './PlatformIcons.vue';
import LanguageFlags from './LanguageFlags.vue';
import type {FilterType, Game} from '@/types';
import {formatNumber} from '@/utils/formatters';

interface Props {
    game: Game;
    selectedStatuses: string[];
    selectedEngines: string[];
    selectedPlatforms: string[];
    selectedLanguages: string[];
    nsfw: boolean;
    sfw: boolean;
}

const props = defineProps<Props>();

interface Language {
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

const emit = defineEmits<{
    (e: 'toggleFilter', type: FilterType, value?: string): void;
}>();

const languages = computed<Language[]>(() => {
    return props.game.supported_languages
        ?.sort((a: Language, b: Language) => a.ref_name.localeCompare(b.ref_name)) || [];
});


const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};
</script>

<template>
    <div
        class="relative bg-white dark:bg-gray-800/50 rounded-lg shadow-sm p-4 flex flex-col backdrop-blur-xs border border-gray-200 dark:border-transparent">
        <div class="flex gap-4">
            <Link :href="route('games.show', { game: game.slug })">
                <img
                    :alt="game.name"
                    :src="game.thumb_url || '/favicon.ico'"
                    class="h-24 w-32 object-cover rounded-sm"
                >
            </Link>

            <div class="flex flex-col min-w-0 flex-1">
                <div class="min-w-0 flex items-top gap-2">
                    <Link
                        :href="route('games.show', {game: game.slug})"
                        class="text-base font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400 line-clamp-2"
                    >
                        {{ game.name }}
                    </Link>
                    <a
                        :href="game.url"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        target="_blank"
                        title="Open on itch.io"
                    >
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>

                <p
                    v-if="game.authors"
                    class="text-sm text-gray-600 dark:text-gray-300 mt-1"
                    v-html="game.authors"
                />

                <div class="flex flex-col gap-2 mt-2">
                    <div class="min-w-0">
                        <button
                            v-if="game.is_nsfw"
                            :class="[
                'shrink-0 text-xs px-1.5 py-0.5 rounded-sm cursor-pointer transition-colors',
                nsfw ? 'bg-red-200 text-red-800 dark:bg-red-800/50 dark:text-red-200/90 ring-2 ring-red-500 dark:ring-red-500'
                     : 'bg-red-100 text-red-700 dark:bg-red-800/50 dark:text-red-300 hover:bg-red-200 hover:text-red-800 dark:hover:bg-red-800/50 dark:hover:text-red-300'
              ]"
                            @click="$emit('toggleFilter', 'nsfw')"
                        >
                            NSFW
                        </button>
                        <button
                            v-else
                            :class="[
                'shrink-0 text-xs px-1.5 py-0.5 rounded-sm cursor-pointer transition-colors',
                sfw ? 'bg-green-200 text-green-800 dark:bg-green-800/50 dark:text-green-200/90 ring-2 ring-green-500 dark:ring-green-500'
                    : 'bg-green-100 text-green-700 dark:bg-green-800/50 dark:text-green-300 hover:bg-green-200 hover:text-green-800 dark:hover:bg-green-800/50 dark:hover:text-green-300'
              ]"
                            @click="$emit('toggleFilter', 'sfw')"
                        >
                            SFW
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <PlatformIcons
                            :platforms="game.platforms"
                            :selected-platforms="selectedPlatforms"
                            @toggle="platform => $emit('toggleFilter', 'platform', platform)"
                        />
                    </div>

                    <LanguageFlags
                        v-if="languages.length"
                        :clickable="true"
                        :languages="languages"
                        :selected-languages="selectedLanguages"
                        @toggle="lang => $emit('toggleFilter', 'language', lang)"
                    />
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 text-sm border-t border-gray-100 dark:border-gray-700/50 pt-4">
            <template v-for="detail in [
        { label: 'Status', value: game.status, type: 'status', isFilter: true },
        { label: 'Engine', value: game.game_engine, type: 'engine', isFilter: true },
        { label: 'Words (EN)', value: formatNumber(game.english_word_count), isFilter: false },
        { label: 'Reviews', value: formatNumber(game.rating_count), isFilter: false },
        { label: 'Released', value: formatDate(game.initially_published_at), isFilter: false },
        { label: 'Updated', value: formatDate(game.latest_version_published_at), isFilter: false }
      ]" :key="detail.label">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">{{ detail.label }}:</span>
                    <button
                        v-if="detail.isFilter"
                        :class="[
              'ml-1 hover:text-blue-400',
              (detail.type === 'status' && selectedStatuses.includes(detail.value)) ||
              (detail.type === 'engine' && selectedEngines.includes(detail.value))
                ? 'text-blue-400 font-medium'
                : 'text-gray-700 dark:text-gray-200'
            ]"
                        @click="$emit('toggleFilter', (detail.type as 'status' | 'engine'), detail.value)"
                    >
                        {{ detail.value }}
                    </button>
                    <span v-else class="ml-1 text-gray-700 dark:text-gray-200">{{ detail.value }}</span>
                </div>
            </template>
        </div>

        <div v-if="game.tags" class="mt-4 flex flex-wrap gap-1.5">
      <span
          v-for="tag in game.tags.split(',')"
          :key="tag"
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-white dark:bg-gray-700/50 text-gray-600 dark:text-gray-200 border border-gray-200 dark:border-gray-600/50 hover:bg-gray-50 dark:hover:bg-gray-600/50 transition-colors"
      >
        {{ tag.trim() }}
      </span>
        </div>
    </div>
</template>
