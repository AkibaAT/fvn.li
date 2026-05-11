export function isDialogBackdropClick(dialogEl: HTMLDialogElement | null | undefined, event: MouseEvent): boolean {
    if (!dialogEl || event.target !== dialogEl) return false;

    const rect = dialogEl.getBoundingClientRect();
    const clickedInside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;

    return !clickedInside;
}
