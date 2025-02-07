<script lang="ts" setup>
interface Props {
    platforms: Record<string, boolean>;
    selectedPlatforms: string[];
    clickable?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'toggle', platform: string): void;
}>();

const platformConfig = {
    windows: {icon: 'fa-brands fa-windows', color: 'text-[#00A4EF]'},
    linux: {icon: 'fa-brands fa-linux', color: 'dark:text-[#F0B90B]'},
    mac: {icon: 'fa-brands fa-apple', color: 'text-[#555555] dark:text-gray-300'},
    android: {icon: 'fa-brands fa-android', color: 'text-[#3DDC84]'},
    web: {icon: 'fa-solid fa-globe', color: 'text-[#4285F4]'}
};
</script>

<template>
    <div class="flex gap-2 text-lg">
        <template v-for="(config, platform) in platformConfig" :key="platform">
            <template v-if="platforms[platform]">
                <button v-if="clickable !== false"
                        :class="[
                  'px-1 rounded-sm',
                  { 'ring-2 ring-blue-500 dark:ring-blue-400': selectedPlatforms.includes(platform) }
                ]"
                        :title="'Filter by ' + platform"
                        @click="emit('toggle', platform)">
                    <i :class="[config.icon, config.color, 'hover:opacity-50']"/>
                </button>
                <i v-else :class="[config.icon, config.color]" :title="platform"/>
            </template>
        </template>
    </div>
</template>
