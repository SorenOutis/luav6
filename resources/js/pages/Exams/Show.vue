<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import axios from 'axios';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Calendar,
    Clock,
    ChevronLeft,
    ChevronRight,
    CheckCircle2,
    FileText,
    ArrowRight,
    Layers,
    ListChecks,
    Lock,
    Flag,
    Zap,
} from 'lucide-vue-next';
import {
    onMounted,
    onUnmounted,
    ref,
    computed,
    reactive,
    watch,
} from 'vue';
import { useAccessibility } from '@/composables/useAccessibility';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { isVisible: isLoaderVisible } = useLoader();
const { isDyslexiaFriendly, toggleDyslexiaMode, updateDyslexiaMode } =
    useAccessibility();
const isBooted = ref(false);

gsap.registerPlugin(ScrollTrigger);

interface Question {
    text: string;
    type: string;
    options: { text: string; is_correct: boolean }[] | null;
    correct_answer: string | null;
    points: number;
}

interface ExamPart {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    questions: Question[] | null;
    points: number;
}

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    duration_minutes: number;
    status: string;
    parts: ExamPart[];
}

const props = defineProps<{
    exam: Exam;
    submissions: Record<number, { status: string; score: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
    { title: props.exam.title, href: `/exams/${props.exam.id}` },
];

const selectedPart = ref<ExamPart | null>(null);
const examStarted = ref(false);
const container = ref<HTMLElement | null>(null);

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const answers = reactive<Record<number, string | number>>({}); // Store answers by question index
const isSubmitting = ref(false);
const isFinalSubmitting = ref(false);
const showSuccessModal = ref(false);
const isCalculatingScore = ref(false);
const successModalRef = ref<HTMLElement | null>(null);

const showStartModal = ref(false);
const startModalRef = ref<HTMLElement | null>(null);
const pendingPart = ref<ExamPart | null>(null);
const pendingIndex = ref<number | null>(null);
const isFullscreen = ref(false);
const showFullscreenLockout = ref(false);
const lockoutModalRef = ref<HTMLElement | null>(null);

const partsPendingCount = ref(0);
const displayedScore = ref(0); // For GSAP counter animation
const flaggedQuestions = ref<Set<number>>(new Set());
const partStartTime = ref<number | null>(null);
const estimatedFinishMinutes = ref<number | null>(null);
const lastSavedAt = ref<string | null>(null);
const pendingUnlockIndex = ref<number | null>(null);

const typedSequence = ref('');
const SECRET_COMMAND = 'blyat';
const isAdminBypass = ref(false);

const showUnansweredWarning = ref(false);
const unansweredWarningRef = ref<HTMLElement | null>(null);
const hasShownUnansweredWarning = ref(false);
const isTimeoutSubmission = ref(false);
const currentPartHasEssay = ref(false);
const calcCountdown = ref(0);

const unansweredCount = computed(() => {
    if (!selectedPart.value || !selectedPart.value.questions) return 0;

    let count = 0;
    selectedPart.value.questions.forEach((q, index) => {
        const answer = answers[index];
        // Count as unanswered if answer is undefined, null, or an empty string (for essays/identification)
        if (
            answer === undefined ||
            answer === null ||
            (typeof answer === 'string' && answer.trim() === '')
        ) {
            count++;
        }
    });
    return count;
});

const totalQuestions = computed(() =>
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.length ?? 0), 0),
);

// ─── LIVE TIMER LOGIC ───────────────────────────────────────
const timeLeftSeconds = ref(props.exam.duration_minutes * 60);
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null);
const monitorHeartbeatInterval = ref<ReturnType<typeof setInterval> | null>(
    null,
);

const getAnsweredCount = () =>
    Object.values(answers).filter(
        (value) =>
            value !== undefined &&
            value !== null &&
            String(value).trim() !== '',
    ).length;

const sendMonitorProgress = async (
    status:
        | 'starting'
        | 'in_progress'
        | 'submitting'
        | 'finished' = 'in_progress',
) => {
    if (!examStarted.value && status === 'in_progress') {
        return;
    }

    try {
        await axios.post(`/exams/${props.exam.id}/monitor-progress`, {
            status,
            exam_part_id: selectedPart.value?.id ?? null,
            submitted_parts_count: submittedPartsCount.value,
            current_part_answered_count: selectedPart.value
                ? getAnsweredCount()
                : 0,
            current_part_total_questions:
                selectedPart.value?.questions?.length ?? 0,
        });
    } catch {
        // Heartbeat failures should not interrupt the exam flow.
    }
};

const formattedTime = computed(() => {
    const mins = Math.floor(timeLeftSeconds.value / 60);
    const secs = timeLeftSeconds.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const startTimer = () => {
    if (timerInterval.value) return;
    partStartTime.value = Date.now();
    timerInterval.value = setInterval(() => {
        if (timeLeftSeconds.value > 0) {
            timeLeftSeconds.value--;
            calculatePace();
        } else {
            stopTimer();
            if (examStarted.value && !isSubmitting.value) {
                isTimeoutSubmission.value = true;
                submitPart(); // Auto-submit on timeout
            }
        }
    }, 1000);
};

const calculatePace = () => {
    if (!partStartTime.value || !selectedPart.value) return;

    const answeredCount = Object.keys(answers).length;
    if (answeredCount === 0) {
        estimatedFinishMinutes.value = props.exam.duration_minutes;
        return;
    }

    const elapsedSeconds = (Date.now() - partStartTime.value) / 1000;
    const avgSecondsPerQuestion = elapsedSeconds / answeredCount;

    // Estimate based on ALL questions in the exam, not just the current part
    
    const remainingQuestionsInPart =
        (selectedPart.value.questions?.length ?? 0) - answeredCount;

    if (remainingQuestionsInPart > 0) {
        estimatedFinishMinutes.value = Math.ceil(
            (remainingQuestionsInPart * avgSecondsPerQuestion) / 60,
        );
    } else {
        estimatedFinishMinutes.value = 0;
    }
};

const overallProgress = computed(() => {
    if (props.exam.parts.length === 0) return 0;
    return (submittedPartsCount.value / props.exam.parts.length) * 100;
});

const partProgress = computed(() => {
    if (!selectedPart.value || !selectedPart.value.questions) return 0;
    const answeredCount = Object.keys(answers).length;
    return (answeredCount / selectedPart.value.questions.length) * 100;
});

const stopTimer = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
};

// ─── PROGRESS NAVIGATOR LOGIC ──────────────────────────────────
const getQuestionStatus = (index: number) => {
    if (flaggedQuestions.value.has(index)) return 'flagged';
    if (
        answers[index] !== undefined &&
        answers[index] !== '' &&
        answers[index] !== null
    )
        return 'answered';
    return 'pending';
};

const toggleFlag = (index: number) => {
    if (flaggedQuestions.value.has(index)) {
        flaggedQuestions.value.delete(index);
    } else {
        flaggedQuestions.value.add(index);
    }
    saveDraft();
};

// ─── AUTO-SAVE LOGIC ───────────────────────────────────────
const DRAFT_KEY_PREFIX = 'exam_draft_';

const getDraftKey = () =>
    `${DRAFT_KEY_PREFIX}${props.exam.id}_${selectedPart.value?.id}`;

const saveDraft = () => {
    if (!selectedPart.value) return;
    const draft = {
        answers: { ...answers },
        flagged: Array.from(flaggedQuestions.value),
        timeLeft: timeLeftSeconds.value,
        timestamp: Date.now(),
    };
    localStorage.setItem(getDraftKey(), JSON.stringify(draft));
    lastSavedAt.value = new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

    // Pulse animation for sync heartbeat
    gsap.fromTo(
        '.sync-heartbeat',
        { scale: 1, opacity: 0.5 },
        {
            scale: 1.2,
            opacity: 1,
            duration: 0.3,
            yoyo: true,
            repeat: 1,
            ease: 'power2.out',
        },
    );
};

const loadDraft = () => {
    if (!selectedPart.value) return;
    const saved = localStorage.getItem(getDraftKey());
    if (saved) {
        try {
            const draft = JSON.parse(saved);
            // Only load if recent (e.g., within the last 2 hours)
            if (Date.now() - draft.timestamp < 2 * 60 * 60 * 1000) {
                Object.assign(answers, draft.answers);
                flaggedQuestions.value = new Set(draft.flagged || []);
                // If the saved time is significantly different, we could sync it,
                // but usually the server-side timer is authority.
                // For now, we trust the props timer unless it's a refresh.
            }
        } catch (e) {
            console.error('Failed to load draft', e);
        }
    }
};

const clearDraft = () => {
    localStorage.removeItem(getDraftKey());
};

watch(selectedPart, (newVal) => {
    if (newVal === null) {
        // We no longer need runEntranceAnimations here as we use Motion components
    }
});

// ─── INTEGRITY & ANTI-CHEATING ───────────────────────────────
const integrityWarnings = ref(0);
const showIntegrityAlert = ref(false);

const handleVisibilityChange = () => {
    if (isAdminBypass.value) return;

    if (
        (document.visibilityState === 'hidden' || !document.hasFocus()) &&
        examStarted.value
    ) {
        integrityWarnings.value++;
        showIntegrityAlert.value = true;

        // --- NEW: Trigger full lockout on Alt+Tab/Focus Loss ---
        showFullscreenLockout.value = true;

        // Stop the keyboard lock if it was active (re-entered on resume)
        if ('keyboard' in navigator && (navigator as any).keyboard.unlock) {
            (navigator as any).keyboard.unlock();
        }

        // Animate lockout modal
        setTimeout(() => {
            if (lockoutModalRef.value) {
                gsap.fromTo(
                    lockoutModalRef.value,
                    { opacity: 0, scale: 0.9, y: 50 },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.8,
                        ease: 'elastic.out(1, 0.7)',
                    },
                );
            }
        }, 10);

        // Auto-close alert after 5 seconds
        setTimeout(() => {
            showIntegrityAlert.value = false;
        }, 5000);
    }
};

const preventCheatingActions = (e: Event) => {
    if (examStarted.value) {
        e.preventDefault();
        return false;
    }
};

const submittedPartsCount = computed(
    () => Object.keys(props.submissions).length,
);

const allPartsSubmitted = computed(
    () =>
        submittedPartsCount.value === props.exam.parts.length &&
        props.exam.parts.length > 0,
);

// Trigger unlock animation setup when progress changes
watch(submittedPartsCount, (newCount, oldCount) => {
    if (newCount > oldCount) {
        // Queue the next index for animation once we return to the list view
        pendingUnlockIndex.value = newCount;
    }
});

const totalScore = computed(() =>
    Object.values(props.submissions).reduce(
        (sum, s) => sum + (Number(s.score) || 0),
        0,
    ),
);

const isExamPendingReview = computed(() =>
    Object.values(props.submissions).some((s) => s.status === 'pending_review'),
);

const totalPossiblePoints = computed(() =>
    props.exam.parts.reduce(
        (sum, p) =>
            sum +
            (p.questions?.reduce(
                (qSum, q) => qSum + (q.points ?? p.points ?? 1),
                0,
            ) ?? 0),
        0,
    ),
);

const remainingPartsCount = computed(
    () => props.exam.parts.length - submittedPartsCount.value,
);

const nextPartId = computed(() => {
    // The next part is the first one that hasn't been submitted and isn't locked
    const nextPart = props.exam.parts.find(
        (part, index) => !isPartSubmitted(part.id) && !isPartLocked(index),
    );
    return nextPart ? nextPart.id : null;
});

const isPartSubmitted = (partId: number) => {
    return !!props.submissions[String(partId)];
};

const isPartLocked = (index: number) => {
    if (index === 0) return false;
    const previousPart = props.exam.parts[index - 1];
    return !isPartSubmitted(previousPart.id);
};

const getQuestionTypes = (part: ExamPart) => [
    ...new Set(part.questions?.map((q) => q.type) ?? []),
];

const formatType = (type: string) => type.replace(/_/g, ' ');

const selectPart = (part: ExamPart, index: number) => {
    // Prevent selecting if exam is closed, part is already submitted, or part is locked
    if (
        props.exam.status === 'closed' ||
        isPartSubmitted(part.id) ||
        isPartLocked(index)
    ) {
        return;
    }

    pendingPart.value = part;
    pendingIndex.value = index;
    showStartModal.value = true;

    // Animate start confirmation modal
    setTimeout(() => {
        if (startModalRef.value) {
            gsap.fromTo(
                startModalRef.value,
                { opacity: 0, scale: 0.85, y: 30 },
                { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' },
            );
        }
    }, 10);
};

const confirmStart = async () => {
    if (!pendingPart.value) return;

    try {
        await reEnterFullscreen();
    } catch (err) {
        console.warn('Fullscreen request failed:', err);
    }

    selectedPart.value = pendingPart.value;
    showStartModal.value = false;
    startPart();
};

const reEnterFullscreen = async () => {
    const element = document.documentElement;
    try {
        if (element.requestFullscreen) {
            await element.requestFullscreen();
        } else if ((element as any).webkitRequestFullscreen) {
            await (element as any).webkitRequestFullscreen();
        } else if ((element as any).msRequestFullscreen) {
            await (element as any).msRequestFullscreen();
        }

        // Try to lock keyboard if supported (Chromium only, requires user gesture)
        if ('keyboard' in navigator && (navigator as any).keyboard.lock) {
            await (navigator as any).keyboard.lock([
                'Escape',
                'F11',
                'Tab',
                'MetaLeft',
                'MetaRight',
                'AltLeft',
                'AltRight',
            ]);
        }

        isFullscreen.value = true;
        showFullscreenLockout.value = false;
    } catch (err) {
        console.warn('Fullscreen/Keyboard lock failed:', err);
    }
};

const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;

    // If they exit full screen while the exam is in progress
    if (!isFullscreen.value && isExamInProgress.value && !isAdminBypass.value) {
        showFullscreenLockout.value = true;
        integrityWarnings.value++;
        showIntegrityAlert.value = true;

        // Stop the keyboard lock if it was active
        if ('keyboard' in navigator && (navigator as any).keyboard.unlock) {
            (navigator as any).keyboard.unlock();
        }

        // Animate lockout modal
        setTimeout(() => {
            if (lockoutModalRef.value) {
                gsap.fromTo(
                    lockoutModalRef.value,
                    { opacity: 0, scale: 0.9, y: 50 },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.8,
                        ease: 'elastic.out(1, 0.7)',
                    },
                );
            }
        }, 10);

        setTimeout(() => {
            showIntegrityAlert.value = false;
        }, 5000);
    }
};

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    const isExamInProgress =
        examStarted.value ||
        (submittedPartsCount.value > 0 && !allPartsSubmitted.value);
    if (isExamInProgress && !isSubmitting.value) {
        e.preventDefault();
        e.returnValue =
            'Assessment Protocol Active: You have not completed all parts of the exam. Exiting now will compromise your submission. Are you sure?';
        return e.returnValue;
    }
};

const startPart = () => {
    // Reset state for the new part
    Object.keys(answers).forEach((key) => delete answers[Number(key)]);
    flaggedQuestions.value.clear();
    estimatedFinishMinutes.value = null;
    lastSavedAt.value = null;

    examStarted.value = true;
    startTimer(); // Start the countdown when the first section begins
    loadDraft(); // Load any saved progress
    void sendMonitorProgress('starting');

    setTimeout(() => {
        gsap.fromTo(
            '.question-card',
            {
                opacity: 0,
                y: 35,
                scale: 0.97,
                rotationX: -4,
                transformOrigin: 'center top',
            },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                rotationX: 0,
                duration: 0.5,
                stagger: 0.08,
                ease: 'power3.out',
            },
        );
    }, 10);
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    const isExamInProgress =
        examStarted.value ||
        (submittedPartsCount.value > 0 && !allPartsSubmitted.value);

    // ─── ADMIN SECRET COMMAND (blyat) ───────────────────────
    // Track keys to detect secret command to exit/enter fullscreen
    if (isExamInProgress) {
        typedSequence.value += e.key.toLowerCase();
        if (typedSequence.value.includes(SECRET_COMMAND)) {
            typedSequence.value = '';
            isAdminBypass.value = !isAdminBypass.value;

            if (isAdminBypass.value) {
                // Admin entering bypass mode: Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            } else {
                // Admin exiting bypass mode: Re-enter fullscreen
                reEnterFullscreen();
            }
            return;
        }
        // Keep sequence short to avoid memory bloat
        if (typedSequence.value.length > 20) {
            typedSequence.value = typedSequence.value.slice(-10);
        }
    }

    // ─── ANTI-CHEATING ───────────────────────────────────────
    // If admin bypass is active, don't block anything or trigger alerts
    if (isAdminBypass.value) return;

    /**
     * SECURITY NOTE: Ctrl+Alt+Del is a hardware interrupt handled by the OS kernel.
     * No web browser can intercept or block it for security reasons.
     * Instead, we rely on the Focus Loss/Alt+Tab detection to log such events.
     */

    // Block Alt+Tab, Alt+Esc, Alt+F4, Windows Key (Meta), and generic Ctrl+Alt combos
    if (
        isExamInProgress &&
        (e.altKey || e.metaKey || (e.ctrlKey && e.altKey))
    ) {
        e.preventDefault();
        integrityWarnings.value++;
        showIntegrityAlert.value = true;
        setTimeout(() => {
            showIntegrityAlert.value = false;
        }, 3000);
        return;
    }

    // Block Ctrl+Shift+Esc (Task Manager)
    if (isExamInProgress && e.ctrlKey && e.shiftKey && e.key === 'Escape') {
        e.preventDefault();
        return;
    }

    // Block Windows Key (Meta) specifically
    if (isExamInProgress && e.key === 'Meta') {
        e.preventDefault();
        return;
    }

    // Block Alt key alone during exam
    if (isExamInProgress && e.key === 'Alt') {
        e.preventDefault();
        return;
    }

    // Block Tab key during exam (prevent focus shifting)
    if (isExamInProgress && e.key === 'Tab') {
        e.preventDefault();
        return;
    }

    // Block ESC key during exam to prevent exiting full screen
    if (isExamInProgress && e.key === 'Escape') {
        e.preventDefault();
        return;
    }

    // Block F5 and Ctrl+R during exam
    if (isExamInProgress && (e.key === 'F5' || (e.key === 'r' && e.ctrlKey))) {
        e.preventDefault();
        return;
    }

    if (!examStarted.value || isSubmitting.value) return;

    const activeElem = document.activeElement;
    const isInput =
        activeElem?.tagName === 'INPUT' || activeElem?.tagName === 'TEXTAREA';

    // Numbers 1-9 for picking MCQ options
    if (!isInput && /^[1-9]$/.test(e.key)) {
        const cards = document.querySelectorAll('.question-card');
        const middle = window.innerHeight / 2;
        let bestCard = null;
        let minDistance = Infinity;

        cards.forEach((card) => {
            const rect = card.getBoundingClientRect();
            const distance = Math.abs(rect.top + rect.height / 2 - middle);
            if (distance < minDistance) {
                minDistance = distance;
                bestCard = card;
            }
        });

        if (bestCard) {
            const idParts = (bestCard as HTMLElement).id.split('-');
            const qIndex = parseInt(idParts[1]);
            const optionIndex = parseInt(e.key) - 1;
            const question = selectedPart.value?.questions?.[qIndex];
            if (
                question &&
                (question.type === 'multiple_choice' ||
                    question.type === 'true_false')
            ) {
                if (question.options && optionIndex < question.options.length) {
                    answers[qIndex] = optionIndex;
                }
            }
        }
    }

    // 'F' for Flagging
    if (!isInput && e.key.toLowerCase() === 'f') {
        const cards = document.querySelectorAll('.question-card');
        const middle = window.innerHeight / 2;
        let bestCard = null;
        let minDistance = Infinity;

        cards.forEach((card) => {
            const rect = card.getBoundingClientRect();
            const distance = Math.abs(rect.top + rect.height / 2 - middle);
            if (distance < minDistance) {
                minDistance = distance;
                bestCard = card;
            }
        });

        if (bestCard) {
            const idParts = (bestCard as HTMLElement).id.split('-');
            const qIndex = parseInt(idParts[1]);
            toggleFlag(qIndex);
        }
    }
};

const goBackToList = () => {
    selectedPart.value = null;
    examStarted.value = false;
};

const submitPart = async () => {
    if (!selectedPart.value) return;

    // Smart Review Nudge - using custom modal to prevent fullscreen exit
    // Use computed unansweredCount which dynamically updates
    if (
        unansweredCount.value > 0 &&
        !isSubmitting.value &&
        !hasShownUnansweredWarning.value
    ) {
        showUnansweredWarning.value = true;
        hasShownUnansweredWarning.value = true;

        // Animate the warning modal
        setTimeout(() => {
            if (unansweredWarningRef.value) {
                gsap.fromTo(
                    unansweredWarningRef.value,
                    { opacity: 0, scale: 0.9, y: 20 },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.4,
                        ease: 'back.out',
                    },
                );
            }
        }, 10);

        return; // Wait for user interaction with custom modal
    }

    isSubmitting.value = true;
    isTimeoutSubmission.value = false; // Reset timeout flag if we are proceeding with submission
    void sendMonitorProgress('submitting');

    // Check if current part has essay
    currentPartHasEssay.value =
        selectedPart.value?.questions?.some((q) => q.type === 'essay') || false;

    // Build detailed answers with question information
    const detailedAnswers = (selectedPart.value?.questions || []).map(
        (question, index) => ({
            question_number: index + 1,
            question_text: question.text,
            question_type: question.type,
            points: question.points ?? selectedPart.value?.points ?? 1,
            answer:
                answers[index] !== undefined && answers[index] !== null
                    ? answers[index]
                    : null,
        }),
    );

    router.post(
        `/exams/${props.exam.id}/parts/${selectedPart.value.id}/submit`,
        {
            answers: detailedAnswers,
        },
        {
            onSuccess: () => {
                hasShownUnansweredWarning.value = false; // Reset warning state after successful submission
                clearDraft(); // Clean up successfully submitted draft

                // Exit full screen mode only if ALL parts are completed
                if (remainingPartsCount.value === 0) {
                    // Set examStarted to false BEFORE exiting fullscreen
                    // to prevent the handleFullscreenChange from triggering the lockout modal
                    examStarted.value = false;

                    if (document.fullscreenElement) {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        } else if ((document as any).webkitExitFullscreen) {
                            (document as any).webkitExitFullscreen();
                        } else if ((document as any).msExitFullscreen) {
                            (document as any).msExitFullscreen();
                        }
                    }
                    isFullscreen.value = false;
                    void sendMonitorProgress('finished');
                }

                // Show success modal
                showSuccessModal.value = true;
                partsPendingCount.value = remainingPartsCount.value;
                isFinalSubmitting.value = false; // Reset early on success to allow modal interactions

                // Animate modal
                setTimeout(() => {
                    if (successModalRef.value) {
                        gsap.fromTo(
                            successModalRef.value,
                            { opacity: 0, scale: 0.85, y: 30 },
                            {
                                opacity: 1,
                                scale: 1,
                                y: 0,
                                duration: 0.6,
                                ease: 'back.out',
                            },
                        );

                        // If all parts are done OR current part has essay, animate the total score/AI assessment
                        if (
                            partsPendingCount.value === 0 ||
                            currentPartHasEssay.value
                        ) {
                            isCalculatingScore.value = true;
                            displayedScore.value = 0;

                            // Simulate calculation delay
                            setTimeout(
                                () => {
                                    isCalculatingScore.value = false;
                                    const targetScore =
                                        Number(totalScore.value) || 0;

                                    gsap.to(displayedScore, {
                                        value: targetScore,
                                        duration: 1.2,
                                        ease: 'none',
                                        onUpdate: () => {
                                            displayedScore.value = Math.floor(
                                                displayedScore.value,
                                            );
                                        },
                                        onComplete: () => {
                                            displayedScore.value = targetScore;
                                        },
                                    });

                                    // Decorative pop for the score box
                                    gsap.fromTo(
                                        '.final-score-box',
                                        { scale: 0.8, opacity: 0, y: 20 },
                                        {
                                            scale: 1,
                                            opacity: 1,
                                            y: 0,
                                            duration: 1.2,
                                            ease: 'elastic.out(1, 0.5)',
                                        },
                                    );
                                },
                                currentPartHasEssay.value ? 2000 : 1000,
                            ); // Faster UI feedback
                        }

                        // Bounce animation for checkmark
                        gsap.fromTo(
                            '.success-checkmark',
                            { scale: 0, rotate: -180 },
                            {
                                scale: 1,
                                rotate: 0,
                                duration: 0.8,
                                delay: 0.2,
                                ease: 'elastic.out(1.2, 0.4)',
                            },
                        );
                    }
                }, 10);
            },
            onFinish: () => {
                isFinalSubmitting.value = false;
                isSubmitting.value = false;
            },
            // Increase timeout for LAN environments where AI might queue
            headers: {
                'X-Inertia-Timeout': 300000, // 5 minutes in milliseconds
            },
        },
    );
};

const closeUnansweredWarning = (proceed: boolean) => {
    if (unansweredWarningRef.value) {
        gsap.to(unansweredWarningRef.value, {
            opacity: 0,
            scale: 0.9,
            y: 20,
            duration: 0.3,
            ease: 'power2.in',
            onComplete: () => {
                showUnansweredWarning.value = false;
                isTimeoutSubmission.value = false;
                if (proceed) {
                    submitPart();
                } else {
                    hasShownUnansweredWarning.value = false; // Reset so it can show again if they click submit again
                }
            },
        });
    } else {
        showUnansweredWarning.value = false;
        isTimeoutSubmission.value = false;
        if (proceed) {
            submitPart();
        } else {
            hasShownUnansweredWarning.value = false; // Reset so it can show again if they click submit again
        }
    }
};



const closeSuccessModal = () => {
    if (successModalRef.value) {
        gsap.to(successModalRef.value, {
            opacity: 0,
            scale: 0.85,
            y: 30,
            duration: 0.4,
            ease: 'power2.in',
            onComplete: () => {
                showSuccessModal.value = false;
                currentPartHasEssay.value = false;

                // If all parts are completed, redirect to the exams list
                if (allPartsSubmitted.value) {
                    router.visit('/exams');
                    return;
                }

                // Reset and go back to parts list
                Object.keys(answers).forEach(
                    (key) => delete answers[Number(key)],
                );
                goBackToList();

                // If there's a pending unlock, animate it now that we are back in the grid
                if (pendingUnlockIndex.value !== null) {
                    const nextIndex = pendingUnlockIndex.value;
                    pendingUnlockIndex.value = null; // Reset

                    setTimeout(() => {
                        const unlockedCard =
                            document.querySelectorAll('.exam-part-card')[
                                nextIndex
                            ];
                        if (unlockedCard) {
                            gsap.fromTo(
                                unlockedCard,
                                {
                                    x: -20,
                                    scale: 0.95,
                                    filter: 'brightness(0.5)',
                                },
                                {
                                    x: 0,
                                    scale: 1,
                                    filter: 'brightness(1)',
                                    duration: 1.2,
                                    ease: 'elastic.out(1, 0.4)',
                                },
                            );

                            // Animate the lock icon breaking away
                            const lockIcon =
                                unlockedCard.querySelector('.lucide-lock');
                            if (lockIcon) {
                                gsap.to(lockIcon, {
                                    rotate: 30,
                                    scale: 0,
                                    opacity: 0,
                                    duration: 0.6,
                                    delay: 0.1,
                                    onComplete: () => lockIcon.remove(),
                                });
                            }
                        }
                    }, 500); // Give Inertia/Vue a moment to render the grid
                }
            },
        });
    }
};

onMounted(() => {
    window.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleVisibilityChange);
    window.addEventListener('contextmenu', preventCheatingActions);
    window.addEventListener('copy', preventCheatingActions);
    window.addEventListener('paste', preventCheatingActions);
    window.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('beforeunload', handleBeforeUnload);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);
    document.addEventListener('MSFullscreenChange', handleFullscreenChange);

    // Sync isBooted with global loader
    if (!isLoaderVisible.value) {
        isBooted.value = true;
    }

    watch(
        isLoaderVisible,
        (visible) => {
            if (!visible) {
                isBooted.value = true;
            }
        },
        { immediate: true },
    );

    // Default to Dyslexia-Friendly mode for exams as requested
    updateDyslexiaMode(true);

    monitorHeartbeatInterval.value = setInterval(() => {
        if (isExamInProgress.value) {
            void sendMonitorProgress('in_progress');
        }
    }, 5000);
});

onUnmounted(() => {
    window.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('blur', handleVisibilityChange);
    window.removeEventListener('contextmenu', preventCheatingActions);
    window.removeEventListener('copy', preventCheatingActions);
    window.removeEventListener('paste', preventCheatingActions);
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    document.removeEventListener(
        'webkitfullscreenchange',
        handleFullscreenChange,
    );
    document.removeEventListener('mozfullscreenchange', handleFullscreenChange);
    document.removeEventListener('MSFullscreenChange', handleFullscreenChange);

    if (monitorHeartbeatInterval.value) {
        clearInterval(monitorHeartbeatInterval.value);
        monitorHeartbeatInterval.value = null;
    }
});
const isExamInProgress = computed(
    () =>
        examStarted.value ||
        (submittedPartsCount.value > 0 && !allPartsSubmitted.value),
);

const hideSidebar = computed(
    () => isExamInProgress.value && !isAdminBypass.value,
);

const scorePercentage = computed(() => {
    if (totalPossiblePoints.value === 0) return 0;
    return (totalScore.value / totalPossiblePoints.value) * 100;
});

const feedbackContent = computed(() => {
    if (scorePercentage.value >= 75) {
        return {
            text: 'Excellence Achieved',
            icon: Trophy,
            color: 'text-primary',
            border: 'border-primary/50',
            bg: 'bg-primary/5',
        };
    }
    return {
        text: 'Keep Pushing Forward',
        icon: Zap,
        color: 'text-amber-500',
        border: 'border-amber-500/50',
        bg: 'bg-amber-500/5',
    };
});

// ─── DRAGGABLE WIDGET LOGIC ────────────────────────────────
const widgetPos = reactive({ x: 0, y: 0 });
const isDragging = ref(false);
const widgetRef = ref<HTMLElement | null>(null);
const startPos = { x: 0, y: 0 };
const dragBounds = reactive({
    minX: -Infinity,
    maxX: Infinity,
    minY: -Infinity,
    maxY: Infinity,
});
let rafId: number | null = null;

const onDragStart = (e: MouseEvent) => {
    // Only drag if left click
    if (e.button !== 0 || !widgetRef.value) return;

    const rect = widgetRef.value.getBoundingClientRect();

    // Calculate bounds so widget stays within viewport
    // Current translation (widgetPos.x/y) + distance to screen edges
    dragBounds.minX = widgetPos.x - rect.left;
    dragBounds.maxX = widgetPos.x + (window.innerWidth - rect.right);
    dragBounds.minY = widgetPos.y - rect.top;
    dragBounds.maxY = widgetPos.y + (window.innerHeight - rect.bottom);

    isDragging.value = true;
    startPos.x = e.clientX - widgetPos.x;
    startPos.y = e.clientY - widgetPos.y;

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', onDragEnd);

    // Prevent text selection while dragging
    e.preventDefault();
};

const onDragMove = (e: MouseEvent) => {
    if (!isDragging.value) return;

    if (rafId) cancelAnimationFrame(rafId);

    rafId = requestAnimationFrame(() => {
        const rawX = e.clientX - startPos.x;
        const rawY = e.clientY - startPos.y;

        // Clamp position within calculated bounds
        widgetPos.x = Math.max(
            dragBounds.minX,
            Math.min(dragBounds.maxX, rawX),
        );
        widgetPos.y = Math.max(
            dragBounds.minY,
            Math.min(dragBounds.maxY, rawY),
        );
    });
};

const onDragEnd = () => {
    isDragging.value = false;
    if (rafId) cancelAnimationFrame(rafId);
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);
};
</script>

<template>
    <Head :title="`${exam.title} — Exam`" />

    <AppLayout :breadcrumbs="breadcrumbs" :hide-sidebar="hideSidebar">
        <div
            ref="container"
            class="exam-theme-page relative flex min-h-full flex-col gap-0 overflow-hidden bg-background"
        >
            <!-- Ambient background decorations -->
            <div
                class="pointer-events-none fixed -top-64 -right-64 h-[800px] w-[800px] animate-pulse rounded-full bg-primary/10 opacity-50 blur-[180px] dark:opacity-40"
                style="animation-duration: 8s"
            ></div>
            <div
                class="pointer-events-none fixed top-1/4 -left-64 h-[600px] w-[600px] animate-pulse rounded-full bg-violet-500/10 opacity-30 blur-[160px] dark:opacity-20"
                style="animation-duration: 12s"
            ></div>
            <div
                class="pointer-events-none fixed right-1/4 -bottom-64 h-[700px] w-[700px] rounded-full bg-blue-500/5 opacity-20 blur-[150px] dark:opacity-10"
            ></div>

            <div class="relative z-10 flex flex-1 flex-col gap-6 p-4 md:p-8">
                <!-- Integrity Alert Overlay -->
                <transition name="modal-fade">
                    <div
                        v-if="showIntegrityAlert"
                        class="pointer-events-none fixed top-24 left-1/2 z-[100] w-full max-w-md -translate-x-1/2 px-4"
                    >
                        <div
                            class="flex animate-bounce items-center gap-4 rounded-2xl border border-white/20 bg-red-500/90 p-4 text-white shadow-2xl backdrop-blur-xl"
                        >
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"
                            >
                                <AlertCircle class="h-6 w-6" />
                            </div>
                            <div class="flex-1">
                                <h4
                                    class="text-sm font-black tracking-widest uppercase"
                                >
                                    Security Warning
                                </h4>
                                <p class="text-[10px] font-bold opacity-90">
                                    Potential integrity breach detected. Your
                                    session activity is being logged. Please
                                    return to full screen and do not leave the
                                    page.
                                </p>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                <Motion
                    :initial="{ opacity: 0, y: -10 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }"
                    class="flex items-center justify-between"
                >
                    <div class="flex items-center gap-3">
                        <Link
                            v-if="!selectedPart"
                            href="/exams"
                            class="group inline-flex items-center gap-2.5 rounded-xl border border-border/40 bg-muted/30 px-4 py-2 text-[10px] font-black tracking-widest text-muted-foreground uppercase backdrop-blur-md transition-all hover:border-primary/40 hover:text-primary"
                        >
                            <ChevronLeft
                                class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1"
                            />
                            All Assessments
                        </Link>
                    </div>

                    <!-- Live Floating Timer & Smart Stats (Only in list view) -->
                    <div
                        v-if="examStarted && !selectedPart"
                        class="group/timer relative flex items-center gap-4 overflow-hidden rounded-2xl border border-white/10 bg-black/60 px-6 py-3 shadow-2xl backdrop-blur-2xl transition-all duration-500 hover:border-primary/40 md:gap-6"
                    >
                        <!-- Pulse decoration for timer -->
                        <div
                            class="absolute inset-0 bg-primary/5 opacity-0 transition-opacity group-hover/timer:opacity-100"
                        ></div>

                        <!-- Draft Status (Desktop Only) -->
                        <div
                            v-if="lastSavedAt"
                            class="hidden items-center gap-2 border-r border-white/10 pr-6 font-mono text-[8px] font-black tracking-[0.2em] text-muted-foreground/60 uppercase lg:flex"
                        >
                            <div
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                            ></div>
                            SYNCED_{{ lastSavedAt.replace(/:/g, '_') }}
                        </div>

                        <!-- Pace Indicator -->
                        <div
                            v-if="
                                estimatedFinishMinutes !== null &&
                                estimatedFinishMinutes > 0
                            "
                            class="hidden items-center gap-2 font-mono text-[9px] font-black tracking-[0.2em] text-amber-400 uppercase md:flex"
                        >
                            <Zap
                                class="h-4 w-4 fill-amber-400/20 transition-transform group-hover/timer:scale-110"
                            />
                            <span class="hidden lg:inline">EST_FINISH:</span>
                            {{ estimatedFinishMinutes }}M
                        </div>

                        <div
                            class="relative z-10 flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/10 px-3 py-1"
                            :class="
                                timeLeftSeconds < 300
                                    ? 'animate-pulse border-red-500/50 bg-red-500/10 text-red-500'
                                    : 'text-primary'
                            "
                        >
                            <Clock
                                class="h-4 w-4 transition-transform group-hover/timer:rotate-12"
                            />
                            <span
                                class="font-mono text-lg font-black tracking-[0.15em] tabular-nums"
                                >{{ formattedTime }}</span
                            >
                        </div>

                        <!-- Accessibility Toggle -->
                        <button
                            @click="toggleDyslexiaMode"
                            class="flex items-center gap-2 rounded-xl border px-3 py-1.5 backdrop-blur-md transition-all duration-300"
                            :class="
                                isDyslexiaFriendly
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-white/10 bg-white/5 text-white/40 hover:border-white/30 hover:text-white/60'
                            "
                            title="Toggle Dyslexia-Friendly Font"
                        >
                            <span
                                class="text-[10px] font-black tracking-widest uppercase"
                                >Font_Accessibility</span
                            >
                            <div class="relative">
                                <span class="text-xs font-black">Aa</span>
                                <div
                                    v-if="isDyslexiaFriendly"
                                    class="absolute -bottom-0.5 left-0 h-0.5 w-full bg-current"
                                ></div>
                            </div>
                        </button>
                    </div>
                </Motion>

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <Motion
                    :initial="{ opacity: 0, y: 30 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{
                        duration: 1,
                        ease: [0.16, 1, 0.3, 1],
                        delay: 0.1,
                    }"
                    class="exam-hero group/hero relative overflow-hidden border border-border bg-card p-6 shadow-2xl md:p-8"
                    @mousemove="handleMouseMove"
                >
                    <!-- Futuristic Corner Brackets -->
                    <div
                        class="exam-bracket pointer-events-none absolute top-0 left-0 h-6 w-6 border-t-2 border-l-2 border-foreground"
                    ></div>
                    <div
                        class="exam-bracket pointer-events-none absolute right-0 bottom-0 h-6 w-6 border-r-2 border-b-2 border-foreground"
                    ></div>

                    <div
                        class="relative z-10 flex flex-col justify-between gap-8 lg:flex-row lg:items-center"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="exam-tactical-mark exam-hero-mark flex h-12 w-12 shrink-0 rotate-45 items-center justify-center border-2 border-amber-500"
                                >
                                    <div
                                        class="h-2 w-2 rotate-45 animate-pulse bg-amber-500"
                                    ></div>
                                </div>
                                <div class="space-y-0.5">
                                    <span
                                        class="exam-friendly-label font-mono text-[9px] font-black tracking-[0.4em] text-primary uppercase"
                                        >Ready to begin</span
                                    >
                                    <h1
                                        class="text-3xl leading-[0.95] font-black tracking-tighter text-foreground uppercase italic md:text-5xl"
                                    >
                                        {{
                                            selectedPart
                                                ? selectedPart.title
                                                : exam.title
                                        }}
                                    </h1>
                                </div>
                            </div>

                            <div
                                v-if="!selectedPart"
                                class="relative overflow-hidden border border-border/50 bg-muted/30 p-4 dark:bg-zinc-950/40"
                            >
                                <div
                                    class="absolute top-0 left-0 h-full w-1 bg-amber-500/50"
                                ></div>
                                <p
                                    class="text-[11px] leading-relaxed font-bold tracking-tight text-muted-foreground uppercase md:text-xs"
                                >
                                    {{
                                        exam.description ||
                                        'Quickly assess and master the material with our streamlined exam interface.'
                                    }}
                                </p>
                                <div
                                    class="mt-2 flex items-center gap-3 font-mono text-[9px] font-black tracking-widest text-foreground/40 uppercase"
                                >
                                    <Calendar class="h-3.5 w-3.5" />
                                    {{ formatDateTime(exam.exam_date) }}
                                </div>
                            </div>

                            <div
                                v-if="selectedPart && lastSavedAt"
                                class="sync-heartbeat flex w-fit items-center gap-2 border border-emerald-500/20 bg-emerald-500/10 px-4 py-2"
                            >
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                <span
                                    class="font-mono text-[10px] font-black tracking-widest text-emerald-500 uppercase"
                                    >ENCRYPTED_SYNC_{{
                                        lastSavedAt.replace(/:/g, '_')
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Stats Architecture -->
                        <div
                            class="relative grid grid-cols-2 gap-4 border border-border/50 bg-muted/20 p-6 md:gap-6 lg:grid-cols-5 dark:bg-zinc-950/20"
                        >
                            <!-- Stat Decoration -->
                            <div
                                class="absolute -top-1 -left-1 h-2 w-2 bg-primary"
                            ></div>
                            <div
                                class="absolute -right-1 -bottom-1 h-2 w-2 bg-primary"
                            ></div>

                            <div
                                v-if="allPartsSubmitted"
                                class="flex flex-col gap-1"
                            >
                                <span
                                    class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                    >Score</span
                                >
                                <div
                                    class="font-mono text-lg font-black text-primary tabular-nums"
                                >
                                    {{ totalScore }}/{{ totalPossiblePoints }}
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span
                                    class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                    >Time Limit</span
                                >
                                <div class="flex items-baseline gap-1">
                                    <span
                                        class="font-mono text-lg font-black text-foreground tabular-nums"
                                        >{{ exam.duration_minutes }}</span
                                    >
                                    <span
                                        class="exam-friendly-label font-mono text-[8px] font-black text-primary uppercase"
                                        >MIN</span
                                    >
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span
                                    class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                    >Sections</span
                                >
                                <div
                                    class="font-mono text-lg font-black text-foreground tabular-nums"
                                >
                                    {{ exam.parts.length }}
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span
                                    class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                    >Questions</span
                                >
                                <div
                                    class="font-mono text-lg font-black text-foreground tabular-nums"
                                >
                                    {{ totalQuestions }}
                                </div>
                            </div>

                            <!-- Accessibility Toggle -->
                            <button
                                @click="toggleDyslexiaMode"
                                class="group/acc -m-3 flex flex-col gap-1 rounded-xl border border-transparent p-3 text-left transition-all hover:border-primary/30 hover:bg-primary/5 active:scale-95"
                                :class="
                                    isDyslexiaFriendly
                                        ? 'border-primary/20 bg-primary/5'
                                        : ''
                                "
                            >
                                <span
                                    class="font-mono text-[8px] font-black tracking-[0.3em] uppercase transition-colors"
                                    :class="
                                        isDyslexiaFriendly
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                    >ACCESSIBILITY</span
                                >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="font-mono text-lg font-black tabular-nums transition-colors"
                                        :class="
                                            isDyslexiaFriendly
                                                ? 'text-primary'
                                                : 'text-foreground'
                                        "
                                    >
                                        Aa
                                    </div>
                                    <div
                                        class="h-2 w-2 rounded-full transition-all"
                                        :class="
                                            isDyslexiaFriendly
                                                ? 'scale-110 bg-primary shadow-lg shadow-primary/50'
                                                : 'bg-muted-foreground/30'
                                        "
                                    ></div>
                                </div>
                            </button>
                        </div>
                    </div>
                </Motion>

                <!-- Global Progress Bar -->
                <Motion
                    v-if="!allPartsSubmitted && examStarted && !selectedPart"
                    :initial="{ opacity: 0 }"
                    :animate="isBooted ? { opacity: 1 } : {}"
                    :transition="{ duration: 1, delay: 0.3 }"
                    class="mt-2 w-full space-y-4"
                >
                    <!-- Overall Evaluation Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <span
                                class="text-[9px] font-black tracking-[0.4em] text-primary/60 uppercase"
                                >System Integrity</span
                            >
                            <span class="text-[9px] font-black text-primary/60"
                                >{{ Math.round(overallProgress) }}%
                                COMPLETED</span
                            >
                        </div>
                        <div
                            class="relative h-1 w-full overflow-hidden border border-primary/10 bg-muted/30"
                        >
                            <div
                                class="h-full bg-primary/40 transition-all duration-1000 ease-out"
                                :style="{ width: `${overallProgress}%` }"
                            ></div>
                        </div>
                    </div>
                </Motion>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PARTS LIST STATE                                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-if="!selectedPart">
                    <Motion
                        :initial="{ opacity: 0, y: 20 }"
                        :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                        :transition="{
                            duration: 0.8,
                            ease: [0.16, 1, 0.3, 1],
                            delay: 0.2,
                        }"
                        class="flex items-center justify-between"
                    >
                        <h2
                            class="flex items-center gap-3 text-xl font-black tracking-tight uppercase italic"
                        >
                            <Layers class="h-6 w-6 text-primary" />
                            Exam Parts
                        </h2>
                        <span
                            class="rounded-none border border-border/50 bg-muted/50 px-4 py-1.5 font-mono text-xs font-black tracking-widest text-muted-foreground uppercase"
                        >
                            {{ exam.parts.length }} Sections
                        </span>
                    </Motion>

                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <Motion
                            v-for="(part, index) in exam.parts"
                            :key="part.id"
                            @click="selectPart(part, index)"
                            :initial="{ opacity: 0, y: 30 }"
                            :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                            :in-view-options="{ once: true, margin: '-50px' }"
                            :transition="{
                                duration: 0.8,
                                ease: [0.16, 1, 0.3, 1],
                                delay: index * 0.05,
                            }"
                            class="exam-part-card group/part relative flex flex-col justify-between overflow-hidden border border-border bg-card p-6 transition-all duration-500 dark:bg-zinc-900/40"
                            :class="[
                                isPartSubmitted(part.id)
                                    ? 'opacity-80'
                                    : isPartLocked(index)
                                      ? 'cursor-not-allowed opacity-60 grayscale'
                                      : 'cursor-pointer hover:-translate-y-1 hover:shadow-xl',
                                nextPartId === part.id
                                    ? 'border-primary/50 shadow-xl ring-2 shadow-primary/20 ring-primary'
                                    : '',
                            ]"
                            @mousemove="handleMouseMove"
                        >
                            <!-- Onboarding/Focus Highlight -->
                            <div
                                v-if="nextPartId === part.id"
                                class="pointer-events-none absolute inset-0 z-0"
                            >
                                <div
                                    class="absolute inset-0 animate-pulse bg-primary/5"
                                ></div>
                                <div
                                    class="exam-recommended-tag absolute top-0 right-0 z-20 flex -skew-x-12 transform items-center gap-2 bg-primary px-4 py-1.5 text-[8px] font-black tracking-[0.3em] text-primary-foreground uppercase shadow-lg"
                                >
                                    <div
                                        class="h-1.5 w-1.5 animate-ping rounded-full bg-primary-foreground"
                                    ></div>
                                    <span
                                        class="exam-friendly-label inline-block skew-x-12"
                                        >Recommended</span
                                    >
                                </div>
                            </div>

                            <!-- Futuristic Corner Brackets -->
                            <div
                                class="pointer-events-none absolute top-0 left-0 h-4 w-4 border-t-2 border-l-2 border-foreground"
                            ></div>
                            <div
                                class="pointer-events-none absolute right-0 bottom-0 h-4 w-4 border-r-2 border-b-2 border-foreground"
                            ></div>

                            <!-- Top: Status & Metadata -->
                            <div class="relative z-10 flex flex-col gap-3">
                                <div class="flex items-center justify-between">
                                    <div
                                        class="exam-part-diamond flex h-10 w-10 rotate-45 items-center justify-center border border-amber-500/30 transition-colors group-hover/part:border-amber-500"
                                    >
                                        <div
                                            class="h-2 w-2 rotate-45 bg-amber-500"
                                        ></div>
                                    </div>
                                    <div
                                        v-if="isPartLocked(index)"
                                        class="rounded-lg border border-white/5 bg-zinc-950/50 p-1.5"
                                    >
                                        <Lock
                                            class="h-3.5 w-3.5 text-muted-foreground/40"
                                        />
                                    </div>
                                    <div
                                        v-else-if="isPartSubmitted(part.id)"
                                        class="exam-part-score-pill -skew-x-12 transform bg-emerald-500 px-3 py-2 font-mono text-xs font-black tracking-widest text-white shadow-[0_0_15px_rgba(16,185,129,0.3)] dark:text-zinc-950"
                                    >
                                        <span class="inline-block skew-x-12">
                                            {{
                                                submissions[part.id]?.score ?? 0
                                            }}
                                            /
                                            {{
                                                part.questions?.reduce(
                                                    (sum, q) =>
                                                        sum +
                                                        (q.points ??
                                                            part.points ??
                                                            1),
                                                    0,
                                                ) ?? 0
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <span
                                        class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                                        >Part {{ index + 1 }}</span
                                    >
                                    <h3
                                        class="text-xl leading-none font-black tracking-tight text-foreground uppercase italic transition-colors group-hover/part:text-primary"
                                    >
                                        {{ part.title }}
                                    </h3>
                                </div>

                                <!-- Middle: Question Types Stagger -->
                                <div
                                    class="space-y-2 border border-border/50 bg-muted/30 p-4 dark:bg-zinc-950/40"
                                >
                                    <div
                                        v-for="type in getQuestionTypes(part)"
                                        :key="type"
                                        class="flex items-center gap-2.5"
                                    >
                                        <span
                                            class="text-[9px] font-black text-amber-500"
                                            >[!]</span
                                        >
                                        <span
                                            class="font-mono text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                                        >
                                            {{ formatType(type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom: Footer Info & Action -->
                            <div
                                class="relative z-10 mt-6 flex items-center justify-between border-t border-border/10 pt-4"
                            >
                                <div class="flex items-center gap-6 font-mono">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-black text-primary"
                                            >{{
                                                part.questions?.length ?? 0
                                            }}</span
                                        >
                                        <span
                                            class="exam-friendly-label text-[9px] font-bold tracking-widest text-muted-foreground/40 uppercase"
                                            >Questions</span
                                        >
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-black text-amber-500"
                                            >{{
                                                part.questions?.reduce(
                                                    (sum, q) =>
                                                        sum +
                                                        (parseInt(q.points) ||
                                                            parseInt(
                                                                part.points,
                                                            ) ||
                                                            1),
                                                    0,
                                                ) ?? 0
                                            }}</span
                                        >
                                        <span
                                            class="text-[9px] font-bold tracking-widest text-muted-foreground/40 uppercase"
                                            >POINTS</span
                                        >
                                    </div>
                                </div>

                                <div
                                    v-if="!isPartSubmitted(part.id)"
                                    class="exam-part-action flex -skew-x-12 transform items-center gap-2 bg-foreground px-4 py-2 text-[10px] font-black tracking-[0.2em] text-background uppercase transition-all hover:bg-primary hover:text-primary-foreground"
                                    :class="
                                        isPartLocked(index)
                                            ? 'opacity-20 grayscale'
                                            : ''
                                    "
                                >
                                    <span class="inline-block skew-x-12">{{
                                        isPartLocked(index) ? 'LOCKED' : 'START'
                                    }}</span>
                                    <ArrowRight
                                        v-if="!isPartLocked(index)"
                                        class="h-3.5 w-3.5 skew-x-12"
                                    />
                                </div>
                            </div>
                        </Motion>
                    </div>

                    <!-- Instructions footer -->
                    <Motion
                        :initial="{ opacity: 0, y: 10 }"
                        :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.5 }"
                        class="mt-2 flex items-start gap-3 rounded-xl border border-border/20 bg-muted/10 p-3"
                    >
                        <ListChecks
                            class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground/60"
                        />
                        <p
                            class="text-[11px] leading-relaxed text-muted-foreground/70"
                        >
                            Click <strong>START</strong> to begin. Sections
                            unlock sequentially. Work is auto-saved locally.
                        </p>
                    </Motion>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  QUESTIONS STATE (after start)                          -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else>
                    <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
                        <!-- Main Question List -->
                        <div class="flex-1 space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <h2
                                        class="flex items-center gap-2 text-base font-bold"
                                    >
                                        <Layers class="h-4 w-4 text-primary" />
                                        {{ selectedPart!.title }}
                                    </h2>
                                    <div
                                        v-if="lastSavedAt"
                                        class="sync-heartbeat flex items-center gap-1.5 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5"
                                    >
                                        <CheckCircle2
                                            class="h-2.5 w-2.5 text-emerald-500"
                                        />
                                        <span
                                            class="text-[8px] font-black tracking-widest text-emerald-500 uppercase"
                                            >Synced {{ lastSavedAt }}</span
                                        >
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="exam-friendly-label rounded-lg border border-border/40 bg-muted/30 px-3 py-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                                    >
                                        {{
                                            selectedPart!.questions?.length ?? 0
                                        }}
                                        Questions
                                    </span>
                                    <span
                                        class="exam-friendly-label rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-1 font-mono text-[9px] font-black tracking-widest text-amber-500 uppercase"
                                    >
                                        {{
                                            selectedPart!.questions?.reduce(
                                                (sum, q) =>
                                                    sum +
                                                    (parseInt(q.points) ||
                                                        parseInt(
                                                            selectedPart!
                                                                .points,
                                                        ) ||
                                                        1),
                                                0,
                                            ) ?? 0
                                        }}
                                        Points
                                    </span>
                                </div>
                            </div>

                            <!-- Part Instructions -->
                            <div
                                v-if="selectedPart!.instructions"
                                class="exam-instruction-callout relative overflow-hidden rounded-none border-2 border-primary/30 bg-gradient-to-br from-amber-500/10 via-primary/10 to-primary/5 p-6 shadow-2xl shadow-primary/15"
                            >
                                <!-- Animated gradient border effect -->
                                <div
                                    class="pointer-events-none absolute inset-0 animate-[shimmer_3s_linear_infinite] bg-[linear-gradient(90deg,transparent,var(--color-primary),transparent)] bg-[length:200%_100%] opacity-10"
                                ></div>

                                <!-- Corner decorations -->
                                <div
                                    class="absolute top-0 left-0 h-6 w-6 border-t-2 border-l-2 border-primary"
                                ></div>
                                <div
                                    class="absolute top-0 right-0 h-6 w-6 border-t-2 border-r-2 border-primary"
                                ></div>
                                <div
                                    class="absolute bottom-0 left-0 h-6 w-6 border-b-2 border-l-2 border-primary"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-6 w-6 border-r-2 border-b-2 border-primary"
                                ></div>

                                <div class="relative flex items-start gap-5">
                                    <div
                                        class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-none border-2 border-primary bg-primary shadow-lg shadow-primary/40"
                                    >
                                        <FileText
                                            class="h-7 w-7 text-primary-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <h4
                                            class="flex items-center gap-3 text-[11px] font-black tracking-[0.4em] text-primary uppercase"
                                        >
                                            <span
                                                class="h-2 w-2 animate-pulse rounded-full bg-primary"
                                            ></span>
                                            Assessment Instructions
                                            <span
                                                class="h-2 w-2 animate-pulse rounded-full bg-primary"
                                            ></span>
                                        </h4>
                                        <p
                                            class="text-base leading-relaxed font-black tracking-tight whitespace-pre-wrap text-foreground md:text-lg"
                                        >
                                            {{ selectedPart!.instructions }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div
                                    v-for="(question, qIndex) in selectedPart!
                                        .questions"
                                    :key="qIndex"
                                    :id="`q-${qIndex}`"
                                    :class="[
                                        'question-card relative flex flex-col gap-8 rounded-none border-t border-r border-b border-l-4 p-6 transition-all duration-500 md:p-8',
                                        getQuestionStatus(qIndex) === 'answered'
                                            ? 'border-primary/20 border-l-primary bg-primary/[0.02] shadow-xl shadow-primary/5'
                                            : 'border-border/40 border-l-muted bg-card/40',
                                        question.type === 'essay'
                                            ? 'md:col-span-2'
                                            : '',
                                    ]"
                                >
                                    <!-- Decorative elements -->
                                    <div
                                        class="absolute top-0 right-0 h-4 w-4 border-t-2 border-r-2 border-primary/20"
                                    ></div>

                                    <!-- Question Content -->
                                    <div
                                        class="flex flex-col items-start gap-6 md:flex-row"
                                    >
                                        <!-- ID & Flag -->
                                        <div
                                            class="flex flex-shrink-0 items-center gap-4"
                                        >
                                            <div
                                                class="exam-question-number flex h-14 w-14 rotate-45 items-center justify-center border-2 border-primary/40 bg-primary/5 text-xl font-black text-primary"
                                            >
                                                <span class="-rotate-45">{{
                                                    qIndex + 1
                                                }}</span>
                                            </div>
                                            <button
                                                @click="toggleFlag(qIndex)"
                                                class="exam-flag-btn flex h-10 w-10 items-center justify-center border border-border/40 transition-all duration-300 hover:border-amber-500/50 hover:bg-amber-500/10"
                                                :class="
                                                    flaggedQuestions.has(qIndex)
                                                        ? 'border-amber-500/60 bg-amber-500/20 text-amber-500'
                                                        : 'text-muted-foreground/30'
                                                "
                                            >
                                                <Flag
                                                    class="h-4 w-4"
                                                    :class="
                                                        flaggedQuestions.has(
                                                            qIndex,
                                                        )
                                                            ? 'fill-amber-500'
                                                            : ''
                                                    "
                                                />
                                            </button>
                                        </div>

                                        <!-- Text & Type -->
                                        <div class="flex-1 space-y-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="border border-primary/20 bg-primary/10 px-2 py-0.5 text-[9px] font-black tracking-[0.3em] text-primary uppercase italic"
                                                >
                                                    {{
                                                        formatType(
                                                            question.type,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    class="exam-friendly-points text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                                >
                                                    {{
                                                        question.points ??
                                                        selectedPart!.points ??
                                                        1
                                                    }}
                                                    {{
                                                        (question.points ??
                                                            selectedPart!
                                                                .points ??
                                                            1) === 1
                                                            ? 'point'
                                                            : 'points'
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="text-lg leading-tight font-black tracking-tight whitespace-pre-wrap text-foreground/90 italic md:text-xl"
                                            >
                                                {{ question.text }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Answer Area -->
                                    <div class="w-full pl-0 md:pl-20">
                                        <!-- Multiple Choice / True-False -->
                                        <div
                                            v-if="
                                                question.type ===
                                                    'multiple_choice' ||
                                                question.type === 'true_false'
                                            "
                                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                        >
                                            <label
                                                v-for="(
                                                    option, oIndex
                                                ) in question.options"
                                                :key="option.text"
                                                class="exam-answer-option group/option relative flex cursor-pointer items-center gap-4 overflow-hidden border border-border/60 bg-muted/20 px-6 py-4 transition-all duration-300 hover:border-primary/60 hover:bg-primary/5 has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                            >
                                                <!-- Background Decoration -->
                                                <div
                                                    class="absolute right-0 bottom-0 h-8 w-8 translate-x-4 translate-y-4 -rotate-45 bg-primary/5 transition-colors group-hover/option:bg-primary/10"
                                                ></div>

                                                <div
                                                    class="exam-answer-radio relative flex h-5 w-5 items-center justify-center border-2 border-border/60 transition-colors group-hover/option:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary"
                                                >
                                                    <input
                                                        type="radio"
                                                        :name="`q-${qIndex}`"
                                                        :value="oIndex"
                                                        v-model.number="
                                                            answers[qIndex]
                                                        "
                                                        class="sr-only"
                                                    />
                                                    <Check
                                                        v-if="
                                                            answers[qIndex] ===
                                                            oIndex
                                                        "
                                                        class="h-3 w-3 text-primary-foreground"
                                                    />
                                                </div>
                                                <span
                                                    class="exam-answer-text relative text-sm font-black tracking-wider whitespace-pre-wrap text-muted-foreground transition-colors group-hover/option:text-foreground has-[:checked]:text-primary"
                                                    >{{ option.text }}</span
                                                >
                                            </label>
                                        </div>

                                        <!-- Identification -->
                                        <div
                                            v-else-if="
                                                question.type ===
                                                'identification'
                                            "
                                            class="max-w-xl"
                                        >
                                            <div class="group/input relative">
                                                <input
                                                    v-model="answers[qIndex]"
                                                    type="text"
                                                    placeholder="Type your answer here..."
                                                    class="exam-text-input w-full rounded-none border border-border/60 bg-muted/20 px-6 py-4 text-sm font-black tracking-widest transition-all duration-300 outline-none placeholder:text-muted-foreground/30 focus:border-primary"
                                                />
                                                <div
                                                    class="absolute top-1/2 right-4 -translate-y-1/2 opacity-0 transition-opacity group-focus-within/input:opacity-100"
                                                >
                                                    <Zap
                                                        class="h-4 w-4 animate-pulse text-primary"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Essay -->
                                        <div
                                            v-else-if="
                                                question.type === 'essay'
                                            "
                                            class="w-full"
                                        >
                                            <div
                                                class="group/textarea relative"
                                            >
                                                <textarea
                                                    v-model="answers[qIndex]"
                                                    rows="10"
                                                    placeholder="Write your answer here..."
                                                    class="exam-essay-input min-h-[300px] w-full resize-y rounded-none border border-border/60 bg-muted/20 px-8 py-6 text-base leading-relaxed font-bold transition-all duration-300 outline-none placeholder:text-muted-foreground/30 focus:border-primary"
                                                ></textarea>
                                                <div
                                                    class="absolute right-6 bottom-4 flex items-center gap-3 text-[9px] font-black tracking-[0.4em] text-primary uppercase opacity-40"
                                                >
                                                    <Terminal class="h-3 w-3" />
                                                    SECURE DATA ENTRY
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Navigator (Mini-Map) - Only show when all parts are completed -->
                        <div
                            v-if="allPartsSubmitted"
                            class="sticky top-8 hidden w-80 space-y-6 lg:block"
                        >
                            <div
                                class="group relative overflow-hidden rounded-none border border-primary/20 bg-card p-8 shadow-2xl"
                            >
                                <!-- Background Glow -->
                                <div
                                    class="absolute -top-20 -right-20 h-40 w-40 rounded-full bg-primary/5 blur-3xl transition-colors duration-700 group-hover:bg-primary/10"
                                ></div>

                                <div class="relative space-y-8">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <h3
                                            class="text-[10px] font-black tracking-[0.4em] text-muted-foreground uppercase"
                                        >
                                            Tactical Overlay
                                        </h3>
                                        <div
                                            class="border border-primary/20 bg-primary/10 px-2 py-1 text-[9px] font-black tracking-widest text-primary uppercase italic"
                                        >
                                            {{ Object.keys(answers).length }}/{{
                                                selectedPart!.questions!.length
                                            }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-5 gap-3">
                                        <a
                                            v-for="(_, qIndex) in selectedPart!
                                                .questions"
                                            :key="qIndex"
                                            :href="`#q-${qIndex}`"
                                            class="group/nav-item relative flex aspect-square items-center justify-center rounded-none border border-border/40 text-xs font-black transition-all duration-300"
                                            :class="[
                                                getQuestionStatus(qIndex) ===
                                                'answered'
                                                    ? 'scale-105 border-primary bg-primary text-primary-foreground shadow-lg shadow-primary/30'
                                                    : getQuestionStatus(
                                                            qIndex,
                                                        ) === 'flagged'
                                                      ? 'border-amber-500 bg-amber-500 text-white shadow-[0_0_15px_rgba(245,158,11,0.3)]'
                                                      : 'bg-muted/30 text-muted-foreground hover:border-primary/50 hover:bg-muted/50',
                                            ]"
                                        >
                                            {{ qIndex + 1 }}

                                            <!-- Flag indicator -->
                                            <div
                                                v-if="
                                                    flaggedQuestions.has(qIndex)
                                                "
                                                class="absolute -top-1 -right-1 h-2.5 w-2.5 border border-card bg-red-600 shadow-sm"
                                            ></div>
                                        </a>
                                    </div>

                                    <div
                                        class="space-y-4 border-t border-border/20 pt-6"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="text-[8px] font-black tracking-[0.4em] text-muted-foreground uppercase italic opacity-60"
                                                    >Current Progress</span
                                                >
                                                <span
                                                    class="text-xl font-black text-foreground italic"
                                                    >{{
                                                        Math.round(
                                                            (Object.keys(
                                                                answers,
                                                            ).length /
                                                                selectedPart!
                                                                    .questions!
                                                                    .length) *
                                                                100,
                                                        )
                                                    }}%</span
                                                >
                                            </div>
                                            <Trophy
                                                class="h-6 w-6 text-primary/20"
                                            />
                                        </div>
                                        <div
                                            class="h-1.5 w-full overflow-hidden rounded-none border border-border/40 bg-muted/30"
                                        >
                                            <div
                                                class="h-full bg-primary shadow-lg shadow-primary/50 transition-all duration-1000 ease-out"
                                                :style="{
                                                    width: `${(Object.keys(answers).length / selectedPart!.questions!.length) * 100}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Quick Stats -->
                                    <div class="grid grid-cols-2 gap-4 pt-2">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="text-[8px] font-black tracking-widest text-muted-foreground uppercase italic opacity-40"
                                                >Flagged</span
                                            >
                                            <span
                                                class="flex items-center gap-2 text-xs font-black text-amber-500"
                                            >
                                                <Flag
                                                    class="h-3 w-3 fill-amber-500/20"
                                                />
                                                {{
                                                    flaggedQuestions.size
                                                }}
                                                Units
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="text-[8px] font-black tracking-widest text-muted-foreground uppercase italic opacity-40"
                                                >System Clock</span
                                            >
                                            <span
                                                class="flex items-center gap-2 text-xs font-black text-primary"
                                            >
                                                <Zap
                                                    class="h-3 w-3 animate-pulse"
                                                />
                                                {{
                                                    estimatedFinishMinutes ||
                                                    '--'
                                                }}m Est.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tips/Shortcuts card -->
                            <div
                                class="rounded-none border border-border/20 bg-muted/20 p-6"
                            >
                                <h4
                                    class="mb-4 text-[9px] font-black tracking-[0.4em] text-muted-foreground uppercase italic"
                                >
                                    Protocol Shortcuts
                                </h4>
                                <ul class="space-y-3">
                                    <li
                                        class="flex items-center gap-3 text-[9px] font-bold tracking-widest text-muted-foreground/80 uppercase"
                                    >
                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-none border border-primary/20 bg-primary/10 font-black text-primary"
                                        >
                                            1-9
                                        </div>
                                        Selection Input
                                    </li>
                                    <li
                                        class="flex items-center gap-3 text-[9px] font-bold tracking-widest text-muted-foreground/80 uppercase"
                                    >
                                        <div
                                            class="flex h-6 w-6 items-center justify-center rounded-none border border-primary/20 bg-primary/10 font-black text-primary"
                                        >
                                            F
                                        </div>
                                        Integrity Flag
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Bottom Bar Navigator (Small) - Only show when all parts are completed -->
                    <div
                        v-if="allPartsSubmitted"
                        class="fixed right-0 bottom-0 left-0 z-40 p-4 lg:hidden"
                    >
                        <div
                            class="no-scrollbar flex items-center gap-3 overflow-x-auto rounded-2xl border border-white/10 bg-black/80 p-3 shadow-2xl backdrop-blur-2xl"
                        >
                            <div
                                class="vertical-writing mr-1 rotate-180 text-[9px] font-black text-muted-foreground uppercase"
                            >
                                NAV
                            </div>
                            <div
                                v-for="(_, qIndex) in selectedPart!.questions"
                                :key="qIndex"
                                class="relative flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg border border-white/5 text-[10px] font-black transition-all"
                                :class="[
                                    getQuestionStatus(qIndex) === 'answered'
                                        ? 'bg-primary text-primary-foreground'
                                        : getQuestionStatus(qIndex) ===
                                            'flagged'
                                          ? 'bg-amber-500 text-white'
                                          : 'bg-white/5 text-white/40',
                                ]"
                            >
                                {{ qIndex + 1 }}
                                <div
                                    v-if="flaggedQuestions.has(qIndex)"
                                    class="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full border border-black bg-red-500 shadow-sm"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-6 flex justify-end pt-8">
                        <button
                            @click="submitPart"
                            :disabled="isSubmitting"
                            class="group relative flex skew-x-[-12deg] items-center gap-6 bg-primary px-10 py-5 font-black text-primary-foreground shadow-xl shadow-primary/40 transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span
                                class="skew-x-[12deg] text-base tracking-[0.2em] uppercase"
                                >{{
                                    isSubmitting
                                        ? currentPartHasEssay
                                            ? 'Checking your answers...'
                                            : 'Submitting...'
                                        : 'Submit this part'
                                }}</span
                            >

                            <div
                                class="skew-x-[12deg] bg-primary-foreground/20 p-1.5 transition-colors group-hover:bg-primary-foreground/30"
                            >
                                <ArrowRight
                                    v-if="!isSubmitting"
                                    class="h-5 w-5 transition-transform group-hover:translate-x-1"
                                />
                                <div
                                    v-else
                                    class="h-5 w-5 animate-spin rounded-full border-3 border-primary-foreground/20 border-t-primary-foreground"
                                ></div>
                            </div>

                            <!-- Decorative Button Edge -->
                            <div
                                class="absolute -top-1 -right-1 h-2 w-2 bg-primary transition-transform group-hover:scale-150"
                            ></div>
                        </button>
                    </div>
                </template>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  UNANSWERED WARNING MODAL                                -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div
                    v-if="showUnansweredWarning"
                    class="fixed inset-0 z-[150] flex items-center justify-center bg-amber-950/60 p-4 backdrop-blur-md"
                >
                    <div
                        ref="unansweredWarningRef"
                        class="relative w-full max-w-md overflow-hidden rounded-none border-2 border-amber-500/50 bg-card p-6 shadow-[0_0_50px_rgba(245,158,11,0.3)] md:p-10"
                    >
                        <!-- Futuristic Corner Accents -->
                        <div
                            class="absolute top-0 left-0 h-6 w-6 border-t-2 border-l-2 border-amber-500"
                        ></div>
                        <div
                            class="absolute top-0 right-0 h-6 w-6 border-t-2 border-r-2 border-amber-500"
                        ></div>
                        <div
                            class="absolute bottom-0 left-0 h-6 w-6 border-b-2 border-l-2 border-amber-500"
                        ></div>
                        <div
                            class="absolute right-0 bottom-0 h-6 w-6 border-r-2 border-b-2 border-amber-500"
                        ></div>

                        <div
                            class="relative z-10 flex flex-col items-center gap-6 text-center"
                        >
                            <div
                                class="relative flex h-16 w-16 rotate-45 items-center justify-center border-2 border-amber-500/50"
                            >
                                <AlertCircle
                                    class="h-8 w-8 -rotate-45 text-amber-500"
                                />
                            </div>

                            <div class="space-y-3">
                                <h3
                                    class="text-xl font-black tracking-tighter text-foreground uppercase italic md:text-3xl"
                                >
                                    {{
                                        isTimeoutSubmission
                                            ? 'Time is up!'
                                            : 'Unanswered Questions'
                                    }}
                                </h3>
                                <div
                                    class="mx-auto h-0.5 w-16 bg-amber-500"
                                ></div>
                                <p
                                    v-if="isTimeoutSubmission"
                                    class="mx-auto max-w-sm text-xs leading-relaxed font-bold tracking-wider text-muted-foreground uppercase md:text-sm"
                                >
                                    The time for this section has expired. Your
                                    progress will be saved automatically.
                                </p>
                                <p
                                    v-else
                                    class="mx-auto max-w-sm text-xs leading-relaxed font-bold tracking-wider text-muted-foreground uppercase md:text-sm"
                                >
                                    You have
                                    <span
                                        class="text-lg font-black text-amber-500"
                                        >{{ unansweredCount }}</span
                                    >
                                    unanswered question{{
                                        unansweredCount > 1 ? 's' : ''
                                    }}
                                    in this section.
                                </p>
                                <p
                                    class="text-[10px] font-medium text-muted-foreground/70 italic"
                                >
                                    {{
                                        isTimeoutSubmission
                                            ? 'Please click the button below to proceed to the next section or finalize your submission.'
                                            : 'You may proceed, but these will be marked as incorrect.'
                                    }}
                                </p>
                            </div>

                            <div class="mt-4 flex w-full flex-col gap-3">
                                <button
                                    @click="closeUnansweredWarning(true)"
                                    class="group/btn flex w-full skew-x-[-12deg] items-center justify-center gap-4 bg-amber-500 px-6 py-4 text-xs font-black tracking-[0.2em] text-black uppercase transition-all hover:bg-amber-400"
                                >
                                    <span class="skew-x-[12deg]">{{
                                        isTimeoutSubmission
                                            ? 'Continue'
                                            : 'Proceed Anyway'
                                    }}</span>
                                    <ArrowRight
                                        class="h-5 w-5 skew-x-[12deg] transition-transform group-hover/btn:translate-x-2"
                                    />
                                </button>
                                <button
                                    v-if="!isTimeoutSubmission"
                                    @click="closeUnansweredWarning(false)"
                                    class="w-full py-3 text-[10px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-colors hover:text-foreground"
                                >
                                    Return to Questions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  START CONFIRMATION MODAL                               -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div
                    v-if="showStartModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-md"
                >
                    <div
                        ref="startModalRef"
                        class="relative w-full max-w-md overflow-hidden rounded-none border-2 border-primary/20 bg-card p-6 shadow-2xl shadow-primary/10 md:p-10"
                    >
                        <!-- Futuristic Corner Accents -->
                        <div
                            class="absolute top-0 left-0 h-6 w-6 border-t-2 border-l-2 border-primary"
                        ></div>
                        <div
                            class="absolute top-0 right-0 h-6 w-6 border-t-2 border-r-2 border-primary"
                        ></div>
                        <div
                            class="absolute bottom-0 left-0 h-6 w-6 border-b-2 border-l-2 border-primary"
                        ></div>
                        <div
                            class="absolute right-0 bottom-0 h-6 w-6 border-r-2 border-b-2 border-primary"
                        ></div>

                        <div
                            class="relative z-10 flex flex-col items-center gap-6 text-center"
                        >
                            <div
                                class="relative flex h-16 w-16 rotate-45 items-center justify-center border-2 border-amber-500/50"
                            >
                                <AlertCircle
                                    class="h-8 w-8 -rotate-45 text-amber-500"
                                />
                            </div>

                            <div class="space-y-3">
                                <h3
                                    class="text-xl font-black tracking-tighter text-foreground uppercase italic md:text-3xl"
                                >
                                    Security Protocol
                                </h3>
                                <div
                                    class="mx-auto h-0.5 w-16 bg-primary"
                                ></div>
                                <p
                                    class="mx-auto max-w-sm text-xs leading-relaxed font-bold tracking-wider text-muted-foreground uppercase md:text-sm"
                                >
                                    Initiating
                                    <span
                                        class="font-black text-primary underline decoration-2"
                                        >Part {{ (pendingIndex || 0) + 1 }}:
                                        {{ pendingPart?.title }}</span
                                    >.
                                </p>
                            </div>

                            <div
                                class="grid w-full gap-3 border border-border/50 bg-muted/50 p-4 text-left font-mono"
                            >
                                <div class="flex items-start gap-3">
                                    <span
                                        class="text-[10px] font-black text-amber-500"
                                        >[!]</span
                                    >
                                    <p
                                        class="text-[9px] leading-tight font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        Persistence Required: No exit allowed
                                        until completion.
                                    </p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span
                                        class="text-[10px] font-black text-amber-500"
                                        >[!]</span
                                    >
                                    <p
                                        class="text-[9px] leading-tight font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        Secure Environment: Auto-enabling Full
                                        Screen mode.
                                    </p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span
                                        class="text-[10px] font-black text-amber-500"
                                        >[!]</span
                                    >
                                    <p
                                        class="text-[9px] leading-tight font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        Integrity Monitoring: Unauthorized exits
                                        will be flagged.
                                    </p>
                                </div>
                            </div>

                            <div class="flex w-full flex-col gap-3">
                                <button
                                    @click="confirmStart"
                                    class="group/btn flex w-full skew-x-[-12deg] items-center justify-center gap-4 bg-primary px-6 py-4 text-xs font-black tracking-[0.2em] text-primary-foreground uppercase transition-all hover:bg-primary/90"
                                >
                                    <span class="skew-x-[12deg]"
                                        >Start Now</span
                                    >
                                    <ArrowRight
                                        class="h-5 w-5 skew-x-[12deg] transition-transform group-hover/btn:translate-x-2"
                                    />
                                </button>
                                <button
                                    @click="showStartModal = false"
                                    class="w-full py-2 text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-colors hover:text-foreground"
                                >
                                    Abort
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  FULLSCREEN LOCKOUT MODAL                               -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div
                    v-if="showFullscreenLockout"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-red-950/90 p-4 backdrop-blur-xl"
                >
                    <div
                        ref="lockoutModalRef"
                        class="relative w-full max-w-md overflow-hidden rounded-none border-2 border-red-600 bg-black p-6 shadow-[0_0_100px_rgba(220,38,38,0.5)] md:p-10"
                    >
                        <!-- Warning Scanline Effect -->
                        <div
                            class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] bg-[length:100%_2px,3px_100%]"
                        ></div>

                        <div
                            class="relative z-10 flex flex-col items-center gap-8 text-center"
                        >
                            <div
                                class="relative flex h-20 w-20 animate-pulse items-center justify-center border-2 border-red-600"
                            >
                                <Lock class="h-10 w-10 text-red-600" />
                                <div
                                    class="absolute -top-1 -left-1 h-2 w-2 bg-red-600"
                                ></div>
                                <div
                                    class="absolute -right-1 -bottom-1 h-2 w-2 bg-red-600"
                                ></div>
                            </div>

                            <div class="space-y-3">
                                <h3
                                    class="animate-pulse text-3xl font-black tracking-tighter text-red-600 uppercase italic"
                                >
                                    Access Denied
                                </h3>
                                <div class="h-0.5 w-full bg-red-600/30">
                                    <div
                                        class="h-full w-1/3 animate-[shimmer_2s_infinite] bg-red-600"
                                    ></div>
                                </div>
                                <p
                                    class="text-base leading-relaxed font-black tracking-widest text-red-500 uppercase"
                                >
                                    Secure Mode Compromised
                                </p>
                                <p
                                    class="mx-auto max-w-xs text-[9px] leading-relaxed font-black tracking-[0.1em] text-red-500/60 uppercase"
                                >
                                    Mandatory Full Screen Protocol Active. All
                                    assessment activities suspended until
                                    re-entry.
                                </p>
                            </div>

                            <button
                                @click="reEnterFullscreen"
                                class="group/btn flex w-full items-center justify-center gap-4 bg-red-600 px-6 py-5 text-xs font-black tracking-[0.3em] text-white uppercase shadow-[0_0_30px_rgba(220,38,38,0.4)] transition-all hover:bg-red-700"
                            >
                                <Zap class="h-5 w-5 animate-bounce" />
                                <span>Restore Protocol</span>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  SUCCESS MODAL OVERLAY                                  -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div
                    v-if="showSuccessModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 p-4 backdrop-blur-2xl"
                >
                    <div
                        ref="successModalRef"
                        class="relative w-full max-w-md overflow-hidden rounded-none border-2 border-primary/30 bg-card p-6 shadow-2xl shadow-primary/15 md:p-10"
                    >
                        <!-- Futuristic Grid Background -->
                        <div
                            class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,#888_1px,transparent_1px),linear-gradient(to_bottom,#888_1px,transparent_1px)] bg-[length:40px_40px] opacity-[0.03]"
                        ></div>

                        <div
                            class="relative z-10 flex flex-col items-center gap-8 text-center"
                        >
                            <!-- Animated Success Ring -->
                            <div
                                class="success-checkmark relative flex h-24 w-24 items-center justify-center"
                            >
                                <div
                                    class="absolute inset-0 animate-[spin_10s_linear_infinite] rounded-full border border-dashed border-primary/40"
                                ></div>
                                <div
                                    class="absolute inset-1 rounded-full border border-primary"
                                ></div>
                                <div
                                    class="flex h-16 w-16 items-center justify-center bg-primary shadow-lg shadow-primary/50"
                                >
                                    <svg
                                        class="h-8 w-8 text-primary-foreground"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="4"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <h3
                                    class="text-3xl font-black tracking-tighter text-foreground uppercase italic md:text-4xl"
                                >
                                    Exam Complete
                                </h3>
                                <div
                                    class="flex items-center justify-center gap-3"
                                >
                                    <div class="h-px w-8 bg-primary"></div>
                                    <span
                                        class="text-[9px] font-black tracking-[0.4em] text-primary uppercase"
                                        >Data Synchronized</span
                                    >
                                    <div class="h-px w-8 bg-primary"></div>
                                </div>
                            </div>

                            <!-- Progress / Score Info -->
                            <div
                                v-if="partsPendingCount > 0"
                                class="w-full border-t border-border pt-6"
                            >
                                <div class="flex flex-col items-center gap-4">
                                    <div
                                        class="flex skew-x-[-10deg] items-center gap-3 border-2 border-primary px-4 py-2 text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                                    >
                                        <span class="skew-x-[10deg]"
                                            >{{ partsPendingCount }} Part{{
                                                partsPendingCount === 1
                                                    ? ''
                                                    : 's'
                                            }}
                                            Remaining</span
                                        >
                                    </div>
                                    <p
                                        class="animate-pulse text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                    >
                                        Preparing next deployment phase...
                                    </p>
                                </div>
                            </div>

                            <!-- Final Score Reveal -->
                            <div
                                v-else
                                class="flex w-full flex-col items-center gap-6 border-t border-border pt-6"
                            >
                                <div
                                    v-if="isCalculatingScore"
                                    class="flex w-full flex-col items-center gap-6 p-8"
                                >
                                    <div class="relative h-16 w-16">
                                        <div
                                            class="absolute inset-0 rounded-full border-4 border-primary/20"
                                        ></div>
                                        <div
                                            class="absolute inset-0 animate-spin rounded-full border-4 border-primary border-t-transparent"
                                        ></div>
                                        <Zap
                                            class="absolute inset-0 m-auto h-6 w-6 animate-pulse text-primary"
                                        />
                                    </div>
                                    <div class="space-y-2 text-center">
                                        <p
                                            class="animate-pulse text-xs font-black tracking-[0.4em] text-primary uppercase"
                                        >
                                            {{
                                                currentPartHasEssay
                                                    ? 'Assessment Protocol Active'
                                                    : 'Calculating your score'
                                            }}
                                        </p>
                                        <p
                                            class="text-[8px] font-bold tracking-widest text-muted-foreground uppercase italic opacity-60"
                                        >
                                            {{
                                                currentPartHasEssay
                                                    ? 'LSI is analyzing your narrative response'
                                                    : 'Excluding Manual Review Components'
                                            }}
                                        </p>
                                        <!-- Countdown Timer -->
                                        <div
                                            class="mt-4 flex items-center justify-center gap-2"
                                        >
                                            <div
                                                class="flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1"
                                            >
                                                <span
                                                    class="font-mono text-[10px] font-black text-primary"
                                                    >T-MINUS:
                                                    {{ calcCountdown }}S</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="final-score-box group relative flex w-full flex-col items-center border border-border bg-muted/50 p-8 shadow-inner"
                                >
                                    <span
                                        class="mb-4 text-[9px] font-black tracking-[0.4em] text-muted-foreground/50 uppercase italic"
                                        >Performance Analytics</span
                                    >

                                    <div class="flex items-baseline gap-3">
                                        <span
                                            class="text-6xl leading-none font-black tracking-tighter text-primary tabular-nums md:text-7xl"
                                            >{{ displayedScore }}</span
                                        >
                                        <span
                                            class="text-2xl font-black text-muted-foreground/30"
                                            >/ {{ totalPossiblePoints }}</span
                                        >
                                    </div>

                                    <div
                                        class="mt-6 flex w-full flex-col items-center gap-3"
                                    >
                                        <div
                                            v-if="isExamPendingReview"
                                            class="flex items-center gap-3 border border-amber-500/50 bg-amber-500/5 px-4 py-2"
                                        >
                                            <Clock
                                                class="h-4 w-4 text-amber-500"
                                            />
                                            <span
                                                class="text-[10px] font-black tracking-[0.2em] text-amber-500 uppercase"
                                                >Validation Pending</span
                                            >
                                        </div>
                                        <div
                                            v-else
                                            :class="[
                                                'animate-in fade-in zoom-in flex w-full items-center justify-center gap-3 border px-6 py-2 duration-500',
                                                feedbackContent.border,
                                                feedbackContent.bg,
                                            ]"
                                        >
                                            <component
                                                :is="feedbackContent.icon"
                                                :class="[
                                                    'h-4 w-4',
                                                    feedbackContent.color,
                                                ]"
                                            />
                                            <span
                                                :class="[
                                                    'text-[11px] font-black tracking-[0.3em] uppercase',
                                                    feedbackContent.color,
                                                ]"
                                                >{{
                                                    feedbackContent.text
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button
                                @click="closeSuccessModal"
                                :disabled="isCalculatingScore"
                                class="flex w-full skew-x-[-12deg] items-center justify-center gap-4 bg-primary px-8 py-5 text-xs font-black tracking-[0.3em] text-primary-foreground uppercase shadow-lg shadow-primary/30 transition-all hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span class="skew-x-[12deg]">{{
                                    isCalculatingScore
                                        ? currentPartHasEssay
                                            ? 'Assessing...'
                                            : 'Calculating...'
                                        : partsPendingCount > 0
                                          ? 'Next Deployment'
                                          : 'Return to Page'
                                }}</span>
                                <ChevronRight
                                    v-if="!isCalculatingScore"
                                    class="h-5 w-5 skew-x-[12deg]"
                                />
                                <div
                                    v-else
                                    class="h-5 w-5 skew-x-[12deg] animate-spin rounded-full border-2 border-primary-foreground/20 border-t-primary-foreground"
                                ></div>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- ─── STICKY EXAM FOOTER (shows when a part is being taken) ─── -->
            <transition name="modal-fade">
                <div
                    v-if="examStarted && selectedPart && !showSuccessModal"
                    class="exam-sticky-header fixed right-0 bottom-0 left-0 z-[90] border-t border-border bg-card/95 shadow-[0_-2px_12px_-4px_rgba(0,0,0,0.1)] backdrop-blur-xl dark:bg-zinc-950/90"
                >
                    <div
                        class="mx-auto flex max-w-screen-2xl items-center gap-3 px-3 py-2.5 md:gap-5 md:px-6"
                    >
                        <!-- Part title (left) -->
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <div
                                class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-primary"
                            ></div>
                            <div class="flex min-w-0 flex-col">
                                <span
                                    class="text-[9px] leading-none font-bold tracking-widest text-muted-foreground uppercase"
                                    >In progress</span
                                >
                                <span
                                    class="truncate text-sm leading-tight font-bold text-foreground"
                                    >{{ selectedPart.title }}</span
                                >
                            </div>
                        </div>

                        <!-- Progress (center, hidden on small) -->
                        <div
                            class="hidden min-w-[200px] items-center gap-3 md:flex"
                        >
                            <span
                                class="text-[10px] font-bold whitespace-nowrap text-muted-foreground"
                            >
                                {{ Object.keys(answers).length }} /
                                {{ selectedPart.questions?.length ?? 0 }}
                            </span>
                            <div
                                class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted/40"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all duration-500 ease-out"
                                    :style="{ width: `${partProgress}%` }"
                                ></div>
                            </div>
                            <span
                                class="text-[10px] font-bold whitespace-nowrap text-primary tabular-nums"
                                >{{ Math.round(partProgress) }}%</span
                            >
                        </div>

                        <!-- Timer (right) -->
                        <div
                            class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-1.5 transition-colors"
                            :class="
                                timeLeftSeconds < 60
                                    ? 'animate-pulse border-red-500/40 bg-red-500/15 text-red-500'
                                    : timeLeftSeconds < 300
                                      ? 'border-amber-500/40 bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                      : 'border-primary/30 bg-primary/10 text-primary'
                            "
                        >
                            <Clock class="h-4 w-4" />
                            <span
                                class="font-mono text-base font-bold tracking-tight tabular-nums"
                                >{{ formattedTime }}</span
                            >
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ─── DRAGGABLE EXAM WIDGET ────────────────────────────── -->
            <transition name="modal-fade">
                <div
                    ref="widgetRef"
                    v-if="examStarted && selectedPart && !showSuccessModal"
                    class="group/widget fixed right-8 bottom-8 z-[100] transition-transform duration-75"
                    :style="{
                        transform: `translate(${widgetPos.x}px, ${widgetPos.y}px)`,
                    }"
                >
                    <div
                        class="relative w-56 overflow-hidden rounded-2xl border border-border bg-card/90 p-4 shadow-2xl backdrop-blur-2xl select-none md:w-64 dark:border-white/10 dark:bg-black/80"
                        :class="{
                            'cursor-grabbing': isDragging,
                            'cursor-grab': !isDragging,
                            'ring-2 ring-primary/20': isDragging,
                        }"
                        @mousedown="onDragStart"
                    >
                        <!-- Drag Indicator (Visual Only) -->
                        <div
                            class="pointer-events-none absolute top-0 right-0 left-0 flex h-6 items-center justify-center opacity-40 transition-opacity group-hover/widget:opacity-100"
                        >
                            <div
                                class="h-1 w-12 rounded-full bg-foreground/10"
                            ></div>
                        </div>

                        <!-- Widget Content -->
                        <div class="mt-2 space-y-3">
                            <!-- Timer Row -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 w-2 animate-pulse rounded-full bg-primary"
                                    ></div>
                                    <span
                                        class="font-mono text-[9px] font-black tracking-widest text-primary uppercase"
                                        >PART_ACTIVE</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-1 dark:bg-primary/10"
                                    :class="
                                        timeLeftSeconds < 300
                                            ? 'animate-pulse border-red-500/50 bg-red-500/5 text-red-500 dark:bg-red-500/10'
                                            : 'text-primary'
                                    "
                                >
                                    <Clock class="h-3 w-3" />
                                    <span
                                        class="font-mono text-sm font-black tracking-widest"
                                        >{{ formattedTime }}</span
                                    >
                                </div>
                            </div>

                            <!-- Progress Section -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="max-w-[120px] truncate text-[8px] font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        {{ selectedPart.title }}
                                    </span>
                                    <span
                                        class="font-mono text-[8px] font-black text-primary"
                                        >{{ Math.round(partProgress) }}%</span
                                    >
                                </div>
                                <div
                                    class="relative h-1.5 w-full overflow-hidden rounded-full border border-border bg-foreground/5 dark:border-white/5"
                                >
                                    <div
                                        class="h-full bg-primary shadow-lg shadow-primary/40 transition-all duration-500 ease-out"
                                        :style="{ width: `${partProgress}%` }"
                                    >
                                        <div
                                            class="absolute inset-0 animate-pulse bg-white/20"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Row -->
                            <div
                                v-if="
                                    estimatedFinishMinutes !== null &&
                                    estimatedFinishMinutes > 0
                                "
                                class="flex items-center gap-2 pt-1"
                            >
                                <Zap
                                    class="h-3 w-3 fill-amber-500/20 text-amber-500"
                                />
                                <span
                                    class="font-mono text-[8px] font-black tracking-widest text-amber-500 uppercase"
                                    >EST_FINISH:
                                    {{ estimatedFinishMinutes }}M</span
                                >
                            </div>
                        </div>

                        <!-- Tech Decoration -->
                        <div
                            class="pointer-events-none absolute -right-4 -bottom-4 h-12 w-12 rotate-45 border border-primary/5 dark:border-primary/10"
                        ></div>
                        <div
                            class="pointer-events-none absolute top-1/2 -left-2 h-8 w-0.5 -translate-y-1/2 rounded-full bg-primary/20 dark:bg-primary/30"
                        ></div>
                    </div>
                </div>
            </transition>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../css/app.css";

.pl-13 {
    padding-left: 3.25rem;
}

.animate-up {
    will-change: transform, opacity;
}

.exam-part-card {
    opacity: 0;
}

@keyframes scan-horizontal {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(1000%);
    }
}

.animate-scan-horizontal {
    animation: scan-horizontal 3s linear infinite;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-to,
.modal-fade-leave-from {
    opacity: 1;
}
</style>
