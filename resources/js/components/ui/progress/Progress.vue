<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  value?: number
  max?: number
  class?: HTMLAttributes['class']
  indicatorClass?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<Props>(), {
  value: 0,
  max: 100,
})

const safeMax = computed(() => (props.max > 0 ? props.max : 100))
const safeValue = computed(() =>
  Math.min(Math.max(props.value, 0), safeMax.value),
)
const percentage = computed(() => (safeValue.value / safeMax.value) * 100)
</script>

<template>
  <div
    role="progressbar"
    aria-label="Progress"
    :aria-valuemin="0"
    :aria-valuemax="safeMax"
    :aria-valuenow="safeValue"
    :class="cn('relative h-2 w-full overflow-hidden rounded-full bg-primary/20', props.class)"
  >
    <div
      :class="cn('h-full bg-primary transition-all', indicatorClass)"
      :style="{ width: `${percentage}%` }"
    />
  </div>
</template>
