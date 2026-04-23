import { reactive, toRefs } from 'vue';

export const LOADER_MESSAGES = {
    INITIALIZING: 'INITIALIZING',
    TERMINATING: 'TERMINATING SESSION',
} as const;

export type LoaderMessage = typeof LOADER_MESSAGES[keyof typeof LOADER_MESSAGES] | string;

const DEV = import.meta.env.DEV;
const log = (...args: unknown[]) => { if (DEV) console.log(...args); };

const state = reactive({
    isVisible: false,
    pendingHide: false,
    message: LOADER_MESSAGES.INITIALIZING as LoaderMessage,
});

export function useLoader() {
    const show = (message: LoaderMessage = LOADER_MESSAGES.INITIALIZING) => {
        state.isVisible = true;
        state.pendingHide = false;
        state.message = message;
        log('[useLoader] state.isVisible set to true with message:', message);
    };

    const hide = () => {
        state.isVisible = false;
        state.pendingHide = false;
        log('[useLoader] state.isVisible set to false');
    };

    /**
     * Signal that the loader should hide, but only after
     * the progress bar has reached 100% (handled by GlobalLoader).
     */
    const hideWhenReady = () => {
        state.pendingHide = true;
        log('[useLoader] pendingHide set to true — waiting for progress 100%');
    };

    return {
        ...toRefs(state),
        show,
        hide,
        hideWhenReady,
    };
}
