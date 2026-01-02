import {authenticatedFetch} from '@/utils/csrf';
import {Head, Link, router} from '@inertiajs/react';
import React, {useState} from 'react';

interface VnList {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_default: boolean;
    is_public: boolean;
}

interface EditListProps {
    vnList: VnList;
    metaTags?: {
        title?: string;
        description?: string;
    };
}

export default function EditList({vnList, metaTags}: EditListProps) {
    const [formData, setFormData] = useState({
        name: vnList.name,
        description: vnList.description || '',
        is_public: vnList.is_public,
    });
    const [isLoading, setIsLoading] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.update', vnList.id),
                {
                    method: 'PUT',
                    body: JSON.stringify(formData),
                },
            );

            const data = await response.json();

            if (data.success) {
                router.visit(route('lists.show', vnList.id));
            } else {
                alert(data.message || 'Failed to update list');
            }
        } catch (error) {
            console.error('Error updating list:', error);
            alert('An error occurred while updating the list');
        } finally {
            setIsLoading(false);
        }
    };

    const handleDelete = async () => {
        if (
            !confirm(
                'Are you sure you want to delete this list? This action cannot be undone.',
            )
        ) {
            return;
        }

        setIsDeleting(true);

        try {
            const response = await authenticatedFetch(
                route('api.vn-lists.destroy', vnList.id),
                {
                    method: 'DELETE',
                },
            );

            const data = await response.json();

            if (data.success) {
                router.visit(route('lists.index'));
            } else {
                alert(data.message || 'Failed to delete list');
            }
        } catch (error) {
            console.error('Error deleting list:', error);
            alert('An error occurred while deleting the list');
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <>
            <Head title={metaTags?.title || `Edit List - ${vnList.name}`}/>

            <div className="mx-auto max-w-2xl space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-[var(--color-ui-text)]">
                        Edit List
                    </h1>
                    <p className="mt-2 text-[var(--color-ui-text-muted)]">
                        Update your visual novel list settings
                    </p>
                </div>

                {/* Form */}
                <div className="section-surface rounded-2xl p-6">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* List Name */}
                        <div>
                            <label
                                htmlFor="name"
                                className="block text-sm font-medium text-[var(--color-ui-text)]"
                            >
                                List Name *
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={formData.name}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        name: e.target.value,
                                    })
                                }
                                required
                                className="mt-1 block w-full rounded-md border border-[var(--color-ui-border)] bg-[var(--color-ui-surface)] px-3 py-2 text-sm text-[var(--color-ui-text)] shadow-sm focus:border-[var(--color-brand-primary)] focus:ring-[var(--color-brand-primary)]"
                                placeholder="Enter list name..."
                            />
                        </div>

                        {/* List Type (Read-only for default lists) */}
                        <div>
                            <label className="block text-sm font-medium text-[var(--color-ui-text)]">
                                List Type
                            </label>
                            <div
                                className="mt-1 rounded-md border border-[var(--color-ui-border)] bg-[var(--color-ui-surface-alt)] p-3">
                                <span className="text-sm text-[var(--color-ui-text)]">
                                    {vnList.type
                                        .replace(/_/g, ' ')
                                        .replace(/\b\w/g, (l) =>
                                            l.toUpperCase(),
                                        )}
                                    {vnList.is_default && ' (Default)'}
                                </span>
                            </div>
                            {vnList.is_default && (
                                <p className="mt-1 text-xs text-[var(--color-ui-text-muted)]">
                                    Default lists cannot be modified
                                </p>
                            )}
                        </div>

                        {/* Description */}
                        <div>
                            <label
                                htmlFor="description"
                                className="block text-sm font-medium text-[var(--color-ui-text)]"
                            >
                                Description
                            </label>
                            <textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        description: e.target.value,
                                    })
                                }
                                rows={4}
                                className="mt-1 block w-full rounded-md border border-[var(--color-ui-border)] bg-[var(--color-ui-surface)] px-3 py-2 text-sm text-[var(--color-ui-text)] shadow-sm focus:border-[var(--color-brand-primary)] focus:ring-[var(--color-brand-primary)]"
                                placeholder="Optional description for your list..."
                            />
                            <p className="mt-1 text-xs text-[var(--color-ui-text-muted)]">
                                Describe what this list is for (optional)
                            </p>
                        </div>

                        {/* Public/Private Toggle */}
                        <div>
                            <label className="flex items-center">
                                <input
                                    type="checkbox"
                                    checked={formData.is_public}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            is_public: e.target.checked,
                                        })
                                    }
                                    className="focus:ring-opacity-50 rounded border-[var(--color-ui-border)] text-[var(--color-link)] shadow-sm focus:border-[var(--color-brand-primary-light)] focus:ring focus:ring-[var(--color-brand-primary)]/20"
                                />
                                <span className="ml-2 text-sm text-[var(--color-ui-text)]">
                                    Make this list public
                                </span>
                            </label>
                            <p className="mt-1 text-xs text-[var(--color-ui-text-muted)]">
                                Public lists can be viewed by anyone, private
                                lists are only visible to you
                            </p>
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-between pt-4">
                            <div className="flex space-x-3">
                                <Link
                                    href={route('lists.show', vnList.id)}
                                    className="inline-flex items-center rounded-md border border-[var(--color-ui-border)] bg-[var(--color-ui-surface)] px-4 py-2 text-sm font-medium text-[var(--color-ui-text)] shadow-sm hover:border-[var(--color-brand-primary)] hover:text-[var(--color-link)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-primary)]"
                                >
                                    Cancel
                                </Link>
                                {!vnList.is_default && (
                                    <button
                                        type="button"
                                        onClick={handleDelete}
                                        disabled={isDeleting}
                                        className="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {isDeleting
                                            ? 'Deleting...'
                                            : 'Delete List'}
                                    </button>
                                )}
                            </div>
                            <button
                                type="submit"
                                disabled={isLoading || !formData.name.trim()}
                                className="inline-flex items-center rounded-md border border-transparent bg-[var(--color-brand-primary)] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[var(--color-brand-primary-dark)] focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-primary)] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isLoading ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
