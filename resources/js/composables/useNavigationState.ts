import { router } from '@inertiajs/vue3';
import type { PendingVisit } from '@inertiajs/core';
import type { DeepReadonly, Ref } from 'vue';
import { readonly, ref } from 'vue';

/**
 * Loads shorter than this show no loading cues at all, so fast pages never flicker. Matches the delay of
 * Inertia's progress bar.
 */
export const SLOW_NAVIGATION_MS = 250;

export type UseNavigationStateReturn = {
    /** Path of the page being opened, set the moment a link is clicked; null when nothing is loading. */
    pendingPath: DeepReadonly<Ref<string | null>>;
    /** True once the pending page has taken longer than SLOW_NAVIGATION_MS. */
    isSlow: DeepReadonly<Ref<boolean>>;
};

const pendingPath = ref<string | null>(null);
const isSlow = ref(false);
let slowTimer: ReturnType<typeof setTimeout> | undefined;
let pendingVisitId: string | null = null;

/**
 * Only real page changes count. Form posts show their own button state; prefetching, background loads of
 * deferred or optional props and in-page filters that keep the page state must not dim or highlight anything.
 */
export function isPageNavigation(visit: PendingVisit): boolean {
    return (
        visit.method === 'get' &&
        !visit.prefetch &&
        !visit.async &&
        visit.only.length === 0 &&
        visit.except.length === 0 &&
        visit.preserveState !== true
    );
}

function clearPending(): void {
    clearTimeout(slowTimer);
    pendingVisitId = null;
    pendingPath.value = null;
    isSlow.value = false;
}

/** Called once from app.ts. */
export function initializeNavigationState(): void {
    // "before" instead of "start": a click on a link that was prefetched on hover reuses that request and
    // fires no start or finish of its own, only before and success.
    router.on('before', (event) => {
        const visit = event.detail.visit;

        if (!isPageNavigation(visit)) {
            return;
        }

        clearTimeout(slowTimer);
        pendingVisitId = visit.id;
        pendingPath.value = visit.url.pathname;
        isSlow.value = false;
        slowTimer = setTimeout(() => (isSlow.value = true), SLOW_NAVIGATION_MS);
    });

    // Matched by id, because the next click fires before the visit it interrupts finishes. A cancelled or
    // failed visit also ends with finish, so the cues never get stuck.
    router.on('finish', (event) => endPendingVisit(event.detail.visit.id));
    router.on('success', (event) => endPendingVisit(event.detail.visitId));
    // A prefetched request that fails reaches neither of the above for the click that waits for it.
    router.on('networkError', clearPending);
}

function endPendingVisit(visitId: string | undefined): void {
    if (visitId !== undefined && visitId === pendingVisitId) {
        clearPending();
    }
}

export function useNavigationState(): UseNavigationStateReturn {
    return {
        pendingPath: readonly(pendingPath),
        isSlow: readonly(isSlow),
    };
}
