<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { previewEmbed } from '@/api/discord';
    import { toast } from '@/utils/toast';

    interface Props {
        template: Record<string, unknown>;
        notificationType: string;
        serverId: number;
        onchange: (template: Record<string, unknown>) => void;
    }

    let { template, notificationType, serverId, onchange }: Props = $props();

    const uid = $props.id();

    let jsonMode = $state(false);
    let jsonText = $state('');
    let previewData = $state<Record<string, unknown> | null>(null);
    let previewLoading = $state(false);
    let showVariableMenu = $state(false);
    let previewError = $state<string | null>(null);
    let previewInitialized = $state(false);
    let previewRequestSeq = 0;
    let previewDebounceTimer: ReturnType<typeof setTimeout> | null = null;

    const variables = [
        { token: '{game.name}', label: 'Game Name', group: 'Game' },
        { token: '{game.slug}', label: 'Game Slug', group: 'Game' },
        { token: '{game.url}', label: 'Game URL', group: 'Game' },
        { token: '{game.description}', label: 'Game Description', group: 'Game' },
        { token: '{game.status}', label: 'Game Status', group: 'Game' },
        { token: '{game.thumbnail}', label: 'Game Thumbnail', group: 'Game' },
        { token: '{game.screenshot}', label: 'Game Screenshot', group: 'Game' },
        { token: '{game.rating}', label: 'Game Rating', group: 'Game' },
        { token: '{game.rating_count}', label: 'Rating Count', group: 'Game' },
        { token: '{game.developer}', label: 'Developer', group: 'Game' },
        { token: '{game.engine}', label: 'Game Engine', group: 'Game' },
        { token: '{game.platform}', label: 'Platform', group: 'Game' },
        { token: '{game.platforms}', label: 'All Platforms', group: 'Game' },
        { token: '{game.tags}', label: 'Tags', group: 'Game' },
        { token: '{game.nsfw_label}', label: 'NSFW Label', group: 'Game' },
        { token: '{game.price}', label: 'Price', group: 'Game' },
        { token: '{game.word_count}', label: 'Word Count', group: 'Game' },
        { token: '{game.language}', label: 'Source Language', group: 'Game' },
        { token: '{version.name}', label: 'Version Name', group: 'Version' },
        { token: '{version.published_at}', label: 'Published Date', group: 'Version' },
        { token: '{version.published_at_discord}', label: 'Published (Discord)', group: 'Version' },
        { token: '{version.published_at_iso}', label: 'Published (ISO)', group: 'Version' },
        { token: '{version.devlog_url}', label: 'Devlog URL', group: 'Version' },
        { token: '{version.word_count_diff}', label: 'Word Count Diff', group: 'Version' },
        { token: '{notification.type}', label: 'Notification Type', group: 'Notification' },
        { token: '{server.name}', label: 'Server Name', group: 'Server' },
    ];

    const presets: Record<string, Record<string, unknown>> = {
        'Default New Game': {
            title: '{game.name}',
            url: '{game.url}',
            description: '{game.description}',
            color: 5763719,
            fields: [
                { name: 'Status', value: '{game.status}', inline: true },
                { name: 'Developer', value: '{game.developer}', inline: true },
                { name: 'Price', value: '{game.price}', inline: true },
            ],
            thumbnail: { url: '{game.thumbnail}' },
            footer: { text: 'New on fvn.li', icon_url: 'https://fvn.li/favicon.ico' },
            timestamp: '{version.published_at_iso}',
        },
        'Default Update': {
            title: '{game.name}',
            url: '{game.url}',
            description: 'Version **{version.name}** has been released!',
            color: 5793266,
            fields: [
                { name: 'Version', value: '{version.name}', inline: true },
                { name: 'Released', value: '{version.published_at_discord}', inline: true },
            ],
            thumbnail: { url: '{game.thumbnail}' },
            footer: { text: 'fvn.li', icon_url: 'https://fvn.li/favicon.ico' },
            timestamp: '{version.published_at_iso}',
        },
        Minimal: {
            title: '{game.name}',
            url: '{game.url}',
            color: 5763719,
        },
        Detailed: {
            title: '{game.name}',
            url: '{game.url}',
            description: '{game.description}',
            color: 5793266,
            fields: [
                { name: 'Status', value: '{game.status}', inline: true },
                { name: 'Developer', value: '{game.developer}', inline: true },
                { name: 'Price', value: '{game.price}', inline: true },
                { name: 'Engine', value: '{game.engine}', inline: true },
                { name: 'Language', value: '{game.language}', inline: true },
                { name: 'Tags', value: '{game.tags}', inline: false },
            ],
            thumbnail: { url: '{game.thumbnail}' },
            image: { url: '{game.screenshot}' },
            footer: { text: '{notification.type} - fvn.li', icon_url: 'https://fvn.li/favicon.ico' },
            timestamp: '{version.published_at_iso}',
        },
    };

    interface EmbedField {
        name: string;
        value: string;
        inline: boolean;
    }

    function stringField(obj: Record<string, unknown>, path: string): string {
        const parts = path.split('.');
        let current: unknown = obj;
        for (const part of parts) {
            if (current && typeof current === 'object') {
                current = (current as Record<string, unknown>)[part];
            } else {
                return '';
            }
        }
        if (typeof current === 'number') return current.toString();
        return typeof current === 'string' ? current : '';
    }

    function arrayField(obj: Record<string, unknown>, key: string): EmbedField[] {
        const val = obj[key];
        return Array.isArray(val) ? (val as EmbedField[]) : [];
    }

    function colorIntToHex(c: unknown): string {
        if (typeof c === 'number') return '#' + c.toString(16).padStart(6, '0');
        if (typeof c === 'string' && c.startsWith('#')) return c;
        return '#5865F2';
    }

    let title = $state('');
    let desc = $state('');
    let tmplUrl = $state('');
    let tmplColor = $state('#5865F2');
    let thumbnailUrl = $state('');
    let imageUrl = $state('');
    let footerText = $state('');
    let footerIconUrl = $state('');
    let fields = $state<EmbedField[]>([]);

    $effect(() => {
        title = stringField(template, 'title');
        desc = stringField(template, 'description');
        tmplUrl = stringField(template, 'url');
        tmplColor = colorIntToHex(template.color);
        thumbnailUrl = stringField(template, 'thumbnail.url');
        imageUrl = stringField(template, 'image.url');
        footerText = stringField(template, 'footer.text');
        footerIconUrl = stringField(template, 'footer.icon_url');
        fields = arrayField(template, 'fields');
    });

    function buildTemplate(): Record<string, unknown> {
        const result: Record<string, unknown> = { ...template };
        if (title) result.title = title;
        else delete result.title;
        if (tmplUrl) result.url = tmplUrl;
        else delete result.url;
        if (desc) result.description = desc;
        else delete result.description;
        const colorNum = tmplColor.startsWith('#') ? parseInt(tmplColor.slice(1), 16) : parseInt(tmplColor, 10);
        if (!isNaN(colorNum)) result.color = colorNum;
        else delete result.color;
        if (thumbnailUrl) result.thumbnail = { url: thumbnailUrl };
        else delete result.thumbnail;
        if (imageUrl) result.image = { url: imageUrl };
        else delete result.image;
        if (footerText || footerIconUrl) {
            const footer: Record<string, unknown> = {};
            if (footerText) footer.text = footerText;
            if (footerIconUrl) footer.icon_url = footerIconUrl;
            result.footer = footer;
        } else delete result.footer;
        const validFields = fields.filter((f) => f.name || f.value);
        if (validFields.length > 0) {
            result.fields = validFields.map((f) => ({
                name: f.name || '\u200b',
                value: f.value || '\u200b',
                inline: f.inline ?? false,
            }));
        } else delete result.fields;
        return result;
    }

    function applyUpdate(fn: () => void) {
        fn();
        onchange(buildTemplate());
    }

    function addField() {
        applyUpdate(() => {
            fields = [...fields, { name: '', value: '', inline: false }];
        });
    }

    function removeField(index: number) {
        applyUpdate(() => {
            fields = fields.filter((_, i) => i !== index);
        });
    }

    function updateField(index: number, key: string, value: unknown) {
        applyUpdate(() => {
            fields = fields.map((f, i) => (i === index ? { ...f, [key]: value } : f));
        });
    }

    function moveField(index: number, direction: -1 | 1) {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= fields.length) return;
        applyUpdate(() => {
            const updated = [...fields];
            const temp = updated[index];
            updated[index] = updated[newIndex];
            updated[newIndex] = temp;
            fields = updated;
        });
    }

    function applyPreset(name: string) {
        const preset = presets[name];
        if (!preset) return;
        onchange({ ...preset });
        toast.info(`Applied "${name}" preset`);
    }

    async function requestPreview() {
        const requestId = ++previewRequestSeq;
        previewLoading = true;
        previewError = null;

        try {
            const embed = await previewEmbed(serverId, template, notificationType);

            if (requestId !== previewRequestSeq) {
                return;
            }

            previewData = embed;
        } catch (e) {
            if (requestId !== previewRequestSeq) {
                return;
            }

            previewData = null;
            previewError = e instanceof Error ? e.message : 'Preview failed';
        } finally {
            if (requestId === previewRequestSeq) {
                previewLoading = false;
            }
        }
    }

    async function previewWithGame() {
        await requestPreview();

        if (previewError) {
            toast.error(previewError);
        }
    }

    function toggleJsonMode() {
        if (!jsonMode) {
            jsonText = JSON.stringify(template, null, 2);
        } else {
            try {
                onchange(JSON.parse(jsonText));
            } catch {
                toast.error('Invalid JSON');
                return;
            }
        }
        jsonMode = !jsonMode;
    }

    const groupedVariables = $derived(
        variables.reduce<Record<string, typeof variables>>((acc, v) => {
            (acc[v.group] ??= []).push(v);
            return acc;
        }, {}),
    );

    $effect(() => {
        void template;
        void notificationType;
        void serverId;

        if (previewDebounceTimer) {
            clearTimeout(previewDebounceTimer);
        }

        previewDebounceTimer = setTimeout(() => {
            previewInitialized = true;
            void requestPreview();
        }, 300);

        return () => {
            if (previewDebounceTimer) {
                clearTimeout(previewDebounceTimer);
                previewDebounceTimer = null;
            }
        };
    });

    const previewSrc = $derived(previewData ?? {});
    let previewTitle = $derived(stringField(previewSrc, 'title') || 'Embed Preview');
    let previewDesc = $derived(stringField(previewSrc, 'description'));
    let previewColor = $derived(colorIntToHex(previewSrc.color));
    let previewFields = $derived(arrayField(previewSrc, 'fields'));
    let previewThumbnail = $derived(stringField(previewSrc, 'thumbnail.url'));
    let previewImage = $derived(stringField(previewSrc, 'image.url'));
    let previewFooterText = $derived(stringField(previewSrc, 'footer.text'));
    let previewFooterIcon = $derived(stringField(previewSrc, 'footer.icon_url'));
</script>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex gap-2">
                <span class="text-sm font-medium text-gray-500 capitalize dark:text-gray-400">{notificationType.replace('_', ' ')} Embed</span>
                <button
                    onclick={toggleJsonMode}
                    class="rounded px-2 py-0.5 text-xs font-medium transition-colors {jsonMode
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600'}"
                >
                    {jsonMode ? 'Visual' : 'JSON'}
                </button>
            </div>
            <div class="flex gap-2">
                <div class="relative">
                    <button
                        onclick={() => (showVariableMenu = !showVariableMenu)}
                        class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                    >
                        Insert Variable
                    </button>
                    {#if showVariableMenu}
                        <div
                            class="absolute right-0 z-50 mt-1 max-h-64 w-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                        >
                            {#each Object.entries(groupedVariables) as [group, vars] (group)}
                                <div
                                    class="border-b border-gray-100 px-3 py-1 text-xs font-semibold text-gray-500 last:border-0 dark:border-gray-700 dark:text-gray-400"
                                >
                                    {group}
                                </div>
                                {#each vars as v (v.token)}
                                    <button
                                        onclick={() => {
                                            showVariableMenu = false;
                                        }}
                                        class="flex w-full items-center justify-between px-3 py-1.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-700"
                                        title="Click to copy: {v.token}"
                                    >
                                        <span class="text-gray-700 dark:text-gray-300">{v.label}</span>
                                        <code class="rounded bg-gray-100 px-1 text-gray-500 dark:bg-gray-700 dark:text-gray-400">{v.token}</code>
                                    </button>
                                {/each}
                            {/each}
                        </div>
                    {/if}
                </div>
                <button
                    onclick={previewWithGame}
                    disabled={previewLoading}
                    class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200 disabled:opacity-50 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50"
                >
                    {previewLoading ? 'Loading...' : 'Preview with Game'}
                </button>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">Presets:</span>
            {#each Object.keys(presets) as name (name)}
                <button
                    onclick={() => applyPreset(name)}
                    class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                >
                    {name}
                </button>
            {/each}
        </div>

        {#if jsonMode}
            <textarea
                bind:value={jsonText}
                rows={16}
                class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                spellcheck="false"></textarea>
        {:else}
            <div class="space-y-3">
                <div>
                    <label for="{uid}-title" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <input
                        id="{uid}-title"
                        type="text"
                        value={title}
                        oninput={(e) =>
                            applyUpdate(() => {
                                title = (e.target as HTMLInputElement).value;
                            })}
                        placeholder={'{game.name}'}
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label for="{uid}-url" class="block text-xs font-medium text-gray-700 dark:text-gray-300">URL</label>
                    <input
                        id="{uid}-url"
                        type="text"
                        value={tmplUrl}
                        oninput={(e) =>
                            applyUpdate(() => {
                                tmplUrl = (e.target as HTMLInputElement).value;
                            })}
                        placeholder={'{game.url}'}
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label for="{uid}-desc" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea
                        id="{uid}-desc"
                        value={desc}
                        oninput={(e) =>
                            applyUpdate(() => {
                                desc = (e.target as HTMLTextAreaElement).value;
                            })}
                        rows={3}
                        placeholder={'{game.description}'}
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    ></textarea>
                </div>
                <div>
                    <label for="{uid}-color" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Color</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            id="{uid}-color"
                            type="color"
                            value={colorIntToHex(tmplColor)}
                            oninput={(e) =>
                                applyUpdate(() => {
                                    tmplColor = (e.target as HTMLInputElement).value;
                                })}
                            class="h-8 w-8 cursor-pointer rounded border-0"
                        />
                        <input
                            type="text"
                            value={tmplColor}
                            oninput={(e) =>
                                applyUpdate(() => {
                                    tmplColor = (e.target as HTMLInputElement).value;
                                })}
                            aria-label="Color value"
                            placeholder="#5865F2 or 5763719"
                            class="flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>
                <div>
                    <label for="{uid}-thumbnail" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Thumbnail URL</label>
                    <input
                        id="{uid}-thumbnail"
                        type="text"
                        value={thumbnailUrl}
                        oninput={(e) =>
                            applyUpdate(() => {
                                thumbnailUrl = (e.target as HTMLInputElement).value;
                            })}
                        placeholder={'{game.thumbnail}'}
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label for="{uid}-image" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Image URL</label>
                    <input
                        id="{uid}-image"
                        type="text"
                        value={imageUrl}
                        oninput={(e) =>
                            applyUpdate(() => {
                                imageUrl = (e.target as HTMLInputElement).value;
                            })}
                        placeholder={'{game.screenshot}'}
                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <span id="{uid}-footer" class="mb-2 block text-xs font-medium text-gray-700 dark:text-gray-300">Footer</span>
                    <div class="grid grid-cols-2 gap-2" role="group" aria-labelledby="{uid}-footer">
                        <input
                            type="text"
                            value={footerText}
                            aria-label="Footer text"
                            oninput={(e) =>
                                applyUpdate(() => {
                                    footerText = (e.target as HTMLInputElement).value;
                                })}
                            placeholder="Footer text"
                            class="rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <input
                            type="text"
                            value={footerIconUrl}
                            aria-label="Footer icon URL"
                            oninput={(e) =>
                                applyUpdate(() => {
                                    footerIconUrl = (e.target as HTMLInputElement).value;
                                })}
                            placeholder="Footer icon URL"
                            class="rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Fields</span>
                        <button onclick={addField} class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                            >+ Add Field</button
                        >
                    </div>
                    <div class="space-y-2">
                        {#each fields as field, i (i)}
                            <div class="flex items-start gap-2 rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <input
                                        type="text"
                                        value={field.name}
                                        aria-label="Field {i + 1} name"
                                        placeholder="Field name"
                                        oninput={(e) => updateField(i, 'name', (e.target as HTMLInputElement).value)}
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    <input
                                        type="text"
                                        value={field.value}
                                        aria-label="Field {i + 1} value"
                                        placeholder="Field value"
                                        oninput={(e) => updateField(i, 'value', (e.target as HTMLInputElement).value)}
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                    <input
                                        type="checkbox"
                                        checked={field.inline}
                                        onchange={() => updateField(i, 'inline', !field.inline)}
                                        class="rounded text-indigo-600"
                                    />
                                    Inline
                                </label>
                                <div class="flex flex-col gap-0.5">
                                    <button
                                        onclick={() => moveField(i, -1)}
                                        disabled={i === 0}
                                        aria-label="Move field {i + 1} up"
                                        class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                    >
                                        <ChevronDownIcon class="h-3 w-3 rotate-180" />
                                    </button>
                                    <button
                                        onclick={() => moveField(i, 1)}
                                        disabled={i === fields.length - 1}
                                        aria-label="Move field {i + 1} down"
                                        class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                    >
                                        <ChevronDownIcon class="h-3 w-3" />
                                    </button>
                                </div>
                                <button
                                    onclick={() => removeField(i)}
                                    aria-label="Remove field {i + 1}"
                                    class="rounded p-0.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                >
                                    <XMarkIcon class="h-4 w-4" />
                                </button>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <div>
        <div class="sticky top-6">
            <h3 class="mb-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Live Preview</h3>
            <div class="rounded-lg border-l-4 bg-[#2b2d31] p-4 shadow-xl" style="border-left-color: {previewColor}">
                {#if previewLoading && !previewData}
                    <div class="text-sm text-[#dbdee1]">Rendering preview...</div>
                {:else if previewError && !previewData}
                    <div class="text-sm text-red-300">{previewError}</div>
                {:else}
                    {#if previewTitle}
                        <div class="mb-1">
                            {#if stringField(previewSrc, 'url')}
                                <a
                                    href={stringField(previewSrc, 'url')}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-[#00aff4] hover:underline">{previewTitle}</a
                                >
                            {:else}
                                <span class="font-semibold text-white">{previewTitle}</span>
                            {/if}
                        </div>
                    {/if}
                    {#if previewDesc}
                        <p class="mb-3 text-sm whitespace-pre-wrap text-[#dbdee1]">{previewDesc}</p>
                    {/if}
                    {#if previewFields && previewFields.length > 0}
                        <div class="mb-3 grid gap-x-4 {previewFields.some((f) => f.inline) ? 'grid-cols-3' : 'grid-cols-1'}">
                            {#each previewFields as field (field.name + '|' + field.value)}
                                <div class={field.inline ? '' : 'col-span-3'}>
                                    <div class="font-semibold text-white">{field.name || '\u200b'}</div>
                                    <div class="text-sm text-[#dbdee1]">{field.value || '\u200b'}</div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                    {#if previewImage}
                        <div class="mb-3 overflow-hidden rounded">
                            <img src={previewImage} alt="Embed preview" class="max-h-72 w-auto rounded" onerror={() => {}} />
                        </div>
                    {/if}
                    {#if previewFooterText}
                        <div class="flex items-center gap-2 text-xs text-[#dbdee1]">
                            {#if previewFooterIcon}
                                <div class="h-4 w-4 shrink-0 overflow-hidden rounded-full bg-gray-600">
                                    <img src={previewFooterIcon} alt="" class="h-4 w-4 object-cover" onerror={() => {}} />
                                </div>
                            {/if}
                            <span>{previewFooterText}</span>
                            <span class="text-[#949ba4]">&bull; Today at 12:00</span>
                        </div>
                    {/if}
                    {#if previewThumbnail && !previewImage}
                        <div class="mt-2 flex justify-end">
                            <img src={previewThumbnail} alt="Thumbnail" class="h-16 w-16 rounded object-cover" onerror={() => {}} />
                        </div>
                    {/if}
                {/if}
            </div>
            {#if previewLoading && previewData}
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Refreshing preview...</div>
            {:else if previewInitialized && !previewError}
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Preview is rendered with the same backend limits used for live Discord sends.
                </div>
            {/if}
        </div>
    </div>
</div>
