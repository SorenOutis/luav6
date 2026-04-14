<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, ref, computed, reactive } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
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
const answers = reactive<Record<number, string | number>>({}); // Store answers by question index
const isSubmitting = ref(false);
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
        defaults: { ease: 'power4.out', duration: 1.0 }
    });

    // Reset base state to hidden before animating
    gsap.set('.animate-up', { opacity: 0, y: 25 });
    gsap.set('.exam-part-card', { opacity: 0, y: 40, scale: 0.98 });

    // Hero: main card in first
    tl.to('.exam-hero', { opacity: 1, y: 0, duration: 0.9 });

    // Hero components
    tl.fromTo('.exam-hero-left', { opacity: 0, y: 30 }, { opacity: 1, y: 0 }, '-=0.6');
    tl.fromTo('.exam-hero-stats', { opacity: 0, scale: 0.9, y: 20 }, { opacity: 1, scale: 1, y: 0, duration: 0.8 }, '-=0.7');
    tl.fromTo('.exam-stat', { opacity: 0, x: -10 }, { opacity: 1, x: 0, stagger: 0.1, duration: 0.5 }, '-=0.4');

    // Parts list cards
    tl.fromTo('.exam-part-card', 
        { opacity: 0, y: 40, scale: 0.98 }, 
        { 
            opacity: 1, 
            y: 0, 
            scale: 1, 
            stagger: 0.08, 
            duration: 0.8 
        }, 
        '-=0.5'
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
    if (document.visibilityState === 'hidden' && examStarted.value) {
        integrityWarnings.value++;
        showIntegrityAlert.value = true;
        
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
            await (navigator as any).keyboard.lock(['Escape', 'F11', 'Tab']);
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
    if (!isFullscreen.value && isExamInProgress.value) {
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
        e.returnValue = 'Mission Protocol Active: You have not completed all parts of the exam. Exiting now will compromise your submission. Are you sure?';
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

    // Block Alt+Tab, Alt+Esc, and Alt+F4 during exam to prevent switching apps
    if (isExamInProgress && e.key === 'Tab' && e.altKey) {
        e.preventDefault();
        return;
    }

    if (isExamInProgress && e.key === 'Escape' && e.altKey) {
        e.preventDefault();
        return;
    }

    // Block Alt key alone during exam
    if (isExamInProgress && e.key === 'Alt') {
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

    // Smart Review Nudge
    const unansweredCount = (selectedPart.value.questions?.length ?? 0) - Object.keys(answers).length;
    if (unansweredCount > 0 && !isSubmitting.value) {
        const confirmResult = confirm(`You have ${unansweredCount} unanswered questions in this section. Are you sure you want to proceed to the next part?`);
        if (!confirmResult) return;
    }

    isSubmitting.value = true;

    try {
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

                // Animate modal
                setTimeout(() => {
                    if (successModalRef.value) {
                        gsap.fromTo(
                            successModalRef.value,
                            { opacity: 0, scale: 0.85, y: 30 },
                            { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' }
                        );

                        // If all parts are done, animate the total score
                        if (partsPendingCount.value === 0) {
                            isCalculatingScore.value = true;
                            displayedScore.value = 0;
                            
                            // Simulate calculation delay
                            setTimeout(() => {
                                isCalculatingScore.value = false;
                                const targetScore = Number(totalScore.value) || 0;
                                
                                gsap.to(displayedScore, {
                                    value: targetScore,
                                    duration: 1.5,
                                    ease: 'power4.out',
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
                            }, 3000); // 3-second "calculation"
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
        });
    } finally {
        isSubmitting.value = false;
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
const isExamInProgress = computed(() => 
    examStarted.value || (submittedPartsCount.value > 0 && !allPartsSubmitted.value)
);

const hideSidebar = computed(() => isExamInProgress.value);

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
                        <button v-if="selectedPart" @click="goBackToList"
                            class="inline-flex items-center gap-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:text-primary transition-all group px-4 py-2 rounded-xl bg-muted/30 border border-border/40 hover:border-primary/40 backdrop-blur-md">
                            <ChevronLeft class="w-3.5 h-3.5 group-hover:-translate-x-1 transition-transform" />
                            Return to Mission Briefing
                        </button>
                        <Link v-else href="/exams"
                            class="inline-flex items-center gap-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:text-primary transition-all group px-4 py-2 rounded-xl bg-muted/30 border border-border/40 hover:border-primary/40 backdrop-blur-md">
                            <ChevronLeft class="w-3.5 h-3.5 group-hover:-translate-x-1 transition-transform" />
                            All Assessments
                        </Link>
                    </div>

                    <!-- Live Floating Timer & Smart Stats -->
                    <div v-if="examStarted" 
                        class="flex items-center gap-4 md:gap-6 px-6 py-3 rounded-[1.5rem] bg-black/60 border border-white/10 backdrop-blur-2xl shadow-2xl transition-all duration-500 hover:border-primary/40 group/timer">
                        
                        <!-- Draft Status (Desktop Only) -->
                        <div v-if="lastSavedAt" class="hidden lg:flex items-center gap-2 text-[10px] font-black text-muted-foreground/60 uppercase tracking-[0.2em] border-r border-white/10 pr-6">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            Synced {{ lastSavedAt }}
                        </div>

                        <!-- Pace Indicator -->
                        <div v-if="estimatedFinishMinutes !== null && estimatedFinishMinutes > 0" 
                            class="hidden md:flex items-center gap-2 text-amber-400 font-black text-[10px] uppercase tracking-[0.2em]">
                            <Zap class="w-4 h-4 fill-amber-400/20 group-hover/timer:scale-110 transition-transform" />
                            <span class="hidden lg:inline">Est. Finish</span> {{ estimatedFinishMinutes }}m
                        </div>

                        <div class="flex items-center gap-3 px-3 py-1 rounded-xl bg-primary/10 border border-primary/20" :class="timeLeftSeconds < 300 ? 'border-red-500/50 text-red-500 animate-pulse bg-red-500/10' : 'text-primary'">
                            <Clock class="w-4 h-4 group-hover/timer:rotate-12 transition-transform" />
                            <span class="font-black text-lg tracking-[0.15em] tabular-nums">{{ formattedTime }}</span>
                        </div>
                    </div>
                </div>

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <div
                    class="animate-up exam-hero relative overflow-hidden rounded-2xl border border-primary/20 bg-card p-6 md:p-8 shadow-[0_32px_64px_-16px_rgba(var(--primary),0.1)] group">
                    
                    <!-- Futuristic Corner Accents -->
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary/40 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary/40 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

                    <!-- Inner Glow / Border Highlight -->
                    <div class="absolute inset-0 rounded-2xl border border-white/5 pointer-events-none"></div>

                    <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-6 lg:gap-12">
                        <div class="space-y-4 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 border border-primary/20 text-[9px] font-black text-primary tracking-[0.2em] uppercase skew-x-[-12deg]">
                                    <span class="skew-x-[12deg] flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                        {{ exam.status }}
                                    </span>
                                </span>
                                <div v-if="examStarted && formattedFinishTime" class="flex items-center gap-2 text-[9px] font-black text-amber-500 uppercase tracking-widest bg-amber-500/10 px-3 py-1 border border-amber-500/20">
                                    <Zap class="w-3 h-3 animate-pulse" />
                                    <span>Deadline: {{ formattedFinishTime }}</span>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <h1 class="text-2xl md:text-4xl font-black tracking-tighter leading-none uppercase italic text-foreground">
                                    {{ selectedPart ? selectedPart.title : exam.title }}
                                </h1>
                                
                                <p v-if="!selectedPart" class="text-xs md:text-sm text-muted-foreground leading-relaxed max-w-2xl font-medium opacity-70">
                                    {{ exam.description || 'Quickly assess and master the material with our streamlined exam interface.' }}
                                </p>
                            </div>
                            
                            <div v-if="!selectedPart" class="flex flex-wrap items-center gap-6">
                                <div class="flex items-center gap-3 text-[10px] font-black text-muted-foreground uppercase tracking-widest">
                                    <Calendar class="w-4 h-4 text-primary" />
                                    {{ formatDateTime(exam.exam_date) }}
                                </div>
                            </div>
                        </div>

                        <!-- Stats Dashboard Bar -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 bg-muted/30 p-6 border border-border/50 relative">
                             <!-- Stat Decoration -->
                             <div class="absolute -top-1 -left-1 w-2 h-2 bg-primary"></div>
                             <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-primary"></div>

                            <div v-if="allPartsSubmitted" class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em]">Achievement</span>
                                <div class="text-xl font-black text-primary">{{ totalScore }}/{{ totalPossiblePoints }}</div>
                            </div>
                            
                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em]">Time Limit</span>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-xl font-black">{{ exam.duration_minutes }}</span>
                                    <span class="text-[8px] font-black text-muted-foreground uppercase">Min</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em]">Sections</span>
                                <div class="text-xl font-black">{{ exam.parts.length }}</div>
                            </div>

                            <div class="flex flex-col gap-1">
                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em]">Total Tasks</span>
                                <div class="text-xl font-black">{{ totalQuestions }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Global Progress Bar -->
                <div v-if="!allPartsSubmitted && examStarted" class="animate-up w-full mt-2 space-y-4">
                    <!-- Overall Mission Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[0.4em] text-primary/60">System Integrity</span>
                            <span class="text-[9px] font-black text-primary/60">{{ Math.round(overallProgress) }}% COMPLETED</span>
                        </div>
                        <div class="w-full h-1 bg-muted/30 overflow-hidden border border-primary/10 relative">
                            <div class="h-full bg-primary/40 transition-all duration-1000 ease-out" :style="{ width: `${overallProgress}%` }"></div>
                        </div>
                    </div>

                    <!-- Current Section Progress -->
                    <div v-if="selectedPart" class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[9px] font-black uppercase tracking-[0.4em] text-primary">Section Deployment</span>
                            <span class="text-[9px] font-black text-primary">{{ Math.round(partProgress) }}% INITIALIZED</span>
                        </div>
                        <div class="w-full h-2 bg-muted/50 overflow-hidden border border-primary/20 relative">
                            <div class="absolute inset-0 bg-primary/5 animate-pulse"></div>
                            <div class="h-full bg-primary transition-all duration-500 ease-out shadow-[0_0_15px_rgba(var(--primary),0.5)]" :style="{ width: `${partProgress}%` }"></div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PARTS LIST STATE                                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-if="!selectedPart">
                    <div class="animate-up flex items-center justify-between">
                        <h2 class="text-base font-bold flex items-center gap-2">
                            <Layers class="w-4 h-4 text-primary" />
                            Exam Parts
                        </h2>
                        <span
                            class="text-[10px] text-muted-foreground bg-muted/50 px-2 py-0.5 rounded-full border border-border/50">
                            {{ exam.parts.length }} Section{{ exam.parts.length !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(part, index) in exam.parts" :key="part.id" @click="selectPart(part, index)"
                            class="exam-part-card animate-up surface-card premium-hover group h-full p-2 md:p-3"
                            :class="[
                                (isPartSubmitted(part.id) || exam.status === 'closed' || isPartLocked(index))
                                    ? 'opacity-60 cursor-not-allowed grayscale-[0.5]' 
                                    : 'cursor-pointer hover:border-primary/40',
                                getPartColor(index)
                            ]">
                            
                            <!-- Light Sweep Animation -->
                            <div v-if="!isPartLocked(index) && !isPartSubmitted(part.id)" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>

                            <!-- Silhouette background icon -->
                            <div
                                class="pointer-events-none absolute -right-2 -bottom-2 opacity-5 group-hover:opacity-15 group-hover:scale-110 transition-all duration-700">
                                <component :is="getPartIcon(part.type)" class="w-12 h-12 text-foreground" />
                            </div>

                            <div class="relative flex flex-col gap-3 h-full">
                                <!-- Top: Part Label & Status -->
                                <div class="flex items-center justify-between">
                                    <div class="px-2 py-0.5 rounded-md bg-primary/5 text-[8px] font-black text-primary/60 uppercase tracking-[0.2em] border border-primary/10">
                                        Part {{ index + 1 }}
                                    </div>
                                    <div v-if="isPartSubmitted(part.id) || exam.status === 'closed' || isPartLocked(index)" class="flex flex-col items-end gap-1">
                                        <div v-if="exam.status === 'closed' && !isPartSubmitted(part.id)" class="flex items-center gap-1.5 text-red-500">
                                            <Lock class="w-3 h-3" />
                                            <span class="text-[9px] font-black uppercase tracking-widest">LOCKED</span>
                                        </div>
                                        <div v-else-if="isPartLocked(index)" class="flex flex-col items-end gap-0.5 mt-0.5">
                                            <div class="flex items-center gap-1 text-muted-foreground/60">
                                                <Lock class="w-2.5 h-2.5" />
                                                <span class="text-[8px] font-black uppercase tracking-widest">LOCKED</span>
                                            </div>
                                            <span class="text-[7px] font-bold text-muted-foreground uppercase tracking-wider opacity-60">
                                                Answer Part {{ index }} to unlock
                                            </span>
                                        </div>
                                        <div v-else-if="submissions[part.id]?.status === 'pending_review'" class="flex items-center gap-1 text-amber-500">
                                            <Clock class="w-3 h-3" />
                                            <span class="text-[9px] font-black uppercase tracking-widest">PENDING REVIEW</span>
                                        </div>
                                        <div v-else class="flex items-center gap-1 text-green-500">
                                            <CheckSquare2 class="w-3 h-3" />
                                            <span class="text-[9px] font-black uppercase tracking-widest">COMPLETED</span>
                                        </div>
                                        <div v-if="isPartSubmitted(part.id)" class="text-[8px] font-bold text-muted-foreground/80">
                                            Score: <span class="text-foreground">{{ submissions[part.id]?.score ?? 0 }}</span> / {{ part.questions?.reduce((sum, q) => sum + (q.points ?? part.points ?? 1), 0) ?? 0 }}
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5 text-muted-foreground/40 group-hover:text-primary/60 transition-colors">
                                        <span class="text-[9px] font-bold uppercase tracking-widest">READY</span>
                                    </div>
                                </div>

                                <!-- Center: Title & Types -->
                                <div class="flex-1 space-y-1.5">
                                    <h3
                                        class="text-sm md:text-base font-black leading-tight transition-colors"
                                        :class="isPartSubmitted(part.id) || isPartLocked(index) ? 'text-muted-foreground' : 'text-foreground group-hover:text-primary'">
                                        {{ part.title }}
                                    </h3>
                                    
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span v-for="type in getQuestionTypes(part)" :key="type"
                                            class="px-2 py-0.5 rounded-md bg-muted text-[8px] font-bold text-muted-foreground/80 uppercase tracking-wider border border-border/40">
                                            {{ formatType(type).toUpperCase() }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Bottom: Footer Info & Action -->
                                <div class="flex items-center justify-between pt-2 border-t border-border/10">
                                    <div class="flex items-center gap-1 text-xs text-muted-foreground font-medium">
                                        <span class="text-foreground/80 font-black">{{ part.questions?.length ?? 0 }}</span>
                                        <span class="opacity-60">Tasks</span>
                                    </div>
                                    
                                    <div v-if="!isPartSubmitted(part.id)"
                                        class="flex items-center gap-1 px-3 py-1 rounded-lg transition-all"
                                        :class="isPartLocked(index) 
                                            ? 'bg-muted/40 text-muted-foreground/40 cursor-not-allowed border border-border/20' 
                                            : 'bg-primary text-primary-foreground font-bold text-[9px] shadow-lg shadow-primary/20 hover:scale-[1.05] active:scale-[0.95]'">
                                        <span class="text-[9px] font-bold">{{ isPartLocked(index) ? 'LOCKED' : 'START' }}</span>
                                        <Lock v-if="isPartLocked(index)" class="w-2.5 h-2.5" />
                                        <ArrowRight v-else class="w-2.5 h-2.5" />
                                    </div>
                                    <div v-else
                                        class="w-8 h-8 rounded-full flex items-center justify-center bg-muted border border-border/60">
                                        <CheckCircle2 class="w-4 h-4 text-green-500" />
                                    </div>
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
                                <span
                                    class="text-[9px] font-black text-muted-foreground bg-muted/30 px-3 py-1 rounded-lg border border-border/40 uppercase tracking-widest">
                                    {{ selectedPart!.questions?.length ?? 0 }} Tasks
                                </span>
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
                                            Mission Briefing
                                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                                        </h4>
                                        <p class="text-base md:text-lg font-black text-foreground leading-relaxed uppercase tracking-tight">
                                            {{ selectedPart!.instructions }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-6">
                                <div v-for="(question, qIndex) in selectedPart!.questions" :key="qIndex"
                                    :id="`q-${qIndex}`"
                                    :class="[
                                        'question-card relative rounded-none border-l-4 border-r border-t border-b p-6 md:p-8 flex flex-col gap-8 transition-all duration-500',
                                        getQuestionStatus(qIndex) === 'answered' 
                                            ? 'border-l-primary border-primary/20 bg-primary/[0.02] shadow-[0_0_40px_rgba(var(--primary),0.05)]' 
                                            : 'border-l-muted border-border/40 bg-card/40'
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
                                            <p class="text-lg md:text-xl font-black leading-tight text-foreground/90 uppercase italic tracking-tight">
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
                                                <span class="relative text-sm font-black uppercase tracking-wider text-muted-foreground group-hover/option:text-foreground transition-colors has-[:checked]:text-primary">{{ option.text }}</span>
                                            </label>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'" class="max-w-xl">
                                            <div class="relative group/input">
                                                <input v-model="answers[qIndex]" type="text" placeholder="ENTER RESPONSE..."
                                                    class="w-full px-6 py-4 rounded-none border border-border/60 bg-muted/20 focus:border-primary outline-none transition-all duration-300 text-sm font-black uppercase tracking-widest placeholder:text-muted-foreground/30" />
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
                                            <span class="text-[8px] font-black text-muted-foreground uppercase tracking-widest opacity-40 italic">Mission Clock</span>
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
                            
                            <span class="skew-x-[12deg] text-base tracking-[0.2em] uppercase">{{ isSubmitting ? 'Transmitting Data...' : 'Finalize Section' }}</span>
                            
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
                                    <span class="skew-x-[12deg]">Initialize Now</span>
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
                                        <p class="text-xs font-black text-primary uppercase tracking-[0.4em] animate-pulse">Calculating your score</p>
                                        <p class="text-[8px] text-muted-foreground font-bold uppercase tracking-widest opacity-60 italic">Excluding Manual Review Components</p>
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
                                <span class="skew-x-[12deg]">{{ isCalculatingScore ? 'Calculating...' : (partsPendingCount > 0 ? 'Next Deployment' : 'Return to Base') }}</span>
                                <ChevronRight v-if="!isCalculatingScore" class="w-5 h-5 skew-x-[12deg]" />
                                <div v-else class="w-5 h-5 border-2 border-primary-foreground/20 border-t-primary-foreground rounded-full animate-spin skew-x-[12deg]"></div>
                            </button>
                        </div>
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
