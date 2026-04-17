import { reactive, toRefs } from 'vue';

const state = reactive({
    isVisible: false,
    pendingHide: false,
    message: 'INITIALIZING',
});

export function useLoader() {
    const show = (message: string = 'INITIALIZING') => {
        state.isVisible = true;
        state.pendingHide = false;
        state.message = message;
        console.log('[useLoader] state.isVisible set to true with message:', message);
    };

    const hide = () => {
        state.isVisible = false;
        state.pendingHide = false;
        console.log('[useLoader] state.isVisible set to false');
    };

    /**
     * Signal that the loader should hide, but only after
     * the progress bar has reached 100% (handled by GlobalLoader).
     */
    const hideWhenReady = () => {
        state.pendingHide = true;
        console.log('[useLoader] pendingHide set to true — waiting for progress 100%');
    };

    return {
        ...toRefs(state),
        show,
        hide,
        hideWhenReady,
    };
}
