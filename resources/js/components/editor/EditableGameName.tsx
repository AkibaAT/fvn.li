import React, { useState, useEffect } from 'react';

interface EditableGameNameProps {
    gameId: number;
    name: string;
    canEdit: boolean;
    hasCustomPage: boolean;
    className?: string;
    onNameUpdate?: (newName: string) => void;
}

export default function EditableGameName({
    gameId,
    name,
    canEdit,
    hasCustomPage,
    className = '',
    onNameUpdate,
}: EditableGameNameProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editName, setEditName] = useState(name);
    const [displayName, setDisplayName] = useState(name);
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');

    // Update display name when parent name changes
    useEffect(() => {
        setDisplayName(name);
        setEditName(name);
    }, [name]);

    // Listen for name revert events
    useEffect(() => {
        const handleNameReverted = (event: CustomEvent) => {
            const { effectiveName } = event.detail;
            if (effectiveName) {
                setDisplayName(effectiveName);
                setEditName(effectiveName);
                if (onNameUpdate) {
                    onNameUpdate(effectiveName);
                }
            }
        };

        window.addEventListener('name-reverted', handleNameReverted as EventListener);
        return () => {
            window.removeEventListener('name-reverted', handleNameReverted as EventListener);
        };
    }, [onNameUpdate]);

    const handleEdit = () => {
        if (!canEdit) return;
        setIsEditing(true);
        setEditName(displayName);
    };

    const handleCancel = () => {
        setIsEditing(false);
        setEditName(displayName);
        setSaveStatus('idle');
    };

    const handleSave = async () => {
        if (!canEdit) return;

        // Trim and validate
        const trimmedName = editName.trim();
        if (!trimmedName) {
            alert('Name cannot be empty');
            return;
        }

        setIsSaving(true);
        setSaveStatus('saving');

        try {
            const response = await window.axios.put(route('react-api.games.name.update', { game: gameId }), {
                name: trimmedName,
            });

            if (response.data.success) {
                setIsEditing(false);
                setSaveStatus('saved');

                // Use effective_name from the server response if available
                const updatedName = response.data.data.effective_name || trimmedName;

                // Update the display name to show the new name immediately
                setDisplayName(updatedName);

                // Notify parent component of the update
                if (onNameUpdate) {
                    onNameUpdate(updatedName);
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
            alert('Failed to save name. Please try again.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            handleSave();
        } else if (e.key === 'Escape') {
            handleCancel();
        }
    };

    return (
        <div className={`relative inline-flex items-center gap-2 ${className}`}>
            {isEditing ? (
                <>
                    <input
                        type="text"
                        value={editName}
                        onChange={(e) => setEditName(e.target.value)}
                        onKeyDown={handleKeyPress}
                        disabled={isSaving}
                        className="text-2xl font-bold bg-white dark:bg-gray-700 border-2 border-blue-300 rounded px-2 py-1 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-500"
                        autoFocus
                        maxLength={255}
                    />
                    <button
                        onClick={handleSave}
                        disabled={isSaving}
                        className="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 disabled:opacity-50 text-sm"
                    >
                        {isSaving ? 'Saving...' : 'Save'}
                    </button>
                    <button
                        onClick={handleCancel}
                        disabled={isSaving}
                        className="bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 disabled:opacity-50 text-sm"
                    >
                        Cancel
                    </button>
                    {saveStatus === 'saved' && (
                        <span className="text-green-600 text-sm">✓</span>
                    )}
                    {saveStatus === 'error' && (
                        <span className="text-red-600 text-sm">✗</span>
                    )}
                </>
            ) : (
                <>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {displayName}
                    </h1>
                    {canEdit && (
                        <button
                            onClick={handleEdit}
                            className="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700 shadow-md opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Edit name"
                        >
                            Edit
                        </button>
                    )}
                    {hasCustomPage && !canEdit && (
                        <span className="text-xs text-blue-600 dark:text-blue-400 font-medium" title="This is a custom name, not synced from itch.io">
                            ✏️
                        </span>
                    )}
                </>
            )}
        </div>
    );
}
