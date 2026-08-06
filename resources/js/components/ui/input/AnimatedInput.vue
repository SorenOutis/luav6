<script setup lang="ts">
import { ref, computed } from 'vue'
import { cn } from '@/lib/utils'
import { useVModel } from "@vueuse/core"

defineOptions({
  inheritAttrs: false
})

const props = defineProps<{
  modelValue?: string | number
  label: string
  class?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emit, {
  passive: true,
})

const isFocused = ref(false)
const internalValue = ref('')

const inputValue = computed(() => {
  return modelValue.value !== undefined ? String(modelValue.value) : internalValue.value
})

const showLabel = computed(() => isFocused.value || inputValue.value.length > 0)

const onInput = (event: Event) => {
  internalValue.value = (event.target as HTMLInputElement).value
}

const chars = computed(() => props.label.split(''))
</script>

<template>
  <div :class="cn('relative pt-4', props.class)">
    <div
      class="absolute top-1/2 -translate-y-1/2 pointer-events-none text-foreground flex mt-2"
    >
      <span
        v-for="(char, index) in chars"
        :key="index"
        class="inline-block text-sm"
        :class="{
          '-translate-y-[120%] text-muted-foreground': showLabel,
          'translate-y-0 text-inherit': !showLabel
        }"
        :style="{
          transition: 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.2)',
          transitionDelay: showLabel ? `${index * 30}ms` : '0ms',
          willChange: 'transform'
        }"
      >
        {{ char === ' ' ? '&nbsp;' : char }}
      </span>
    </div>

    <input
      v-model="modelValue"
      @focus="isFocused = true"
      @blur="isFocused = false"
      @input="onInput"
      v-bind="$attrs"
      class="outline-none border-b-2 border-foreground py-2 w-full text-base font-medium text-foreground bg-transparent placeholder-transparent"
    />
  </div>
</template>

<style>
/* Override browser's default autofill styling */
input:-webkit-autofill,
input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, 
input:-webkit-autofill:active {
    -webkit-text-fill-color: var(--color-foreground) !important;
    transition: background-color 5000s ease-in-out 0s;
}
</style>
