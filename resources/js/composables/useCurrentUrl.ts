import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import { useNavigationState } from '@/composables/useNavigationState';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    /** Path of the page on screen. */
    currentUrl: DeepReadonly<ComputedRef<string>>;
    /**
     * The checks below compare with the page being opened while one loads, so navigation highlights move
     * the moment a link is clicked instead of when the next page arrives.
     */
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
    /** True while this exact page is being opened and has taken longer than a moment. */
    isOpeningSlowly: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ) => boolean;
};

const page = usePage();
const currentUrlReactive = computed(
    () =>
        new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname,
);
const { pendingPath, isSlow } = useNavigationState();
const highlightedUrl = computed(
    () => pendingPath.value ?? currentUrlReactive.value,
);

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        const urlToCompare = currentUrl ?? highlightedUrl.value;
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    function isOpeningSlowly(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ) {
        return (
            isSlow.value &&
            pendingPath.value !== null &&
            isCurrentUrl(urlToCheck, pendingPath.value)
        );
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
        isOpeningSlowly,
    };
}
