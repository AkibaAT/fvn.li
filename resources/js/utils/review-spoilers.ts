export function revealInlineSpoiler(target: EventTarget | null): boolean {
    if (!(target instanceof Element)) return false;

    const spoiler = target.closest<HTMLElement>('.spoiler');
    if (!spoiler || spoiler.classList.contains('revealed')) return false;

    spoiler.classList.add('revealed');
    spoiler.removeAttribute('role');
    spoiler.removeAttribute('tabindex');
    spoiler.removeAttribute('title');

    return true;
}
