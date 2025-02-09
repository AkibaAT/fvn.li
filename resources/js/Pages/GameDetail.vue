<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PlatformIcons from '@/Components/GameList/PlatformIcons.vue';
import LanguageFlags from '@/Components/GameList/LanguageFlags.vue';
import RatingStars from '@/Components/SystemStatus/RatingStars.vue';
import CharacterStatsDialog from '@/Components/GameDetail/CharacterStatsDialog.vue';
import FileStatsDialog from '@/Components/GameDetail/FileStatsDialog.vue';
import { useGameDetailStore } from '@/stores/gameDetail';
import { useReviewsStore } from '@/stores/gameReviews';
import { formatNumber } from '@/utils/formatters';

interface SupportedLanguage {
    iso_code: string;
    ref_name: string;
    flag_code: string;
}

interface Version {
    id: number;
    version: string;
    published_at: string;
    rating: number | null;
    rating_count: number | null;
    is_windows: boolean;
    is_linux: boolean;
    is_mac: boolean;
    is_android: boolean;
    is_web: boolean;
    english_stats: {
        words: number;
    } | null;
    supported_languages: SupportedLanguage[];
    file_categories: boolean;
}

interface Props {
    game: {
        id: number;
        name: string;
        slug: string;
        url: string;
        thumb_url: string | null;
        authors: string | null;
        description: string | null;
        status: string;
        game_engine: string;
        is_nsfw: boolean;
        is_visible: boolean;
        initially_published_at: string | null;
        tags: string | null;
        custom_tags: string | null;
    };
    latestVersion: {
        id: number;
        version: string;
        published_at: string;
        rating: number | null;
        rating_count: number | null;
        devlog: string | null;
        platforms: {
            windows: boolean;
            linux: boolean;
            mac: boolean;
            android: boolean;
            web: boolean;
        };
    } | null;
    englishStats: any;
    supportedLanguages: SupportedLanguage[];
    availableRatings: number[];
    versionCharacterCounts: Record<number, number>;
}

const props = defineProps<Props>();

// State
const showAllRatings = ref(false);
const selectedRating = ref<number | null>(null);
const selectedVersionId = ref<number | null>(null);
const versionsPerPage = ref(5);
const reviewsPerPage = ref(5);
const characterStatsDialog = ref<{ show: () => void } | null>(null);
const fileStatsDialog = ref<{ show: () => void } | null>(null);

// Store setup
const gameDetailStore = useGameDetailStore();
const reviewsStore = useReviewsStore();

// Computed values for store refs
const versions = computed(() => gameDetailStore.versions.value.value);
const loadingVersions = computed(() => gameDetailStore.loadingVersions.value.value);
const characterStats = computed(() => gameDetailStore.characterStats.value.value);
const fileStats = computed(() => gameDetailStore.fileStats.value.value);
const loadingStats = computed(() => gameDetailStore.loadingStats.value.value);

// Reviews computed properties
const reviews = computed(() => reviewsStore.reviews.value);
const loadingReviews = computed(() => reviewsStore.loadingReviews.value);

// Load data on mount
onMounted(() => {
    if (props.game.id) {
        gameDetailStore.loadVersions(props.game.id, versionsPerPage.value);
        loadReviews();
    }
});

// Methods
const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

const toggleRatingsView = () => {
    showAllRatings.value = !showAllRatings.value;
    selectedRating.value = null;
    // Reload reviews when toggling view
    loadReviews();
};

const loadReviews = (page: number = 1) => {
    if (props.game.id) {
        reviewsStore.loadReviews(
            props.game.id,
            reviewsPerPage.value,
            page,
            showAllRatings.value,
            selectedRating.value
        );
    }
};

const getPageFromUrl = (url: string): number => {
    try {
        const parsedUrl = new URL(url);
        const page = parsedUrl.searchParams.get('page');
        return page ? parseInt(page, 10) : 1;
    } catch (e) {
        console.error('Error parsing page from URL:', e);
        return 1;
    }
};

const tags = computed(() => {
    const allTags = [];
    if (props.game.tags) {
        allTags.push(...props.game.tags.split(','));
    }
    if (props.game.custom_tags) {
        allTags.push(...props.game.custom_tags.split(','));
    }
    return allTags.map(tag => tag.trim());
});

const getMetaTitle = computed(() => {
    return `${props.game.name} - FVN.li`;
});

const getMetaDescription = computed(() => {
    const descriptionParts = [];

    if (props.game.is_visible) {
        if (props.game.status) {
            descriptionParts.push(`A ${props.game.status} game`);
        }

        if (props.game.game_engine && props.game.game_engine !== 'unknown') {
            descriptionParts.push(`made with ${props.game.game_engine}`);
        }

        const platforms = [];
        if (props.latestVersion) {
            if (props.latestVersion.platforms.windows) platforms.push('Windows');
            if (props.latestVersion.platforms.linux) platforms.push('Linux');
            if (props.latestVersion.platforms.mac) platforms.push('Mac');
            if (props.latestVersion.platforms.android) platforms.push('Android');
            if (props.latestVersion.platforms.web) platforms.push('Web');
        }
        if (platforms.length) {
            descriptionParts.push('available on ' + platforms.join(', '));
        }

        if (props.englishStats?.words) {
            descriptionParts.push(formatNumber(props.englishStats.words) + ' words long');
        }

        if (props.latestVersion?.rating_count) {
            descriptionParts.push(`rated ${formatNumber(props.latestVersion.rating_count)} times`);
        }
    }

    return descriptionParts.join(', ') + '.';
});

// Watch for changes that should trigger reloads
watch(versionsPerPage, () => {
    gameDetailStore.loadVersions(props.game.id, versionsPerPage.value);
});

watch([reviewsPerPage, selectedRating], () => {
    loadReviews(1); // Reset to first page when changing filters
});

// Add these refs to the script section
const hasLoadedVersions = ref(false);
const hasLoadedReviews = ref(false);

// Modify the watch handlers to update the hasLoaded flags
watch(versions, (newVersions) => {
    console.log('versions', newVersions);
    if (newVersions?.data?.length) {
        hasLoadedVersions.value = true;
        console.log('hasLoadedVersions', hasLoadedVersions.value);
    }
});

watch(reviews, (newReviews) => {
    if (newReviews?.data?.length) {
        hasLoadedReviews.value = true;
    }
});
</script>

<template>
    <Head>
        <title>{{ getMetaTitle }}</title>
        <meta :content="getMetaDescription" name="description" />
    </Head>

    <AppLayout>
        <div class="bg-gray-100 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Back Link -->
                <div class="mb-4 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
                    <Link
                        :href="route('games.index')"
                        class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                    >
                        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M15 19l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                        </svg>
                        Back to Game List
                    </Link>

                    <!-- Section Navigation -->
                    <nav v-if="game.is_visible" class="flex space-x-4">
                        <a
                            href="#details"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                        >
                            Details
                        </a>
                        <a
                            v-if="versions?.data.length"
                            href="#versions"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                        >
                            Versions
                        </a>
                        <a
                            v-if="reviews?.data.length"
                            href="#reviews"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                        >
                            {{ showAllRatings ? 'Ratings' : 'Reviews' }}
                        </a>
                    </nav>
                </div>

                <!-- Game Header -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6">
                    <div class="flex flex-col sm:flex-row gap-6">
                        <div v-if="game.is_visible && game.thumb_url" class="shrink-0">
                            <img
                                :alt="game.name"
                                :src="game.thumb_url"
                                class="max-h-52 max-w-64 rounded-lg object-cover"
                            >
                        </div>

                        <div class="flex-1">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ game.name }}
                                </h1>
                                <a
                                    :href="game.url"
                                    class="text-blue-600 dark:text-blue-400 hover:underline"
                                    target="_blank"
                                >
                                    Visit Game Page
                                </a>
                            </div>

                            <div class="mt-4 sm:mt-2 flex flex-wrap items-center gap-4">
                                <PlatformIcons
                                    v-if="latestVersion"
                                    :platforms="latestVersion.platforms"
                                    :selected-platforms="[]"
                                    :clickable="false"
                                />

                                <span
                                    v-if="game.is_nsfw"
                                    class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200"
                                >
                                    NSFW
                                </span>
                            </div>

                            <div
                                v-if="game.authors"
                                class="mt-4 sm:mt-2 text-gray-600 dark:text-gray-300"
                                v-html="game.authors"
                            />

                            <div
                                v-if="game.description"
                                class="prose dark:prose-invert mt-4 max-w-none text-gray-600 dark:text-gray-300"
                                v-html="game.description"
                            />
                        </div>
                    </div>
                </div>

                <!-- Game Details -->
                <div v-if="game.is_visible" class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Left Column: Basic Info -->
                    <div class="rounded-lg bg-white p-6 shadow-xs dark:bg-gray-800">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Game Details
                        </h2>

                        <dl class="grid grid-cols-1 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-if="game.status">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ game.status }}</dd>
                            </div>

                            <div v-if="game.game_engine">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Engine</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ game.game_engine }}</dd>
                            </div>

                            <div v-if="game.initially_published_at">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Initial Release</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ formatDate(game.initially_published_at) }}</dd>
                            </div>

                            <div v-if="latestVersion">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Latest Update</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ formatDate(latestVersion.published_at) }}</dd>
                            </div>

                            <div v-if="latestVersion">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Current Version</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ latestVersion.version }}</dd>
                            </div>

                            <div v-if="englishStats?.words">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Word Count (English)</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ formatNumber(englishStats.words) }}</dd>
                            </div>

                            <div v-if="latestVersion">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Characters</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ formatNumber(versionCharacterCounts[latestVersion.id]) }}
                                </dd>
                            </div>

                            <div v-if="latestVersion?.rating">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Rating</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ latestVersion.rating.toFixed(1) }}</dd>
                            </div>

                            <div v-if="latestVersion?.rating_count">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Review Count</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ formatNumber(latestVersion.rating_count) }}</dd>
                            </div>
                        </dl>

                        <div v-if="supportedLanguages.length" class="mt-4">
                            <h3 class="mb-2 text-sm text-gray-500 dark:text-gray-400">Supported Languages</h3>
                            <LanguageFlags
                                :languages="supportedLanguages"
                                :selected-languages="[]"
                                :show-labels="false"
                                :clickable="false"
                            />
                        </div>
                    </div>

                    <!-- Right Column: Tags -->
                    <div class="rounded-lg bg-white p-6 shadow-xs dark:bg-gray-800">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Tags
                        </h2>

                        <div v-if="tags.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="tag in tags"
                                :key="tag"
                                class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {{ tag }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Game Versions -->
                <div
                    v-if="hasLoadedVersions"
                    id="versions"
                    class="mb-6 scroll-mt-14 rounded-lg bg-white p-6 shadow-xs dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Version History
                    </h2>

                    <div class="relative">
                        <!-- Loading overlay -->
                        <div
                            v-if="loadingVersions"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm"
                        >
                            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-900 dark:border-gray-100" />
                        </div>

                        <!-- Content - no v-if here -->
                        <div>
                            <div
                                v-for="version in versions?.data || []"
                                :key="version.id"
                                class="my-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div class="flex flex-col gap-4 sm:flex-row">
                                    <div class="flex-1 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                        <div class="w-full flex items-center">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ formatDate(version.published_at) }}
                                            </div>
                                        </div>

                                        <div class="w-full flex items-center">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                Version {{ version.version }}
                                            </div>
                                        </div>

                                        <!-- Languages -->
                                        <div class="w-full flex items-center">
                                            <LanguageFlags
                                                :languages="version.supported_languages.sort((a: SupportedLanguage, b: SupportedLanguage) => a.ref_name.localeCompare(b.ref_name))"
                                                :selected-languages="[]"
                                                :clickable="false"
                                            />
                                        </div>

                                        <!-- Platforms -->
                                        <div class="w-full flex items-center">
                                            <PlatformIcons
                                                :platforms="{
                                                    windows: version.is_windows,
                                                    linux: version.is_linux,
                                                    mac: version.is_mac,
                                                    android: version.is_android,
                                                    web: version.is_web,
                                                }"
                                                :selected-platforms="[]"
                                                :clickable="false"
                                            />
                                        </div>

                                        <!-- Word count -->
                                        <div class="w-full flex items-center whitespace-nowrap text-sm">
                                            <span class="text-gray-500">Words:</span>
                                            <span class="ml-1 text-gray-900 dark:text-gray-100">
                                                {{ version.english_stats?.words ? formatNumber(version.english_stats.words) : '-' }}
                                            </span>
                                        </div>

                                        <!-- Rating -->
                                        <div class="w-full flex items-center whitespace-nowrap text-sm">
                                            <span class="text-gray-500">Rating:</span>
                                            <span class="ml-1 text-gray-900 dark:text-gray-100">
                                                {{ version.rating ? version.rating.toFixed(1) : '-' }}
                                            </span>
                                            <span v-if="version.rating_count" class="ml-1 text-gray-500">
                                                ({{ version.rating_count }})
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 flex gap-4">
                                    <button
                                        v-if="versionCharacterCounts[version.id] > 0"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                        @click="
                                            selectedVersionId = version.id;
                                            gameDetailStore.loadCharacterStats(game.id, version.id);
                                            characterStatsDialog?.show();
                                        "
                                    >
                                        View {{ formatNumber(versionCharacterCounts[version.id]) }} Characters
                                    </button>

                                    <button
                                        v-if="version.file_categories"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                        @click="
                                            selectedVersionId = version.id;
                                            gameDetailStore.loadFileStats(game.id, version.id);
                                            fileStatsDialog?.show();
                                        "
                                    >
                                        View File Stats
                                    </button>
                                </div>
                            </div>

                            <!-- Versions Pagination -->
                            <div v-if="versions?.links" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <select
                                    v-model="versionsPerPage"
                                    class="px-4 py-2 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                    :disabled="loadingVersions"
                                >
                                    <option :value="5">5 per page</option>
                                    <option :value="10">10 per page</option>
                                    <option :value="25">25 per page</option>
                                </select>

                                <div v-if="versions.links?.length > 3" class="flex -mb-1 flex-wrap">
                                    <template v-for="(link, key) in versions.links" :key="key">
                                        <button
                                            v-if="!link.url"
                                            class="mr-1 mb-1 border rounded border-gray-200 px-4 py-3 text-sm leading-4 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600 select-none cursor-default"
                                            v-html="link.label"
                                        />
                                        <button
                                            v-else
                                            :class="[
                                'mr-1 mb-1 border rounded border-gray-200 px-4 py-3 text-sm leading-4 hover:bg-blue-500 hover:text-white dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-blue-500 dark:text-gray-100',
                                { 'bg-blue-500 text-white dark:text-gray-900': link.active }
                            ]"
                                            :disabled="loadingVersions"
                                            @click="gameDetailStore.loadVersions(game.id, versionsPerPage, getPageFromUrl(link.url))"
                                            v-html="link.label"
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div
                    v-if="hasLoadedReviews"
                    id="reviews"
                    class="scroll-mt-14 rounded-lg bg-white p-6 shadow-xs dark:bg-gray-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ showAllRatings ? 'Ratings' : 'Reviews' }}
                            </h2>
                            <select
                                v-if="availableRatings.length"
                                v-model="selectedRating"
                                class="w-40 rounded-lg border-gray-200 bg-white px-4 py-2 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                :disabled="loadingReviews"
                            >
                                <option :value="null">Any Stars</option>
                                <option
                                    v-for="rating in availableRatings"
                                    :key="rating"
                                    :value="rating"
                                >
                                    {{ rating }} Star{{ rating !== 1 ? 's' : '' }}
                                </option>
                            </select>
                        </div>
                        <button
                            class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            :disabled="loadingReviews"
                            @click="toggleRatingsView"
                        >
                            Show {{ showAllRatings ? 'reviews only' : 'all ratings' }}
                        </button>
                    </div>

                    <div class="relative">
                        <!-- Loading overlay -->
                        <div
                            v-if="loadingReviews"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm"
                        >
                            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-900 dark:border-gray-100"/>
                        </div>

                        <!-- Content - no v-if here -->
                        <div>
                            <div class="space-y-6">
                                <div
                                    v-for="review in reviews?.data || []"
                                    :key="review.id"
                                    class="border-b border-gray-200 pb-6 last:border-0 dark:border-gray-700"
                                >
                                <div class="mb-2 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="route('raters.show', { rater: review.rater.id })"
                                            class="font-medium text-gray-900 hover:underline dark:text-gray-100"
                                        >
                                            {{ review.rater.id }}
                                        </Link>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(review.published_at) }}
                            </span>
                                    </div>
                                    <RatingStars :rating="review.rating" />
                                </div>

                                <div
                                    v-if="review.review && (!showAllRatings || review.is_reviewed)"
                                    class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300"
                                    v-html="review.review"
                                />
                            </div>
                        </div>

                            <!-- Reviews Pagination -->
                            <div v-if="reviews?.links" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <select
                                    v-model="reviewsPerPage"
                                    class="px-4 py-2 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                                    :disabled="loadingReviews"
                                >
                                    <option :value="5">5 per page</option>
                                    <option :value="10">10 per page</option>
                                    <option :value="25">25 per page</option>
                                </select>

                                <div v-if="reviews.links?.length > 3" class="flex -mb-1 flex-wrap">
                                    <template v-for="(link, key) in reviews.links" :key="key">
                                        <button
                                            v-if="!link.url"
                                            class="mr-1 mb-1 border rounded border-gray-200 px-4 py-3 text-sm leading-4 text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600 select-none cursor-default"
                                            v-html="link.label"
                                        />
                                        <button
                                            v-else
                                            :class="[
                                'mr-1 mb-1 border rounded border-gray-200 px-4 py-3 text-sm leading-4 hover:bg-blue-500 hover:text-white dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-blue-500 dark:text-gray-100',
                                { 'bg-blue-500 text-white dark:text-gray-900': link.active }
                            ]"
                                            :disabled="loadingReviews"
                                            @click="loadReviews(getPageFromUrl(link.url))"
                                            v-html="link.label"
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialogs -->
        <CharacterStatsDialog
            ref="characterStatsDialog"
            :stats="characterStats"
            :loading="loadingStats"
        />

        <FileStatsDialog
            ref="fileStatsDialog"
            :stats="fileStats"
            :loading="loadingStats"
        />
    </AppLayout>
</template>
