import DragHandle from '@/components/drag-drop/drag-handle';
import GameStats from '@/components/game-stats';
import SortableList, {DragHandleProps} from '@/components/drag-drop/sortable-list';
import {notify} from '@/components/toast';
import {FormError} from '@/components/form-elements';
import {authenticatedFetch} from '@/utils/csrf';
import {Head, Link, useForm} from '@inertiajs/react';
import React, {useMemo, useState} from 'react';

interface GameLink {
    id?: string;
    name: string;
    url: string;
    platform?: string | null;
    sort_order?: number;
    last_edited_at?: string;
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
    errors?: { name?: string; url?: string; platform?: string };
    disabled?: boolean;
    dragHandleProps?: DragHandleProps;
}) {
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

            {/* Last edited timestamp */}
            {link.last_edited_at && (
                <div className="col-span-12 mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Last edited: {new Date(link.last_edited_at).toLocaleString()}
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
    const [links, setLinks] = useState<GameLink[]>(
        Array.isArray(game.additional_links) ? [...game.additional_links] : [],
    );

    const form = useForm({});
    const {processing, errors, clearErrors} = form;
    const setFormError = form.setError as (key: string, value?: string) => void;
    const [saving, setSaving] = useState(false);

    // Media management state
    const [thumbnail, setThumbnail] = useState<string | null>(game.thumb_url || null);
    const [screenshots, setScreenshots] = useState<Array<{
        url: string;
        width?: number;
        height?: number;
        optimized?: Record<string, { path: string; width: number; height: number }>;
    }>>(game.custom_screenshots || game.screenshots || []);
    const [uploadingThumbnail, setUploadingThumbnail] = useState(false);
    const [uploadingScreenshots, setUploadingScreenshots] = useState(false);
    const [dragOver, setDragOver] = useState(false);


    // Handle reordering of links with immediate save
    const handleReorder = async (reorderedLinks: GameLink[]) => {
        const originalLinks = [...links]; // Store original order for rollback

        // Update local state immediately for responsive UI (optimistic update)
        setLinks(reorderedLinks);

        // Save in background without additional state changes
        authenticatedFetch(
            route('react-api.my-games.update', {game: game.slug}),
            {
                method: 'PUT',
                body: JSON.stringify({links: reorderedLinks}),
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

    // Thumbnail management
    const handleThumbnailUpload = async (file: File) => {
        if (!file.type.startsWith('image/')) {
            notify('Please upload an image file', 'error');
            return;
        }

        setUploadingThumbnail(true);
        const formData = new FormData();
        formData.append('thumbnail', file);

        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.thumbnail.update', {game: game.slug}),
                {
                    method: 'POST',
                    body: formData,
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to upload thumbnail', 'error');
                return;
            }

            setThumbnail(data.thumbnail_url);
            notify('Thumbnail updated successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload thumbnail';
            notify(errorMessage, 'error');
        } finally {
            setUploadingThumbnail(false);
        }
    };

    const handleThumbnailDelete = async () => {
        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.thumbnail.delete', {game: game.slug}),
                {
                    method: 'DELETE',
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to delete thumbnail', 'error');
                return;
            }

            setThumbnail(null);
            notify('Thumbnail deleted successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete thumbnail';
            notify(errorMessage, 'error');
        }
    };

    // Screenshot management
    const handleScreenshotUpload = async (files: FileList) => {
        const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            notify('Please upload image files', 'error');
            return;
        }

        setUploadingScreenshots(true);
        const formData = new FormData();
        imageFiles.forEach(file => {
            formData.append('screenshots[]', file);
        });

        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.screenshots.upload', {game: game.slug}),
                {
                    method: 'POST',
                    body: formData,
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to upload screenshots', 'error');
                return;
            }

            setScreenshots(data.screenshots || []);
            notify('Screenshots uploaded successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload screenshots';
            notify(errorMessage, 'error');
        } finally {
            setUploadingScreenshots(false);
        }
    };

    const handleScreenshotDelete = async (index: number) => {
        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.screenshots.delete', {game: game.slug, index}),
                {
                    method: 'DELETE',
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to delete screenshot', 'error');
                return;
            }

            setScreenshots(data.screenshots || []);
            notify('Screenshot deleted successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete screenshot';
            notify(errorMessage, 'error');
        }
    };


    // Drag and drop handlers
    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
    };

    const handleDrop = (e: React.DragEvent, type: 'thumbnail' | 'screenshots') => {
        e.preventDefault();
        setDragOver(false);

        const files = e.dataTransfer.files;
        if (type === 'thumbnail' && files.length === 1) {
            handleThumbnailUpload(files[0]);
        } else if (type === 'screenshots' && files.length > 0) {
            handleScreenshotUpload(files);
        }
    };

    const save = async () => {
        clearErrors();
        setSaving(true);
        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.update', {game: game.slug}),
                {
                    method: 'PUT',
                    body: JSON.stringify({links: sortedLinks}),
                },
            );
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                parseAndSetErrors(data);
                notify(data?.message || 'Failed to save changes', 'error');
                return;
            }
            // Replace local state with saved (canonical) order/payload
            setLinks(data.links || []);
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

                {/* Media Management Section */}
                <div className="rounded-xl border border-gray-200/50 bg-white/70 p-6 backdrop-blur-xl shadow-lg dark:border-gray-700/50 dark:bg-gray-800/70">
                    <div className="mb-6">
                        <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                            Media Management
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage your game's thumbnail and screenshots
                        </p>
                    </div>

                    {/* Thumbnail Management */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            Thumbnail
                        </h3>
                        <div className="flex items-start gap-6">
                            <div
                                className={`relative border-2 border-dashed rounded-lg p-4 transition-colors ${
                                    dragOver
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                        : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                                }`}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={(e) => handleDrop(e, 'thumbnail')}
                            >
                                {thumbnail ? (
                                    <div className="relative">
                                        <img
                                            src={thumbnail}
                                            alt="Game thumbnail"
                                            className="w-32 h-32 object-cover rounded-lg"
                                        />
                                        <button
                                            onClick={handleThumbnailDelete}
                                            disabled={uploadingThumbnail}
                                            className="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition-colors"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                ) : (
                                    <div className="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                )}
                            </div>

                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Upload Thumbnail
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        if (e.target.files?.[0]) {
                                            handleThumbnailUpload(e.target.files[0]);
                                        }
                                    }}
                                    disabled={uploadingThumbnail}
                                    className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 disabled:opacity-50"
                                />
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Recommended: 16:9 aspect ratio, max 5MB. Supports JPG, PNG, WebP.
                                </p>
                                {uploadingThumbnail && (
                                    <div className="mt-2 flex items-center text-sm text-blue-600 dark:text-blue-400">
                                        <svg className="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Uploading thumbnail...
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Screenshots Management */}
                    <div>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                                Screenshots
                            </h3>
                            <label className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors cursor-pointer disabled:opacity-50">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Add Screenshots</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    onChange={(e) => {
                                        if (e.target.files) {
                                            handleScreenshotUpload(e.target.files);
                                        }
                                    }}
                                    disabled={uploadingScreenshots}
                                    className="hidden"
                                />
                            </label>
                        </div>

                        <div
                            className={`border-2 border-dashed rounded-lg p-6 mb-4 transition-colors ${
                                dragOver
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                            }`}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={(e) => handleDrop(e, 'screenshots')}
                        >
                            {screenshots.length > 0 ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    {screenshots.map((screenshot, index) => (
                                        <div key={index} className="relative group">
                                            <img
                                                src={screenshot.url}
                                                alt={`Screenshot ${index + 1}`}
                                                className="w-full h-32 object-cover rounded-lg"
                                            />
                                            <div className="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center gap-2">
                                                <button
                                                    onClick={() => handleScreenshotDelete(index)}
                                                    className="bg-red-600 text-white rounded-full p-2 hover:bg-red-700 transition-colors"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div className="absolute bottom-2 left-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                                #{index + 1}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center">
                                    <svg className="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p className="text-gray-500 dark:text-gray-400 mb-2">
                                        No screenshots uploaded yet
                                    </p>
                                    <p className="text-sm text-gray-400 dark:text-gray-500">
                                        Drag and drop images here or click "Add Screenshots" to upload
                                    </p>
                                </div>
                            )}
                        </div>

                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Recommended: 16:9 aspect ratio, max 10MB per image. Supports JPG, PNG, WebP. Multiple files can be uploaded at once.
                        </p>

                        {uploadingScreenshots && (
                            <div className="mt-4 flex items-center text-sm text-blue-600 dark:text-blue-400">
                                <svg className="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading screenshots...
                            </div>
                        )}
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
