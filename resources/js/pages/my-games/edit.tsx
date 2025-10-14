import DragHandle from '@/components/drag-drop/drag-handle';
import GameStats from '@/components/game-stats';
import SortableList, {DragHandleProps} from '@/components/drag-drop/sortable-list';
import {notify} from '@/components/toast';
import {FormError} from '@/components/form-elements';
import {authenticatedFetch} from '@/utils/csrf';
import {Head, Link, useForm} from '@inertiajs/react';
import React, {useMemo, useState} from 'react';
import {formatLocalDateTime} from '@/utils/date-formatting';

interface GameLink {
    id?: string;
    name: string;
    url: string;
    platform?: string | null;
    sort_order?: number;
    last_edited_at?: string;
    release_at?: string | null;
}

interface GamePayload {
    id: number;
    name: string;
    slug: string;
    additional_links?: GameLink[];
    thumb_url?: string | null;
    screenshots?: Array<{
        url: string;
        width?: number;
        height?: number;
        optimized?: Record<string, { path: string; width: number; height: number }>;
    }>;
    custom_screenshots?: Array<{
        url: string;
        width?: number;
        height?: number;
        optimized?: Record<string, { path: string; width: number; height: number }>;
    }>;
    optimized_thumbnails?: Record<string, { path: string; width: number; height: number }>;
}

interface DailyStats {
    date: string;
    page_views_unique: number;
    page_views_total: number;
    external_project_unique: number;
    external_project_total: number;
    custom_links_unique: number;
    custom_links_total: number;
}


interface ClickStats {
    page_views_total: number;
    page_views_unique: number;
    last_page_view?: string;
    external_project_total: number;
    external_project_unique: number;
    last_external_project?: string;
    custom_links?: Array<{
        link_id: string;
        link_name: string;
        total_clicks: number;
        unique_clicks: number;
        last_click?: string;
    }>;
}

interface Props {
    game: GamePayload;
    platforms: string[];
    clickStats?: ClickStats;
    dailyStats?: DailyStats[];
    metaTags?: { title?: string };
}

interface ApiError {
    message?: string;
    errors?: Record<string, string[]>;
}

interface FormErrors {
    [key: string]: string | undefined;
}


const LinkRow = React.memo(function LinkRow({
                                                link,
                                                index,
                                                platforms,
                                                onChange,
                                                onRemove,
                                                errors,
                                                disabled,
                                                dragHandleProps,
                                            }: {
    link: GameLink;
    index: number;
    platforms: string[];
    onChange: (idx: number, next: GameLink) => void;
    onRemove: (idx: number) => void;
    errors?: { name?: string; url?: string; platform?: string; release_at?: string };
    disabled?: boolean;
    dragHandleProps?: DragHandleProps;
}) {
    // State always contains local datetime format (YYYY-MM-DDTHH:mm)
    // Conversion from UTC happens on initial load in useState
    const localReleaseAt = link.release_at || '';

    return (
        <div
            className="grid grid-cols-12 items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition-all hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700">
            <div className="col-span-3">
                <input
                    value={link.name}
                    onChange={(e) =>
                        onChange(index, {...link, name: e.target.value})
                    }
                    placeholder="Link name (e.g. Windows download)"
                    className={`w-full rounded-lg border bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 dark:bg-gray-700 dark:text-white ${
                        errors?.name
                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-gray-500 dark:focus:border-blue-400'
                    }`}
                    disabled={disabled}
                />
                {errors?.name && (
                    <FormError error={errors.name}/>
                )}
            </div>
            <div className="col-span-6">
                <input
                    value={link.url}
                    onChange={(e) =>
                        onChange(index, {...link, url: e.target.value})
                    }
                    placeholder="https://..."
                    className={`w-full rounded-lg border bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 dark:bg-gray-700 dark:text-white ${
                        errors?.url
                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-gray-500 dark:focus:border-blue-400'
                    }`}
                    disabled={disabled}
                />
                {errors?.url && (
                    <FormError error={errors.url}/>
                )}
            </div>
            <div className="col-span-2">
                <select
                    value={link.platform ?? ''}
                    onChange={(e) =>
                        onChange(index, {
                            ...link,
                            platform: e.target.value || null,
                        })
                    }
                    className={`w-full rounded-lg border bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 dark:bg-gray-700 dark:text-white ${
                        errors?.platform
                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-gray-500 dark:focus:border-blue-400'
                    }`}
                    disabled={disabled}
                >
                    <option value="">Platform</option>
                    {platforms.map((p) => (
                        <option key={p} value={p}>
                            {p}
                        </option>
                    ))}
                </select>
                {errors?.platform && (
                    <FormError error={errors.platform}/>
                )}
            </div>
            <div className="col-span-1 flex items-center justify-end gap-1">
                <DragHandle
                    dragHandleProps={dragHandleProps}
                    disabled={disabled}
                />
                <button
                    onClick={() => onRemove(index)}
                    className="rounded-lg bg-red-600 p-2 text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    aria-label="Remove link"
                    disabled={disabled}
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {/* Release date field */}
            <div className="col-span-12 mt-2">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Release Date & Time <span className="text-gray-500">(Optional)</span>
                </label>
                <input
                    type="datetime-local"
                    value={localReleaseAt}
                    onChange={(e) => {
                        // Store the local datetime string as-is
                        // Backend will handle conversion to UTC using timezone_offset
                        onChange(index, {...link, release_at: e.target.value || null});
                    }}
                    className={`w-auto rounded-lg border bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 dark:bg-gray-700 dark:text-white ${
                        errors?.release_at
                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-gray-500 dark:focus:border-blue-400'
                    }`}
                    disabled={disabled}
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Leave empty to make the download available immediately. The download will automatically appear when the release time is reached.
                </p>
                {errors?.release_at && (
                    <FormError error={errors.release_at}/>
                )}
            </div>

            {/* Last edited timestamp */}
            {link.last_edited_at && (
                <div className="col-span-12 mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Last edited: {formatLocalDateTime(link.last_edited_at)}
                </div>
            )}
        </div>
    );
});

export default function MyGamesEdit({
                                        game,
                                        platforms,
                                        clickStats,
                                        dailyStats,
                                        metaTags,
                                    }: Props) {
    // Convert UTC datetimes to local format on initial load
    const [links, setLinks] = useState<GameLink[]>(() => {
        const initialLinks = Array.isArray(game.additional_links) ? [...game.additional_links] : [];
        return initialLinks.map(link => {
            if (!link.release_at) return link;

            // Convert UTC to local format for datetime-local input
            const utcDate = new Date(link.release_at);
            const year = utcDate.getFullYear();
            const month = String(utcDate.getMonth() + 1).padStart(2, '0');
            const day = String(utcDate.getDate()).padStart(2, '0');
            const hours = String(utcDate.getHours()).padStart(2, '0');
            const minutes = String(utcDate.getMinutes()).padStart(2, '0');

            return {
                ...link,
                release_at: `${year}-${month}-${day}T${hours}:${minutes}`
            };
        });
    });

    const form = useForm({});
    const {processing, errors, clearErrors} = form;
    const setFormError = form.setError as (key: string, value?: string) => void;
    const [saving, setSaving] = useState(false);


    // Handle reordering of links with immediate save
    const handleReorder = async (reorderedLinks: GameLink[]) => {
        const originalLinks = [...links]; // Store original order for rollback

        // Update local state immediately for responsive UI (optimistic update)
        setLinks(reorderedLinks);

        // Get timezone offset in hours
        const timezoneOffset = -new Date().getTimezoneOffset() / 60;

        // Save in background without additional state changes
        authenticatedFetch(
            route('react-api.my-games.update', {game: game.slug}),
            {
                method: 'PUT',
                body: JSON.stringify({
                    links: reorderedLinks,
                    timezone_offset: timezoneOffset,
                }),
            },
        ).then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                // Only update state on error (rollback)
                setLinks(originalLinks);
                notify(data?.message || 'Failed to save link order', 'error');
                return;
            }

            // Success - no state update needed since optimistic update was correct
            // Only show notification
            notify('Link order updated', 'success');
        }).catch((e: unknown) => {
            // Only update state on error (rollback)
            setLinks(originalLinks);
            const errorMessage = e instanceof Error ? e.message : 'Failed to save link order';
            notify(errorMessage, 'error');
        });
    };

    const addLink = () => {
        setLinks((prev) => [
            ...prev,
            {
                id: undefined,
                name: '',
                url: '',
                platform: null,
                release_at: null,
            },
        ]);
    };

    const updateLink = (idx: number, next: GameLink) => {
        setLinks((prev) => prev.map((l, i) => (i === idx ? next : l)));
    };

    const removeLink = (idx: number) => {
        setLinks((prev) => prev.filter((_, i) => i !== idx));
    };

    const sortedLinks = useMemo(
        () =>
            links.map((l, i) => ({
                ...l,
                sort_order: i,
            })),
        [links],
    );

    // Helper: map Laravel validation errors into useForm errors keys and inline mapping
    const parseAndSetErrors = (data: unknown) => {
        // Clear previous
        clearErrors();
        let anySet = false;
        const maybe = data as ApiError;
        if (maybe?.errors && typeof maybe.errors === 'object') {
            // errors may look like { 'links.0.name': ['The name field is required.'], ... }
            Object.entries(maybe.errors as Record<string, unknown>).forEach(
                ([key, val]) => {
                    const msg = Array.isArray(val)
                        ? String(val[0])
                        : String(val);
                    setFormError(key, msg);
                    anySet = true;
                },
            );
        }
        if (!anySet) {
            const message =
                maybe && typeof maybe === 'object' && 'message' in maybe
                    ? String(maybe.message)
                    : 'Failed to save';
            setFormError('links', message);
        }
    };

    const save = async () => {
        clearErrors();
        setSaving(true);
        try {
            // Get timezone offset in hours
            const timezoneOffset = -new Date().getTimezoneOffset() / 60;

            const res = await authenticatedFetch(
                route('react-api.my-games.update', {game: game.slug}),
                {
                    method: 'PUT',
                    body: JSON.stringify({
                        links: sortedLinks,
                        timezone_offset: timezoneOffset,
                    }),
                },
            );
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                parseAndSetErrors(data);
                notify(data?.message || 'Failed to save changes', 'error');
                return;
            }
            // Don't replace state with backend data - keep local datetime values as-is
            // The backend returns UTC datetimes, but we want to keep the local format in state
            notify('Changes saved successfully', 'success');
        } catch (e: unknown) {
            const errorMessage =
                e instanceof Error ? e.message : 'Request failed';
            setFormError('links', errorMessage);
            notify(errorMessage, 'error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <Head title={metaTags?.title || `Edit ${game.name}`}/>
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-blue-600">
                            Edit {game.name}
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage download links and view analytics for your game
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('my-games.index')}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back to My Games
                        </Link>
                        <button
                            onClick={save}
                            disabled={processing || saving}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors ${
                                processing || saving
                                    ? 'bg-blue-400 cursor-not-allowed'
                                    : 'bg-blue-600 hover:bg-blue-700'
                            }`}
                        >
                            {processing || saving ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Save Changes
                                </>
                            )}
                        </button>
                    </div>
                </div>

                {/* Links Editor */}
                <div
                    className="rounded-xl border border-gray-200/50 bg-white/70 p-6 backdrop-blur-xl shadow-lg dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div className="mb-6 flex items-start justify-between">
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                                Download Links
                            </h2>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Add download links for your game. {sortedLinks.length} of 15 links used.
                            </p>
                        </div>
                        <button
                            onClick={addLink}
                            disabled={processing || saving || sortedLinks.length >= 15}
                            className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:opacity-50 ${
                                sortedLinks.length >= 15
                                    ? 'bg-gray-400 cursor-not-allowed text-white'
                                    : 'bg-green-600 hover:bg-green-700 text-white'
                            }`}
                        >
                            <svg
                                className="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            <span>{sortedLinks.length >= 15 ? 'Limit Reached' : 'Add Link'}</span>
                        </button>
                    </div>

                    <div className="space-y-3">
                        {sortedLinks.length === 0 && (
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                No links added yet.
                            </div>
                        )}
                        <SortableList
                            items={sortedLinks}
                            onReorder={handleReorder}
                            getItemId={(link) => link.id ?? `new-${sortedLinks.indexOf(link)}`}
                            renderItem={(link, index, dragHandleProps) => {
                                // Build field-level errors from useForm errors object
                                const nameKey = `links.${index}.name`;
                                const urlKey = `links.${index}.url`;
                                const platformKey = `links.${index}.platform`;
                                const releaseAtKey = `links.${index}.release_at`;
                                const fieldErrors = {
                                    name: (errors as FormErrors)?.[nameKey]
                                        ? String((errors as FormErrors)[nameKey])
                                        : undefined,
                                    url: (errors as FormErrors)?.[urlKey]
                                        ? String((errors as FormErrors)[urlKey])
                                        : undefined,
                                    platform: (errors as FormErrors)?.[platformKey]
                                        ? String(
                                            (errors as FormErrors)[platformKey],
                                        )
                                        : undefined,
                                    release_at: (errors as FormErrors)?.[releaseAtKey]
                                        ? String((errors as FormErrors)[releaseAtKey])
                                        : undefined,
                                };

                                return (
                                    <LinkRow
                                        link={link}
                                        index={index}
                                        platforms={platforms}
                                        onChange={updateLink}
                                        onRemove={removeLink}
                                        errors={fieldErrors}
                                        disabled={processing || saving}
                                        dragHandleProps={dragHandleProps}
                                    />
                                );
                            }}
                            className="space-y-3"
                            disabled={processing || saving}
                        />
                    </div>

                    {Boolean((errors as Record<string, unknown>)?.links) && (
                        <div className="mt-3 text-sm text-red-600 dark:text-red-400">
                            {String((errors as Record<string, unknown>).links)}
                        </div>
                    )}

                    <div className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Security: localhost and private IP addresses are
                        blocked. Up to 15 links allowed.
                    </div>
                </div>

                {/* Analytics Section */}
                <GameStats
                    clickStats={clickStats}
                    dailyStats={dailyStats}
                />
            </div>
        </>
    );
}
