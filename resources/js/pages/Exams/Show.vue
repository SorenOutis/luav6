<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed, reactive, nextTick } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types';
import {
    Calendar, Clock, ChevronLeft, ChevronRight, BookOpen,
    CheckCircle2, HelpCircle, FileText, Settings2, GraduationCap,
    PlayCircle, ArrowRight, Layers, ListChecks, Users2, Trophy, Lock, CheckSquare2,
    Flag, Zap, BarChart3, RotateCcw
} from 'lucide-vue-next';
import { watch } from 'vue';

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
const calcTimerInterval = ref<ReturnType<typeof setInterval> | null>(null);

const unansweredCount = computed(() => {
    if (!selectedPart.value || !selectedPart.value.questions) return 0;
    
    let count = 0;
    selectedPart.value.questions.forEach((q, index) => {
        const answer = answers[index];
        // Count as unanswered if answer is undefined, null, or an empty string (for essays/identification)
        if (answer === undefined || answer === null || (typeof answer === 'string' && answer.trim() === '')) {
            count++;
        }
    });
    return count;
});

const totalQuestions = computed(() =>
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.length ?? 0), 0)
);

// ─── LIVE TIMER LOGIC ───────────────────────────────────────
const timeLeftSeconds = ref(props.exam.duration_minutes * 60);
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null);

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
    const remainingQuestionsTotal = totalQuestions.value - (submittedPartsCount.value * 1) - answeredCount; // Simplified: assumes uniform distribution or just current part context
    const remainingQuestionsInPart = (selectedPart.value.questions?.length ?? 0) - answeredCount;
    
    if (remainingQuestionsInPart > 0) {
        estimatedFinishMinutes.value = Math.ceil((remainingQuestionsInPart * avgSecondsPerQuestion) / 60);
    } else {
        estimatedFinishMinutes.value = 0;
    }
};

const formattedFinishTime = computed(() => {
    if (!examStarted.value) return null;
    const now = new Date();
    // Use the remaining duration if pace isn't established, otherwise use pace
    const minsToAdd = estimatedFinishMinutes.value !== null ? estimatedFinishMinutes.value : Math.ceil(timeLeftSeconds.value / 60);
    const finishDate = new Date(now.getTime() + minsToAdd * 60000);
    return finishDate.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
});

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
    if (answers[index] !== undefined && answers[index] !== '' && answers[index] !== null) return 'answered';
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

const getDraftKey = () => `${DRAFT_KEY_PREFIX}${props.exam.id}_${selectedPart.value?.id}`;

const saveDraft = () => {
    if (!selectedPart.value) return;
    const draft = {
        answers: { ...answers },
        flagged: Array.from(flaggedQuestions.value),
        timeLeft: timeLeftSeconds.value,
        timestamp: Date.now()
    };
    localStorage.setItem(getDraftKey(), JSON.stringify(draft));
    lastSavedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    
    // Pulse animation for sync heartbeat
    gsap.fromTo('.sync-heartbeat', 
        { scale: 1, opacity: 0.5 }, 
        { scale: 1.2, opacity: 1, duration: 0.3, yoyo: true, repeat: 1, ease: 'power2.out' }
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

// Re-trigger entrance animations when returning to list view
const runEntranceAnimations = () => {
    const tl = gsap.timeline({
        defaults: { ease: 'expo.out', duration: 0.8 }
    });

    // 1. Hero entrance
    tl.fromTo('.exam-hero', 
        { opacity: 0, y: 30 },
        { opacity: 1, y: 0, duration: 0.6 }
    );

    // 2. Part cards entrance - Tactical slide-in with overshoot
    tl.fromTo('.exam-part-card', 
        { 
            opacity: 0, 
            x: -40, 
            skewX: -5, 
            scale: 0.98 
        },
        { 
            opacity: 1, 
            x: 0, 
            skewX: 0,
            scale: 1, 
            stagger: 0.08, 
            duration: 1,
            ease: 'back.out(1.2)'
        }, 
        '-=0.5'
    );

    // 3. Bracket "Lock-In" reveal
    tl.fromTo(
        '.exam-hero .absolute.top-0, .exam-hero .absolute.bottom-0, .exam-part-card .absolute.top-0, .exam-part-card .absolute.bottom-0',
        { scale: 0 },
        {
            scale: 1,
            stagger: 0.02,
            duration: 0.5,
            ease: 'back.out(2)'
        },
        '-=0.8'
    );
};

watch(selectedPart, (newVal) => {
    if (newVal === null) {
        setTimeout(runEntranceAnimations, 50);
    }
});

// ─── INTEGRITY & ANTI-CHEATING ───────────────────────────────
const integrityWarnings = ref(0);
const showIntegrityAlert = ref(false);

const handleVisibilityChange = () => {
    if (isAdminBypass.value) return;

    if ((document.visibilityState === 'hidden' || !document.hasFocus()) && examStarted.value) {
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
                    { opacity: 1, scale: 1, y: 0, duration: 0.8, ease: 'elastic.out(1, 0.7)' }
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

const submittedPartsCount = computed(() =>
    Object.keys(props.submissions).length
);

const allPartsSubmitted = computed(() =>
    submittedPartsCount.value === props.exam.parts.length && props.exam.parts.length > 0
);

// Trigger unlock animation setup when progress changes
watch(submittedPartsCount, (newCount, oldCount) => {
    if (newCount > oldCount) {
        // Queue the next index for animation once we return to the list view
        pendingUnlockIndex.value = newCount;
    }
});

const totalScore = computed(() => 
    Object.values(props.submissions).reduce((sum, s) => sum + (Number(s.score) || 0), 0)
);

const isExamPendingReview = computed(() => 
    Object.values(props.submissions).some(s => s.status === 'pending_review')
);

const totalPossiblePoints = computed(() => 
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.reduce((qSum, q) => qSum + (q.points ?? p.points ?? 1), 0) ?? 0), 0)
);

const remainingPartsCount = computed(() =>
    props.exam.parts.length - submittedPartsCount.value
);

const isPartSubmitted = (partId: number) => {
    return !!props.submissions[partId];
};

const isPartLocked = (index: number) => {
    if (index === 0) return false;
    const previousPart = props.exam.parts[index - 1];
    return !isPartSubmitted(previousPart.id);
};

const formatDateTime = (dateStr: string) => new Date(dateStr).toLocaleString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
});

const getPartIcon = (type: string) => ({
    multiple_choice: HelpCircle,
    identification: CheckCircle2,
    essay: FileText,
    true_false: Settings2,
}[type] ?? BookOpen);

const getPartColor = (index: number) => {
    const colors = [
        'from-primary/8 to-primary/2 border-primary/15',
        'from-secondary/60 to-secondary/20 border-border/60',
        'from-primary/12 to-primary/4 border-primary/20',
        'from-muted/80 to-muted/30 border-border/50',
        'from-primary/6 to-transparent border-primary/10',
    ];
    return colors[index % colors.length];
};

const getIconColor = (index: number) => {
    const colors = ['text-primary/70', 'text-foreground/50', 'text-primary/80', 'text-foreground/60', 'text-primary/60'];
    return colors[index % colors.length];
};

const getQuestionTypes = (part: ExamPart) =>
    [...new Set(part.questions?.map(q => q.type) ?? [])];

const formatType = (type: string) => type.replace(/_/g, ' ');

const selectPart = (part: ExamPart, index: number) => {
    // Prevent selecting if exam is closed, part is already submitted, or part is locked
    if (props.exam.status === 'closed' || isPartSubmitted(part.id) || isPartLocked(index)) {
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
                { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' }
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
            await (navigator as any).keyboard.lock(['Escape', 'F11', 'Tab', 'MetaLeft', 'MetaRight', 'AltLeft', 'AltRight']);
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
                    { opacity: 1, scale: 1, y: 0, duration: 0.8, ease: 'elastic.out(1, 0.7)' }
                );
            }
        }, 10);

        setTimeout(() => { showIntegrityAlert.value = false; }, 5000);
    }
};

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    const isExamInProgress = examStarted.value || (submittedPartsCount.value > 0 && !allPartsSubmitted.value);
    if (isExamInProgress && !isSubmitting.value) {
        e.preventDefault();
        e.returnValue = 'Assessment Protocol Active: You have not completed all parts of the exam. Exiting now will compromise your submission. Are you sure?';
        return e.returnValue;
    }
};

const startPart = () => {
    // Reset state for the new part
    Object.keys(answers).forEach(key => delete answers[Number(key)]);
    flaggedQuestions.value.clear();
    estimatedFinishMinutes.value = null;
    lastSavedAt.value = null;

    examStarted.value = true;
    startTimer(); // Start the countdown when the first section begins
    loadDraft(); // Load any saved progress

    setTimeout(() => {
        gsap.fromTo(
            '.question-card',
            {
                opacity: 0,
                y: 35,
                scale: 0.97,
                rotationX: -4,
                transformOrigin: 'center top'
            },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                rotationX: 0,
                duration: 0.5,
                stagger: 0.08,
                ease: 'power3.out'
            }
        );
    }, 10);
};

const handleGlobalKeydown = (e: KeyboardEvent) => {
    const isExamInProgress = examStarted.value || (submittedPartsCount.value > 0 && !allPartsSubmitted.value);

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
    if (isExamInProgress && (e.altKey || e.metaKey || (e.ctrlKey && e.altKey))) {
        e.preventDefault();
        integrityWarnings.value++;
        showIntegrityAlert.value = true;
        setTimeout(() => { showIntegrityAlert.value = false; }, 3000);
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
    const isInput = activeElem?.tagName === 'INPUT' || activeElem?.tagName === 'TEXTAREA';

    // Numbers 1-9 for picking MCQ options
    if (!isInput && /^[1-9]$/.test(e.key)) {
        const cards = document.querySelectorAll('.question-card');
        const middle = window.innerHeight / 2;
        let bestCard = null;
        let minDistance = Infinity;

        cards.forEach(card => {
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
            if (question && (question.type === 'multiple_choice' || question.type === 'true_false')) {
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

        cards.forEach(card => {
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
    if (unansweredCount.value > 0 && !isSubmitting.value && !hasShownUnansweredWarning.value) {
        showUnansweredWarning.value = true;
        hasShownUnansweredWarning.value = true;
        
        // Animate the warning modal
        setTimeout(() => {
            if (unansweredWarningRef.value) {
                gsap.fromTo(
                    unansweredWarningRef.value,
                    { opacity: 0, scale: 0.9, y: 20 },
                    { opacity: 1, scale: 1, y: 0, duration: 0.4, ease: 'back.out' }
                );
            }
        }, 10);
        
        return; // Wait for user interaction with custom modal
    }

    isSubmitting.value = true;
    isTimeoutSubmission.value = false; // Reset timeout flag if we are proceeding with submission
    
    // Check if current part has essay
    currentPartHasEssay.value = selectedPart.value?.questions?.some(q => q.type === 'essay') || false;

    // Build detailed answers with question information
    const detailedAnswers = (selectedPart.value?.questions || []).map((question, index) => ({
        question_number: index + 1,
        question_text: question.text,
        question_type: question.type,
        points: question.points ?? selectedPart.value?.points ?? 1,
        answer: (answers[index] !== undefined && answers[index] !== null) ? answers[index] : null,
    }));

    router.post(`/exams/${props.exam.id}/parts/${selectedPart.value.id}/submit`, {
        answers: detailedAnswers,
    }, {
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
                        { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' }
                    );

                    // If all parts are done OR current part has essay, animate the total score/AI assessment
                    if (partsPendingCount.value === 0 || currentPartHasEssay.value) {
                        isCalculatingScore.value = true;
                        displayedScore.value = 0;
                        
                        // Simulate calculation delay
                        setTimeout(() => {
                            isCalculatingScore.value = false;
                            const targetScore = Number(totalScore.value) || 0;
                            
                            gsap.to(displayedScore, {
                                value: targetScore,
                                duration: 1.2,
                                ease: 'none',
                                onUpdate: () => {
                                    displayedScore.value = Math.floor(displayedScore.value);
                                },
                                onComplete: () => {
                                    displayedScore.value = targetScore;
                                }
                            });

                            // Decorative pop for the score box
                            gsap.fromTo('.final-score-box',
                                { scale: 0.8, opacity: 0, y: 20 },
                                { scale: 1, opacity: 1, y: 0, duration: 1.2, ease: 'elastic.out(1, 0.5)' }
                            );
                        }, currentPartHasEssay.value ? 2000 : 1000); // Faster UI feedback
                    }

                    // Bounce animation for checkmark
                    gsap.fromTo(
                        '.success-checkmark',
                        { scale: 0, rotate: -180 },
                        { scale: 1, rotate: 0, duration: 0.8, delay: 0.2, ease: 'elastic.out(1.2, 0.4)' }
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
            'X-Inertia-Timeout': 300000 // 5 minutes in milliseconds
        }
    });
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
            }
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

const submitPartFinal = async () => {
    if (isFinalSubmitting.value) return;
    isFinalSubmitting.value = true;
    
    // 1. Prepare data
    const detailedAnswers = (selectedPart.value?.questions || []).map((question, index) => ({
        question_number: index + 1,
        question_text: question.text,
        question_type: question.type,
        points: question.points ?? selectedPart.value?.points ?? 1,
        answer: (answers[index] !== undefined && answers[index] !== null) ? answers[index] : null,
    }));

    const hadEssay = selectedPart.value?.questions?.some(q => q.type === 'essay');
    currentPartHasEssay.value = hadEssay;

    // 2. IMMEDIATELY show calculating modal
    showSuccessModal.value = true;
    isCalculatingScore.value = true;
    displayedScore.value = 0;
    partsPendingCount.value = Math.max(0, remainingPartsCount.value - 1); // Anticipate the submission

    const calcDurationSeconds = hadEssay ? 2 : 1;
    calcCountdown.value = calcDurationSeconds;

    // Start UI countdown
    if (calcTimerInterval.value) clearInterval(calcTimerInterval.value);
    calcTimerInterval.value = setInterval(() => {
        if (calcCountdown.value > 0) {
            calcCountdown.value--;
        } else {
            if (calcTimerInterval.value) clearInterval(calcTimerInterval.value);
        }
    }, 1000);

    // Ensure DOM is updated before animating
    await nextTick();

    // GSAP Entrance for Modal (TRULY IMMEDIATE)
    if (successModalRef.value) {
        gsap.fromTo(
            successModalRef.value,
            { opacity: 0, scale: 0.85, y: 30 },
            { opacity: 1, scale: 1, y: 0, duration: 0.4, ease: 'power3.out' }
        );
    }

    // Trigger AI pre-warm in the background while modal is showing
    if (hadEssay) {
        axios.post(route('exams.preWarmAI')).catch(() => {});
    }

    let requestFinished = false;

    // 3. Start background submission
    router.post(`/exams/${props.exam.id}/parts/${selectedPart.value.id}/submit`, {
        answers: detailedAnswers,
    }, {
        onSuccess: () => {
            requestFinished = true;
            clearDraft();
            
            if (remainingPartsCount.value === 0) {
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
            }
        },
        onError: () => {
            requestFinished = true;
            isCalculatingScore.value = false;
            showSuccessModal.value = false;
            isFinalSubmitting.value = false;
            isSubmitting.value = false;
        }
    });

    // 4. Reveal logic: Wait for BOTH the minimum timer AND the server request
    setTimeout(() => {
        const checkAndReveal = () => {
            if (requestFinished) {
                isCalculatingScore.value = false;
                if (calcTimerInterval.value) clearInterval(calcTimerInterval.value);
                
                const targetScore = Number(totalScore.value) || 0;
                
                gsap.to(displayedScore, {
                    value: targetScore,
                    duration: 1.2,
                    ease: 'none',
                    onUpdate: () => {
                        displayedScore.value = Math.floor(displayedScore.value);
                    },
                    onComplete: () => {
                        displayedScore.value = targetScore;
                        isFinalSubmitting.value = false;
                        isSubmitting.value = false;
                    }
                });

                gsap.fromTo('.final-score-box',
                    { scale: 0.8, opacity: 0, y: 20 },
                    { scale: 1, opacity: 1, y: 0, duration: 1.2, ease: 'elastic.out(1, 0.5)' }
                );

                gsap.fromTo(
                    '.success-checkmark',
                    { scale: 0, rotate: -180 },
                    { scale: 1, rotate: 0, duration: 0.8, delay: 0.2, ease: 'elastic.out(1.2, 0.4)' }
                );
            } else {
                setTimeout(checkAndReveal, 200);
            }
        };
        checkAndReveal();
    }, calcDurationSeconds * 1000);
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
                Object.keys(answers).forEach(key => delete answers[Number(key)]);
                goBackToList();
                
                // If there's a pending unlock, animate it now that we are back in the grid
                if (pendingUnlockIndex.value !== null) {
                    const nextIndex = pendingUnlockIndex.value;
                    pendingUnlockIndex.value = null; // Reset
                    
                    setTimeout(() => {
                        const unlockedCard = document.querySelectorAll('.exam-part-card')[nextIndex];
                        if (unlockedCard) {
                            gsap.fromTo(unlockedCard, 
                                { x: -20, scale: 0.95, filter: 'brightness(0.5)' }, 
                                { x: 0, scale: 1, filter: 'brightness(1)', duration: 1.2, ease: 'elastic.out(1, 0.4)' }
                            );
                            
                            // Animate the lock icon breaking away
                            const lockIcon = unlockedCard.querySelector('.lucide-lock');
                            if (lockIcon) {
                                gsap.to(lockIcon, { 
                                    rotate: 30, 
                                    scale: 0, 
                                    opacity: 0, 
                                    duration: 0.6, 
                                    delay: 0.1,
                                    onComplete: () => lockIcon.remove() 
                                });
                            }
                        }
                    }, 500); // Give Inertia/Vue a moment to render the grid
                }
            }
        });
    }
};

onMounted(() => {
    runEntranceAnimations();
    
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
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.removeEventListener('mozfullscreenchange', handleFullscreenChange);
    document.removeEventListener('MSFullscreenChange', handleFullscreenChange);
});
const isExamInProgress = computed(() => 
    examStarted.value || (submittedPartsCount.value > 0 && !allPartsSubmitted.value)
);

const hideSidebar = computed(() => isExamInProgress.value && !isAdminBypass.value);

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
            bg: 'bg-primary/5'
        };
    }
    return {
        text: 'Keep Pushing Forward',
        icon: Zap,
        color: 'text-amber-500',
        border: 'border-amber-500/50',
        bg: 'bg-amber-500/5'
    };
});

// ─── DRAGGABLE WIDGET LOGIC ────────────────────────────────
const widgetPos = reactive({ x: 0, y: 0 });
const isDragging = ref(false);
const widgetRef = ref<HTMLElement | null>(null);
const startPos = { x: 0, y: 0 };
const dragBounds = reactive({ minX: -Infinity, maxX: Infinity, minY: -Infinity, maxY: Infinity });
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
        widgetPos.x = Math.max(dragBounds.minX, Math.min(dragBounds.maxX, rawX));
        widgetPos.y = Math.max(dragBounds.minY, Math.min(dragBounds.maxY, rawY));
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
        <div ref="container" class="min-h-full flex flex-col gap-0 relative overflow-hidden bg-background">
            <!-- Ambient background decorations -->
            <div
                class="fixed -top-64 -right-64 w-[800px] h-[800px] bg-primary/10 rounded-full blur-[180px] pointer-events-none opacity-50 dark:opacity-40 animate-pulse"
                style="animation-duration: 8s">
            </div>
            <div
                class="fixed top-1/4 -left-64 w-[600px] h-[600px] bg-violet-500/10 rounded-full blur-[160px] pointer-events-none opacity-30 dark:opacity-20 animate-pulse"
                style="animation-duration: 12s">
            </div>
            <div
                class="fixed -bottom-64 right-1/4 w-[700px] h-[700px] bg-blue-500/5 rounded-full blur-[150px] pointer-events-none opacity-20 dark:opacity-10">
            </div>

            <div class="flex-1 flex flex-col p-4 md:p-8 gap-6 relative z-10">

                <!-- Integrity Alert Overlay -->
                <transition name="modal-fade">
                    <div v-if="showIntegrityAlert" class="fixed top-24 left-1/2 -translate-x-1/2 z-[100] w-full max-w-md px-4 pointer-events-none">
                        <div class="bg-red-500/90 backdrop-blur-xl border border-white/20 rounded-2xl p-4 shadow-2xl flex items-center gap-4 text-white animate-bounce">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0">
                                <AlertCircle class="w-6 h-6" />
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-black uppercase tracking-widest">Security Warning</h4>
                                <p class="text-[10px] font-bold opacity-90">Potential integrity breach detected. Your session activity is being logged. Please return to full screen and do not leave the page.</p>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                <div class="animate-up flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Link v-if="!selectedPart" href="/exams"
                            class="inline-flex items-center gap-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:text-primary transition-all group px-4 py-2 rounded-xl bg-muted/30 border border-border/40 hover:border-primary/40 backdrop-blur-md">
                            <ChevronLeft class="w-3.5 h-3.5 group-hover:-translate-x-1 transition-transform" />
                            All Assessments
                        </Link>
                    </div>

                    <!-- Live Floating Timer & Smart Stats (Only in list view) -->
                    <div v-if="examStarted && !selectedPart" 
                        class="flex items-center gap-4 md:gap-6 px-6 py-3 rounded-2xl bg-black/60 border border-white/10 backdrop-blur-2xl shadow-2xl transition-all duration-500 hover:border-primary/40 group/timer relative overflow-hidden">
                        
                        <!-- Pulse decoration for timer -->
                        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover/timer:opacity-100 transition-opacity"></div>
                        
                        <!-- Draft Status (Desktop Only) -->
                        <div v-if="lastSavedAt" class="hidden lg:flex items-center gap-2 text-[8px] font-black text-muted-foreground/60 uppercase tracking-[0.2em] border-r border-white/10 pr-6 font-mono">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            SYNCED_{{ lastSavedAt.replace(/:/g, '_') }}
                        </div>

                        <!-- Pace Indicator -->
                        <div v-if="estimatedFinishMinutes !== null && estimatedFinishMinutes > 0" 
                            class="hidden md:flex items-center gap-2 text-amber-400 font-black text-[9px] uppercase tracking-[0.2em] font-mono">
                            <Zap class="w-4 h-4 fill-amber-400/20 group-hover/timer:scale-110 transition-transform" />
                            <span class="hidden lg:inline">EST_FINISH:</span> {{ estimatedFinishMinutes }}M
                        </div>

                        <div class="flex items-center gap-3 px-3 py-1 rounded-xl bg-primary/10 border border-primary/20 relative z-10" :class="timeLeftSeconds < 300 ? 'border-red-500/50 text-red-500 animate-pulse bg-red-500/10' : 'text-primary'">
                            <Clock class="w-4 h-4 group-hover/timer:rotate-12 transition-transform" />
                            <span class="font-black text-lg tracking-[0.15em] tabular-nums font-mono">{{ formattedTime }}</span>
                        </div>
                    </div>
                </div>

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <div
                    class="animate-up exam-hero relative overflow-hidden p-6 md:p-8 border border-border bg-card dark:bg-zinc-900/40 shadow-2xl group/hero"
                    @mousemove="handleMouseMove"
                >
                    <!-- Futuristic Corner Brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-foreground pointer-events-none"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-foreground pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-8">
                        <div class="space-y-4 max-w-3xl">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 border-2 border-amber-500 rotate-45 flex items-center justify-center shrink-0">
                                     <div class="w-2 h-2 bg-amber-500 rotate-45 animate-pulse"></div>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[9px] font-black text-primary uppercase tracking-[0.4em] font-mono">ASSESSMENT_READY_PROTOCOL</span>
                                    <h1 class="text-3xl md:text-5xl font-black italic uppercase tracking-tighter text-foreground leading-[0.95]">
                                        {{ selectedPart ? selectedPart.title : exam.title }}
                                    </h1>
                                </div>
                            </div>
                            
                            <div v-if="!selectedPart" class="bg-muted/30 dark:bg-zinc-950/40 p-4 border border-border/50 relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-amber-500/50"></div>
                                <p class="text-[11px] md:text-xs font-bold text-muted-foreground uppercase leading-relaxed tracking-tight">
                                    {{ exam.description || 'Quickly assess and master the material with our streamlined exam interface.' }}
                                </p>
                                <div class="mt-2 flex items-center gap-3 text-[9px] font-black text-foreground/40 uppercase tracking-widest font-mono">
                                    <Calendar class="w-3.5 h-3.5" />
                                    {{ formatDateTime(exam.exam_date) }}
                                </div>
                            </div>
                            
                            <div v-if="selectedPart && lastSavedAt" class="sync-heartbeat flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 w-fit">
                                <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                                <span class="text-[10px] font-black text-emerald-500 uppercase tracking-widest font-mono">ENCRYPTED_SYNC_{{ lastSavedAt.replace(/:/g, '_') }}</span>
                            </div>
                        </div>

                        <!-- Stats Architecture -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 bg-muted/20 dark:bg-zinc-950/20 p-6 border border-border/50 relative">
                             <!-- Stat Decoration -->
                             <div class="absolute -top-1 -left-1 w-2 h-2 bg-primary"></div>
                             <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-primary"></div>

                            <div v-if="allPartsSubmitted" class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">ACHIEVEMENT</span>
                                <div class="text-lg font-black text-primary font-mono tabular-nums">{{ totalScore }}/{{ totalPossiblePoints }}</div>
                            </div>
                            
                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">TIME_LIMIT</span>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-lg font-black font-mono tabular-nums text-foreground">{{ exam.duration_minutes }}</span>
                                    <span class="text-[8px] font-black text-primary uppercase font-mono">MIN</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">SECTIONS</span>
                                <div class="text-lg font-black font-mono tabular-nums text-foreground">{{ exam.parts.length }}</div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">TOTAL_TASKS</span>
                                <div class="text-lg font-black font-mono tabular-nums text-foreground">{{ totalQuestions }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Global Progress Bar -->
                <div v-if="!allPartsSubmitted && examStarted && !selectedPart" class="animate-up w-full mt-2 space-y-4">
                    <!-- Overall Evaluation Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[0.4em] text-primary/60">System Integrity</span>
                            <span class="text-[9px] font-black text-primary/60">{{ Math.round(overallProgress) }}% COMPLETED</span>
                        </div>
                        <div class="w-full h-1 bg-muted/30 overflow-hidden border border-primary/10 relative">
                            <div class="h-full bg-primary/40 transition-all duration-1000 ease-out" :style="{ width: `${overallProgress}%` }"></div>
                        </div>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PARTS LIST STATE                                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-if="!selectedPart">
                    <div class="animate-up flex items-center justify-between">
                        <h2 class="text-xl font-black flex items-center gap-3 uppercase italic tracking-tight">
                            <Layers class="w-6 h-6 text-primary" />
                            Exam Parts
                        </h2>
                        <span
                            class="text-xs font-black text-muted-foreground bg-muted/50 px-4 py-1.5 rounded-none border border-border/50 uppercase tracking-widest font-mono">
                            {{ exam.parts.length }} Sections
                        </span>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(part, index) in exam.parts" :key="part.id" @click="selectPart(part, index)"
                            class="exam-part-card animate-up relative flex flex-col justify-between p-6 transition-all duration-500 overflow-hidden group/part border border-border bg-card dark:bg-zinc-900/40"
                            :class="[
                                isPartSubmitted(part.id) 
                                    ? 'opacity-80' 
                                    : isPartLocked(index) 
                                        ? 'opacity-60 cursor-not-allowed grayscale' 
                                        : 'hover:shadow-xl hover:-translate-y-1 cursor-pointer'
                            ]"
                            @mousemove="handleMouseMove"
                        >
                            <!-- Futuristic Corner Brackets -->
                            <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-foreground pointer-events-none"></div>
                            <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-foreground pointer-events-none"></div>

                            <!-- Top: Status & Metadata -->
                            <div class="relative z-10 flex flex-col gap-3">
                                <div class="flex items-center justify-between">
                                    <div class="w-10 h-10 border border-amber-500/30 rotate-45 flex items-center justify-center group-hover/part:border-amber-500 transition-colors">
                                         <div class="w-2 h-2 bg-amber-500 rotate-45"></div>
                                    </div>
                                    <div v-if="isPartLocked(index)" class="p-1.5 rounded-lg bg-zinc-950/50 border border-white/5">
                                        <Lock class="w-3.5 h-3.5 text-muted-foreground/40" />
                                    </div>
                                    <div v-else-if="isPartSubmitted(part.id)" class="px-3 py-2 bg-emerald-500 text-white dark:text-zinc-950 font-black text-xs font-mono tracking-widest transform -skew-x-12 shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                                        <span class="inline-block skew-x-12">
                                            {{ submissions[part.id]?.score ?? 0 }} / {{ part.questions?.reduce((sum, q) => sum + (q.points ?? part.points ?? 1), 0) ?? 0 }}
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em] font-mono">PART_{{ (index + 1).toString().padStart(2, '0') }}</span>
                                    <h3 class="text-xl font-black text-foreground uppercase italic tracking-tight group-hover/part:text-primary transition-colors leading-none">
                                        {{ part.title }}
                                    </h3>
                                </div>

                                <!-- Middle: Question Types Stagger -->
                                <div class="bg-muted/30 dark:bg-zinc-950/40 p-4 border border-border/50 space-y-2">
                                    <div v-for="type in getQuestionTypes(part)" :key="type" class="flex items-center gap-2.5">
                                        <span class="text-amber-500 font-black text-[9px]">[!]</span>
                                        <span class="text-[9px] font-black text-muted-foreground uppercase tracking-widest font-mono">
                                            {{ formatType(type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom: Footer Info & Action -->
                            <div class="relative z-10 mt-6 pt-4 border-t border-border/10 flex items-center justify-between">
                                <div class="flex items-center gap-6 font-mono">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-primary">{{ part.questions?.length ?? 0 }}</span>
                                        <span class="text-[9px] font-bold text-muted-foreground/40 uppercase tracking-widest">TASKS</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-amber-500">{{ part.questions?.reduce((sum, q) => sum + (parseInt(q.points) || parseInt(part.points) || 1), 0) ?? 0 }}</span>
                                        <span class="text-[9px] font-bold text-muted-foreground/40 uppercase tracking-widest">POINTS</span>
                                    </div>
                                </div>
                                
                                <div v-if="!isPartSubmitted(part.id)"
                                    class="px-4 py-2 bg-foreground text-background font-black text-[10px] uppercase tracking-[0.2em] transform -skew-x-12 transition-all hover:bg-primary hover:text-primary-foreground flex items-center gap-2"
                                    :class="isPartLocked(index) ? 'opacity-20 grayscale' : ''">
                                    <span class="inline-block skew-x-12">{{ isPartLocked(index) ? 'LOCKED' : 'START' }}</span>
                                    <ArrowRight v-if="!isPartLocked(index)" class="w-3.5 h-3.5 skew-x-12" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions footer -->
                    <div
                        class="animate-up mt-2 rounded-xl border border-border/20 bg-muted/10 p-3 flex items-start gap-3">
                        <ListChecks class="w-4 h-4 text-muted-foreground/60 flex-shrink-0 mt-0.5" />
                        <p class="text-[11px] text-muted-foreground/70 leading-relaxed">
                            Click <strong>START</strong> to begin. Sections unlock sequentially. Work is auto-saved locally.
                        </p>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  QUESTIONS STATE (after start)                          -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else>
                    <div class="flex flex-col lg:flex-row lg:items-start gap-8">
                        <!-- Main Question List -->
                        <div class="flex-1 space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <h2 class="text-base font-bold flex items-center gap-2">
                                        <Layers class="w-4 h-4 text-primary" />
                                        {{ selectedPart!.title }}
                                    </h2>
                                    <div v-if="lastSavedAt" class="sync-heartbeat flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                        <CheckCircle2 class="w-2.5 h-2.5 text-emerald-500" />
                                        <span class="text-[8px] font-black text-emerald-500 uppercase tracking-widest">Synced {{ lastSavedAt }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-[9px] font-black text-muted-foreground bg-muted/30 px-3 py-1 rounded-lg border border-border/40 uppercase tracking-widest">
                                        {{ selectedPart!.questions?.length ?? 0 }} Tasks
                                    </span>
                                    <span
                                        class="text-[9px] font-black text-amber-500 bg-amber-500/5 px-3 py-1 rounded-lg border border-amber-500/20 uppercase tracking-widest font-mono">
                                        {{ selectedPart!.questions?.reduce((sum, q) => sum + (parseInt(q.points) || parseInt(selectedPart!.points) || 1), 0) ?? 0 }} Points
                                    </span>
                                </div>
                            </div>

                            <!-- Part Instructions -->
                            <div v-if="selectedPart!.instructions" class="relative p-6 bg-gradient-to-br from-amber-500/10 via-primary/10 to-primary/5 border-2 border-primary/30 rounded-none shadow-[0_0_40px_rgba(var(--primary),0.15)] overflow-hidden">
                                <!-- Animated gradient border effect -->
                                <div class="absolute inset-0 bg-[linear-gradient(90deg,transparent,rgba(var(--primary),0.1),transparent)] animate-[shimmer_3s_linear_infinite] bg-[length:200%_100%] pointer-events-none"></div>
                                
                                <!-- Corner decorations -->
                                <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary"></div>
                                <div class="absolute top-0 right-0 w-6 h-6 border-t-2 border-r-2 border-primary"></div>
                                <div class="absolute bottom-0 left-0 w-6 h-6 border-b-2 border-l-2 border-primary"></div>
                                <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary"></div>
                                
                                <div class="relative flex items-start gap-5">
                                    <div class="w-14 h-14 rounded-none bg-primary flex items-center justify-center border-2 border-primary shadow-[0_0_20px_rgba(var(--primary),0.4)] flex-shrink-0">
                                        <FileText class="w-7 h-7 text-primary-foreground" />
                                    </div>
                                    <div class="space-y-2 flex-1">
                                        <h4 class="text-[11px] font-black text-primary uppercase tracking-[0.4em] flex items-center gap-3">
                                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                                            Assessment Instructions
                                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                                        </h4>
                                        <p class="text-base md:text-lg font-black text-foreground leading-relaxed tracking-tight whitespace-pre-wrap">
                                            {{ selectedPart!.instructions }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="(question, qIndex) in selectedPart!.questions" :key="qIndex"
                                    :id="`q-${qIndex}`"
                                    :class="[
                                        'question-card relative rounded-none border-l-4 border-r border-t border-b p-6 md:p-8 flex flex-col gap-8 transition-all duration-500',
                                        getQuestionStatus(qIndex) === 'answered' 
                                            ? 'border-l-primary border-primary/20 bg-primary/[0.02] shadow-[0_0_40px_rgba(var(--primary),0.05)]' 
                                            : 'border-l-muted border-border/40 bg-card/40',
                                        question.type === 'essay' ? 'md:col-span-2' : ''
                                    ]">
                                    
                                    <!-- Decorative elements -->
                                    <div class="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-primary/20"></div>

                                    <!-- Question Content -->
                                    <div class="flex flex-col md:flex-row gap-6 items-start">
                                        <!-- ID & Flag -->
                                        <div class="flex items-center gap-4 flex-shrink-0">
                                            <div class="w-14 h-14 flex items-center justify-center border-2 border-primary/40 bg-primary/5 text-xl font-black text-primary rotate-45">
                                                <span class="-rotate-45">{{ qIndex + 1 }}</span>
                                            </div>
                                            <button @click="toggleFlag(qIndex)"
                                                class="w-10 h-10 flex items-center justify-center transition-all duration-300 border border-border/40 hover:bg-amber-500/10 hover:border-amber-500/50"
                                                :class="flaggedQuestions.has(qIndex) ? 'bg-amber-500/20 border-amber-500/60 text-amber-500' : 'text-muted-foreground/30'">
                                                <Flag class="w-4 h-4" :class="flaggedQuestions.has(qIndex) ? 'fill-amber-500' : ''" />
                                            </button>
                                        </div>

                                        <!-- Text & Type -->
                                        <div class="flex-1 space-y-4">
                                            <div class="flex items-center gap-3">
                                                <span class="text-[9px] font-black text-primary uppercase tracking-[0.3em] bg-primary/10 px-2 py-0.5 border border-primary/20 italic">
                                                    {{ formatType(question.type) }}
                                                </span>
                                                <span class="text-[9px] font-black text-muted-foreground uppercase tracking-[0.3em]">
                                                    Value: {{ question.points ?? selectedPart!.points ?? 1 }} Units
                                                </span>
                                            </div>
                                            <p class="text-lg md:text-xl font-black leading-tight text-foreground/90 italic tracking-tight whitespace-pre-wrap">
                                                {{ question.text }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Answer Area -->
                                    <div class="w-full pl-0 md:pl-20">
                                        <!-- Multiple Choice / True-False -->
                                        <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'"
                                            class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <label v-for="(option, oIndex) in question.options" :key="option.text"
                                                class="flex items-center gap-4 px-6 py-4 border border-border/60 bg-muted/20 hover:border-primary/60 hover:bg-primary/5 cursor-pointer transition-all duration-300 group/option relative overflow-hidden has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                                                
                                                <!-- Background Decoration -->
                                                <div class="absolute right-0 bottom-0 w-8 h-8 bg-primary/5 -rotate-45 translate-x-4 translate-y-4 group-hover/option:bg-primary/10 transition-colors"></div>

                                                <div class="relative flex items-center justify-center w-5 h-5 border-2 border-border/60 group-hover/option:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary">
                                                    <input type="radio" :name="`q-${qIndex}`" :value="oIndex"
                                                        v-model.number="answers[qIndex]" class="sr-only" />
                                                    <Check v-if="answers[qIndex] === oIndex" class="w-3 h-3 text-primary-foreground" />
                                                </div>
                                                <span class="relative text-sm font-black tracking-wider text-muted-foreground group-hover/option:text-foreground transition-colors has-[:checked]:text-primary whitespace-pre-wrap">{{ option.text }}</span>
                                            </label>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'" class="max-w-xl">
                                            <div class="relative group/input">
                                                <input v-model="answers[qIndex]" type="text" placeholder="ENTER RESPONSE..."
                                                    class="w-full px-6 py-4 rounded-none border border-border/60 bg-muted/20 focus:border-primary outline-none transition-all duration-300 text-sm font-black tracking-widest placeholder:text-muted-foreground/30" />
                                                <div class="absolute right-4 top-1/2 -translate-y-1/2 opacity-0 group-focus-within/input:opacity-100 transition-opacity">
                                                    <Zap class="w-4 h-4 text-primary animate-pulse" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Essay -->
                                        <div v-else-if="question.type === 'essay'" class="w-full">
                                            <div class="relative group/textarea">
                                                <textarea v-model="answers[qIndex]" rows="10" placeholder="INITIALIZE DETAILED RESPONSE..."
                                                    class="w-full px-8 py-6 rounded-none border border-border/60 bg-muted/20 focus:border-primary outline-none transition-all duration-300 text-base font-bold leading-relaxed resize-y min-h-[300px] placeholder:text-muted-foreground/30"></textarea>
                                                <div class="absolute bottom-4 right-6 flex items-center gap-3 text-[9px] font-black text-primary uppercase tracking-[0.4em] opacity-40">
                                                    <Terminal class="w-3 h-3" />
                                                    SECURE DATA ENTRY
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Navigator (Mini-Map) - Only show when all parts are completed -->
                        <div v-if="allPartsSubmitted" class="hidden lg:block sticky top-8 w-80 space-y-6">
                            <div class="p-8 rounded-none border border-primary/20 bg-card shadow-2xl relative overflow-hidden group">
                                <!-- Background Glow -->
                                <div class="absolute -top-20 -right-20 w-40 h-40 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors duration-700"></div>
                                
                                <div class="relative space-y-8">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-[10px] font-black text-muted-foreground uppercase tracking-[0.4em]">Tactical Overlay</h3>
                                        <div class="px-2 py-1 bg-primary/10 border border-primary/20 text-[9px] font-black text-primary uppercase italic tracking-widest">
                                            {{ Object.keys(answers).length }}/{{ selectedPart!.questions!.length }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-5 gap-3">
                                        <a v-for="(_, qIndex) in selectedPart!.questions" :key="qIndex"
                                            :href="`#q-${qIndex}`"
                                            class="aspect-square rounded-none flex items-center justify-center text-xs font-black transition-all duration-300 border border-border/40 relative group/nav-item"
                                            :class="[
                                                getQuestionStatus(qIndex) === 'answered'
                                                    ? 'bg-primary text-primary-foreground border-primary shadow-[0_0_15px_rgba(var(--primary),0.3)] scale-105'
                                                    : getQuestionStatus(qIndex) === 'flagged'
                                                    ? 'bg-amber-500 text-white border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.3)]'
                                                    : 'bg-muted/30 text-muted-foreground hover:border-primary/50 hover:bg-muted/50'
                                            ]">
                                            {{ qIndex + 1 }}
                                            
                                            <!-- Flag indicator -->
                                            <div v-if="flaggedQuestions.has(qIndex)" 
                                                class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-600 border border-card shadow-sm"></div>
                                        </a>
                                    </div>

                                    <div class="space-y-4 pt-6 border-t border-border/20">
                                        <div class="flex items-center justify-between">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.4em] opacity-60 italic">Current Progress</span>
                                                <span class="text-xl font-black text-foreground italic">{{ Math.round((Object.keys(answers).length / selectedPart!.questions!.length) * 100) }}%</span>
                                            </div>
                                            <Trophy class="w-6 h-6 text-primary/20" />
                                        </div>
                                        <div class="h-1.5 w-full bg-muted/30 rounded-none overflow-hidden border border-border/40">
                                            <div class="h-full bg-primary transition-all duration-1000 ease-out shadow-[0_0_10px_rgba(var(--primary),0.5)]" 
                                                :style="{ width: `${(Object.keys(answers).length / selectedPart!.questions!.length) * 100}%` }"></div>
                                        </div>
                                    </div>

                                    <!-- Quick Stats -->
                                    <div class="grid grid-cols-2 gap-4 pt-2">
                                        <div class="flex flex-col gap-1">
                                            <span class="text-[8px] font-black text-muted-foreground uppercase tracking-widest opacity-40 italic">Flagged</span>
                                            <span class="text-xs font-black text-amber-500 flex items-center gap-2">
                                                <Flag class="w-3 h-3 fill-amber-500/20" />
                                                {{ flaggedQuestions.size }} Units
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="text-[8px] font-black text-muted-foreground uppercase tracking-widest opacity-40 italic">System Clock</span>
                                            <span class="text-xs font-black text-primary flex items-center gap-2">
                                                <Zap class="w-3 h-3 animate-pulse" />
                                                {{ estimatedFinishMinutes || '--' }}m Est.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tips/Shortcuts card -->
                            <div class="p-6 rounded-none border border-border/20 bg-muted/20">
                                <h4 class="text-[9px] font-black text-muted-foreground uppercase tracking-[0.4em] mb-4 italic">Protocol Shortcuts</h4>
                                <ul class="space-y-3">
                                    <li class="flex items-center gap-3 text-[9px] font-bold text-muted-foreground/80 uppercase tracking-widest">
                                        <div class="w-6 h-6 rounded-none bg-primary/10 flex items-center justify-center font-black text-primary border border-primary/20">1-9</div>
                                        Selection Input
                                    </li>
                                    <li class="flex items-center gap-3 text-[9px] font-bold text-muted-foreground/80 uppercase tracking-widest">
                                        <div class="w-6 h-6 rounded-none bg-primary/10 flex items-center justify-center font-black text-primary border border-primary/20">F</div>
                                        Integrity Flag
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Bottom Bar Navigator (Small) - Only show when all parts are completed -->
                    <div v-if="allPartsSubmitted" class="lg:hidden fixed bottom-0 left-0 right-0 p-4 z-40">
                         <div class="bg-black/80 backdrop-blur-2xl rounded-2xl border border-white/10 p-3 shadow-2xl flex items-center gap-3 overflow-x-auto no-scrollbar">
                            <div class="text-[9px] font-black uppercase text-muted-foreground vertical-writing rotate-180 mr-1">NAV</div>
                            <div v-for="(_, qIndex) in selectedPart!.questions" :key="qIndex"
                                class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black border border-white/5 transition-all relative"
                                :class="[
                                    getQuestionStatus(qIndex) === 'answered' ? 'bg-primary text-primary-foreground' : 
                                    getQuestionStatus(qIndex) === 'flagged' ? 'bg-amber-500 text-white' : 
                                    'bg-white/5 text-white/40'
                                ]">
                                {{ qIndex + 1 }}
                                <div v-if="flaggedQuestions.has(qIndex)" class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-red-500 border border-black shadow-sm"></div>
                            </div>
                         </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-6 flex justify-end pt-8">
                        <button @click="submitPart" :disabled="isSubmitting"
                            class="group relative px-10 py-5 bg-primary text-primary-foreground font-black hover:bg-primary/90 transition-all flex items-center gap-6 disabled:opacity-50 disabled:cursor-not-allowed skew-x-[-12deg] shadow-[0_20px_40px_-10px_rgba(var(--primary),0.4)]">
                            
                            <span class="skew-x-[12deg] text-base tracking-[0.2em] uppercase">{{ isSubmitting ? (currentPartHasEssay ? 'Assessing Answers...' : 'Transmitting Data...') : 'Finalize Section' }}</span>
                            
                            <div class="skew-x-[12deg] p-1.5 bg-primary-foreground/20 group-hover:bg-primary-foreground/30 transition-colors">
                                <ArrowRight v-if="!isSubmitting" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                                <div v-else class="w-5 h-5 border-3 border-primary-foreground/20 border-t-primary-foreground rounded-full animate-spin"></div>
                            </div>

                            <!-- Decorative Button Edge -->
                            <div class="absolute -right-1 -top-1 w-2 h-2 bg-primary group-hover:scale-150 transition-transform"></div>
                        </button>
                    </div>
                </template>

            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  UNANSWERED WARNING MODAL                                -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div v-if="showUnansweredWarning" class="fixed inset-0 bg-amber-950/60 backdrop-blur-md z-[150] flex items-center justify-center p-4">
                    <div ref="unansweredWarningRef"
                        class="relative max-w-md w-full rounded-none border-2 border-amber-500/50 bg-card p-6 md:p-10 shadow-[0_0_50px_rgba(245,158,11,0.3)] overflow-hidden">

                        <!-- Futuristic Corner Accents -->
                        <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-amber-500"></div>
                        <div class="absolute top-0 right-0 w-6 h-6 border-t-2 border-r-2 border-amber-500"></div>
                        <div class="absolute bottom-0 left-0 w-6 h-6 border-b-2 border-l-2 border-amber-500"></div>
                        <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-amber-500"></div>

                        <div class="relative z-10 flex flex-col items-center gap-6 text-center">
                            <div class="relative w-16 h-16 flex items-center justify-center border-2 border-amber-500/50 rotate-45">
                                <AlertCircle class="w-8 h-8 text-amber-500 -rotate-45" />
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-xl md:text-3xl font-black text-foreground tracking-tighter uppercase italic">
                                    {{ isTimeoutSubmission ? 'Time is up!' : 'Unanswered Questions' }}
                                </h3>
                                <div class="h-0.5 w-16 bg-amber-500 mx-auto"></div>
                                <p v-if="isTimeoutSubmission" class="text-muted-foreground text-xs md:text-sm font-bold leading-relaxed max-w-sm mx-auto uppercase tracking-wider">
                                    The time for this section has expired. Your progress will be saved automatically.
                                </p>
                                <p v-else class="text-muted-foreground text-xs md:text-sm font-bold leading-relaxed max-w-sm mx-auto uppercase tracking-wider">
                                    You have <span class="text-amber-500 font-black text-lg">{{ unansweredCount }}</span> unanswered question{{ unansweredCount > 1 ? 's' : '' }} in this section.
                                </p>
                                <p class="text-[10px] text-muted-foreground/70 font-medium italic">
                                    {{ isTimeoutSubmission ? 'Please click the button below to proceed to the next section or finalize your submission.' : 'You may proceed, but these will be marked as incorrect.' }}
                                </p>
                            </div>

                            <div class="flex flex-col w-full gap-3 mt-4">
                                <button @click="closeUnansweredWarning(true)"
                                    class="w-full px-6 py-4 bg-amber-500 text-black font-black hover:bg-amber-400 transition-all flex items-center justify-center gap-4 group/btn uppercase tracking-[0.2em] text-xs skew-x-[-12deg]">
                                    <span class="skew-x-[12deg]">{{ isTimeoutSubmission ? 'Continue' : 'Proceed Anyway' }}</span>
                                    <ArrowRight class="w-5 h-5 group-hover/btn:translate-x-2 transition-transform skew-x-[12deg]" />
                                </button>
                                <button v-if="!isTimeoutSubmission" @click="closeUnansweredWarning(false)"
                                    class="w-full py-3 text-muted-foreground font-black hover:text-foreground transition-colors text-[10px] uppercase tracking-[0.3em]">
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
                <div v-if="showStartModal" class="fixed inset-0 bg-background/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                    <div ref="startModalRef" 
                        class="relative max-w-md w-full rounded-none border-2 border-primary/20 bg-card p-6 md:p-10 shadow-[0_0_50px_rgba(var(--primary),0.1)] overflow-hidden">
                        
                        <!-- Futuristic Corner Accents -->
                        <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary"></div>
                        <div class="absolute top-0 right-0 w-6 h-6 border-t-2 border-r-2 border-primary"></div>
                        <div class="absolute bottom-0 left-0 w-6 h-6 border-b-2 border-l-2 border-primary"></div>
                        <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary"></div>
                        
                        <div class="relative z-10 flex flex-col items-center gap-6 text-center">
                            <div class="relative w-16 h-16 flex items-center justify-center border-2 border-amber-500/50 rotate-45">
                                <AlertCircle class="w-8 h-8 text-amber-500 -rotate-45" />
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-xl md:text-3xl font-black text-foreground tracking-tighter uppercase italic">Security Protocol</h3>
                                <div class="h-0.5 w-16 bg-primary mx-auto"></div>
                                <p class="text-muted-foreground text-xs md:text-sm font-bold leading-relaxed max-w-sm mx-auto uppercase tracking-wider">
                                    Initiating <span class="text-primary font-black underline decoration-2">Part {{ (pendingIndex || 0) + 1 }}: {{ pendingPart?.title }}</span>.
                                </p>
                            </div>

                            <div class="w-full grid gap-3 p-4 bg-muted/50 border border-border/50 text-left font-mono">
                                <div class="flex items-start gap-3">
                                    <span class="text-amber-500 font-black text-[10px]">[!]</span>
                                    <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest leading-tight">Persistence Required: No exit allowed until completion.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="text-amber-500 font-black text-[10px]">[!]</span>
                                    <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest leading-tight">Secure Environment: Auto-enabling Full Screen mode.</p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <span class="text-amber-500 font-black text-[10px]">[!]</span>
                                    <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest leading-tight">Integrity Monitoring: Unauthorized exits will be flagged.</p>
                                </div>
                            </div>

                            <div class="flex flex-col w-full gap-3">
                                <button @click="confirmStart"
                                    class="w-full px-6 py-4 bg-primary text-primary-foreground font-black hover:bg-primary/90 transition-all flex items-center justify-center gap-4 group/btn uppercase tracking-[0.2em] text-xs skew-x-[-12deg]">
                                    <span class="skew-x-[12deg]">Start Now</span>
                                    <ArrowRight class="w-5 h-5 group-hover/btn:translate-x-2 transition-transform skew-x-[12deg]" />
                                </button>
                                <button @click="showStartModal = false"
                                    class="w-full py-2 text-muted-foreground font-black hover:text-foreground transition-colors text-[9px] uppercase tracking-[0.3em]">
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
                <div v-if="showFullscreenLockout" class="fixed inset-0 bg-red-950/90 backdrop-blur-xl z-[200] flex items-center justify-center p-4">
                    <div ref="lockoutModalRef" 
                        class="relative max-w-md w-full rounded-none border-2 border-red-600 bg-black p-6 md:p-10 shadow-[0_0_100px_rgba(220,38,38,0.5)] overflow-hidden">
                        
                        <!-- Warning Scanline Effect -->
                        <div class="absolute inset-0 bg-[linear-gradient(rgba(18,16,16,0)_50%,rgba(0,0,0,0.25)_50%),linear-gradient(90deg,rgba(255,0,0,0.06),rgba(0,255,0,0.02),rgba(0,0,255,0.06))] bg-[length:100%_2px,3px_100%] pointer-events-none"></div>
                        
                        <div class="relative z-10 flex flex-col items-center gap-8 text-center">
                            <div class="relative w-20 h-20 flex items-center justify-center border-2 border-red-600 animate-pulse">
                                <Lock class="w-10 h-10 text-red-600" />
                                <div class="absolute -top-1 -left-1 w-2 h-2 bg-red-600"></div>
                                <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-red-600"></div>
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-3xl font-black text-red-600 tracking-tighter uppercase italic animate-pulse">Access Denied</h3>
                                <div class="h-0.5 w-full bg-red-600/30">
                                    <div class="h-full bg-red-600 w-1/3 animate-[shimmer_2s_infinite]"></div>
                                </div>
                                <p class="text-red-500 text-base font-black leading-relaxed uppercase tracking-widest">
                                    Secure Mode Compromised
                                </p>
                                <p class="text-red-500/60 text-[9px] font-black max-w-xs mx-auto uppercase tracking-[0.1em] leading-relaxed">
                                    Mandatory Full Screen Protocol Active. All assessment activities suspended until re-entry.
                                </p>
                            </div>

                            <button @click="reEnterFullscreen"
                                class="w-full px-6 py-5 bg-red-600 text-white font-black hover:bg-red-700 transition-all flex items-center justify-center gap-4 group/btn uppercase tracking-[0.3em] text-xs shadow-[0_0_30px_rgba(220,38,38,0.4)]">
                                <Zap class="w-5 h-5 animate-bounce" />
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
                <div v-if="showSuccessModal" class="fixed inset-0 bg-background/90 backdrop-blur-2xl z-50 flex items-center justify-center p-4">
                    <div ref="successModalRef" 
                        class="relative max-w-md w-full rounded-none border-2 border-primary/30 bg-card p-6 md:p-10 shadow-[0_0_80px_rgba(var(--primary),0.15)] overflow-hidden">
                        
                        <!-- Futuristic Grid Background -->
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[length:40px_40px] bg-[linear-gradient(to_right,#888_1px,transparent_1px),linear-gradient(to_bottom,#888_1px,transparent_1px)]"></div>
                        
                        <div class="relative z-10 flex flex-col items-center gap-8 text-center">
                            <!-- Animated Success Ring -->
                            <div class="success-checkmark relative w-24 h-24 flex items-center justify-center">
                                <div class="absolute inset-0 border border-dashed border-primary/40 rounded-full animate-[spin_10s_linear_infinite]"></div>
                                <div class="absolute inset-1 border border-primary rounded-full"></div>
                                <div class="w-16 h-16 bg-primary flex items-center justify-center shadow-[0_0_30px_rgba(var(--primary),0.5)]">
                                    <svg class="w-8 h-8 text-primary-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <h3 class="text-3xl md:text-4xl font-black text-foreground tracking-tighter uppercase italic">Exam Complete</h3>
                                <div class="flex items-center justify-center gap-3">
                                    <div class="h-px w-8 bg-primary"></div>
                                    <span class="text-[9px] font-black text-primary uppercase tracking-[0.4em]">Data Synchronized</span>
                                    <div class="h-px w-8 bg-primary"></div>
                                </div>
                            </div>

                            <!-- Progress / Score Info -->
                            <div v-if="partsPendingCount > 0" class="w-full pt-6 border-t border-border">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="flex items-center gap-3 px-4 py-2 border-2 border-primary text-primary font-black uppercase tracking-[0.2em] text-[10px] skew-x-[-10deg]">
                                        <span class="skew-x-[10deg]">{{ partsPendingCount }} Part{{ partsPendingCount === 1 ? '' : 's' }} Remaining</span>
                                    </div>
                                    <p class="text-[9px] text-muted-foreground font-black uppercase tracking-[0.3em] animate-pulse">Preparing next deployment phase...</p>
                                </div>
                            </div>

                            <!-- Final Score Reveal -->
                            <div v-else class="w-full pt-6 border-t border-border flex flex-col items-center gap-6">
                                <div v-if="isCalculatingScore" class="flex flex-col items-center gap-6 p-8 w-full">
                                    <div class="relative w-16 h-16">
                                        <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
                                        <div class="absolute inset-0 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                                        <Zap class="absolute inset-0 m-auto w-6 h-6 text-primary animate-pulse" />
                                    </div>
                                    <div class="space-y-2 text-center">
                                        <p class="text-xs font-black text-primary uppercase tracking-[0.4em] animate-pulse">
                                            {{ currentPartHasEssay ? 'Assessment Protocol Active' : 'Calculating your score' }}
                                        </p>
                                        <p class="text-[8px] text-muted-foreground font-bold uppercase tracking-widest opacity-60 italic">
                                            {{ currentPartHasEssay ? 'LSI is analyzing your narrative response' : 'Excluding Manual Review Components' }}
                                        </p>
                                        <!-- Countdown Timer -->
                                        <div class="mt-4 flex items-center justify-center gap-2">
                                            <div class="px-3 py-1 bg-primary/10 border border-primary/30 rounded-full flex items-center gap-2">
                                                <span class="text-[10px] font-mono font-black text-primary">T-MINUS: {{ calcCountdown }}S</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="final-score-box flex flex-col items-center p-8 bg-muted/50 border border-border shadow-inner w-full relative group">
                                    <span class="text-[9px] font-black uppercase tracking-[0.4em] text-muted-foreground/50 mb-4 italic">Performance Analytics</span>
                                    
                                    <div class="flex items-baseline gap-3">
                                        <span class="text-6xl md:text-7xl font-black text-primary tabular-nums tracking-tighter leading-none">{{ displayedScore }}</span>
                                        <span class="text-2xl font-black text-muted-foreground/30">/ {{ totalPossiblePoints }}</span>
                                    </div>

                                    <div class="mt-6 flex flex-col items-center gap-3 w-full">
                                        <div v-if="isExamPendingReview" class="flex items-center gap-3 px-4 py-2 border border-amber-500/50 bg-amber-500/5">
                                            <Clock class="w-4 h-4 text-amber-500" />
                                            <span class="text-[10px] font-black text-amber-500 uppercase tracking-[0.2em]">Validation Pending</span>
                                        </div>
                                        <div v-else :class="['flex items-center gap-3 px-6 py-2 border w-full justify-center animate-in fade-in zoom-in duration-500', feedbackContent.border, feedbackContent.bg]">
                                            <component :is="feedbackContent.icon" :class="['w-4 h-4', feedbackContent.color]" />
                                            <span :class="['text-[11px] font-black uppercase tracking-[0.3em]', feedbackContent.color]">{{ feedbackContent.text }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button @click="closeSuccessModal" :disabled="isCalculatingScore"
                                class="w-full px-8 py-5 bg-primary text-primary-foreground font-black hover:bg-primary/90 transition-all flex items-center justify-center gap-4 uppercase tracking-[0.3em] text-xs skew-x-[-12deg] shadow-[0_15px_30px_rgba(var(--primary),0.3)] disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="skew-x-[12deg]">{{ isCalculatingScore ? (currentPartHasEssay ? 'Assessing...' : 'Calculating...') : (partsPendingCount > 0 ? 'Next Deployment' : 'Return to Page') }}</span>
                                <ChevronRight v-if="!isCalculatingScore" class="w-5 h-5 skew-x-[12deg]" />
                                <div v-else class="w-5 h-5 border-2 border-primary-foreground/20 border-t-primary-foreground rounded-full animate-spin skew-x-[12deg]"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- ─── DRAGGABLE EXAM WIDGET ────────────────────────────── -->
            <transition name="modal-fade">
                <div ref="widgetRef" v-if="examStarted && selectedPart && !showSuccessModal" 
                    class="fixed bottom-8 right-8 z-[100] group/widget transition-transform duration-75"
                    :style="{ transform: `translate(${widgetPos.x}px, ${widgetPos.y}px)` }">
                    
                    <div class="relative bg-card/90 dark:bg-black/80 backdrop-blur-2xl border border-border dark:border-white/10 rounded-2xl p-4 shadow-2xl w-56 md:w-64 overflow-hidden select-none"
                        :class="{ 'cursor-grabbing': isDragging, 'cursor-grab': !isDragging, 'ring-2 ring-primary/20': isDragging }"
                        @mousedown="onDragStart">
                        
                        <!-- Drag Indicator (Visual Only) -->
                        <div class="absolute top-0 left-0 right-0 h-6 flex items-center justify-center opacity-40 group-hover/widget:opacity-100 transition-opacity pointer-events-none">
                            <div class="w-12 h-1 bg-foreground/10 rounded-full"></div>
                        </div>

                        <!-- Widget Content -->
                        <div class="space-y-3 mt-2">
                            <!-- Timer Row -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                                    <span class="text-[9px] font-black text-primary uppercase tracking-widest font-mono">PART_ACTIVE</span>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1 rounded-lg bg-primary/5 dark:bg-primary/10 border border-primary/20"
                                    :class="timeLeftSeconds < 300 ? 'border-red-500/50 text-red-500 animate-pulse bg-red-500/5 dark:bg-red-500/10' : 'text-primary'">
                                    <Clock class="w-3 h-3" />
                                    <span class="font-mono font-black text-sm tracking-widest">{{ formattedTime }}</span>
                                </div>
                            </div>

                            <!-- Progress Section -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest truncate max-w-[120px]">
                                        {{ selectedPart.title }}
                                    </span>
                                    <span class="text-[8px] font-black text-primary font-mono">{{ Math.round(partProgress) }}%</span>
                                </div>
                                <div class="h-1.5 w-full bg-foreground/5 rounded-full overflow-hidden border border-border dark:border-white/5 relative">
                                    <div class="h-full bg-primary transition-all duration-500 ease-out shadow-[0_0_10px_rgba(var(--primary),0.4)]"
                                        :style="{ width: `${partProgress}%` }">
                                        <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats Row -->
                            <div v-if="estimatedFinishMinutes !== null && estimatedFinishMinutes > 0" 
                                class="flex items-center gap-2 pt-1">
                                <Zap class="w-3 h-3 text-amber-500 fill-amber-500/20" />
                                <span class="text-[8px] font-black text-amber-500 uppercase tracking-widest font-mono">EST_FINISH: {{ estimatedFinishMinutes }}M</span>
                            </div>
                        </div>

                        <!-- Tech Decoration -->
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 border border-primary/5 dark:border-primary/10 rotate-45 pointer-events-none"></div>
                        <div class="absolute -left-2 top-1/2 -translate-y-1/2 w-0.5 h-8 bg-primary/20 dark:bg-primary/30 rounded-full pointer-events-none"></div>
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
    0% { transform: translateX(-100%); }
    100% { transform: translateX(1000%); }
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
