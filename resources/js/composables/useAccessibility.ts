import { ref, onMounted } from 'vue';

export function useAccessibility() {
    const isDyslexiaFriendly = ref(false);

    const updateDyslexiaMode = (value: boolean) => {
        isDyslexiaFriendly.value = value;
        localStorage.setItem('dyslexia-friendly', value ? 'true' : 'false');

        if (value) {
            document.documentElement.classList.add('dyslexia-friendly');
        } else {
            document.documentElement.classList.remove('dyslexia-friendly');
        }
    };

    const toggleDyslexiaMode = () => {
        updateDyslexiaMode(!isDyslexiaFriendly.value);
    };

    onMounted(() => {
        const saved = localStorage.getItem('dyslexia-friendly');
        if (saved === 'true') {
            updateDyslexiaMode(true);
        }
    });

    return {
        isDyslexiaFriendly,
        toggleDyslexiaMode,
        updateDyslexiaMode,
    };
}
