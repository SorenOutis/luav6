/**
 * The `@motionone/vue` `<Motion>` component supports `once` in its
 * `in-view-options` prop (the runtime destructures it and stops observing
 * after the first intersection), but the shipped `InViewOptions` type omits
 * it. This augmentation restores that option so templates type-check.
 */
declare module '@motionone/dom' {
    interface InViewOptions {
        /** Whether to stop observing after the first intersection. */
        once?: boolean;
    }
}

export {};
