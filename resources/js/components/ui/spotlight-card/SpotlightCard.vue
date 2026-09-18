<script setup lang="ts">
import { ref, computed } from 'vue';

interface Props {
  className?: string;
  glowColor?: 'blue' | 'purple' | 'green' | 'red' | 'orange' | 'emerald' | 'yellow';
  size?: 'sm' | 'md' | 'lg';
  spotlightSize?: number;
  radius?: number;
  width?: string | number;
  height?: string | number;
  customSize?: boolean; // When true, ignores size prop and uses width/height or className
  as?: any;
}

const props = withDefaults(defineProps<Props>(), {
  className: '',
  glowColor: 'blue',
  size: 'md',
  spotlightSize: 200,
  radius: 26,
  customSize: false,
  as: 'div',
});

const cardRef = ref<HTMLElement | any>(null);

const glowColorMap = {
  blue: { base: 220, spread: 200 },
  purple: { base: 280, spread: 300 },
  green: { base: 120, spread: 200 },
  emerald: { base: 160, spread: 200 },
  red: { base: 0, spread: 200 },
  orange: { base: 30, spread: 200 },
  yellow: { base: 45, spread: 100 }
};

const sizeMap = {
  sm: 'w-48 h-64',
  md: 'w-64 h-80',
  lg: 'w-80 h-96'
};

// The old implementation ran a requestAnimationFrame loop for every card,
// including cards the user was not interacting with. Update CSS variables
// only for the card receiving pointer input instead.
const updatePointerPosition = (e: PointerEvent) => {
  const el = cardRef.value?.$el ?? cardRef.value;
  if (!el) return;

  const rect = el.getBoundingClientRect();
  el.style.setProperty('--x', (e.clientX - rect.left).toFixed(2));
  el.style.setProperty('--y', (e.clientY - rect.top).toFixed(2));
  el.style.setProperty('--xp', (e.clientX / window.innerWidth).toFixed(2));
  el.style.setProperty('--yp', (e.clientY / window.innerHeight).toFixed(2));
};

const handlePointerMove = (e: PointerEvent) => {
  updatePointerPosition(e);
};

const handlePointerEnter = (e: PointerEvent) => {
  updatePointerPosition(e);
};

const handlePointerLeave = () => {
  const el = cardRef.value?.$el ?? cardRef.value;
  if (el) {
    el.style.setProperty('--x', '0');
    el.style.setProperty('--y', '0');
  }
};

const sizeClasses = computed(() => {
  if (props.customSize) return '';
  return sizeMap[props.size as keyof typeof sizeMap];
});

const inlineStyles = computed(() => {
  const { base, spread } = glowColorMap[props.glowColor];
  
  const baseStyles: Record<string, any> = {
    '--base': base,
    '--spread': spread,
    '--radius': String(props.radius),
    '--border': '2',
    '--backdrop': 'hsl(0 0% 60% / 0.12)',
    '--backup-border': 'var(--backdrop)',
    '--size': String(props.spotlightSize),
    '--outer': '1',
    '--border-size': 'calc(var(--border, 2) * 1px)',
    '--spotlight-size': 'calc(var(--size, 150) * 1px)',
    '--hue': 'calc(var(--base) + (var(--xp, 0) * var(--spread, 0)))',
    backgroundImage: `radial-gradient(
      var(--spotlight-size) var(--spotlight-size) at
      calc(var(--x, 0) * 1px)
      calc(var(--y, 0) * 1px),
      hsl(var(--hue, 210) calc(var(--saturation, 100) * 1%) calc(var(--lightness, 70) * 1%) / var(--bg-spot-opacity, 0.1)), transparent
    )`,
    backgroundColor: 'var(--backdrop, transparent)',
    backgroundSize: 'calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)))',
    backgroundPosition: '50% 50%',
    border: 'var(--border-size) solid var(--backup-border)',
    position: 'relative',
    // Allow vertical touch scrolling (otherwise the card traps scroll on
    // mobile when it fills the viewport — e.g. leaderboard podium cards).
    touchAction: 'pan-y',
  };

  if (props.width !== undefined) {
    baseStyles.width = typeof props.width === 'number' ? `${props.width}px` : props.width;
  }
  if (props.height !== undefined) {
    baseStyles.height = typeof props.height === 'number' ? `${props.height}px` : props.height;
  }

  return baseStyles;
});
</script>

<template>
  <component
    :is="as"
    ref="cardRef"
    data-glow
    :style="inlineStyles"
    :class="[
      sizeClasses,
      !customSize ? 'aspect-[3/4]' : '',
      'rounded-2xl relative grid shadow-[0_1rem_2rem_-1rem_black] backdrop-blur-[5px]',
      className
    ]"
    @pointermove="handlePointerMove"
    @pointerenter="handlePointerEnter"
    @pointerleave="handlePointerLeave"
  >
    <div data-glow></div>
    <slot></slot>
  </component>
</template>

<style>
[data-glow]::before,
[data-glow]::after {
  pointer-events: none;
  content: "";
  position: absolute;
  inset: calc(var(--border-size) * -1);
  border: var(--border-size) solid transparent;
  border-radius: calc(var(--radius) * 1px);
  background-size: calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
  background-repeat: no-repeat;
  background-position: 50% 50%;
  -webkit-mask: linear-gradient(white, white) border-box, linear-gradient(white, white) padding-box;
  -webkit-mask-composite: destination-out;
  mask: linear-gradient(white, white) border-box exclude, linear-gradient(white, white) padding-box;
}

[data-glow]::before {
  background-image: radial-gradient(
    calc(var(--spotlight-size) * 0.75) calc(var(--spotlight-size) * 0.75) at
    calc(var(--x, 0) * 1px)
    calc(var(--y, 0) * 1px),
    hsl(var(--hue, 210) calc(var(--saturation, 100) * 1%) calc(var(--lightness, 50) * 1%) / var(--border-spot-opacity, 1)), transparent 100%
  );
  filter: brightness(2);
}

[data-glow]::after {
  background-image: radial-gradient(
    calc(var(--spotlight-size) * 0.5) calc(var(--spotlight-size) * 0.5) at
    calc(var(--x, 0) * 1px)
    calc(var(--y, 0) * 1px),
    hsl(0 100% 100% / var(--border-light-opacity, 1)), transparent 100%
  );
}

[data-glow] > [data-glow] {
  position: absolute;
  inset: 0;
  opacity: var(--outer, 1);
  border-radius: calc(var(--radius) * 1px);
  border-width: calc(var(--border-size) * 20);
  filter: blur(calc(var(--border-size) * 10));
  background: none;
  pointer-events: none;
  border: none;
}

[data-glow] > [data-glow]::before {
  inset: -10px;
  border-width: 10px;
}

.theme-transitioning [data-glow] > [data-glow] {
  display: none !important;
}
</style>
