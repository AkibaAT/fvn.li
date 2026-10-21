import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { expect, test } from 'vitest';
import Toast from './Toast.svelte';
import { toast } from '@/utils/toast';

test('renders and dismisses toast notifications', async () => {
    render(Toast);
    toast.success('Saved through the store');
    toast.error('Action failed');
    await tick();

    expect(screen.getByRole('alert', { name: 'success notification: Saved through the store' })).toBeVisible();
    expect(screen.getByRole('alert', { name: 'error notification: Action failed' })).toBeVisible();
    await fireEvent.click(screen.getByRole('button', { name: 'Close success notification' }));
    expect(screen.queryByText('Saved through the store')).toBeNull();
    await fireEvent.click(screen.getByRole('button', { name: 'Close error notification' }));
});
