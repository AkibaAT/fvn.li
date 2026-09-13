import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/svelte';
import { expect, test, vi } from 'vitest';
import Switch from './Switch.svelte';

test('reports the requested value but waits for the controlled value to change', async () => {
    const requested: boolean[] = [];
    const { rerender } = render(Switch, {
        checked: false,
        ariaLabel: 'Notifications',
        onchange: (event) => requested.push((event.currentTarget as HTMLInputElement).checked),
    });
    const input = screen.getByRole('checkbox');
    await fireEvent.click(input);
    expect(requested).toEqual([true]);
    expect(input).not.toBeChecked();
    await rerender({ checked: true });
    expect(input).toBeChecked();
    await fireEvent.click(input);
    expect(requested).toEqual([true, false]);
    expect(input).toBeChecked();
});

test('disabling the switch prevents user changes', async () => {
    const onchange = vi.fn();
    render(Switch, { checked: false, disabled: true, onchange });
    const input = screen.getByRole('checkbox') as HTMLInputElement;
    input.click();
    expect(onchange).not.toHaveBeenCalled();
    expect(input).not.toBeChecked();
});
