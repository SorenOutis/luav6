<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, LayoutDashboard } from 'lucide-vue-next';

const props = defineProps<{
    canRegister: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
}>();

const emit = defineEmits(['magnetic', 'resetMagnetic']);

const words = ['Peak Performance.', 'Operational Elite.', 'Architectural Might.', 'System Synergy.', 'High Precision.'];
const currentWordIndex = ref(0);
const currentCharIndex = ref(words[0].length);
const isTyping = ref(false);
const typedText = ref(words[0]);
let typingTimeout: ReturnType<typeof setTimeout> | null = null;

const type = () => {
    const currentWord = words[currentWordIndex.value];
    
    if (isTyping.value) {
        typedText.value = currentWord.substring(0, currentCharIndex.value + 1);
        currentCharIndex.value++;
        
        if (currentCharIndex.value === currentWord.length) {
            isTyping.value = false;
            typingTimeout = setTimeout(type, 2500);
            return;
        }
    } else {
        typedText.value = currentWord.substring(0, currentCharIndex.value - 1);
        currentCharIndex.value--;
        
        if (currentCharIndex.value === 0) {
            isTyping.value = true;
            currentWordIndex.value = (currentWordIndex.value + 1) % words.length;
            typingTimeout = setTimeout(type, 800);
            return;
        }
    }
    
    let delay = isTyping.value ? 40 + Math.random() * 60 : 30;
    if (isTyping.value && typedText.value.endsWith(' ')) delay += 60 + Math.random() * 40;
    
    typingTimeout = setTimeout(type, delay);
};

onMounted(() => {
    typingTimeout = setTimeout(type, 2500);
});

onBeforeUnmount(() => {
    if (typingTimeout) {
        clearTimeout(typingTimeout);
    }
});

const handleMagnetic = (e: MouseEvent) => emit('magnetic', e);
const resetMagnetic = (e: MouseEvent) => emit('resetMagnetic', e);
</script>

<template>
    <div class="max-w-6xl relative">
        <slot name="background"></slot>

        <div class="hero-reveal overflow-hidden mb-2 lg:mb-4 relative z-10">
            <h1 class="reveal-content text-5xl sm:text-7xl lg:text-[8rem] font-black tracking-[-0.04em] leading-[0.9] sm:leading-[0.8] uppercase flex flex-col">
                <span>Learning Systems</span>
                <span class="bg-gradient-to-r from-muted-foreground/30 via-muted-foreground/15 to-muted-foreground/5 bg-clip-text text-transparent italic">Intelligence</span>
            </h1>
        </div>
        
        <div class="hero-reveal mb-10 lg:mb-16 lg:pl-2 relative">
            <p class="max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium leading-relaxed tracking-tight opacity-0 pointer-events-none select-none invisible whitespace-pre-wrap">
                Experience the smart assessment engine engineered for high-fidelity learning and academic growth in 
                <span class="font-black uppercase tracking-widest inline-flex items-center">
                    Architectural Might.<span class="ml-1 w-1 h-[0.8em] bg-primary"></span>
                </span> 
            </p>
            
            <p class="reveal-content absolute inset-0 max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium text-muted-foreground leading-relaxed tracking-tight">
                Experience the smart assessment engine engineered for high-fidelity learning and academic growth in 
                <span class="text-foreground font-black uppercase tracking-widest inline-flex items-center">
                    {{ typedText }}<span class="ml-1 w-1 h-[0.8em] bg-primary animate-[pulse_1s_infinite] shadow-[0_0_8px_var(--color-primary)]"></span>
                </span> 
            </p>
        </div>

        <div class="hero-reveal overflow-hidden p-2 -m-2">
            <div class="reveal-content flex flex-col sm:flex-row gap-4 sm:gap-6 lg:gap-8">
                <Link v-if="auth.user" :href="dashboard()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="group relative flex items-center justify-center bg-primary px-12 py-5 lg:py-6 text-primary-foreground transition-all active:scale-[0.98] shadow-[0_8px_40px_-12px] shadow-primary/30 -skew-x-[12deg] hover:bg-primary/90"
                >
                    <div class="skew-x-[12deg] flex items-center gap-4">
                        <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em] relative z-10">Access Dashboard</span>
                        <ArrowRight class="h-4 w-4 lg:h-5 lg:w-5 relative z-10 group-hover:translate-x-1 transition-transform" />
                    </div>
                </Link>
                <Link v-else :href="login()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="group relative flex items-center justify-center bg-foreground text-background px-12 py-5 lg:py-6 transition-all active:scale-[0.98] shadow-2xl -skew-x-[12deg] hover:bg-primary hover:text-primary-foreground"
                >
                    <div class="skew-x-[12deg] flex items-center gap-4">
                        <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em] relative z-10">Login to Hub</span>
                        <ArrowRight class="h-4 w-4 lg:h-5 lg:w-5 relative z-10 group-hover:translate-x-1 transition-transform" />
                    </div>
                </Link>
                
                <Link v-if="!auth.user && canRegister" :href="register()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="group relative flex items-center justify-center border-2 border-border/40 dark:border-border/20 px-12 py-5 lg:py-6 transition-all hover:bg-muted/30 hover:border-primary/30 active:scale-[0.98] text-muted-foreground -skew-x-[12deg]"
                >
                    <div class="skew-x-[12deg] flex items-center gap-4">
                        <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em]">Register Account</span>
                        <LayoutDashboard class="h-4 w-4 lg:h-5 lg:w-5 opacity-40 group-hover:opacity-100 group-hover:text-primary transition-all" />
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
