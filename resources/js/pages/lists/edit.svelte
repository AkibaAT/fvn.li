<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { untrack } from 'svelte';
    import { destroyVnList, updateVnList } from '@/api/lists';
    import { router } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import ListFormFields from '@/components/lists/ListFormFields.svelte';
    import { formatListType } from '@/components/ui/tones';
    import { useAsyncAction } from '@/utils/async-action.svelte';

    interface VnList {
        id: number;
        name: string;
        description?: string;
        type: string;
        is_default: boolean;
        is_public: boolean;
    }

    interface Props {
        vnList: VnList;
        metaTags?: {
            title?: string;
            description?: string;
        };
    }

    let { vnList, metaTags }: Props = $props();

    let formData = $state(
        untrack(() => ({
            name: vnList.name,
            description: vnList.description || '',
            is_public: vnList.is_public,
        })),
    );
    const saveAction = useAsyncAction();
    const deleteAction = useAsyncAction();

    async function handleSubmit(e: Event) {
        e.preventDefault();
        const data = await saveAction.run(
            async () => {
                const result = await updateVnList(vnList.id, formData);
                if (!(await refreshPage(['vnList']))) return null;
                return result;
            },
            { fallbackError: 'Failed to update list' },
        );
        if (!data) return;
        router.visit(route('lists.show', vnList.id));
    }

    async function handleDelete() {
        if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
            return;
        }

        const deleted = await deleteAction.run(
            async () => {
                await destroyVnList(vnList.id);
                return true;
            },
            { fallbackError: 'Failed to delete list' },
        );
        if (!deleted) return;
        router.visit(route('lists.index'), { replace: true });
    }
</script>

<SeoHead {metaTags} title={`Edit List - ${vnList.name}`} />

<div class="mx-auto max-w-2xl space-y-8">
    <PageHeader title="Edit List" backHref={route('lists.show', vnList.id)} backLabel="Back to list" />

    <Card variant="flat">
        <form onsubmit={handleSubmit} class="space-y-6">
            <ListFormFields bind:form={formData}>
                {#snippet afterName()}
                    <div>
                        <p class="block text-sm font-medium text-fg-muted">List Type</p>
                        <div class="mt-1 rounded-md border border-border bg-surface-alt p-3">
                            <span class="text-sm text-fg">
                                {formatListType(vnList.type)}
                                {vnList.is_default ? ' (Default)' : ''}
                            </span>
                        </div>
                        {#if vnList.is_default}
                            <p class="mt-1 text-xs text-fg-faint">The type of a default list cannot be changed</p>
                        {/if}
                    </div>
                {/snippet}
            </ListFormFields>

            <div class="flex justify-between pt-4">
                <div class="flex space-x-3">
                    <Button href={route('lists.show', vnList.id)} variant="outline" tone="neutral">Cancel</Button>
                    {#if !vnList.is_default}
                        <Button type="button" onclick={handleDelete} disabled={deleteAction.isLoading} tone="danger" loading={deleteAction.isLoading}>
                            {deleteAction.isLoading ? 'Deleting...' : 'Delete List'}
                        </Button>
                    {/if}
                </div>
                <Button type="submit" disabled={saveAction.isLoading || !formData.name.trim()} loading={saveAction.isLoading}>
                    {saveAction.isLoading ? 'Saving...' : 'Save Changes'}
                </Button>
            </div>
        </form>
    </Card>
</div>
