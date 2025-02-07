<script lang="ts" setup>
interface Props {
    type: 'platform' | 'status' | 'engine' | 'language' | 'sort' | 'nsfw' | 'sfw';
    label: string;
    value?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'remove', type: string, value?: string): void; // Corrected emit definition.
}>();

const typeClasses: Record<Props['type'], string> = {
    platform: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    status: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    engine: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    language: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
    sort: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    nsfw: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
    sfw: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
};

const getTypeClass = (type: Props['type']) => typeClasses[type];
</script>

<template>
    <button
        :class="[
    'inline-flex items-center px-3 py-1 rounded-full text-sm',
    getTypeClass(type)
    ]"
        @click="emit('remove', type, value)"
    >
        <template v-if="type === 'language' && value">
            <span :class="['fi', `fi-${value}`, 'rounded-xs mr-2']"/>
        </template>
        {{ label }}
        <span class="ml-2">×</span>
    </button>
</template>
