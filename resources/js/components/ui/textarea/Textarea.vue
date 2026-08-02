<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

// Native `v-model` on <textarea> type-checks the bound ref strictly, and
// `useVModel` returns a Ref from @vueuse's own bundled copy of @vue/reactivity,
// which vue-tsc rejects. A plain computed from this project's `vue` resolves
// the same behavior with types vue-tsc accepts.
const modelValue = computed<string | number | undefined>({
  get: () => props.modelValue ?? props.defaultValue,
  set: (value) => {
    emits('update:modelValue', value ?? '')
  },
})
</script>

<template>
  <textarea
    v-model="modelValue"
    :class="cn(
      'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  />
</template>
