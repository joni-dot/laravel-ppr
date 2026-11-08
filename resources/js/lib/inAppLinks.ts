import { router } from '@inertiajs/vue3';

/**
 * Opens plain links marked with data-in-app inside the app, without reloading the page. Long lists use one
 * listener for all their links instead of a Link component per link. Clicks meant to open a new tab or
 * window are left to the browser.
 */
export function visitLinkInApp(event: MouseEvent): void {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const link =
        event.target instanceof Element
            ? event.target.closest('a[data-in-app]')
            : null;

    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }

    event.preventDefault();
    router.visit(link.href);
}
