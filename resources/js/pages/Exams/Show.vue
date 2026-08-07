<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import axios from 'axios';
import gsap from 'gsap';
import {
    Calendar,
    Clock,
    ChevronLeft,
    ChevronRight,
    Check,
    CheckCircle2,
    FileText,
    ArrowRight,
    Layers,
    ListChecks,
    Lock,
    Flag,
    Zap,
    AlertCircle,
    Trophy,
    Grid3x3,
    X,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, computed, reactive, watch } from 'vue';
import PageSkeleton from '@/components/PageSkeleton.vue';
import { useAccessibility } from '@/composables/useAccessibility';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { isVisible: isLoaderVisible } = useLoader();
const { isDyslexiaFriendly, toggleDyslexiaMode, updateDyslexiaMode } =
    useAccessibility();
const isBooted = ref(false);

interface Question {
    text: string;
    type: string;
    // The answer key is stripped server-side while an exam is in progress and
    // is only present once the exam is closed (review mode). Never rely on it
    // here — this screen is used for *taking* the exam.
    options: { text: string; is_correct?: boolean }[] | null;
    correct_answer?: string | null;
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

interface ExamSubmissionSummary {
    status: string;
    score: number;
}

const props = defineProps<{
    exam: Exam;
    submissions: Record<number, ExamSubmissionSummary>;
    submittedPartId?: number | null;
    // Server-anchored deadlines (ISO strings) for parts the student has started
    // but not yet submitted, keyed by part id. Used to resume the countdown
    // after a reload instead of resetting to a fresh `duration_minutes`.
    partDeadlines?: Record<number, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
    { title: props.exam.title, href: `/exams/${props.exam.id}` },
];

const selectedPart = ref<ExamPart | null>(null);
const examStarted = ref(false);
const container = ref<HTMLElement | null>(null);

const answers = reactive<Record<number, string | number>>({}); // Store answers by question index
// Track submitted part IDs locally to handle stale server data after redirect
const locallySubmittedPartIds = ref(
    new Set<number>(Object.keys(props.submissions).map(Number)),
);
// Keep a client-side copy because the submit request can complete before an
// Inertia visit refreshes the page props. This prevents a saved part from
// briefly appearing as available again.
const localSubmissions = ref<Record<number, ExamSubmissionSummary>>(
    Object.fromEntries(
        Object.entries(props.submissions).map(([id, submission]) => [
            Number(id),
            { ...submission },
        ]),
    ),
);
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
const gradingPollTimer = ref<ReturnType<typeof setInterval> | null>(null);

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

const visibleQuestionIndex = ref(0);
const progressBoxRef = ref<HTMLElement | null>(null);

// ─── MOBILE: Swipe & Bottom Sheet ───────────────────────────
const mobileQuestionIndex = ref(0);
const showMobileProgress = ref(false);
const touchStartX = ref(0);
const touchEndX = ref(0);

function goToNextQuestion() {
    if (!selectedPart.value?.questions) return;
    const maxIndex = selectedPart.value.questions.length - 1;
    if (mobileQuestionIndex.value < maxIndex) {
        mobileQuestionIndex.value++;
        visibleQuestionIndex.value = mobileQuestionIndex.value;
        scrollToQuestion(mobileQuestionIndex.value);
    }
}

function goToPrevQuestion() {
    if (mobileQuestionIndex.value > 0) {
        mobileQuestionIndex.value--;
        visibleQuestionIndex.value = mobileQuestionIndex.value;
        scrollToQuestion(mobileQuestionIndex.value);
    }
}

function handleTouchStart(e: TouchEvent) {
    const target = e.target as HTMLElement;
    if (['INPUT', 'TEXTAREA'].includes(target.tagName)) return;
    touchStartX.value = e.changedTouches[0].screenX;
}

function handleTouchEnd(e: TouchEvent) {
    if (touchStartX.value === 0) return;
    touchEndX.value = e.changedTouches[0].screenX;
    const threshold = 50;
    const diff = touchStartX.value - touchEndX.value;
    if (Math.abs(diff) > threshold) {
        if (diff > 0) goToNextQuestion();
        else goToPrevQuestion();
    }
    touchStartX.value = 0;
}

const submitFromSheet = () => {
    showMobileProgress.value = false;
    submitPart();
};

// Reset mobile question index when starting a new part
watch(selectedPart, () => {
    mobileQuestionIndex.value = 0;
    showMobileProgress.value = false;
});

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

// Whether this exam actually enforces a time limit. Guards the auto-submit so
// an untimed exam (no duration) never force-submits at 00:00.
const hasTimeLimit = computed(() => (props.exam.duration_minutes ?? 0) > 0);

// Apply an authoritative server deadline (ISO) to the local countdown.
const applyDeadline = (deadlineIso: string) => {
    const ms = new Date(deadlineIso).getTime() - Date.now();
    timeLeftSeconds.value = Math.max(0, Math.floor(ms / 1000));
};

// Sync the per-part countdown with the server clock. The server anchors
// `started_at` once and never resets it, so a page reload resumes the real
// remaining time instead of silently granting a fresh full duration.
const startServerClock = async () => {
    const partId = selectedPart.value?.id;
    if (!partId) return;

    // Instant resume: apply a deadline we already know from the show props so
    // the timer doesn't flash a full duration while the request is in flight.
    const known = props.partDeadlines?.[partId];
    if (known) applyDeadline(known);

    try {
        const { data } = await axios.post(
            `/exams/${props.exam.id}/parts/${partId}/start`,
        );
        if (data?.deadline) applyDeadline(data.deadline);
    } catch {
        // Offline / server hiccup — fall back to a local full-duration
        // countdown so the student can keep working. The server stays the
        // source of truth for late-flagging when the part is submitted.
        if (!known) timeLeftSeconds.value = props.exam.duration_minutes * 60;
    }
};

const startTimer = () => {
    // Always (re)establish a clean per-part countdown. The old early-return
    // guard meant the timer never reset between parts, so the client ran one
    // continuous exam-long countdown while the server enforced a per-part
    // limit — the two definitions disagreed.
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
    partStartTime.value = Date.now();
    timerInterval.value = setInterval(() => {
        if (timeLeftSeconds.value > 0) {
            timeLeftSeconds.value--;
            calculatePace();
        } else {
            stopTimer();
            if (hasTimeLimit.value && examStarted.value && !isSubmitting.value) {
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

// ─── CONFETTI CELEBRATION ──────────────────────────────────
// Creates a burst of confetti particles when all questions are answered
const burstConfetti = () => {
    const colors = [
        'var(--color-primary)',
        '#22c55e', // emerald-500
        '#f59e0b', // amber-500
        '#a78bfa', // violet-400
        '#f472b6', // pink-400
        '#60a5fa', // blue-400
        '#34d399', // emerald-400
        '#fb923c', // orange-400
    ];

    const container = progressBoxRef.value;
    if (!container) return;

    const rect = container.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    // Create 50 confetti pieces
    for (let i = 0; i < 50; i++) {
        const el = document.createElement('div');
        const size = 6 + Math.random() * 6;
        const color = colors[Math.floor(Math.random() * colors.length)];
        const isCircle = Math.random() > 0.5;

        el.style.cssText = `
      position: fixed;
      z-index: 9999;
      pointer-events: none;
      width: ${isCircle ? size : size * 0.6}px;
      height: ${size}px;
      background: ${color};
      border-radius: ${isCircle ? '50%' : '2px'};
      left: ${centerX}px;
      top: ${centerY}px;
      opacity: 1;
    `;
        document.body.appendChild(el);

        const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.2;
        const velocity = 300 + Math.random() * 500;
        const vx = Math.cos(angle) * velocity;
        const vy = Math.sin(angle) * velocity;
        const rotation = Math.random() * 720 - 360;
        const gravity = 800;
        const duration = 1.2 + Math.random() * 0.8;

        gsap.to(el, {
            x: vx * 0.5,
            y: vy * 0.5 + 0.5 * gravity * 0.25,
            rotation: rotation,
            opacity: 0,
            scale: 0.3,
            duration: duration,
            ease: 'power2.out',
            onComplete: () => el.remove(),
        });
    }
};

// ─── ALL-ANSWERED CELEBRATION ──────────────────────────────────
// Watches for when all questions are answered and animates the progress box
watch(unansweredCount, (newCount, oldCount) => {
    if (oldCount > 0 && newCount === 0 && progressBoxRef.value) {
        burstConfetti();
        gsap.killTweensOf(progressBoxRef.value);
        gsap.fromTo(
            progressBoxRef.value,
            {
                scale: 1,
                borderColor: 'var(--color-border)',
                boxShadow: '0 0 0px rgba(var(--color-primary), 0)',
            },
            {
                scale: 1.02,
                borderColor: 'var(--color-primary)',
                boxShadow: '0 0 30px var(--color-primary)',
                duration: 1.2,
                ease: 'elastic.out(1, 0.4)',
                yoyo: true,
                repeat: 2,
                clearProps: 'borderColor,boxShadow,transform',
            },
        );

        // Also pulse the progress percentage
        const pctEl = progressBoxRef.value.querySelector('.progress-pct');
        if (pctEl) {
            gsap.fromTo(
                pctEl,
                { scale: 1, color: '' },
                {
                    scale: 1.3,
                    color: 'var(--color-primary)',
                    duration: 0.4,
                    ease: 'power2.out',
                    yoyo: true,
                    repeat: 5,
                },
            );
        }

        // Pulse the submit buttons to encourage submission
        const submitBtns = document.querySelectorAll('.submit-celebration-btn');
        submitBtns.forEach((btn) => {
            gsap.killTweensOf(btn);
            gsap.fromTo(
                btn,
                { scale: 1, boxShadow: '0 0 0px transparent' },
                {
                    scale: 1.05,
                    boxShadow: '0 0 25px var(--color-primary)',
                    duration: 0.8,
                    ease: 'power1.inOut',
                    yoyo: true,
                    repeat: -1,
                },
            );
        });
    }
});

// Stop the submit button pulse when user starts submitting
watch(isSubmitting, (submitting) => {
    if (submitting) {
        document.querySelectorAll('.submit-celebration-btn').forEach((btn) => {
            gsap.killTweensOf(btn);
            gsap.set(btn, { clearProps: 'transform,boxShadow' });
        });
    }
});

// Find the first unanswered question index (for the jump-to-unanswered button)
const firstUnansweredIndex = computed(() => {
    if (!selectedPart.value?.questions) return -1;
    return selectedPart.value.questions.findIndex((_, i) => {
        const a = answers[i];
        return (
            a === undefined ||
            a === null ||
            (typeof a === 'string' && a.trim() === '')
        );
    });
});

// Find the first flagged question index (for the review-flagged button)
const firstFlaggedIndex = computed(() => {
    if (flaggedQuestions.value.size === 0) return -1;
    return Math.min(...Array.from(flaggedQuestions.value));
});

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

// Cycle to the next flagged question. If at the last flagged question, wrap to the first.
const jumpToNextFlagged = () => {
    const flagged = Array.from(flaggedQuestions.value).sort((a, b) => a - b);
    if (flagged.length === 0) return;

    // Find the current position among flagged questions, or start from the first
    const currentIdx = flagged.indexOf(visibleQuestionIndex.value);
    const nextIdx =
        currentIdx >= 0 && currentIdx < flagged.length - 1 ? currentIdx + 1 : 0;
    scrollToQuestion(flagged[nextIdx]);
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
                // Remaining time is intentionally NOT restored from the draft —
                // the server-anchored deadline (startServerClock) is the
                // authority and survives a reload.
            }
        } catch (e) {
            console.error('Failed to load draft', e);
        }
    }
};

const clearDraft = () => {
    localStorage.removeItem(getDraftKey());
};

// ─── AUTO-SAVE ON ANSWER CHANGE ─────────────────────────────
let saveDraftTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
    answers,
    () => {
        if (saveDraftTimeout) {
            clearTimeout(saveDraftTimeout);
        }
        saveDraftTimeout = setTimeout(() => {
            saveDraft();
        }, 500);
    },
    { deep: true },
);

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
    () => Object.keys(localSubmissions.value).length,
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
    Object.values(localSubmissions.value).reduce(
        (sum, s) => sum + (Number(s.score) || 0),
        0,
    ),
);

const isExamPendingReview = computed(() =>
    Object.values(localSubmissions.value).some(
        (s) => s.status === 'pending_review',
    ),
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
    return (
        locallySubmittedPartIds.value.has(partId) ||
        !!localSubmissions.value[partId]
    );
};

watch(
    () => props.submissions,
    (submissions) => {
        Object.entries(submissions).forEach(([id, submission]) => {
            const partId = Number(id);
            localSubmissions.value[partId] = { ...submission };
            locallySubmittedPartIds.value.add(partId);
        });
    },
    { deep: true },
);

const isPartLocked = (index: number) => {
    if (index === 0) return false;
    const previousPart = props.exam.parts[index - 1];
    return !isPartSubmitted(previousPart.id);
};

const getQuestionTypes = (part: ExamPart) => [
    ...new Set(part.questions?.map((q) => q.type) ?? []),
];

const formatType = (type: string) => type.replace(/_/g, ' ');

const formatDateTime = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

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

    // Halt any countdown left over from a previous part before anchoring this
    // part's clock.
    stopTimer();

    examStarted.value = true;
    loadDraft(); // Load any saved progress
    void sendMonitorProgress('starting');

    // Anchor the per-part clock on the server, then run the countdown from the
    // authoritative deadline it returns.
    void startServerClock().finally(() => startTimer());

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

const scrollToQuestion = (index: number) => {
    const el = document.getElementById(`q-${index}`);
    if (!el) return;

    // Force reactivity: set to -1 first so clicking an already-highlighted
    // number still triggers a re-render (Vue batches, no visual flash)
    visibleQuestionIndex.value = -1;
    visibleQuestionIndex.value = index;

    // Smooth scroll to the question card
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Kill any existing GSAP tweens on this element to ensure a clean replay
    gsap.killTweensOf(el);

    // GSAP highlight pulse animation
    // Use individual outline properties instead of the shorthand to avoid
    // stale inline-style interference on repeated clicks
    gsap.fromTo(
        el,
        {
            scale: 1.03,
            outlineWidth: '3px',
            outlineStyle: 'solid',
            outlineColor: 'var(--color-primary)',
            outlineOffset: '2px',
        },
        {
            scale: 1,
            outlineWidth: '3px',
            outlineStyle: 'solid',
            outlineColor: 'transparent',
            outlineOffset: '2px',
            duration: 0.8,
            ease: 'power2.out',
            // Clear leftover inline styles after animation so next click starts clean
            clearProps:
                'outline,outlineWidth,outlineStyle,outlineColor,outlineOffset,transform',
        },
    );
};

const submitPart = async () => {
    if (!selectedPart.value) return;
    // Guard against re-entry while already submitting
    if (isSubmitting.value) return;

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

    // If the warning modal is already visible, ignore the click
    if (showUnansweredWarning.value) return;

    locallySubmittedPartIds.value.add(selectedPart.value.id);
    isSubmitting.value = true;
    stopTimer(); // Stop countdown during submission to prevent auto-submit race condition
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
            onSuccess: (page) => {
                const submittedPartId = Number(selectedPart.value?.id);
                locallySubmittedPartIds.value.add(submittedPartId);
                localSubmissions.value[submittedPartId] = {
                    status: currentPartHasEssay.value
                        ? 'pending_review'
                        : 'submitted',
                    score: 0,
                };

                Object.entries(page.props.submissions ?? {}).forEach(
                    ([id, submission]) => {
                        localSubmissions.value[Number(id)] = { ...submission };
                        locallySubmittedPartIds.value.add(Number(id));
                    },
                );

                const effectiveCount = Object.keys(
                    localSubmissions.value,
                ).length;
                const freshSubmittedPartId = (page.props.submittedPartId ??
                    props.submittedPartId) as number | null;
                triggerSuccessModal(
                    props.exam.parts.length - effectiveCount,
                    freshSubmittedPartId ?? submittedPartId,
                );
            },
            // A request that dies (network drop, tab killed, server restart)
            // can leave isSubmitting stuck true — Inertia may not fire
            // onFinish for a request that never completes. Reset here so the
            // submit button can never spin forever.
            onError: () => {
                isFinalSubmitting.value = false;
                isSubmitting.value = false;
            },
            onFinish: () => {
                isFinalSubmitting.value = false;
                isSubmitting.value = false;
            },
            // Increase timeout for LAN environments where AI might queue
            headers: {
                'X-Inertia-Timeout': '300000', // 5 minutes in milliseconds
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

const animateDisplayedScore = (score: number) => {
    gsap.killTweensOf(displayedScore);
    gsap.to(displayedScore, {
        value: score,
        duration: 1.2,
        ease: 'none',
        onUpdate: () => {
            displayedScore.value = Math.floor(displayedScore.value);
        },
        onComplete: () => {
            displayedScore.value = score;
        },
    });
};

const startEssayGradingPoll = (partId: number) => {
    if (gradingPollTimer.value) clearInterval(gradingPollTimer.value);

    const checkStatus = async () => {
        try {
            const { data } = await axios.get(
                `/exams/${props.exam.id}/parts/${partId}/status`,
            );
            if (data.status === 'not_submitted') return;

            const submission = localSubmissions.value[partId] ?? {
                status: 'pending_review',
                score: 0,
            };
            submission.status = data.status;

            if (data.scored && data.score !== null) {
                submission.score = Number(data.score);
                localSubmissions.value[partId] = submission;
                isCalculatingScore.value = false;
                animateDisplayedScore(submission.score);
                if (gradingPollTimer.value)
                    clearInterval(gradingPollTimer.value);
                gradingPollTimer.value = null;
            } else if (data.grading_failed) {
                localSubmissions.value[partId] = submission;
                isCalculatingScore.value = false;
                if (gradingPollTimer.value)
                    clearInterval(gradingPollTimer.value);
                gradingPollTimer.value = null;
            }
        } catch {
            // Keep polling through transient network failures.
        }
    };

    void checkStatus();
    gradingPollTimer.value = setInterval(checkStatus, 2000);
};

const triggerSuccessModal = (
    remainingCount?: number,
    newSubmittedPartId?: number | null,
) => {
    const effectiveRemainingCount = remainingCount ?? remainingPartsCount.value;
    const effectiveSubmittedPartId =
        newSubmittedPartId ?? props.submittedPartId;

    hasShownUnansweredWarning.value = false;
    clearDraft();

    // Exit full screen mode only if ALL parts are completed
    if (effectiveRemainingCount === 0) {
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
    partsPendingCount.value = effectiveRemainingCount;
    isFinalSubmitting.value = false;

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

            // If all parts are done OR the submitted part has an essay, animate the total score/AI assessment
            const hasEssayInSubmittedPart = currentPartHasEssay.value;

            if (partsPendingCount.value === 0 || hasEssayInSubmittedPart) {
                isCalculatingScore.value = true;
                displayedScore.value = 0;

                if (hasEssayInSubmittedPart && effectiveSubmittedPartId) {
                    startEssayGradingPoll(effectiveSubmittedPartId);
                } else {
                    isCalculatingScore.value = false;
                    animateDisplayedScore(Number(totalScore.value) || 0);
                }

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
                // Use partsPendingCount (correctly tracks via local submitted IDs) instead of allPartsSubmitted (stale server data)
                if (partsPendingCount.value === 0) {
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

    // If we landed here after submitting a part (from redirect), show the success modal
    if (props.submittedPartId) {
        currentPartHasEssay.value = Boolean(
            props.exam.parts
                .find((part) => part.id === props.submittedPartId)
                ?.questions?.some((question) => question.type === 'essay'),
        );
        // Pass current computed values explicitly for consistency
        triggerSuccessModal(remainingPartsCount.value, props.submittedPartId);
    }

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
    if (gradingPollTimer.value) {
        clearInterval(gradingPollTimer.value);
        gradingPollTimer.value = null;
    }
    if (saveDraftTimeout) {
        clearTimeout(saveDraftTimeout);
        saveDraftTimeout = null;
    }

    // Kill any lingering submit button pulse animations
    document.querySelectorAll('.submit-celebration-btn').forEach((btn) => {
        gsap.killTweensOf(btn);
    });
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
</script>

<template>
    <Head :title="`${exam.title} — Exam`" />

    <AppLayout :breadcrumbs="breadcrumbs" :hide-sidebar="hideSidebar">
        <!-- Skeleton Loading State -->
        <template v-if="!isBooted">
            <div
                class="exam-theme-page relative flex min-h-full flex-col gap-0 overflow-hidden bg-background p-4 md:p-8"
            >
                <PageSkeleton
                    :hero="true"
                    :stats="5"
                    :count="0"
                    variant="minimal"
                    wrapperClass="mb-6"
                />
                <div class="mb-4 flex items-center justify-between">
                    <div
                        class="h-6 w-24 animate-pulse rounded bg-primary/10"
                    ></div>
                    <div
                        class="h-6 w-28 animate-pulse rounded bg-primary/10"
                    ></div>
                </div>
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="h-64 animate-pulse rounded-lg border border-border/10 bg-card/30"
                    ></div>
                </div>
            </div>
        </template>

        <!-- Real Content -->
        <template v-if="isBooted">
            <div
                ref="container"
                class="exam-theme-page relative flex min-h-full flex-col gap-0 overflow-hidden bg-background"
            >
                <div
                    class="relative z-10 flex flex-1 flex-col gap-6 p-4 md:p-8"
                >
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
                                        Potential integrity breach detected.
                                        Your session activity is being logged.
                                        Please return to full screen and do not
                                        leave the page.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </transition>

                    <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                    <Motion
                        :initial="{ opacity: 0, y: -10 }"
                        :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                        :transition="{
                            duration: 0.8,
                            easing: [0.16, 1, 0.3, 1],
                        }"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <Link
                                v-if="!selectedPart"
                                href="/exams"
                                class="inline-flex items-center gap-2 rounded-lg border border-border/40 bg-muted/30 px-3 py-1.5 text-xs text-muted-foreground transition-all hover:border-primary/40 hover:text-primary"
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
                                class="hidden items-center gap-2 border-r border-white/10 pr-6 text-xs text-muted-foreground/60 lg:flex"
                            >
                                <div
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></div>
                                {{ lastSavedAt }}
                            </div>

                            <!-- Pace Indicator -->
                            <div
                                v-if="
                                    estimatedFinishMinutes !== null &&
                                    estimatedFinishMinutes > 0
                                "
                                class="hidden items-center gap-2 text-xs text-amber-500 md:flex"
                            >
                                <Zap
                                    class="h-4 w-4 fill-amber-400/20 transition-transform group-hover/timer:scale-110"
                                />
                                <span class="hidden lg:inline"></span>
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
                                    class="font-mono text-lg font-semibold tracking-tight tabular-nums"
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
                                <span class="text-xs font-medium"
                                    >Font_Accessibility</span
                                >
                                <div class="relative">
                                    <span class="text-sm font-medium">Aa</span>
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
                        v-if="!selectedPart"
                        :initial="{ opacity: 0, y: 30 }"
                        :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                        :transition="{
                            duration: 1,
                            easing: [0.16, 1, 0.3, 1],
                            delay: 0.1,
                        }"
                        class="exam-hero relative rounded-lg border border-border bg-card p-6 shadow-sm md:p-8"
                    >
                        <div
                            class="relative z-10 flex flex-col justify-between gap-8 lg:flex-row lg:items-center"
                        >
                            <div class="max-w-3xl space-y-4">
                                <div class="flex items-center gap-4">
                                    <div class="space-y-0.5">
                                        <span
                                            class="text-xs font-medium text-muted-foreground"
                                            >Exam</span
                                        >
                                        <h1
                                            class="text-2xl font-bold tracking-tight text-foreground md:text-4xl"
                                        >
                                            {{ exam.title }}
                                        </h1>
                                    </div>
                                </div>

                                <div
                                    v-if="!selectedPart"
                                    class="rounded-lg border border-border/40 bg-muted/20 p-4"
                                >
                                    <p
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {{
                                            exam.description ||
                                            'Quickly assess and master the material with our streamlined exam interface.'
                                        }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground/60"
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
                                    <span class="text-xs text-emerald-600"
                                        >Auto-saved at
                                        {{
                                            lastSavedAt.replace(/:/g, '_')
                                        }}</span
                                    >
                                </div>
                            </div>

                            <!-- Stats Architecture -->
                            <div
                                class="grid grid-cols-2 gap-4 rounded-lg border border-border/40 bg-muted/10 p-4 md:gap-6 lg:grid-cols-5"
                            >
                                <div
                                    v-if="allPartsSubmitted"
                                    class="flex flex-col gap-1"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >Score</span
                                    >
                                    <div
                                        class="text-lg font-semibold text-foreground tabular-nums"
                                    >
                                        {{ totalScore }}/{{
                                            totalPossiblePoints
                                        }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-muted-foreground"
                                        >Time Limit</span
                                    >
                                    <div class="flex items-baseline gap-1">
                                        <span
                                            class="text-lg font-semibold text-foreground tabular-nums"
                                            >{{ exam.duration_minutes }}</span
                                        >
                                        <span
                                            class="text-xs text-muted-foreground/60"
                                            >min</span
                                        >
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-muted-foreground"
                                        >Sections</span
                                    >
                                    <div
                                        class="text-lg font-semibold text-foreground tabular-nums"
                                    >
                                        {{ exam.parts.length }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-muted-foreground"
                                        >Questions</span
                                    >
                                    <div
                                        class="text-lg font-semibold text-foreground tabular-nums"
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
                                        class="text-[10px] font-medium transition-colors"
                                        :class="
                                            isDyslexiaFriendly
                                                ? 'text-primary'
                                                : 'text-muted-foreground'
                                        "
                                        >ACCESSIBILITY</span
                                    >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="text-lg font-semibold tabular-nums transition-colors"
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
                        v-if="
                            !allPartsSubmitted && examStarted && !selectedPart
                        "
                        :initial="{ opacity: 0 }"
                        :animate="isBooted ? { opacity: 1 } : {}"
                        :transition="{ duration: 1, delay: 0.3 }"
                        class="mt-2 w-full space-y-4"
                    >
                        <!-- Overall Evaluation Progress -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between px-1">
                                <span class="text-xs text-muted-foreground/60"
                                    >Progress</span
                                >
                                <span
                                    class="text-[9px] font-black text-primary/60"
                                    >{{ Math.round(overallProgress) }}%
                                    COMPLETED</span
                                >
                            </div>
                            <div
                                class="relative h-1 w-full overflow-hidden border border-primary/10 bg-muted/30"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all duration-1000 ease-out"
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
                                easing: [0.16, 1, 0.3, 1],
                                delay: 0.2,
                            }"
                            class="flex items-center justify-between"
                        >
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Layers class="h-5 w-5 text-primary" />
                                Parts
                            </h2>
                            <span
                                class="rounded-lg border border-border/40 bg-muted/30 px-3 py-1 text-xs text-muted-foreground"
                            >
                                {{ exam.parts.length }} sections
                            </span>
                        </Motion>

                        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <Motion
                                v-for="(part, index) in exam.parts"
                                :key="part.id"
                                @click="selectPart(part, index)"
                                :initial="{ opacity: 0, y: 30 }"
                                :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                                :in-view-options="{
                                    once: true,
                                    margin: '-50px',
                                }"
                                :transition="{
                                    duration: 0.8,
                                    easing: [0.16, 1, 0.3, 1],
                                    delay: index * 0.05,
                                }"
                                class="exam-part-card group/part flex flex-col justify-between rounded-lg border border-border bg-card p-5 transition-all duration-500"
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
                            >
                                <!-- Onboarding -->
                                <div
                                    v-if="nextPartId === part.id"
                                    class="pointer-events-none absolute inset-0 z-0"
                                >
                                    <div
                                        class="absolute inset-0 animate-pulse bg-primary/5"
                                    ></div>
                                    <div
                                        class="absolute top-0 right-0 z-20 flex items-center gap-1.5 rounded-md bg-primary px-3 py-1 text-[10px] font-medium text-primary-foreground shadow-sm"
                                    >
                                        Recommended
                                    </div>
                                </div>

                                <!-- Top: Status & Metadata -->
                                <div class="relative z-10 flex flex-col gap-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted/50 text-xs font-medium text-muted-foreground"
                                        >
                                            {{ index + 1 }}
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
                                            class="rounded-md bg-emerald-500 px-2.5 py-1 text-xs font-medium text-white"
                                        >
                                            {{
                                                localSubmissions[part.id]
                                                    ?.score ?? 0
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
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <span
                                            class="text-xs font-medium text-primary"
                                            >Part {{ index + 1 }}</span
                                        >
                                        <h3
                                            class="text-lg font-semibold text-foreground transition-colors group-hover/part:text-primary"
                                        >
                                            {{ part.title }}
                                        </h3>
                                    </div>

                                    <!-- Middle: Question Types Stagger -->
                                    <div
                                        class="space-y-2 border border-border/50 bg-muted/30 p-4 dark:bg-zinc-950/40"
                                    >
                                        <span
                                            v-for="type in getQuestionTypes(
                                                part,
                                            )"
                                            :key="type"
                                            class="inline-flex items-center rounded-md bg-muted/50 px-2 py-0.5 text-xs text-muted-foreground"
                                        >
                                            {{ formatType(type) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Bottom: Footer Info & Action -->
                                <div
                                    class="relative z-10 mt-6 flex items-center justify-between border-t border-border/10 pt-4"
                                >
                                    <div class="flex items-center gap-6">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-sm font-semibold text-foreground"
                                                >{{
                                                    part.questions?.length ?? 0
                                                }}</span
                                            >
                                            <span
                                                class="text-xs text-muted-foreground/60"
                                                >questions</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-xs font-black text-amber-500"
                                                >{{
                                                    part.questions?.reduce(
                                                        (sum, q) =>
                                                            sum +
                                                            (q.points ||
                                                                part.points ||
                                                                1),
                                                        0,
                                                    ) ?? 0
                                                }}</span
                                            >
                                            <span
                                                class="text-xs text-muted-foreground/60"
                                                >points</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        v-if="!isPartSubmitted(part.id)"
                                        class="flex items-center gap-1.5 rounded-lg bg-foreground px-3 py-1.5 text-xs font-medium text-background transition-all hover:bg-primary hover:text-primary-foreground"
                                        :class="
                                            isPartLocked(index)
                                                ? 'opacity-20 grayscale'
                                                : ''
                                        "
                                    >
                                        <span>{{
                                            isPartLocked(index)
                                                ? 'Locked'
                                                : 'Start'
                                        }}</span>
                                        <ArrowRight
                                            v-if="!isPartLocked(index)"
                                            class="h-3.5 w-3.5"
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
                            class="mt-2 flex items-start gap-2 rounded-lg border border-border/20 bg-muted/10 p-3"
                        >
                            <ListChecks
                                class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground/50"
                            />
                            <p
                                class="text-xs leading-relaxed text-muted-foreground/70"
                            >
                                Parts unlock sequentially. Work is auto-saved
                                locally.
                            </p>
                        </Motion>
                    </template>

                    <!-- ═══════════════════════════════════════════════════════ -->
                    <!--  QUESTIONS STATE (after start)                          -->
                    <!-- ═══════════════════════════════════════════════════════ -->
                    <template v-else>
                        <div
                            class="relative flex flex-col gap-6 lg:flex-row lg:items-start"
                        >
                            <!-- Main Question List -->
                            <div class="flex-1 space-y-6 lg:pr-[22rem]">
                                <!-- Part Instructions -->
                                <div
                                    v-if="selectedPart!.instructions"
                                    class="relative overflow-hidden rounded-lg border border-border/50 bg-muted/30 p-6"
                                >
                                    <div
                                        class="relative flex items-start gap-5"
                                    >
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10"
                                        >
                                            <FileText
                                                class="h-5 w-5 text-primary-foreground"
                                            />
                                        </div>
                                        <div class="flex-1 space-y-2">
                                            <h4
                                                class="text-xs font-semibold tracking-wide text-foreground"
                                            >
                                                Instructions
                                            </h4>
                                            <p
                                                class="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground"
                                            >
                                                {{ selectedPart!.instructions }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- ═══ MOBILE: Single question carousel (swipeable) ═══ -->
                                <div class="block md:hidden">
                                    <!-- Current question card -->
                                    <div
                                        v-if="
                                            selectedPart!.questions![
                                                mobileQuestionIndex
                                            ]
                                        "
                                        :id="`q-${mobileQuestionIndex}`"
                                        @touchstart="handleTouchStart"
                                        @touchend="handleTouchEnd"
                                        :class="[
                                            'question-card relative flex flex-col gap-4 rounded-xl border border-l-[3px] border-border/40 p-4 transition-all duration-500',
                                            getQuestionStatus(
                                                mobileQuestionIndex,
                                            ) === 'answered'
                                                ? 'border-primary/20 border-l-primary bg-primary/[0.02] shadow-xl shadow-primary/5'
                                                : 'border-border/40 border-l-muted bg-card/40',
                                        ]"
                                    >
                                        <!-- Question Content -->
                                        <div
                                            class="flex flex-col items-start gap-4"
                                        >
                                            <!-- ID & Flag row -->
                                            <div
                                                class="flex w-full items-center justify-between"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-[10px] font-semibold text-primary"
                                                    >
                                                        {{
                                                            mobileQuestionIndex +
                                                            1
                                                        }}
                                                    </div>
                                                    <span
                                                        class="rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                                                    >
                                                        {{
                                                            formatType(
                                                                selectedPart!
                                                                    .questions![
                                                                    mobileQuestionIndex
                                                                ].type,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        class="text-[10px] text-muted-foreground"
                                                    >
                                                        {{
                                                            selectedPart!
                                                                .questions![
                                                                mobileQuestionIndex
                                                            ].points ??
                                                            selectedPart!
                                                                .points ??
                                                            1
                                                        }}
                                                        pt
                                                    </span>
                                                </div>
                                                <button
                                                    @click="
                                                        toggleFlag(
                                                            mobileQuestionIndex,
                                                        )
                                                    "
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-border/40 transition-all"
                                                    :class="
                                                        flaggedQuestions.has(
                                                            mobileQuestionIndex,
                                                        )
                                                            ? 'border-amber-500/60 bg-amber-500/20 text-amber-500'
                                                            : 'text-muted-foreground/30'
                                                    "
                                                >
                                                    <Flag
                                                        class="h-3.5 w-3.5"
                                                        :class="
                                                            flaggedQuestions.has(
                                                                mobileQuestionIndex,
                                                            )
                                                                ? 'fill-amber-500'
                                                                : ''
                                                        "
                                                    />
                                                </button>
                                            </div>

                                            <p
                                                class="text-sm leading-relaxed whitespace-pre-wrap text-foreground"
                                            >
                                                {{
                                                    selectedPart!.questions![
                                                        mobileQuestionIndex
                                                    ].text
                                                }}
                                            </p>
                                        </div>

                                        <!-- Answer Area -->
                                        <div class="w-full">
                                            <!-- Multiple Choice / True-False -->
                                            <div
                                                v-if="
                                                    selectedPart!.questions![
                                                        mobileQuestionIndex
                                                    ].type ===
                                                        'multiple_choice' ||
                                                    selectedPart!.questions![
                                                        mobileQuestionIndex
                                                    ].type === 'true_false'
                                                "
                                                class="flex flex-col gap-3"
                                            >
                                                <label
                                                    v-for="(
                                                        option, oIndex
                                                    ) in selectedPart!
                                                        .questions![
                                                        mobileQuestionIndex
                                                    ].options"
                                                    :key="option.text"
                                                    class="group/option relative flex cursor-pointer items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 transition-all hover:border-primary/60 hover:bg-primary/5 has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                                >
                                                    <div
                                                        class="relative flex h-5 w-5 items-center justify-center rounded-full border-2 border-border/60 transition-colors group-hover/option:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary"
                                                    >
                                                        <input
                                                            type="radio"
                                                            :name="`q-mobile-${mobileQuestionIndex}`"
                                                            :value="oIndex"
                                                            v-model.number="
                                                                answers[
                                                                    mobileQuestionIndex
                                                                ]
                                                            "
                                                            class="sr-only"
                                                        />
                                                        <Check
                                                            v-if="
                                                                answers[
                                                                    mobileQuestionIndex
                                                                ] === oIndex
                                                            "
                                                            class="h-3 w-3 text-primary-foreground"
                                                        />
                                                    </div>
                                                    <span
                                                        class="relative text-sm text-muted-foreground transition-colors group-hover/option:text-foreground has-[:checked]:text-primary"
                                                        >{{ option.text }}</span
                                                    >
                                                </label>
                                            </div>

                                            <!-- Identification -->
                                            <div
                                                v-else-if="
                                                    selectedPart!.questions![
                                                        mobileQuestionIndex
                                                    ].type === 'identification'
                                                "
                                                class="max-w-full"
                                            >
                                                <input
                                                    v-model="
                                                        answers[
                                                            mobileQuestionIndex
                                                        ]
                                                    "
                                                    type="text"
                                                    placeholder="Type your answer here..."
                                                    class="w-full rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm transition-all outline-none placeholder:text-muted-foreground/30 focus:border-primary focus:ring-1 focus:ring-primary"
                                                />
                                            </div>

                                            <!-- Essay -->
                                            <div
                                                v-else-if="
                                                    selectedPart!.questions![
                                                        mobileQuestionIndex
                                                    ].type === 'essay'
                                                "
                                                class="w-full"
                                            >
                                                <textarea
                                                    v-model="
                                                        answers[
                                                            mobileQuestionIndex
                                                        ]
                                                    "
                                                    rows="6"
                                                    placeholder="Write your answer here..."
                                                    class="min-h-[150px] w-full resize-y rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm leading-relaxed transition-all outline-none placeholder:text-muted-foreground/30 focus:border-primary focus:ring-1 focus:ring-primary"
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mobile: Prev / Next Navigation -->
                                    <div
                                        class="mt-4 flex items-center justify-between gap-2"
                                    >
                                        <button
                                            @click="goToPrevQuestion"
                                            :disabled="
                                                mobileQuestionIndex === 0
                                            "
                                            class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[10px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                                        >
                                            <ChevronLeft class="h-3.5 w-3.5" />
                                            Prev
                                        </button>

                                        <span
                                            class="text-[10px] font-black tracking-widest text-muted-foreground/60 uppercase"
                                        >
                                            {{ mobileQuestionIndex + 1 }} /
                                            {{
                                                selectedPart!.questions!.length
                                            }}
                                        </span>

                                        <button
                                            @click="goToNextQuestion"
                                            :disabled="
                                                mobileQuestionIndex >=
                                                selectedPart!.questions!
                                                    .length -
                                                    1
                                            "
                                            class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[10px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                                        >
                                            Next
                                            <ChevronRight class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- ═══ DESKTOP: Full question grid ═══ -->
                                <div
                                    class="hidden gap-6 md:grid md:grid-cols-2"
                                >
                                    <div
                                        v-for="(
                                            question, qIndex
                                        ) in selectedPart!.questions"
                                        :key="qIndex"
                                        :id="`q-${qIndex}`"
                                        :class="[
                                            'question-card relative flex scroll-mt-24 flex-col gap-4 rounded-xl border border-l-[3px] border-border/40 p-4 transition-all duration-500 md:p-5',
                                            getQuestionStatus(qIndex) ===
                                            'answered'
                                                ? 'border-primary/20 border-l-primary bg-primary/[0.02] shadow-xl shadow-primary/5'
                                                : 'border-border/40 border-l-muted bg-card/40',
                                            question.type === 'essay'
                                                ? 'md:col-span-2'
                                                : '',
                                        ]"
                                    >
                                        <!-- Question Content -->
                                        <div
                                            class="flex flex-col items-start gap-6 md:flex-row"
                                        >
                                            <!-- ID & Flag -->
                                            <div
                                                class="flex flex-shrink-0 items-center gap-4"
                                            >
                                                <div
                                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                >
                                                    {{ qIndex + 1 }}
                                                </div>
                                                <button
                                                    @click="toggleFlag(qIndex)"
                                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-border/40 transition-all hover:border-amber-500/50 hover:bg-amber-500/10"
                                                    :class="
                                                        flaggedQuestions.has(
                                                            qIndex,
                                                        )
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
                                                        class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"
                                                    >
                                                        {{
                                                            formatType(
                                                                question.type,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            question.points ??
                                                            selectedPart!
                                                                .points ??
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
                                                    class="text-base leading-relaxed whitespace-pre-wrap text-foreground"
                                                >
                                                    {{ question.text }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Answer Area -->
                                        <div class="w-full pl-0 md:pl-16">
                                            <!-- Multiple Choice / True-False -->
                                            <div
                                                v-if="
                                                    question.type ===
                                                        'multiple_choice' ||
                                                    question.type ===
                                                        'true_false'
                                                "
                                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                            >
                                                <label
                                                    v-for="(
                                                        option, oIndex
                                                    ) in question.options"
                                                    :key="option.text"
                                                    class="group/option relative flex cursor-pointer items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 transition-all hover:border-primary/60 hover:bg-primary/5 has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                                >
                                                    <div
                                                        class="relative flex h-5 w-5 items-center justify-center rounded-full border-2 border-border/60 transition-colors group-hover/option:border-primary/40 has-[:checked]:border-primary has-[:checked]:bg-primary"
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
                                                                answers[
                                                                    qIndex
                                                                ] === oIndex
                                                            "
                                                            class="h-3 w-3 text-primary-foreground"
                                                        />
                                                    </div>
                                                    <span
                                                        class="relative text-sm text-muted-foreground transition-colors group-hover/option:text-foreground has-[:checked]:text-primary"
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
                                                <div
                                                    class="group/input relative"
                                                >
                                                    <input
                                                        v-model="
                                                            answers[qIndex]
                                                        "
                                                        type="text"
                                                        placeholder="Type your answer here..."
                                                        class="w-full rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm transition-all outline-none placeholder:text-muted-foreground/30 focus:border-primary focus:ring-1 focus:ring-primary"
                                                    />
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
                                                        v-model="
                                                            answers[qIndex]
                                                        "
                                                        rows="10"
                                                        placeholder="Write your answer here..."
                                                        class="min-h-[200px] w-full resize-y rounded-lg border border-border/60 bg-muted/20 px-4 py-3 text-sm leading-relaxed transition-all outline-none placeholder:text-muted-foreground/30 focus:border-primary focus:ring-1 focus:ring-primary"
                                                    ></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Navigator (Mini-Map) - Question status overview -->
                            <div
                                v-if="selectedPart && examStarted"
                                class="fixed top-24 right-8 z-50 hidden w-80 space-y-6 lg:block"
                            >
                                <div
                                    ref="progressBoxRef"
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
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                Progress
                                            </h3>
                                            <div
                                                class="border border-primary/20 bg-primary/10 px-2 py-1 text-[9px] font-black tracking-widest text-primary uppercase italic"
                                            >
                                                {{
                                                    Object.keys(answers).length
                                                }}/{{
                                                    selectedPart!.questions!
                                                        .length
                                                }}
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-5 gap-3">
                                            <button
                                                v-for="(
                                                    _, qIndex
                                                ) in selectedPart!.questions"
                                                :key="qIndex"
                                                @click.prevent="
                                                    scrollToQuestion(qIndex)
                                                "
                                                class="group/nav-item relative flex aspect-square cursor-pointer items-center justify-center rounded-none border border-border/40 text-xs font-black transition-all duration-300"
                                                :class="[
                                                    qIndex ===
                                                    visibleQuestionIndex
                                                        ? 'scale-110 border-primary bg-primary text-primary-foreground shadow-lg ring-2 shadow-primary/40 ring-primary/30'
                                                        : getQuestionStatus(
                                                                qIndex,
                                                            ) === 'answered'
                                                          ? 'scale-105 border-primary/70 bg-primary/10 text-primary shadow-lg shadow-primary/20'
                                                          : getQuestionStatus(
                                                                  qIndex,
                                                              ) === 'flagged'
                                                            ? 'border-amber-500 bg-amber-500/20 text-amber-600 shadow-sm'
                                                            : 'bg-muted/30 text-muted-foreground hover:border-primary/50 hover:bg-muted/50',
                                                ]"
                                            >
                                                {{ qIndex + 1 }}

                                                <!-- Flag indicator -->
                                                <div
                                                    v-if="
                                                        flaggedQuestions.has(
                                                            qIndex,
                                                        )
                                                    "
                                                    class="absolute -top-1 -right-1 h-2.5 w-2.5 border border-card bg-red-600 shadow-sm"
                                                ></div>
                                            </button>
                                        </div>

                                        <div
                                            class="space-y-4 border-t border-border/20 pt-6"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div
                                                    class="flex flex-col gap-1"
                                                >
                                                    <span
                                                        class="text-[8px] font-black tracking-[0.4em] text-muted-foreground uppercase italic opacity-60"
                                                        >Current Progress</span
                                                    >
                                                    <span
                                                        v-if="
                                                            unansweredCount ===
                                                            0
                                                        "
                                                        class="progress-pct mb-1 inline-flex items-center gap-1.5 text-[10px] font-black tracking-widest text-emerald-500 uppercase"
                                                    >
                                                        <CheckCircle2
                                                            class="h-3 w-3"
                                                        />
                                                        All Answered
                                                    </span>
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
                                        <div
                                            class="grid grid-cols-2 gap-4 pt-2"
                                        >
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
                                                    {{ flaggedQuestions.size }}
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

                                <!-- Exam Summary card -->
                                <div
                                    class="rounded-none border border-border/20 bg-muted/20 p-6"
                                >
                                    <h4
                                        class="mb-4 text-[9px] font-black tracking-[0.4em] text-muted-foreground uppercase italic"
                                    >
                                        Question Status
                                    </h4>
                                    <div class="space-y-3">
                                        <!-- Answered count -->
                                        <div
                                            class="flex items-center justify-between border-b border-border/10 pb-2"
                                        >
                                            <span
                                                class="text-[9px] font-bold tracking-widest text-muted-foreground/70 uppercase"
                                                >Answered</span
                                            >
                                            <span
                                                class="font-mono text-xs font-black text-emerald-500"
                                                >{{ getAnsweredCount() }}
                                                /
                                                {{
                                                    selectedPart?.questions
                                                        ?.length ?? 0
                                                }}</span
                                            >
                                        </div>

                                        <!-- Unanswered count -->
                                        <div
                                            class="flex items-center justify-between border-b border-border/10 pb-2"
                                        >
                                            <span
                                                class="text-[9px] font-bold tracking-widest text-muted-foreground/70 uppercase"
                                                >Unanswered</span
                                            >
                                            <span
                                                class="font-mono text-xs font-black"
                                                :class="
                                                    unansweredCount > 0
                                                        ? 'text-rose-500'
                                                        : 'text-emerald-500'
                                                "
                                                >{{
                                                    unansweredCount > 0
                                                        ? unansweredCount
                                                        : 'None'
                                                }}</span
                                            >
                                        </div>

                                        <!-- Flagged count -->
                                        <div
                                            class="flex items-center justify-between border-b border-border/10 pb-2"
                                        >
                                            <span
                                                class="text-[9px] font-bold tracking-widest text-muted-foreground/70 uppercase"
                                                >Flagged</span
                                            >
                                            <span
                                                class="font-mono text-xs font-black text-amber-500"
                                                >{{
                                                    flaggedQuestions.size > 0
                                                        ? flaggedQuestions.size
                                                        : 'None'
                                                }}</span
                                            >
                                        </div>

                                        <!-- Time remaining -->
                                        <div
                                            class="flex items-center justify-between pt-1"
                                        >
                                            <span
                                                class="text-[9px] font-bold tracking-widest text-muted-foreground/70 uppercase"
                                                >Time Left</span
                                            >
                                            <span
                                                class="font-mono text-xs font-black"
                                                :class="
                                                    timeLeftSeconds < 300
                                                        ? 'text-rose-500'
                                                        : 'text-primary'
                                                "
                                                >{{ formattedTime }}</span
                                            >
                                        </div>
                                    </div>

                                    <!-- Action buttons -->
                                    <div class="mt-4 space-y-2">
                                        <button
                                            v-if="firstUnansweredIndex >= 0"
                                            @click.prevent="
                                                scrollToQuestion(
                                                    firstUnansweredIndex,
                                                )
                                            "
                                            class="flex w-full items-center justify-center gap-2 border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[9px] font-black tracking-widest text-rose-500 uppercase transition-all hover:bg-rose-500/20"
                                        >
                                            <ArrowRight class="h-3 w-3" />
                                            Jump to Unanswered
                                        </button>

                                        <!-- Submit part -->
                                        <button
                                            @click="submitPart"
                                            :disabled="isSubmitting"
                                            class="submit-celebration-btn group relative flex w-full items-center justify-center gap-2 overflow-hidden bg-primary px-4 py-3 text-[11px] font-black tracking-[0.15em] text-primary-foreground uppercase shadow-lg shadow-primary/40 transition-all hover:shadow-xl hover:shadow-primary/50 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <!-- Shine effect on hover -->
                                            <div
                                                class="absolute inset-0 w-1/3 -translate-x-full skew-x-[-12deg] bg-white/20 transition-transform duration-700 group-hover:translate-x-[400%]"
                                            ></div>
                                            <ArrowRight
                                                class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                                            />
                                            {{
                                                isSubmitting
                                                    ? currentPartHasEssay
                                                        ? 'Checking your answers...'
                                                        : 'Submitting...'
                                                    : 'Submit this part'
                                            }}
                                        </button>

                                        <button
                                            v-if="firstFlaggedIndex >= 0"
                                            @click.prevent="jumpToNextFlagged"
                                            class="flex w-full items-center justify-center gap-2 border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[9px] font-black tracking-widest text-amber-500 uppercase transition-all hover:bg-amber-500/20"
                                        >
                                            <Flag
                                                class="h-3 w-3 fill-amber-500/20"
                                            />
                                            Jump to Next Flagged
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Bottom Bar Navigator (Small) - Only show when all parts are completed -->
                        <div
                            v-if="selectedPart && examStarted"
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
                                    v-for="(_, qIndex) in selectedPart!
                                        .questions"
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
                    </template>
                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- ═════════════════════════════════════════════════════════════════ -->

                <!-- ═══ MOBILE: Progress Bottom Sheet Overlay ═══ -->
                <div v-if="selectedPart && examStarted" class="md:hidden">
                    <!-- Floating progress button -->
                    <button
                        @click="showMobileProgress = !showMobileProgress"
                        class="fixed right-4 z-[60] flex items-center gap-2 rounded-full border border-primary/20 bg-primary/90 px-3.5 py-2.5 text-[10px] font-black tracking-widest text-primary-foreground uppercase shadow-2xl shadow-primary/30 backdrop-blur-xl transition-all duration-300 hover:bg-primary active:scale-95"
                        :style="{
                            bottom: `calc(5rem + env(safe-area-inset-bottom, 0px))`,
                        }"
                    >
                        <Grid3x3 class="h-3.5 w-3.5" />
                        {{ Object.keys(answers).length }}/{{
                            selectedPart!.questions!.length
                        }}
                    </button>

                    <!-- Overlay backdrop -->
                    <transition name="modal-fade">
                        <div
                            v-if="showMobileProgress"
                            class="fixed inset-0 z-[70] bg-black/40 backdrop-blur-sm"
                            @click="showMobileProgress = false"
                        />
                    </transition>

                    <!-- Bottom sheet -->
                    <transition name="slide-up">
                        <div
                            v-if="showMobileProgress"
                            class="fixed right-0 bottom-0 left-0 z-[80] max-h-[70vh] overflow-y-auto rounded-t-2xl border border-border/50 bg-background/95 p-5 pb-8 shadow-2xl backdrop-blur-2xl"
                            style="
                                padding-bottom: max(
                                    2rem,
                                    calc(
                                        env(safe-area-inset-bottom, 0px) +
                                            0.5rem
                                    )
                                );
                            "
                        >
                            <!-- Sheet grab handle -->
                            <div
                                class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-muted-foreground/20"
                            />

                            <!-- Header -->
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <h3
                                        class="text-sm font-bold text-foreground"
                                    >
                                        Progress
                                    </h3>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ Object.keys(answers).length }} of
                                        {{ selectedPart!.questions!.length }}
                                        answered
                                    </p>
                                </div>
                                <button
                                    @click="showMobileProgress = false"
                                    class="rounded-lg border border-border/40 p-1.5 text-muted-foreground transition-all hover:bg-muted/30"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- Question grid -->
                            <div class="mb-5 grid grid-cols-5 gap-2.5">
                                <button
                                    v-for="(_, qIndex) in selectedPart!
                                        .questions"
                                    :key="qIndex"
                                    @click="
                                        mobileQuestionIndex = qIndex;
                                        visibleQuestionIndex = qIndex;
                                        showMobileProgress = false;
                                        scrollToQuestion(qIndex);
                                    "
                                    class="group/nav-item relative flex aspect-square items-center justify-center rounded-lg border text-xs font-black transition-all duration-200"
                                    :class="[
                                        qIndex === mobileQuestionIndex
                                            ? 'scale-110 border-primary bg-primary text-primary-foreground shadow-lg ring-2 shadow-primary/30 ring-primary/30'
                                            : getQuestionStatus(qIndex) ===
                                                'answered'
                                              ? 'border-primary/60 bg-primary/10 text-primary'
                                              : getQuestionStatus(qIndex) ===
                                                  'flagged'
                                                ? 'border-amber-500 bg-amber-500/20 text-amber-600'
                                                : 'border-border/40 bg-muted/20 text-muted-foreground hover:border-primary/40 hover:bg-muted/40',
                                    ]"
                                >
                                    {{ qIndex + 1 }}
                                    <div
                                        v-if="flaggedQuestions.has(qIndex)"
                                        class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-amber-500 shadow-sm"
                                    />
                                </button>
                            </div>

                            <!-- Quick stats -->
                            <div
                                class="mb-4 flex items-center justify-between rounded-lg border border-border/30 bg-muted/20 px-3.5 py-2.5"
                            >
                                <div
                                    class="flex items-center gap-2 text-[10px] text-muted-foreground"
                                >
                                    <Flag class="h-3 w-3 text-amber-500" />
                                    Flagged: {{ flaggedQuestions.size }}
                                </div>
                                <div
                                    class="flex items-center gap-2 text-[10px] text-muted-foreground"
                                >
                                    <Zap class="h-3 w-3 text-primary" />
                                    Est:
                                    {{ estimatedFinishMinutes || '--' }} min
                                </div>
                            </div>

                            <!-- Submit button -->
                            <button
                                @click="submitFromSheet"
                                :disabled="isSubmitting"
                                class="w-full rounded-xl bg-primary py-3 text-xs font-black tracking-widest text-primary-foreground uppercase shadow-lg shadow-primary/20 transition-all hover:bg-primary/90 active:scale-[0.98] disabled:opacity-50"
                            >
                                <template v-if="isSubmitting"
                                    >Submitting...</template
                                >
                                <template v-else>Submit Answers</template>
                            </button>
                        </div>
                    </transition>
                </div>

                <!--  UNANSWERED WARNING MODAL                                -->

                <!-- ═════════════════════════════════════════════════════════════════ -->

                <transition name="modal-fade">
                    <div
                        v-if="showUnansweredWarning"
                        class="fixed inset-0 z-[150] flex items-center justify-center bg-background/80 p-4 backdrop-blur-md"
                    >
                        <div
                            ref="unansweredWarningRef"
                            class="surface-card relative w-full max-w-md p-8 md:p-10"
                        >
                            <div
                                class="flex flex-col items-center gap-6 text-center"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/10"
                                >
                                    <HelpCircle
                                        class="h-6 w-6 text-amber-500"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <h3
                                        class="text-2xl font-semibold tracking-tight text-foreground"
                                    >
                                        {{
                                            isTimeoutSubmission
                                                ? "Time's Up!"
                                                : 'Almost There!'
                                        }}
                                    </h3>

                                    <p
                                        v-if="isTimeoutSubmission"
                                        class="mx-auto max-w-sm text-sm text-muted-foreground"
                                    >
                                        The time for this section has expired.
                                        Your progress will be saved
                                        automatically.
                                    </p>

                                    <p
                                        v-else
                                        class="mx-auto max-w-sm text-sm text-muted-foreground"
                                    >
                                        You have

                                        <span
                                            class="font-semibold text-amber-600"
                                            >{{ unansweredCount }}</span
                                        >

                                        unanswered question{{
                                            unansweredCount > 1 ? 's' : ''
                                        }}.
                                    </p>

                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            isTimeoutSubmission
                                                ? 'Click below to proceed to the next section.'
                                                : 'You can go back to review them or submit as-is.'
                                        }}
                                    </p>
                                </div>

                                <div class="flex w-full flex-col gap-3">
                                    <button
                                        @click="closeUnansweredWarning(true)"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-[0.98]"
                                    >
                                        <span>{{
                                            isTimeoutSubmission
                                                ? 'Continue'
                                                : 'Submit Anyway'
                                        }}</span>

                                        <ArrowRight class="h-4 w-4" />
                                    </button>

                                    <button
                                        v-if="!isTimeoutSubmission"
                                        @click="closeUnansweredWarning(false)"
                                        class="w-full py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        Review Answers
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- ══════════════════════════════════════════════════════════════════════ -->

                <!--  START CONFIRMATION MODAL                               -->

                <!-- ══════════════════════════════════════════════════════════════════════ -->

                <transition name="modal-fade">
                    <div
                        v-if="showStartModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-md"
                    >
                        <div
                            ref="startModalRef"
                            class="surface-card relative w-full max-w-md p-8 md:p-10"
                        >
                            <div
                                class="flex flex-col items-center gap-6 text-center"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10"
                                >
                                    <Play class="h-6 w-6 text-primary" />
                                </div>

                                <div class="space-y-2">
                                    <h3
                                        class="text-2xl font-semibold tracking-tight text-foreground"
                                    >
                                        Ready to Start?
                                    </h3>

                                    <p
                                        class="mx-auto max-w-sm text-sm text-muted-foreground"
                                    >
                                        You're about to begin

                                        <span
                                            class="font-medium text-foreground"
                                            >Part {{ (pendingIndex || 0) + 1 }}:

                                            {{ pendingPart?.title }}</span
                                        >.
                                    </p>
                                </div>

                                <div
                                    class="w-full space-y-3 rounded-lg bg-muted/50 p-4 text-left"
                                >
                                    <div class="flex items-start gap-3">
                                        <Info
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                        />

                                        <p
                                            class="text-sm leading-relaxed text-muted-foreground"
                                        >
                                            Full screen will be enabled for
                                            focus. Once started, this part must
                                            be completed — please avoid exiting
                                            until you’re done.
                                        </p>
                                    </div>

                                    <div class="flex items-start gap-3">
                                        <Info
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                        />

                                        <p
                                            class="text-sm leading-relaxed text-muted-foreground"
                                        >
                                            Take your time and answer each
                                            question carefully. You'll be able
                                            to review your answers before
                                            submitting.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex w-full flex-col gap-3">
                                    <button
                                        @click="confirmStart"
                                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-[0.98]"
                                    >
                                        Start Part

                                        <ArrowRight class="h-4 w-4" />
                                    </button>

                                    <button
                                        @click="showStartModal = false"
                                        class="w-full py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- ═════════════════════════════════════════════════════════════════ -->

                <!--  FULLSCREEN LOCKOUT MODAL                               -->

                <!-- ═════════════════════════════════════════════════════════════════ -->

                <transition name="modal-fade">
                    <div
                        v-if="showFullscreenLockout"
                        class="fixed inset-0 z-[200] flex items-center justify-center bg-background/80 p-4 backdrop-blur-md"
                    >
                        <div
                            ref="lockoutModalRef"
                            class="surface-card relative w-full max-w-md p-8 md:p-10"
                        >
                            <div
                                class="flex flex-col items-center gap-6 text-center"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/10"
                                >
                                    <AlertCircle
                                        class="h-6 w-6 text-amber-500"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <h3
                                        class="text-2xl font-semibold tracking-tight text-foreground"
                                    >
                                        Focus Mode Required
                                    </h3>

                                    <p
                                        class="mx-auto max-w-sm text-sm text-muted-foreground"
                                    >
                                        Please return to full screen to continue
                                        your exam. This helps maintain a fair
                                        testing environment.
                                    </p>
                                </div>

                                <button
                                    @click="reEnterFullscreen"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-[0.98]"
                                >
                                    <Maximize class="h-4 w-4" />

                                    Return to Full Screen
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- ═════════════════════════════════════════════════════════════════ -->

                <!--  SUCCESS MODAL OVERLAY                                  -->

                <!-- ═════════════════════════════════════════════════════════════════ -->

                <transition name="modal-fade">
                    <div
                        v-if="showSuccessModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 p-4 backdrop-blur-2xl"
                    >
                        <div
                            ref="successModalRef"
                            class="surface-card relative w-full max-w-md p-8 md:p-10"
                        >
                            <div
                                class="flex flex-col items-center gap-6 text-center"
                            >
                                <div
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10"
                                >
                                    <CheckCircle2
                                        class="h-8 w-8 text-emerald-500"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <h3
                                        class="text-2xl font-semibold tracking-tight text-foreground"
                                    >
                                        Part Complete!
                                    </h3>

                                    <p
                                        class="mx-auto max-w-sm text-sm text-muted-foreground"
                                    >
                                        Great work! Your answers have been saved
                                        successfully.
                                    </p>
                                </div>

                                <!-- Progress / Score Info -->

                                <div
                                    v-if="partsPendingCount > 0"
                                    class="w-full space-y-3"
                                >
                                    <div class="rounded-lg bg-muted/50 p-4">
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ partsPendingCount }} part{{
                                                partsPendingCount === 1
                                                    ? ''
                                                    : 's'
                                            }}
                                            remaining
                                        </p>
                                    </div>
                                </div>

                                <!-- Final Score Reveal -->

                                <div v-else class="w-full space-y-4">
                                    <div
                                        v-if="isCalculatingScore"
                                        class="flex flex-col items-center gap-4 py-4"
                                    >
                                        <div
                                            class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent"
                                        ></div>

                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                currentPartHasEssay
                                                    ? 'Reviewing your essay...'
                                                    : 'Calculating your score...'
                                            }}
                                        </p>
                                    </div>

                                    <div v-else class="space-y-4">
                                        <div class="rounded-lg bg-muted/50 p-6">
                                            <div
                                                class="flex items-baseline justify-center gap-2"
                                            >
                                                <span
                                                    class="text-5xl font-bold tracking-tight text-primary tabular-nums"
                                                    >{{ displayedScore }}</span
                                                >

                                                <span
                                                    class="text-xl font-semibold text-muted-foreground/40"
                                                    >/
                                                    {{
                                                        totalPossiblePoints
                                                    }}</span
                                                >
                                            </div>
                                        </div>

                                        <div
                                            v-if="isExamPendingReview"
                                            class="flex items-center justify-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-2"
                                        >
                                            <Clock
                                                class="h-4 w-4 text-amber-500"
                                            />

                                            <span
                                                class="text-sm font-medium text-amber-600"
                                                >Awaiting review</span
                                            >
                                        </div>

                                        <div
                                            v-else
                                            class="flex items-center justify-center gap-2 rounded-lg border px-4 py-2"
                                            :class="[
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
                                                    'text-sm font-medium',
                                                    feedbackContent.color,
                                                ]"
                                                >{{
                                                    feedbackContent.text
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <button
                                    @click="closeSuccessModal"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span>{{
                                        isCalculatingScore
                                            ? currentPartHasEssay
                                                ? 'Continue while reviewing'
                                                : 'Calculating...'
                                            : partsPendingCount > 0
                                              ? 'Continue to Next Part'
                                              : 'Back to Exams'
                                    }}</span>

                                    <ArrowRight
                                        v-if="!isCalculatingScore"
                                        class="h-4 w-4"
                                    />

                                    <div
                                        v-else
                                        class="h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground/20 border-t-primary-foreground"
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

                        <!-- Mobile submit button -->
                        <div class="fixed right-4 bottom-20 z-40 lg:hidden">
                            <button
                                @click="submitPart"
                                :disabled="isSubmitting"
                                class="submit-celebration-btn group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-xl bg-primary px-6 py-3.5 text-xs font-black tracking-[0.15em] text-primary-foreground uppercase shadow-2xl shadow-primary/50 transition-all hover:shadow-2xl hover:shadow-primary/70 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <!-- Shine effect on hover -->
                                <div
                                    class="absolute inset-0 w-1/3 -translate-x-full skew-x-[-12deg] bg-white/20 transition-transform duration-700 group-hover:translate-x-[400%]"
                                ></div>
                                {{
                                    isSubmitting
                                        ? currentPartHasEssay
                                            ? 'Checking...'
                                            : 'Submitting...'
                                        : 'Submit this part'
                                }}
                                <ArrowRight
                                    class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                                />
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </template>
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
