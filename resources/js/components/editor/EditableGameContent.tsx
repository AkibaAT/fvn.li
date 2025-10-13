import React, {lazy, Suspense, useState, useEffect} from 'react';

// Lazy-load the editor so it doesn't bloat the initial page chunk
const TinyMCEEditor = lazy(() => import('./TinyMCEEditor'));

interface EditableGameContentProps {
    gameId: number;
    content: string;
    canEdit: boolean;
    hasCustomPage: boolean;
    className?: string;
    onContentUpdate?: (newContent: string) => void;
}

export default function EditableGameContent({
                                                gameId,
                                                content,
                                                canEdit,
                                                hasCustomPage,
                                                className = '',
                                                onContentUpdate,
                                            }: EditableGameContentProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editContent, setEditContent] = useState(content);
    const [displayContent, setDisplayContent] = useState(content);
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const [isReverting, setIsReverting] = useState(false);
    const [showRevertMenu, setShowRevertMenu] = useState(false);
    const [viewMode, setViewMode] = useState<'custom' | 'original'>('original');
    const [isLoadingViewMode, setIsLoadingViewMode] = useState(true);
    // Update display content when parent content changes
    useEffect(() => {
        setDisplayContent(content);
        setEditContent(content);
    }, [content]);

    // Fetch current view mode when component mounts (only if user can edit)
    useEffect(() => {
        if (hasCustomPage && canEdit) {
            fetchViewMode();
        } else {
            setIsLoadingViewMode(false);
        }
    }, [hasCustomPage, canEdit]);

    // Close revert menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest('.revert-menu-container')) {
                setShowRevertMenu(false);
            }
        };

        if (showRevertMenu) {
            document.addEventListener('click', handleClickOutside);
            return () => document.removeEventListener('click', handleClickOutside);
        }
    }, [showRevertMenu]);

    const fetchViewMode = async () => {
        try {
            const response = await window.axios.get(route('react-api.games.content.view', {game: gameId}));
            if (response.data.success) {
                setViewMode(response.data.data.current_view_mode || 'original');
            }
        } catch (error) {
            console.error('Failed to fetch view mode:', error);
        } finally {
            setIsLoadingViewMode(false);
        }
    };

    const handleViewModeChange = async (newMode: 'custom' | 'original') => {
        try {
            const response = await window.axios.put(route('react-api.games.content.view-mode', {game: gameId}), {
                view_mode: newMode,
            });

            if (response.data.success) {
                setViewMode(newMode);
                // Reload the page to show the new content to all visitors
                window.location.reload();
            }
        } catch (error) {
            console.error('Failed to update view mode:', error);
            alert('Failed to update view mode. Please try again.');
        }
    };

    const handleEdit = () => {
        if (!canEdit) return;
        setIsEditing(true);
        setEditContent(displayContent);
    };

    const handleCancel = () => {
        setIsEditing(false);
        setEditContent(displayContent);
        setSaveStatus('idle');
    };

    const handleSave = async () => {
        if (!canEdit) return;

        setIsSaving(true);
        setSaveStatus('saving');

        try {
            const response = await window.axios.put(route('react-api.games.content.update', {game: gameId}), {
                content: editContent,
            });

            if (response.data.success) {
                setIsEditing(false);
                setSaveStatus('saved');

                // Update the display content to show the new content immediately
                setDisplayContent(editContent);

                // Notify parent component of the update
                if (onContentUpdate) {
                    onContentUpdate(editContent);
                }

                // Clear saved status after 3 seconds
                setTimeout(() => {
                    setSaveStatus('idle');
                }, 3000);
            } else {
                throw new Error(response.data.message || 'Failed to save');
            }
        } catch (error) {
            console.error('Save error:', error);
            setSaveStatus('error');
        } finally {
            setIsSaving(false);
        }
    };

    const handleRevert = async (options?: { screenshots?: boolean; thumbnail?: boolean }) => {
        if (!canEdit) return;

        const { screenshots = false, thumbnail = false } = options || {};

        let confirmMessage = "Are you sure you want to revert to the original itch.io content?";
        if (screenshots && !thumbnail) {
            confirmMessage += " This will replace your custom screenshots with the current ones from itch.io.";
        } else if (thumbnail && !screenshots) {
            confirmMessage += " This will replace your custom thumbnail with the current one from itch.io.";
        } else if (screenshots && thumbnail) {
            confirmMessage += " This will replace all your custom content (description, screenshots, and thumbnail) with the current versions from itch.io.";
        } else {
            confirmMessage += " This will replace your custom description with the current version from itch.io.";
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        setIsReverting(true);
        setSaveStatus('saving');

        try {
            const response = await window.axios.post(route('react-api.games.content.revert', {game: gameId}), {
                revert_screenshots: screenshots,
                revert_thumbnail: thumbnail,
            });

            if (response.data.success) {
                setSaveStatus('saved');

                // Update the display content to show the reverted content immediately
                const revertedContent = response.data.data.content;
                setDisplayContent(revertedContent);
                setEditContent(revertedContent);
                setIsEditing(false);

                // Notify parent component of the update
                if (onContentUpdate) {
                    onContentUpdate(revertedContent);
                }

                // Handle screenshot revert
                if (screenshots && response.data.data.screenshots) {
                    // Trigger a page reload to show new screenshots
                    window.location.reload();
                }

                // Handle thumbnail revert
                if (thumbnail && response.data.data.thumbnail_url) {
                    // Update the thumbnail in the parent component if it handles it
                    const event = new CustomEvent('thumbnail-reverted', {
                        detail: { thumbnailUrl: response.data.data.thumbnail_url }
                    });
                    window.dispatchEvent(event);

                    // Also reload to show new thumbnail
                    setTimeout(() => window.location.reload(), 1000);
                }

                // Clear saved status after 3 seconds
                setTimeout(() => {
                    setSaveStatus('idle');
                }, 3000);
            } else {
                throw new Error(response.data.message || 'Failed to revert');
            }
        } catch (error) {
            console.error('Revert error:', error);
            setSaveStatus('error');
            alert('Failed to revert content. Please try again.');
        } finally {
            setIsReverting(false);
        }
    };

    return (
        <div className={`relative revert-menu-container ${className}`}>
            {/* Status indicator when no edit permissions */}
            {hasCustomPage && !canEdit && !isEditing && (
                <div className="mb-2 text-xs text-blue-600 dark:text-blue-400 font-medium">
                    ✏️ Custom content (not synced from itch.io)
                </div>
            )}

            <div
                className={`prose dark:prose-invert game_description max-w-none text-gray-600 dark:text-gray-300 ${
                    isEditing ? 'border-2 border-blue-300 rounded' : ''
                } ${canEdit && !isEditing ? 'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer' : ''}`}
                onClick={!isEditing ? handleEdit : undefined}
            >
                {isEditing ? (
                    <Suspense
                        fallback={
                            <div
                                className="flex h-64 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                Loading editor…
                            </div>
                        }
                    >
                        <TinyMCEEditor
                            content={editContent}
                            onUpdate={setEditContent}
                            gameId={gameId}
                            editable={true}
                            placeholder="Start writing your game description..."
                            height={400}
                        />
                    </Suspense>
                ) : (
                    <div
                        className="inner_column size_very_large family_grandstander"
                        id="inner_column"
                    >
                        <div
                            dangerouslySetInnerHTML={{
                                __html: displayContent || '',
                            }}
                        />
                    </div>
                )}
            </div>

            {canEdit && !isEditing && (
                <div className="absolute top-2 right-2 flex gap-2 transition-opacity">
                    {hasCustomPage && !isLoadingViewMode && (
                        <div className="flex items-center gap-1 mr-2">
                            <span className="text-xs text-gray-600 dark:text-gray-400 mr-2">Visitors see:</span>
                            <button
                                onClick={() => handleViewModeChange('original')}
                                className={`text-xs px-2 py-1 rounded transition-colors ${
                                    viewMode === 'original'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                                }`}
                                title="Show visitors original itch.io content"
                            >
                                itch.io
                            </button>
                            <button
                                onClick={() => handleViewModeChange('custom')}
                                className={`text-xs px-2 py-1 rounded transition-colors ${
                                    viewMode === 'custom'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                                }`}
                                title="Show visitors custom content"
                            >
                                Custom
                            </button>
                        </div>
                    )}
                    {hasCustomPage && (
                        <div className="relative">
                            <button
                                onClick={() => setShowRevertMenu(!showRevertMenu)}
                                disabled={isReverting}
                                className="text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700 disabled:opacity-50 shadow-md"
                                title="Revert to original itch.io content"
                            >
                                {isReverting ? 'Reverting...' : 'Revert'}
                            </button>
                            {showRevertMenu && (
                                <div className="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg z-50">
                                    <div className="py-1">
                                        <button
                                            onClick={() => {
                                                setShowRevertMenu(false);
                                                handleRevert();
                                            }}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            Revert Description Only
                                        </button>
                                        <button
                                            onClick={() => {
                                                setShowRevertMenu(false);
                                                handleRevert({ screenshots: true });
                                            }}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            Revert Screenshots
                                        </button>
                                        <button
                                            onClick={() => {
                                                setShowRevertMenu(false);
                                                handleRevert({ thumbnail: true });
                                            }}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            Revert Thumbnail
                                        </button>
                                        <button
                                            onClick={() => {
                                                setShowRevertMenu(false);
                                                handleRevert({ screenshots: true, thumbnail: true });
                                            }}
                                            className="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-semibold border-t border-gray-200 dark:border-gray-600"
                                        >
                                            Revert Everything
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                    <button
                        onClick={handleEdit}
                        className="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700 shadow-md"
                    >
                        Edit
                    </button>
                </div>
            )}

            {isEditing && (
                <div className="mt-4 flex items-center gap-2">
                    <button
                        onClick={handleSave}
                        disabled={isSaving || isReverting}
                        className="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {isSaving ? 'Saving...' : 'Save'}
                    </button>

                    <button
                        onClick={handleCancel}
                        disabled={isSaving || isReverting}
                        className="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 disabled:opacity-50"
                    >
                        Cancel
                    </button>

                    <button
                        onClick={() => handleRevert({ screenshots: true, thumbnail: true })}
                        disabled={isSaving || isReverting}
                        className="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 disabled:opacity-50"
                        title="Revert everything to original itch.io content"
                    >
                        {isReverting ? 'Reverting...' : 'Revert All'}
                    </button>

                    {saveStatus === 'saved' && (
                        <span className="text-green-600 text-sm">✓ Saved</span>
                    )}

                    {saveStatus === 'error' && (
                        <span className="text-red-600 text-sm">✗ Error saving</span>
                    )}
                </div>
            )}
        </div>
    );
}
