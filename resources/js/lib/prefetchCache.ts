import { config, router } from '@inertiajs/vue3';

/**
 * How long a page loaded on hover is kept for the click. Most clicks follow the hover within a second or
 * two; a short life keeps the shown data close to what a fresh load would show, also for colleagues'
 * changes, while still sparing the server when the pointer passes the same link again.
 */
export const PREFETCH_CACHE_FOR = '10s';

/** Called once from app.ts, after createInertiaApp, which replaces the whole Inertia config. */
export function initializePrefetchCache(): void {
    config.set('prefetch.cacheFor', PREFETCH_CACHE_FOR);

    // Inertia forgets only the page a save redirects to, so any other page loaded on hover would still
    // show the data from before the save. A cancelled save may have reached the server too.
    router.on('finish', (event) => {
        if (event.detail.visit.method !== 'get') {
            router.flushAll();
        }
    });
}
