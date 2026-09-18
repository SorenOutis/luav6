import { defineComponent, h } from 'vue';

export const Motion = defineComponent({
    props: ['initial', 'animate', 'in-view', 'in-view-options', 'transition'],
    setup(_: any, { slots }: any) {
        return () => h('div', slots.default?.());
    },
});

export const Presence = defineComponent({
    setup(_: any, { slots }: any) {
        return () => h('div', slots.default?.());
    },
});

export default { Motion, Presence };
