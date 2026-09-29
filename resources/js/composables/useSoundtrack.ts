import * as inertia from '@inertiajs/vue3';
import { ref } from 'vue';
import type { UserSoundtrack } from '@/types/auth';

const router = (
    inertia as {
        router?: {
            on?: (
                event: string,
                callback: (event: {
                    detail: { page: { url: string } };
                }) => void,
            ) => () => void;
        };
    }
).router;

// Module-level reactive singleton state (persists across all Inertia page transitions)
const isPlaying = ref(false);
const isMuted = ref(true);
const volume = ref(1.0);
const previousVolume = ref(1.0);
const isPausedForExam = ref(false);
const wasPlayingBeforeExam = ref(false);
const eqBars = ref<[number, number, number, number]>([3, 3, 3, 3]);
const currentTrack = ref<UserSoundtrack | null>(null);
const userTrack = ref<UserSoundtrack | null>(null);

let globalAudio: HTMLAudioElement | null = null;
let audioCtx: AudioContext | null = null;
let analyserNode: AnalyserNode | null = null;
let gainNode: GainNode | null = null;
let mediaSourceNode: MediaElementAudioSourceNode | null = null;
let animFrameId: number | null = null;
let isStarting = false;
let routerListenerInitialized = false;

const isExamUrl = (url: string): boolean => {
    try {
        const path = (
            url.startsWith('http') ? new URL(url).pathname : url
        ).split('?')[0];
        // Only active exam-taking sessions (/exams/{id}...) pause the soundtrack.
        // The Activities Hub listing (/activities and /exams) is for browsing and should play music!
        return path.startsWith('/exams/') && path !== '/exams';
    } catch {
        return false;
    }
};

const getGlobalAudio = (): HTMLAudioElement => {
    if (!globalAudio && typeof window !== 'undefined') {
        globalAudio = new Audio();
        globalAudio.loop = true;
        globalAudio.crossOrigin = 'anonymous';
        globalAudio.preload = 'metadata';

        globalAudio.onended = () => {
            isPlaying.value = false;
            stopVisualizer();
        };
        globalAudio.onpause = () => {
            if (!isPausedForExam.value) {
                isPlaying.value = false;
                stopVisualizer();
            }
        };
    }
    return globalAudio!;
};

const setupAudioGraph = () => {
    if (audioCtx || typeof window === 'undefined') return;

    try {
        const AudioCtxClass =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext: typeof AudioContext })
                .webkitAudioContext;
        if (!AudioCtxClass) return;

        audioCtx = new AudioCtxClass();
        analyserNode = audioCtx.createAnalyser();
        analyserNode.fftSize = 128;
        analyserNode.smoothingTimeConstant = 0.65;

        gainNode = audioCtx.createGain();
        gainNode.gain.value = isMuted.value ? 0 : volume.value;

        const audio = getGlobalAudio();
        audio.volume = 1.0; // Pinned at unity; gainNode controls volume without double-attenuation

        mediaSourceNode = audioCtx.createMediaElementSource(audio);
        mediaSourceNode.connect(analyserNode);
        analyserNode.connect(gainNode);
        gainNode.connect(audioCtx.destination);
    } catch (e) {
        console.warn('Web Audio global graph setup:', e);
    }
};

const renderVisualizer = () => {
    if (!isPlaying.value || isPausedForExam.value) return;

    const effectiveVol = isMuted.value ? 0 : volume.value;

    if (analyserNode && effectiveVol > 0) {
        const data = new Uint8Array(analyserNode.frequencyBinCount);
        analyserNode.getByteFrequencyData(data);

        const bass =
            (((data[1] || 0) * 1.25 + (data[2] || 0)) / 2 / 255) * effectiveVol;
        const lowMid =
            (((data[4] || 0) + (data[5] || 0) + (data[6] || 0)) / 3 / 255) *
            effectiveVol;
        const mid =
            (((data[8] || 0) + (data[10] || 0) + (data[12] || 0)) / 3 / 255) *
            effectiveVol;
        const treble =
            (((data[16] || 0) + (data[20] || 0) + (data[24] || 0)) / 3 / 255) *
            effectiveVol;

        eqBars.value = [
            Math.max(3, Math.min(13, 3 + bass * 10)),
            Math.max(3, Math.min(13, 3 + lowMid * 10)),
            Math.max(3, Math.min(13, 3 + mid * 10)),
            Math.max(3, Math.min(13, 3 + treble * 10)),
        ];
    } else {
        eqBars.value = [3, 3, 3, 3];
    }

    animFrameId = requestAnimationFrame(renderVisualizer);
};

const startVisualizer = () => {
    setupAudioGraph();
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
    }
    if (animFrameId) cancelAnimationFrame(animFrameId);
    animFrameId = requestAnimationFrame(renderVisualizer);
};

const stopVisualizer = () => {
    if (animFrameId) {
        cancelAnimationFrame(animFrameId);
        animFrameId = null;
    }
    eqBars.value = [3, 3, 3, 3];
};

const pauseForExam = () => {
    if (isPlaying.value) {
        wasPlayingBeforeExam.value = true;
    }
    isPausedForExam.value = true;
    const audio = getGlobalAudio();
    audio.pause();
    isPlaying.value = false;
    stopVisualizer();
};

const resumeFromExam = () => {
    isPausedForExam.value = false;
    wasPlayingBeforeExam.value = false;
    play();
};

const initRouterListener = () => {
    if (routerListenerInitialized || typeof window === 'undefined') return;
    routerListenerInitialized = true;

    // Automatic pause / resume when navigating into or out of exams & activities
    if (typeof router?.on === 'function') {
        router.on('navigate', (event) => {
            const targetUrl = event.detail.page.url;

            if (isExamUrl(targetUrl)) {
                // Entering activities/exam page: pause if currently playing
                if (isPlaying.value && !isPausedForExam.value) {
                    pauseForExam();
                }
            } else {
                // Leaving activities/exam page: resume if it was playing before
                if (wasPlayingBeforeExam.value) {
                    resumeFromExam();
                }
            }
        });
    }
};

const initTrack = (track: UserSoundtrack | null | undefined) => {
    userTrack.value = track ?? null;

    if (!track) {
        if (isPlaying.value || (globalAudio && !globalAudio.paused)) {
            pause();
        }
        currentTrack.value = null;
        return;
    }

    if (!currentTrack.value || currentTrack.value.id !== track.id) {
        currentTrack.value = track;
        const audio = getGlobalAudio();
        const resolvedUrl =
            typeof window !== 'undefined'
                ? new URL(track.audioUrl, window.location.href).href
                : track.audioUrl;
        if (audio.src !== resolvedUrl) {
            audio.src = resolvedUrl;
        }
    }
};

const restoreUserTrack = () => {
    if (userTrack.value && currentTrack.value?.id !== userTrack.value.id) {
        pause();
        initTrack(userTrack.value);
    }
};

const playTrack = async (track: UserSoundtrack) => {
    if (!currentTrack.value || currentTrack.value.id !== track.id) {
        currentTrack.value = track;
        const audio = getGlobalAudio();
        const resolvedUrl =
            typeof window !== 'undefined'
                ? new URL(track.audioUrl, window.location.href).href
                : track.audioUrl;
        if (audio.src !== resolvedUrl) {
            audio.src = resolvedUrl;
        }
    }
    await play();
};

const getAnalyserFrequencyData = (
    target?: Uint8Array<ArrayBuffer>,
): Uint8Array<ArrayBuffer> | null => {
    if (!analyserNode) {
        setupAudioGraph();
    }
    if (!analyserNode) return null;
    const data = target ?? new Uint8Array(analyserNode.frequencyBinCount);
    analyserNode.getByteFrequencyData(data);
    return data;
};

const play = async () => {
    if (!currentTrack.value) return;

    // Never play inside active exam pages
    if (typeof window !== 'undefined' && isExamUrl(window.location.pathname)) {
        isPausedForExam.value = true;
        return;
    }

    if (isStarting) return;
    isStarting = true;

    try {
        setupAudioGraph();
        if (audioCtx && audioCtx.state === 'suspended') {
            await audioCtx.resume();
        }

        const audio = getGlobalAudio();
        const resolvedUrl =
            typeof window !== 'undefined'
                ? new URL(currentTrack.value.audioUrl, window.location.href)
                      .href
                : currentTrack.value.audioUrl;
        if (audio.src !== resolvedUrl) {
            audio.src = resolvedUrl;
        }

        isMuted.value = false;
        if (volume.value === 0) volume.value = previousVolume.value || 1.0;

        const targetGain = volume.value;
        if (gainNode && audioCtx) {
            gainNode.gain.cancelScheduledValues(audioCtx.currentTime);
            gainNode.gain.setValueAtTime(targetGain, audioCtx.currentTime);
        }

        await audio.play();
        isPlaying.value = true;
        isPausedForExam.value = false;
        startVisualizer();
    } catch (err) {
        console.error('Soundtrack play error:', err);
        isPlaying.value = false;
        stopVisualizer();
    } finally {
        isStarting = false;
    }
};

const pause = () => {
    const audio = getGlobalAudio();
    audio.pause();
    isPlaying.value = false;
    stopVisualizer();
};

const togglePlay = () => {
    if (isPlaying.value) {
        pause();
    } else {
        play();
    }
};

const setVolume = async (val: number) => {
    const clamped = Math.max(0, Math.min(1, val));
    volume.value = clamped;

    if (clamped > 0) {
        previousVolume.value = clamped;
        isMuted.value = false;

        if (!isPlaying.value && !isStarting && !isPausedForExam.value) {
            await play();
        }
    } else {
        isMuted.value = true;
    }

    const targetGain = isMuted.value ? 0 : volume.value;
    if (gainNode && audioCtx) {
        gainNode.gain.cancelScheduledValues(audioCtx.currentTime);
        gainNode.gain.setValueAtTime(targetGain, audioCtx.currentTime);
    } else if (globalAudio) {
        globalAudio.volume = targetGain;
    }
};

const toggleMute = async () => {
    if (!isPlaying.value) {
        isMuted.value = false;
        if (volume.value === 0) volume.value = previousVolume.value || 1.0;
        await play();
        return;
    }

    if (isMuted.value || volume.value === 0) {
        isMuted.value = false;
        volume.value = previousVolume.value || 1.0;
        setVolume(volume.value);
    } else {
        previousVolume.value = volume.value;
        isMuted.value = true;
        setVolume(0);
    }
};

export function useSoundtrack() {
    initRouterListener();

    return {
        currentTrack,
        isPlaying,
        isMuted,
        volume,
        eqBars,
        isPausedForExam,
        wasPlayingBeforeExam,
        initTrack,
        play,
        pause,
        togglePlay,
        toggleMute,
        setVolume,
        playTrack,
        restoreUserTrack,
        getAnalyserFrequencyData,
        pauseForExam,
        resumeFromExam,
    };
}
