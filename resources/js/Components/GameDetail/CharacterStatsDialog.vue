<script setup lang="ts">
import DialogHeader from '@/Components/DialogHeader.vue';
import DialogFooter from '@/Components/DialogFooter.vue';
import {formatNumber} from '@/utils/formatters';
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
    stats: {
        characters: string[];
        languages: Array<{
            id: string;
            name: string;
            flag: string;
        }>;
        wordCounts: Record<string, Record<string, number>>;
        languageTotals: Record<string, number>;
    } | null;
    loading: boolean;
}

const props = defineProps<Props>();

const dialogElement = ref<HTMLDialogElement | null>(null);

const show = () => {
    dialogElement.value?.showModal();
};

const close = () => {
    dialogElement.value?.close();
};

defineExpose({ show, close });
</script>

<template>
    <dialog
        ref="dialogElement"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl min-w-80 max-w-6xl dark:text-gray-100 backdrop:backdrop-blur-md"
        @click.self="close"
    >
        <DialogHeader title="Character Statistics"/>

        <div v-if="loading" class="flex items-center justify-center p-4">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-900 dark:border-gray-100"/>
        </div>

        <div v-else-if="stats" class="overflow-x-auto max-w-[calc(100vw-3rem)] -mx-6 px-6">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 px-3 font-medium">Character</th>
                    <th
                        v-for="lang in stats.languages"
                        :key="lang.id"
                        class="text-right py-2 px-3 font-medium"
                    >
                        <div class="flex items-center justify-end gap-2">
                            <span :class="['fi', `fi-${lang.flag}`, 'rounded-xs']"/>
                            <span>{{ lang.name }}</span>
                        </div>
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr
                    v-for="character in stats.characters"
                    :key="character"
                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                    <td class="py-2 px-3">{{ character }}</td>
                    <td
                        v-for="lang in stats.languages"
                        :key="lang.id"
                        class="py-2 px-3 text-right tabular-nums"
                    >
                        {{
                            stats.wordCounts[character]?.[lang.id]
                                ? formatNumber(stats.wordCounts[character][lang.id])
                                : '-'
                        }}
                    </td>
                </tr>
                </tbody>
                <tfoot class="border-t border-gray-200 dark:border-gray-700 font-medium">
                <tr>
                    <td class="py-2 px-3">Total</td>
                    <td
                        v-for="lang in stats.languages"
                        :key="lang.id"
                        class="py-2 px-3 text-right tabular-nums"
                    >
                        {{ formatNumber(stats.languageTotals[lang.id] ?? 0) }}
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>

        <DialogFooter/>
    </dialog>
</template>
