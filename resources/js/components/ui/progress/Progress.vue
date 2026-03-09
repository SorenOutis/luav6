<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
  value?: number
  max?: number
  class?: HTMLAttributes['class']
}

withDefaults(defineProps<Props>(), {
  value: 0,
  max: 100,
})
</script>

<template>
  <div :class="cn('relative h-2 w-full overflow-hidden rounded-full bg-primary/20', $props.class)">
    <div
      :class="cn('h-full bg-primary transition-all')"
      :style="{ width: `${(value / max) * 100}%` }"
    />
  </div>
</template>
