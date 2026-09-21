<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { storeVnList } from '@/api/lists';
    import { router } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import ListFormFields from '@/components/lists/ListFormFields.svelte';
    import { useAsyncAction } from '@/utils/async-action.svelte';

    interface Props {
        metaTags?: {
            title?: string;
            description?: string;
        };
    }

    let { metaTags }: Props = $props();

    let formData = $state({
        name: '',
        description: '',
        is_public: false,
    });
    const createAction = useAsyncAction();

    async function handleSubmit(e: Event) {
        e.preventDefault();
        const data = await createAction.run(() => storeVnList(formData), { fallbackError: 'Failed to create list' });
        if (!data) return;
        router.visit(route('lists.show', data.list.id));
    }
</script>

<SeoHead {metaTags} title="Create New List" />

<div class="mx-auto max-w-2xl space-y-8">
    <PageHeader title="Create New List" />

    <Card variant="flat">
        <form onsubmit={handleSubmit} class="space-y-6">
            <ListFormFields bind:form={formData} />

            <div class="flex justify-end space-x-3 pt-4">
                <Button href={route('lists.index')} variant="outline" tone="neutral">Cancel</Button>
                <Button type="submit" disabled={createAction.isLoading || !formData.name.trim()} loading={createAction.isLoading}>
                    {createAction.isLoading ? 'Creating...' : 'Create List'}
                </Button>
            </div>
        </form>
    </Card>
</div>
