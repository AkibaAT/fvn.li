<script lang="ts" setup>
interface Language {
  iso_code: string;
  ref_name: string;
  flag_code: string;
}

interface Props {
  languages: Language[];
  selectedLanguages: string[];
  showLabels?: boolean;
  clickable?: boolean; // Make sure clickable is in the props
}

const props = withDefaults(defineProps<Props>(), {
  showLabels: false,
  clickable: false,
});

const emit = defineEmits<{
  (e: 'toggle', language: string): void;
}>();
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <template v-for="language in languages" :key="language.iso_code">
      <button v-if="clickable"
              :class="[
                'inline-flex items-center gap-1 px-1 py-0.5 rounded-sm transition-all duration-150',
                'hover:bg-gray-100 dark:hover:bg-gray-700',
                { 'ring-2 ring-blue-500 dark:ring-blue-400': selectedLanguages.includes(language.iso_code) }
              ]"
              :title="language.ref_name"
              @click="emit('toggle', language.iso_code)"
      >
        <span :class="[
          'fi',
          `fi-${language.flag_code}`,
          'rounded-xs',
          selectedLanguages.includes(language.iso_code) ? 'opacity-100 scale-110' : 'opacity-80'
        ]"/>
        <span v-if="showLabels" class="text-xs">{{ language.ref_name }}</span>
      </button>

      <div v-else
           :title="language.ref_name"
           class="inline-flex items-center gap-1 px-1 py-0.5">
        <span :class="['fi', `fi-${language.flag_code}`, 'rounded-xs opacity-80']"/>
        <span v-if="showLabels" class="text-xs">{{ language.ref_name }}</span>
      </div>
    </template>
  </div>
</template>
