import type { Attachment } from 'svelte/attachments';

const NEXT_KEYS = new Set(['ArrowDown', 'ArrowRight']);
const PREVIOUS_KEYS = new Set(['ArrowUp', 'ArrowLeft']);

export function rovingFocus(itemSelector: string): Attachment<HTMLElement> {
    return (root) => {
        let current: HTMLElement | null = null;

        const items = () => Array.from(root.querySelectorAll<HTMLElement>(itemSelector)).filter((item) => !item.hasAttribute('disabled'));

        const sync = () => {
            const list = items();
            if (!current || !list.includes(current)) current = list.find((item) => item.getAttribute('aria-selected') === 'true') ?? list[0] ?? null;
            for (const item of list) item.tabIndex = item === current ? 0 : -1;
        };

        const move = (target: HTMLElement) => {
            current = target;
            sync();
            target.focus();
        };

        const handleKeydown = (event: KeyboardEvent) => {
            const list = items();
            const index = list.indexOf(event.target as HTMLElement);
            if (index === -1) return;

            if (NEXT_KEYS.has(event.key)) move(list[(index + 1) % list.length]);
            else if (PREVIOUS_KEYS.has(event.key)) move(list[(index - 1 + list.length) % list.length]);
            else if (event.key === 'Home') move(list[0]);
            else if (event.key === 'End') move(list[list.length - 1]);
            else return;

            event.preventDefault();
        };

        const handleFocusin = (event: FocusEvent) => {
            const target = event.target as HTMLElement;
            if (!items().includes(target)) return;
            current = target;
            sync();
        };

        const observer = new MutationObserver(sync);
        observer.observe(root, { childList: true, subtree: true });
        sync();

        root.addEventListener('keydown', handleKeydown);
        root.addEventListener('focusin', handleFocusin);
        return () => {
            observer.disconnect();
            root.removeEventListener('keydown', handleKeydown);
            root.removeEventListener('focusin', handleFocusin);
        };
    };
}
