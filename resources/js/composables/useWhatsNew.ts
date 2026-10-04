import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CHANGELOG_RELEASES } from '@/data/changelog';
import type { ChangelogRelease } from '@/data/changelog';

const isOpen = ref(false);
const LAST_SEEN_KEY = 'app:last_seen_version';

export function useWhatsNew() {
    const page = usePage();

    const currentVersion = computed<string>(() => {
        const propVersion = page.props.appVersion as string | undefined;
        return propVersion || CHANGELOG_RELEASES[0]?.version || '6.2.0';
    });

    const hasUnread = computed<boolean>(() => {
        if (typeof window === 'undefined') return false;
        try {
            const seen = window.localStorage.getItem(LAST_SEEN_KEY);
            return seen !== currentVersion.value;
        } catch {
            return false;
        }
    });

    const markAsRead = (): void => {
        if (typeof window === 'undefined') return;
        try {
            window.localStorage.setItem(LAST_SEEN_KEY, currentVersion.value);
        } catch {
            // Ignore localStorage errors
        }
    };

    const open = (): void => {
        markAsRead();
        isOpen.value = true;
    };

    const close = (): void => {
        isOpen.value = false;
    };

    const toggle = (): void => {
        if (isOpen.value) {
            close();
        } else {
            open();
        }
    };

    return {
        isOpen,
        hasUnread,
        currentVersion,
        releases: CHANGELOG_RELEASES as ChangelogRelease[],
        open,
        close,
        toggle,
        markAsRead,
    };
}
