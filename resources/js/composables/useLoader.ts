import { reactive, toRefs } from 'vue';

const state = reactive({
    isVisible: false
});

export function useLoader() {
    const show = () => {
        state.isVisible = true;
        console.log('[useLoader] state.isVisible set to true');
    };

    const hide = () => {
        state.isVisible = false;
        console.log('[useLoader] state.isVisible set to false');
    };

    return {
        ...toRefs(state),
        show,
        hide
    };
}
