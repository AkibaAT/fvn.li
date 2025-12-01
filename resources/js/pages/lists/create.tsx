
import {authenticatedFetch} from '@/utils/csrf';
import {Head, Link, router} from '@inertiajs/react';
import React, {useState} from 'react';

interface CreateListProps {
    metaTags?: {
        title?: string;
        description?: string;
    };
}

export default function CreateList({metaTags}: CreateListProps) {
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        is_public: false,
    });
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);

        try {
            const response = await authenticatedFetch(route('api.vn-lists.store'), {
                method: 'POST',
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (data.success) {
                router.visit(route('lists.show', data.list.id));
            } else {
                alert(data.message || 'Failed to create list');
            }
        } catch (error) {
            console.error('Error creating list:', error);
            alert('An error occurred while creating the list');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <>
            <Head title={metaTags?.title || 'Create New List'}/>

            <div className="mx-auto max-w-2xl space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold text-blue-600">
                        Create New List
                    </h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">
                        Create a new visual novel list to organize your games
                    </p>
                </div>

                {/* Form */}
                <div className="rounded-xl bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:bg-gray-800/70">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* List Name */}
                        <div>
                            <label
                                htmlFor="name"
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
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
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                placeholder="Enter list name..."
                            />
                        </div>


                        {/* Description */}
                        <div>
                            <label
                                htmlFor="description"
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
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
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                placeholder="Optional description for your list..."
                            />
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
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
                                    className="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                />
                                <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Make this list public
                                </span>
                            </label>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Public lists can be viewed by anyone, private
                                lists are only visible to you
                            </p>
                        </div>

                        {/* Submit Buttons */}
                        <div className="flex justify-end space-x-3 pt-4">
                            <Link
                                href={route('lists.index')}
                                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={isLoading || !formData.name.trim()}
                                className="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isLoading ? 'Creating...' : 'Create List'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
