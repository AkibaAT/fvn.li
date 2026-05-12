<script lang="ts">
  import Itchio from '@/components/icons/Itchio.svelte';
  import Steam from '@/components/icons/Steam.svelte';

  let {
    url,
    platform = 'other',
    gameId,
    class: className = '',
  }: {
    url: string;
    platform?: 'itch_io' | 'steam' | 'other';
    gameId: number;
    class?: string;
  } = $props();

  const getPlatformLabel = (p: string) => {
    switch (p) {
      case 'itch_io':
        return 'Visit on itch.io';
      case 'steam':
        return 'Visit on Steam';
      default:
        return 'Visit Game Page';
    }
  };

  const getPlatformColor = (p: string) => {
    switch (p) {
      case 'itch_io':
        return 'text-orange-700 hover:text-orange-800 dark:text-orange-300 dark:hover:text-orange-200';
      case 'steam':
        return 'text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300';
      default:
        return 'text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300';
    }
  };

  const label = $derived(getPlatformLabel(platform));
  const defaultClassName = $derived(
    `inline-flex items-center gap-2 font-medium transition-colors ${getPlatformColor(platform)}`,
  );

  const trackingUrl = $derived(
    (() => {
      try {
        return route('track.external-project', { game_id: gameId, url });
      } catch {
        return url;
      }
    })(),
  );
</script>

<a
  href={trackingUrl}
  target="_blank"
  rel="noopener"
  class={className || defaultClassName}
  title={label}
  aria-label="{label} - opens in new window"
>
  {#if platform === 'itch_io'}
    <Itchio class="h-4 w-4" />
  {:else if platform === 'steam'}
    <Steam class="h-4 w-4" />
  {:else}
    <svg
      class="h-4 w-4"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="10" />
      <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
    </svg>
  {/if}
  <span>{label}</span>
</a>
