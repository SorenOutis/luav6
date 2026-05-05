<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';

interface Props {
  className?: string;
  glowColor?: 'blue' | 'purple' | 'green' | 'red' | 'orange' | 'emerald';
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
  orange: { base: 30, spread: 200 }
};

const sizeMap = {
  sm: 'w-48 h-64',
  md: 'w-64 h-80',
  lg: 'w-80 h-96'
};

const isHovered = ref(false);
const targetX = ref(0);
const targetY = ref(0);
const currentX = ref(0);
const currentY = ref(0);
let animationFrameId: number;

const animate = () => {
  // 0.12 gives a smooth trailing delay when following the cursor, 0.08 gives a slower return
  const ease = isHovered.value ? 0.12 : 0.08; 
  
  if (Math.abs(targetX.value - currentX.value) > 0.1 || Math.abs(targetY.value - currentY.value) > 0.1) {
    currentX.value += (targetX.value - currentX.value) * ease;
    currentY.value += (targetY.value - currentY.value) * ease;
    
    const el = cardRef.value?.$el ?? cardRef.value;
    if (el) {
      el.style.setProperty('--x', currentX.value.toFixed(2));
      el.style.setProperty('--y', currentY.value.toFixed(2));
      
      const rect = el.getBoundingClientRect();
      const fakeClientX = rect.left + currentX.value;
      const fakeClientY = rect.top + currentY.value;
      
      el.style.setProperty('--xp', (fakeClientX / window.innerWidth).toFixed(2));
      el.style.setProperty('--yp', (fakeClientY / window.innerHeight).toFixed(2));
    }
  }
  
  animationFrameId = requestAnimationFrame(animate);
};

const handlePointerMove = (e: PointerEvent) => {
  const el = cardRef.value?.$el ?? cardRef.value;
  if (!el) return;
  
  const rect = el.getBoundingClientRect();
  targetX.value = e.clientX - rect.left;
  targetY.value = e.clientY - rect.top;
};

const handlePointerEnter = (e: PointerEvent) => {
  isHovered.value = true;
  
  const el = cardRef.value?.$el ?? cardRef.value;
  if (el) {
    const rect = el.getBoundingClientRect();
    targetX.value = e.clientX - rect.left;
    targetY.value = e.clientY - rect.top;
  }
};

const handlePointerLeave = () => {
  isHovered.value = false;
  targetX.value = 0;
  targetY.value = 0;
};

onMounted(() => {
  // Initialize with default 0,0 position
  const el = cardRef.value?.$el ?? cardRef.value;
  if (el) {
    el.style.setProperty('--x', '0');
    el.style.setProperty('--y', '0');
  }
  animationFrameId = requestAnimationFrame(animate);
});

onUnmounted(() => {
  cancelAnimationFrame(animationFrameId);
});

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
    touchAction: 'none',
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
  will-change: filter;
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
