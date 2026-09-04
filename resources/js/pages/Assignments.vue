<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import axios from 'axios';
import gsap from 'gsap';
import {
    FileUp,
    CheckCircle2,
    Clock,
    FileText,
    Download,
    Calendar,
    BookOpen,
    Search,
    X,
    AlertTriangle,
    UploadCloud,
    ChevronDown,
    Loader2,
    TrendingUp,
    RotateCcw,
    Eye,
    Award,
    Zap,
    MessageSquareText,
    Users,
    UserPlus,
    LogOut,
} from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import MascotEmptyState from '@/components/MascotEmptyState.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import PageSkeleton from '@/components/PageSkeleton.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { useLoader } from '@/composables/useLoader';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TourStep } from '@/lib/onboarding';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface GroupMember {
    id: number;
    name: string | null;
    avatar: string | null;
}

interface AssignmentGroup {
    id: number;
    created_by: number;
    members: GroupMember[];
    pending_invites?: PendingGroupInvite[];
}

interface PendingGroupInvite {
    id: number;
    user: {
        id: number;
        name: string | null;
        avatar: string | null;
    };
    expires_at: string | null;
}

interface IncomingInvite {
    id: number;
    inviter: {
        id: number;
        name: string | null;
        avatar: string | null;
    };
    expires_at: string | null;
}

interface GroupCandidate {
    id: number;
    name: string;
    avatar: string | null;
    sections: string[];
}

interface Assignment {
    id: number;
    title: string;
    description: string;
    status: 'draft' | 'published' | 'closed';
    due_date: string | null;
    points_possible: number | string | null;
    group_rules: {
        min: number | null;
        max: number | null;
    } | null;
    incoming_invite: IncomingInvite | null;
    course: {
        id: number;
        name: string;
    } | null;
    sections: {
        id: number;
        name: string;
    }[];
    group: AssignmentGroup | null;
    submission: {
        submitted: boolean;
        status: string;
        grade: string | null;
        file_path: string | null;
        file_url: string | null;
        submitted_at: string | null;
        submitted_by: number | null;
        submitted_by_name: string | null;
        points: number | string | null;
        xp_earned: number | string | null;
        feedback: string | null;
        graded_at: string | null;
        graded_by: number | null;
        feedback_seen_at: string | null;
        has_unseen_feedback?: boolean;
        file_extension: string | null;
    } | null;
}

/** Push payload of App\Events\AssignmentGraded (minimal identifying bits). */
interface AssignmentGradedPayload {
    assignment_id: number;
    status: string;
    grade: string | null;
    points: number;
    xp_earned: number;
    has_feedback: boolean;
    graded_at: string | null;
}

const props = withDefaults(
    defineProps<{
        assignments?: Assignment[];
    }>(),
    {
        assignments: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Assignments', href: '/assignments' },
];

const { isVisible: isLoaderVisible } = useLoader();
const { prefersReducedMotion, isLowEndDevice } = useMobile();
const isBooted = ref(false);
const pageContainer = ref<HTMLElement | null>(null);

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

// ─── Onboarding Tour ────────────────────────────────────────────────────────
const assignmentsTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Welcome to Assignments',
        body: 'Track your coursework, monitor upcoming deadlines, and submit your completed files all in one place.',
    },
    {
        id: 'overview',
        target: 'assignments-overview',
        title: 'Assignments overview',
        body: 'At-a-glance stats of pending tasks, completed work, evaluated grades, and overall progress.',
    },
    {
        id: 'search',
        target: 'assignments-search',
        title: 'Search and filter',
        body: 'Quickly find assignments by subject, status tabs, or submission month.',
    },
    {
        id: 'cards',
        target: 'assignments-grid',
        title: 'Assignment cards',
        body: 'Each card shows the due date, the exact time you submitted, and when it was graded — plus instructions, feedback, and your file.',
    },
    {
        id: 'submit',
        target: 'assignments-submit-btn',
        title: 'Submit your work',
        body: 'Upload docx, pptx, excel, pdf, or images. Files are stored on R2 and visible to teachers instantly.',
    },
];

// ─── Filter & Search State ──────────────────────────────────────────────────
const activeTab = ref<'all' | 'pending' | 'submitted' | 'graded'>('all');
const searchQuery = ref('');
const selectedCourseId = ref<string>('all');
const selectedMonth = ref<string>('all');
const sortBy = ref<'due_soon' | 'due_late' | 'title' | 'newest'>('due_soon');

const months = [
    { value: 'all', label: 'All months' },
    { value: '0', label: 'January' },
    { value: '1', label: 'February' },
    { value: '2', label: 'March' },
    { value: '3', label: 'April' },
    { value: '4', label: 'May' },
    { value: '5', label: 'June' },
    { value: '6', label: 'July' },
    { value: '7', label: 'August' },
    { value: '8', label: 'September' },
    { value: '9', label: 'October' },
    { value: '10', label: 'November' },
    { value: '11', label: 'December' },
];

// ─── Course Filter Options ──────────────────────────────────────────────────
const availableCourses = computed(() => {
    const map = new Map<number, string>();
    for (const a of props.assignments) {
        if (a.course) {
            map.set(a.course.id, a.course.name);
        }
    }
    return Array.from(map.entries()).map(([id, name]) => ({ id, name }));
});

// ─── Stats Computations ─────────────────────────────────────────────────────
const totalCount = computed(() => props.assignments.length);

const pendingAssignmentsList = computed(() =>
    props.assignments.filter((a) => !a.submission?.submitted),
);

const pendingCount = computed(() => pendingAssignmentsList.value.length);

const submittedCount = computed(
    () => props.assignments.filter((a) => a.submission?.submitted).length,
);

const gradedCount = computed(
    () =>
        props.assignments.filter(
            (a) =>
                a.submission?.submitted &&
                (a.submission?.status === 'Graded' ||
                    a.submission?.grade !== null),
        ).length,
);

// ─── Live Clock ─────────────────────────────────────────────────────────────
// Relative labels ("Due today" → "Overdue") must keep moving without a page
// reload. A coarse 30s tick is plenty for minute-level labels and keeps the
// re-render cost negligible even with a wall of cards.
const nowMs = ref(Date.now());
let clockTimer: ReturnType<typeof setInterval> | null = null;

const isOverdue = (dueDate: string | null) => {
    if (!dueDate) return false;
    const due = new Date(dueDate);
    if (Number.isNaN(due.getTime())) return false;
    return due.getTime() < nowMs.value;
};

const overdueCount = computed(
    () =>
        props.assignments.filter(
            (a) => !a.submission?.submitted && isOverdue(a.due_date),
        ).length,
);

const completionRate = computed(() => {
    if (totalCount.value === 0) return 0;
    return Math.round((submittedCount.value / totalCount.value) * 100);
});

// ─── Graded Detection ───────────────────────────────────────────────────────
// A submission counts as "graded" once the teacher has posted a grade or
// marked the status as Graded. Used to lock the resubmit action.
const isGraded = (submission: Assignment['submission']) => {
    if (!submission?.submitted) return false;
    return submission.status === 'Graded' || submission.grade !== null;
};

// ─── Points Possible ────────────────────────────────────────────────────────
const formatNumber = (value: number) =>
    value.toLocaleString('en-US', { maximumFractionDigits: 2 });

const pointsPossibleOf = (assignment: Assignment) =>
    Number(assignment.points_possible ?? 0);

/**
 * Compact "what's at stake" label. With a possible value set the pill reads
 * "85 / 100 pts" (or "0 / 100 pts" once graded without points yet);
 * otherwise it keeps the legacy "+85 pts" for awarded points only.
 */
const pointsLabel = (assignment: Assignment): string | null => {
    const earned = Number(assignment.submission?.points ?? 0);
    const possible = pointsPossibleOf(assignment);

    if (possible > 0) {
        return `${formatNumber(earned)} / ${formatNumber(possible)} pts`;
    }
    if (earned > 0) {
        return `+${formatNumber(earned)} pts`;
    }
    return null;
};

// ─── Per-card grade details expand/collapse ─────────────────────────────────
const expandedGradeIds = ref<Set<number>>(new Set());
const isGradeExpanded = (id: number) => expandedGradeIds.value.has(id);

const toggleGradeExpanded = (assignment: Assignment) => {
    const id = assignment.id;
    const next = new Set(expandedGradeIds.value);
    if (next.has(id)) next.delete(id);
    else {
        next.add(id);
        // Opening the details is exactly "the student has seen the feedback".
        markFeedbackSeen(assignment);
    }
    expandedGradeIds.value = next;
};

// ─── Unseen Feedback Acknowledgement ────────────────────────────────────────
const hasUnseenFeedback = (assignment: Assignment) =>
    Boolean(assignment.submission?.has_unseen_feedback);

const feedbackSeenPending = ref<Set<number>>(new Set());

const markFeedbackSeen = (assignment: Assignment) => {
    if (
        !hasUnseenFeedback(assignment) ||
        feedbackSeenPending.value.has(assignment.id)
    ) {
        return;
    }

    feedbackSeenPending.value = new Set(feedbackSeenPending.value).add(
        assignment.id,
    );

    router.post(
        `/assignments/${assignment.id}/feedback-seen`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['assignments'],
            onFinish: () => {
                const next = new Set(feedbackSeenPending.value);
                next.delete(assignment.id);
                feedbackSeenPending.value = next;
            },
        },
    );
};

// ─── File Helpers ───────────────────────────────────────────────────────────
const isImageFile = (ext: string | null | undefined) => {
    if (!ext) return false;
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext.toLowerCase());
};

const isPdfFile = (ext: string | null | undefined) => {
    return ext?.toLowerCase() === 'pdf';
};

const getFileName = (filePath: string | null) => {
    if (!filePath) return 'Uploaded submission';
    return filePath.split('/').pop() || 'Uploaded submission';
};

// ─── Date Formatting & Helpers ──────────────────────────────────────────────
const formatFullDateTime = (dateStr: string | null) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatDueDate = (dateStr: string | null) =>
    formatFullDateTime(dateStr) || 'No due date';

const formatDateTime = (dateStr: string | null) => formatFullDateTime(dateStr);

const dueMeta = (assignment: Assignment) =>
    getRelativeDueInfo(assignment.due_date, false, null);

const wasSubmittedLate = (assignment: Assignment) => {
    const submittedAt = assignment.submission?.submitted_at;
    const dueDate = assignment.due_date;
    if (!submittedAt || !dueDate) return false;
    const submitted = new Date(submittedAt);
    const due = new Date(dueDate);
    if (Number.isNaN(submitted.getTime()) || Number.isNaN(due.getTime())) {
        return false;
    }
    return submitted.getTime() > due.getTime();
};

const startOfDay = (date: Date) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();

/** Calendar-day gap between two dates (0 = same day). DST-safe via rounding. */
const calendarDaysBetween = (from: Date, to: Date) =>
    Math.round((startOfDay(to) - startOfDay(from)) / 86_400_000);

const getRelativeDueInfo = (
    dueDate: string | null,
    isSubmitted: boolean,
    submittedAt?: string | null,
) => {
    if (isSubmitted) {
        if (submittedAt) {
            const subDate = new Date(submittedAt);
            if (!Number.isNaN(subDate.getTime())) {
                return {
                    text: `Submitted ${formatDateTime(submittedAt)}`,
                    color: 'text-emerald-700 dark:text-emerald-400',
                    isOverdue: false,
                    isSubmitted: true,
                };
            }
        }
        return {
            text: 'Submitted',
            color: 'text-emerald-700 dark:text-emerald-400',
            isOverdue: false,
            isSubmitted: true,
        };
    }

    if (!dueDate) {
        return {
            text: 'No deadline',
            color: 'text-muted-foreground',
            isOverdue: false,
            isSubmitted: false,
        };
    }

    const due = new Date(dueDate);
    if (Number.isNaN(due.getTime())) {
        return {
            text: 'No deadline',
            color: 'text-muted-foreground',
            isOverdue: false,
            isSubmitted: false,
        };
    }

    const now = new Date(nowMs.value);
    const diffMs = due.getTime() - now.getTime();
    const daysAhead = calendarDaysBetween(now, due);

    // Past due. Sub-day gaps read better in hours ("Overdue by 5h") than the
    // old ceil-based math, which called anything under 24h "1 day".
    if (diffMs < 0) {
        const elapsedMs = -diffMs;
        const daysOverdue = calendarDaysBetween(due, now);
        if (daysOverdue <= 0) {
            return {
                text: 'Overdue today',
                color: 'text-red-700 dark:text-red-400',
                isOverdue: true,
                isSubmitted: false,
            };
        }
        if (elapsedMs < 86_400_000) {
            const hours = Math.max(1, Math.ceil(elapsedMs / 3_600_000));
            return {
                text: `Overdue by ${hours}h`,
                color: 'text-red-700 dark:text-red-400',
                isOverdue: true,
                isSubmitted: false,
            };
        }
        return {
            text: `Overdue by ${daysOverdue} day${daysOverdue === 1 ? '' : 's'}`,
            color: 'text-red-700 dark:text-red-400',
            isOverdue: true,
            isSubmitted: false,
        };
    }

    // Under an hour left: minute-level urgency.
    if (diffMs < 3_600_000) {
        const minutes = Math.max(1, Math.ceil(diffMs / 60_000));
        return {
            text: `Due in ${minutes} min`,
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    // Same calendar day: say so, with the exact time so "today" is actionable.
    if (daysAhead <= 0) {
        return {
            text: `Due today · ${due.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`,
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    if (daysAhead === 1) {
        return {
            text: 'Due tomorrow',
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    // 1–2 calendar days out but under 24h on the clock (late-night due
    // times): hours are more precise than "2 days".
    if (diffMs < 86_400_000) {
        const hours = Math.ceil(diffMs / 3_600_000);
        return {
            text: `Due in ${hours}h`,
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    if (daysAhead <= 7) {
        return {
            text: `Due in ${daysAhead} days`,
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    return {
        text: `Due ${formatDueDate(dueDate)}`,
        color: 'text-muted-foreground',
        isOverdue: false,
        isSubmitted: false,
    };
};

const getStatusBadge = (assignment: Assignment) => {
    if (assignment.submission?.submitted) {
        if (
            assignment.submission.status === 'Graded' ||
            assignment.submission.grade !== null
        ) {
            return {
                label: assignment.submission.grade
                    ? `Graded · ${assignment.submission.grade}`
                    : 'Graded',
                classes:
                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
            };
        }
        return {
            label: 'Submitted',
            classes:
                'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20',
        };
    }

    // Closed: same muted rose the exam hub uses for closed exams.
    if (isClosed(assignment)) {
        return {
            label: 'Closed',
            classes: 'bg-[#CB7676]/10 text-[#CB7676] border-[#CB7676]/25',
        };
    }

    if (isOverdue(assignment.due_date)) {
        return {
            label: 'Overdue',
            classes:
                'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
        };
    }

    return {
        label: 'Pending',
        classes:
            'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
    };
};

const getCardBorderClass = (assignment: Assignment) => {
    if (assignment.submission?.submitted) {
        if (
            assignment.submission.status === 'Graded' ||
            assignment.submission.grade !== null
        ) {
            return 'border-l-[3px] border-l-emerald-600/70 hover:border-l-emerald-600 dark:border-l-emerald-400/70';
        }
        return 'border-l-[3px] border-l-orange-600/70 hover:border-l-orange-600 dark:border-l-orange-400/70';
    }
    if (isClosed(assignment)) {
        return 'border-l-[3px] border-l-[#CB7676]/70 hover:border-l-[#CB7676]';
    }
    if (isOverdue(assignment.due_date)) {
        return 'border-l-[3px] border-l-red-600/70 hover:border-l-red-600 dark:border-l-red-400/70';
    }
    return 'border-l-[3px] border-l-amber-600/70 hover:border-l-amber-600 dark:border-l-amber-400/70';
};

// ─── Filtered Assignments ───────────────────────────────────────────────────
const filteredAssignments = computed(() => {
    let list = [...props.assignments];

    // Status Tab Filter
    if (activeTab.value === 'pending') {
        list = list.filter((a) => !a.submission?.submitted);
    } else if (activeTab.value === 'submitted') {
        list = list.filter((a) => a.submission?.submitted);
    } else if (activeTab.value === 'graded') {
        list = list.filter(
            (a) =>
                a.submission?.submitted &&
                (a.submission?.status === 'Graded' ||
                    a.submission?.grade !== null),
        );
    }

    // Course Filter
    if (selectedCourseId.value !== 'all') {
        const courseId = parseInt(selectedCourseId.value);
        list = list.filter((a) => a.course?.id === courseId);
    }

    // Month Filter
    if (selectedMonth.value !== 'all') {
        const monthIndex = parseInt(selectedMonth.value);
        list = list.filter((a) => {
            const dateStr = a.due_date || a.submission?.submitted_at;
            if (!dateStr) return false;
            return new Date(dateStr).getMonth() === monthIndex;
        });
    }

    // Search Query Filter
    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        list = list.filter(
            (a) =>
                a.title.toLowerCase().includes(query) ||
                a.description?.toLowerCase().includes(query) ||
                a.course?.name.toLowerCase().includes(query) ||
                a.sections?.some((s) => s.name.toLowerCase().includes(query)),
        );
    }

    // Sort
    if (sortBy.value === 'due_soon') {
        list.sort((a, b) => {
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return (
                new Date(a.due_date).getTime() - new Date(b.due_date).getTime()
            );
        });
    } else if (sortBy.value === 'due_late') {
        list.sort((a, b) => {
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return (
                new Date(b.due_date).getTime() - new Date(a.due_date).getTime()
            );
        });
    } else if (sortBy.value === 'title') {
        list.sort((a, b) => a.title.localeCompare(b.title));
    } else if (sortBy.value === 'newest') {
        list.sort((a, b) => b.id - a.id);
    }

    return list;
});

const clearAllFilters = () => {
    activeTab.value = 'all';
    searchQuery.value = '';
    selectedCourseId.value = 'all';
    selectedMonth.value = 'all';
    sortBy.value = 'due_soon';
};

const hasActiveFilters = computed(() => {
    return (
        activeTab.value !== 'all' ||
        Boolean(searchQuery.value.trim()) ||
        selectedCourseId.value !== 'all' ||
        selectedMonth.value !== 'all' ||
        sortBy.value !== 'due_soon'
    );
});

// ─── Instructions Modal State & Logic ───────────────────────────────────────
// Each card exposes a "View instructions" action; the full instructions open
// in a modal (bottom sheet on mobile, centered dialog on desktop) so students
// can read them without leaving the page.
const instructionsAssignmentId = ref<number | null>(null);

const instructionsAssignment = computed(
    () =>
        (instructionsAssignmentId.value === null
            ? null
            : props.assignments.find(
                  (a) => a.id === instructionsAssignmentId.value,
              )) ?? null,
);

const openInstructions = (assignment: Assignment) => {
    instructionsAssignmentId.value = assignment.id;
};

const closeInstructions = () => {
    instructionsAssignmentId.value = null;
};

const openUploadFromInstructions = () => {
    const assignment = instructionsAssignment.value;
    closeInstructions();
    if (assignment && !isClosed(assignment)) {
        openModalForAssignment(assignment);
    }
};

// ─── Upload Modal State & Logic ─────────────────────────────────────────────
const showUploadModal = ref(false);
const selectedAssignmentId = ref<number | string>('');
const fileInput = ref<HTMLInputElement | null>(null);
const fileError = ref<string | null>(null);
const isDraggingFile = ref(false);

const selectedAssignment = computed(() => {
    if (!selectedAssignmentId.value) return null;
    return (
        props.assignments.find(
            (a) => a.id === Number(selectedAssignmentId.value),
        ) ?? null
    );
});

const form = useForm({
    file: null as File | null,
});

const openModalForAssignment = (assignment: Assignment) => {
    fileError.value = null;
    form.reset();

    // The modal is always opened for a specific assignment card, so the
    // submission is targeted at that assignment — never switchable.
    selectedAssignmentId.value = assignment.id;
    showUploadModal.value = true;
};

const closeModal = () => {
    showUploadModal.value = false;
    form.reset();
    fileError.value = null;
    isDraggingFile.value = false;
};

const validateAndSetFile = (file: File | null) => {
    fileError.value = null;
    if (!file) {
        form.file = null;
        return;
    }

    // 10MB limit
    const MAX_SIZE = 10 * 1024 * 1024;
    if (file.size > MAX_SIZE) {
        fileError.value =
            'File size exceeds the 10 MB limit. Please select a smaller file.';
        return;
    }

    // Allowed extensions: docx, pptx, excel (xls/xlsx), pdf, jpg, png + legacy
    const allowedExtensions = [
        'pdf',
        'doc',
        'docx',
        'ppt',
        'pptx',
        'xls',
        'xlsx',
        'png',
        'jpg',
        'jpeg',
    ];
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    if (!allowedExtensions.includes(ext)) {
        fileError.value =
            'Unsupported file format. Please upload PDF, Word (docx), PowerPoint (pptx), Excel (xls/xlsx), or Images (jpg/png).';
        return;
    }

    form.file = file;
};

const handleFileInputChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        validateAndSetFile(input.files[0]);
    }
};

const handleFileDrop = (event: DragEvent) => {
    isDraggingFile.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        validateAndSetFile(event.dataTransfer.files[0]);
    }
};

const removeSelectedFile = () => {
    form.file = null;
    fileError.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const submitAssignment = () => {
    if (!form.file || !selectedAssignmentId.value || form.processing) return;

    form.post(`/assignments/${selectedAssignmentId.value}/submit`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
    });
};

// ─── Group Activity (shared submission) ─────────────────────────────────────
const page = usePage();
const currentUserId = computed<number | null>(
    () => (page.props.auth?.user as { id?: number } | null)?.id ?? null,
);

// ─── Live Grade Updates ─────────────────────────────────────────────────────
// The backend broadcasts AssignmentGraded on this student's private channel
// when a teacher grades (or re-grades) their work. Coalesce bursts — group
// grading fans out one event per member — into a single partial reload of
// the list so the card updates in place without losing local state.
let gradeReloadTimer: ReturnType<typeof setTimeout> | null = null;

const refreshAssignmentsFromBroadcast = () => {
    if (gradeReloadTimer) clearTimeout(gradeReloadTimer);
    gradeReloadTimer = setTimeout(() => {
        gradeReloadTimer = null;
        // reload() preserves scroll + component state by design in Inertia 2,
        // so open modals and expanded cards survive the refresh.
        router.reload({ only: ['assignments'] });
    }, 400);
};

if (currentUserId.value) {
    useEcho<AssignmentGradedPayload>(
        `App.Models.User.${currentUserId.value}`,
        'AssignmentGraded',
        (event) => {
            if (props.assignments.some((a) => a.id === event.assignment_id)) {
                refreshAssignmentsFromBroadcast();
            }
        },
    );

    // Group invites: the bell notifies, and this listener refreshes the
    // accept/decline banners and "waiting" states on the page live.
    useEcho<Record<string, unknown>>(
        `App.Models.User.${currentUserId.value}`,
        'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
        (payload) => {
            const kind = (payload as { type?: unknown }).type;
            if (
                kind === 'assignment_invite' ||
                kind === 'invite_accepted' ||
                kind === 'invite_declined'
            ) {
                refreshAssignmentsFromBroadcast();
            }
        },
    );
}

/**
 * A closed or graded assignment is locked: no add/remove/leave/resubmit.
 * Closed mirrors the exam behavior — the card stays visible (with any
 * submission and grade), but no new work is possible.
 */
const isClosed = (assignment: Assignment) => assignment.status === 'closed';

const isGroupLocked = (assignment: Assignment) =>
    isGraded(assignment.submission) || isClosed(assignment);

const isGroupCreator = (assignment: Assignment) =>
    assignment.group?.created_by === currentUserId.value;

const groupMembers = (assignment: Assignment) =>
    assignment.group?.members ?? [];

const groupMemberNames = (assignment: Assignment) =>
    groupMembers(assignment)
        .map((m) => m.name ?? 'A member')
        .join(', ');

// ─── Per-card group roster expand/collapse ──────────────────────────────────
// Member + pending-invite chips wrap and can dominate a card, so the roster
// hides behind a compact "N members · M waiting" summary by default and opens
// on demand — the same disclosure pattern as the "View grade" details above.
const expandedGroupIds = ref<Set<number>>(new Set());
const isGroupExpanded = (id: number) => expandedGroupIds.value.has(id);

const toggleGroupExpanded = (assignment: Assignment) => {
    const id = assignment.id;
    const next = new Set(expandedGroupIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expandedGroupIds.value = next;
};

const initials = (name: string | null | undefined) =>
    (name ?? '?')
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

// ── Create / invite / cancel / leave ────────────────────────────────────────
const groupActionLoading = ref(false);

// Confirmation modal state: member = null means "leave by yourself".
const groupConfirm = ref<{
    assignment: Assignment;
    member: GroupMember | null;
} | null>(null);

const requestRemoveMember = (assignment: Assignment, member: GroupMember) => {
    if (groupActionLoading.value || isGroupLocked(assignment)) return;
    groupConfirm.value = { assignment, member };
};

const requestLeaveGroup = (assignment: Assignment) => {
    if (groupActionLoading.value || isGroupLocked(assignment)) return;
    groupConfirm.value = { assignment, member: null };
};

const confirmGroupAction = () => {
    const pending = groupConfirm.value;
    if (!pending || groupActionLoading.value) return;

    const memberId = pending.member?.id ?? currentUserId.value;
    if (memberId == null) return;

    groupActionLoading.value = true;
    router.delete(
        `/assignments/${pending.assignment.id}/groups/members/${memberId}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                groupConfirm.value = null;
            },
            onFinish: () => (groupActionLoading.value = false),
        },
    );
};

// ── Invite modal (step 1: pick classmates, step 2: sent confirmation) ───────
const showInviteModal = ref(false);
const inviteAssignmentId = ref<number | null>(null);
const inviteStep = ref<'select' | 'sent'>('select');
const inviteSelection = ref<GroupCandidate[]>([]);
const inviteSearchQuery = ref('');
const inviteCandidates = ref<GroupCandidate[]>([]);
const inviteSearching = ref(false);
const inviteError = ref<string | null>(null);

const inviteAssignment = computed(
    () =>
        props.assignments.find((a) => a.id === inviteAssignmentId.value) ??
        null,
);

/** Teacher cap minus members and already-invited students (null = ∞). */
const openInviteSlots = computed(() => {
    const assignment = inviteAssignment.value;
    if (!assignment) return Infinity;
    const max = assignment.group_rules?.max;
    if (max !== null && max !== undefined && max <= 1) return 0;
    if (!max) return Infinity;
    const members = assignment.group?.members?.length ?? 0;
    const pending = assignment.group?.pending_invites?.length ?? 0;
    return Math.max(0, max - members - pending);
});

const canInviteMore = (assignment: Assignment) => {
    const max = assignment.group_rules?.max;
    if (max !== null && max !== undefined && max <= 1) return false;
    if (!max) return true;
    const members = assignment.group?.members?.length ?? 0;
    const pending = assignment.group?.pending_invites?.length ?? 0;
    return members + pending < max;
};

const groupRulesLabel = (assignment: Assignment) => {
    const min = assignment.group_rules?.min;
    const max = assignment.group_rules?.max;
    if (max === 1) return 'Individual activity';
    if (min && max) {
        return min === max ? `Groups of ${max}` : `Groups of ${min}–${max}`;
    }
    if (max) return `Up to ${max} members`;
    if (min) return `At least ${min} members`;
    return '';
};

const pendingInviteCount = (assignment: Assignment) =>
    assignment.group?.pending_invites?.length ?? 0;

const openInviteModal = (assignment: Assignment) => {
    if (isGroupLocked(assignment)) return;
    inviteError.value = null;
    inviteSelection.value = [];
    inviteSearchQuery.value = '';
    inviteCandidates.value = [];
    inviteStep.value = 'select';
    inviteAssignmentId.value = assignment.id;
    showInviteModal.value = true;
    searchInviteCandidates();
};

const closeInviteModal = () => {
    showInviteModal.value = false;
    inviteAssignmentId.value = null;
};

let inviteSearchTimer: ReturnType<typeof setTimeout> | null = null;

const searchInviteCandidates = () => {
    const assignmentId = inviteAssignmentId.value;
    if (!assignmentId) return;

    inviteSearching.value = true;
    inviteError.value = null;

    axios
        .get(`/assignments/${assignmentId}/groups/candidates`, {
            params: {
                q: inviteSearchQuery.value.trim() || undefined,
            },
        })
        .then((res) => {
            inviteCandidates.value = res.data.candidates ?? [];
        })
        .catch(() => {
            inviteError.value = 'Could not load classmates. Please try again.';
        })
        .finally(() => {
            inviteSearching.value = false;
        });
};

watch(inviteSearchQuery, () => {
    if (inviteSearchTimer) clearTimeout(inviteSearchTimer);
    inviteSearchTimer = setTimeout(searchInviteCandidates, 300);
});

const isCandidateSelected = (candidate: GroupCandidate) =>
    inviteSelection.value.some((c) => c.id === candidate.id);

const toggleCandidate = (candidate: GroupCandidate) => {
    inviteError.value = null;

    if (isCandidateSelected(candidate)) {
        inviteSelection.value = inviteSelection.value.filter(
            (c) => c.id !== candidate.id,
        );
        return;
    }

    if (inviteSelection.value.length >= openInviteSlots.value) {
        const max = inviteAssignment.value?.group_rules?.max;
        inviteError.value = max
            ? `Only ${openInviteSlots.value} more invite${openInviteSlots.value === 1 ? '' : 's'} fit — the group limit is ${max}.`
            : 'No invite slots left in this group.';
        return;
    }

    inviteSelection.value = [...inviteSelection.value, candidate];
};

const firstErrorText = (error: unknown): string | null => {
    if (Array.isArray(error)) return error[0] ?? null;
    if (typeof error === 'string') return error;
    return null;
};

const sendInvites = () => {
    const assignmentId = inviteAssignmentId.value;
    if (
        !assignmentId ||
        groupActionLoading.value ||
        inviteSelection.value.length === 0
    )
        return;

    groupActionLoading.value = true;
    inviteError.value = null;

    router.post(
        `/assignments/${assignmentId}/invites`,
        { user_ids: inviteSelection.value.map((c) => c.id) },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['assignments'],
            onSuccess: () => {
                inviteStep.value = 'sent';
            },
            onError: (errors) => {
                const record = errors as Record<string, unknown>;
                inviteError.value =
                    firstErrorText(record.user_ids) ??
                    firstErrorText(record.message) ??
                    'Could not send the invites. Please try again.';
            },
            onFinish: () => (groupActionLoading.value = false),
        },
    );
};

// ── Incoming invite (invitee side) ──────────────────────────────────────────
const inviteRespondLoading = ref(false);

const respondToIncomingInvite = (
    assignment: Assignment,
    action: 'accept' | 'decline',
) => {
    const invite = assignment.incoming_invite;
    if (!invite || inviteRespondLoading.value) return;

    inviteRespondLoading.value = true;
    router.post(
        `/assignments/${assignment.id}/invites/${invite.id}/respond`,
        { action },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['assignments'],
            onFinish: () => (inviteRespondLoading.value = false),
        },
    );
};

// ── Cancel a pending invite (creator side) ──────────────────────────────────
const cancelPendingInvite = (assignment: Assignment, inviteId: number) => {
    if (groupActionLoading.value || isGroupLocked(assignment)) return;

    groupActionLoading.value = true;
    router.delete(`/assignments/${assignment.id}/invites/${inviteId}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['assignments'],
        onFinish: () => (groupActionLoading.value = false),
    });
};

// ─── Animations ─────────────────────────────────────────────────────────────
let animationContext: ReturnType<typeof gsap.context> | null = null;

onBeforeUnmount(() => {
    if (clockTimer) clearInterval(clockTimer);
    if (gradeReloadTimer) clearTimeout(gradeReloadTimer);
    animationContext?.revert();
});

onMounted(() => {
    // Ticks even on reduced-motion / low-end devices: it moves text labels,
    // not animation.
    clockTimer = setInterval(() => {
        nowMs.value = Date.now();
    }, 30_000);

    if (
        !pageContainer.value ||
        prefersReducedMotion.value ||
        isLowEndDevice.value
    )
        return;

    animationContext = gsap.context(() => {
        gsap.fromTo(
            '.animate-section',
            { opacity: 0, y: 15 },
            {
                opacity: 1,
                y: 0,
                duration: 0.6,
                stagger: 0.08,
                ease: 'power2.out',
            },
        );
    }, pageContainer.value);
});
</script>

<template>
    <Head title="Assignments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Skeleton Loading State -->
        <template v-if="!isBooted">
            <div
                class="student-ui mobile-ui-page container mx-auto max-w-[1600px] px-3 py-3 perspective-[1000px] sm:px-6 sm:py-6 lg:px-8 lg:py-8"
            >
                <PageSkeleton
                    :hero="true"
                    :subtitle="true"
                    :actions="2"
                    :stats="4"
                    :count="0"
                    variant="minimal"
                    wrapperClass="z-10 mb-6"
                />
                <div
                    class="mb-6 h-10 w-full animate-pulse rounded-xl bg-muted/40"
                />
                <div
                    class="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-36 animate-pulse rounded-2xl border border-border/50 bg-card/40"
                    />
                </div>
            </div>
        </template>

        <!-- Real Content -->
        <template v-if="isBooted">
            <div
                ref="pageContainer"
                class="student-ui mobile-ui-page container mx-auto max-w-[1600px] px-3 py-3 perspective-[1000px] sm:px-6 sm:py-6 lg:px-8 lg:py-8"
            >
                <section
                    class="mobile-assignment-mobile-intro md:hidden"
                    aria-label="Assignment summary"
                >
                    <div class="mobile-assignment-mobile-intro__topline">
                        <div>
                            <span class="mobile-dashboard-kicker"
                                >Your work</span
                            >
                            <h1 class="mobile-dashboard-title">Assignments</h1>
                        </div>
                        <span class="mobile-assignment-count-pill"
                            >{{ pendingCount }} pending</span
                        >
                    </div>
                    <p class="mobile-assignment-mobile-intro__copy">
                        Keep an eye on what is due, what is submitted, and what
                        needs your attention next.
                    </p>
                    <div class="mobile-assignment-mobile-stats">
                        <div>
                            <strong>{{ pendingCount }}</strong
                            ><span>Pending</span>
                        </div>
                        <div>
                            <strong>{{ submittedCount }}</strong
                            ><span>Submitted</span>
                        </div>
                        <div :class="{ 'is-alert': overdueCount > 0 }">
                            <strong>{{ overdueCount }}</strong
                            ><span>Overdue</span>
                        </div>
                    </div>
                </section>

                <!-- Page Header -->
                <div
                    class="mobile-existing-header assignment-desktop-only animate-section mb-6 hidden flex-col gap-4 sm:mb-8 sm:flex sm:flex-row sm:items-start sm:justify-between md:flex"
                >
                    <div>
                        <h1
                            class="dash-title text-[22px] text-foreground sm:text-[34px]"
                        >
                            Assignments
                        </h1>
                        <p
                            class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                        >
                            What’s due, when you turned it in, and when it was
                            graded.
                        </p>
                    </div>
                </div>

                <!-- Overview — restrained, editorial hierarchy; two columns on small screens -->
                <section
                    data-tour="assignments-overview"
                    aria-label="Assignment overview"
                    class="assignment-desktop-only animate-section grid grid-cols-2 items-stretch divide-x divide-y divide-border/70 overflow-hidden rounded-xl border border-border/70 bg-card sm:mb-8 lg:grid-cols-4 lg:rounded-2xl"
                >
                    <Card
                        class="surface-card h-full min-w-0 gap-0 rounded-none border-0 bg-transparent p-3.5 shadow-none sm:p-5"
                    >
                        <CardHeader class="p-0">
                            <CardTitle
                                class="dash-label text-xs font-medium text-muted-foreground"
                                >Pending</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="p-0 pt-2">
                            <p
                                class="dash-metric text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {{ pendingCount }}
                            </p>
                            <p
                                class="mt-1 truncate text-xs"
                                :class="
                                    overdueCount > 0
                                        ? 'font-medium text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    overdueCount > 0
                                        ? `${overdueCount} overdue`
                                        : 'Awaiting submission'
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card
                        class="surface-card h-full min-w-0 gap-0 rounded-none border-0 bg-transparent p-3.5 shadow-none sm:p-5"
                    >
                        <CardHeader class="p-0">
                            <CardTitle
                                class="dash-label text-xs font-medium text-muted-foreground"
                                >Submitted</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="p-0 pt-2">
                            <p
                                class="dash-metric text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {{ submittedCount }}
                            </p>
                            <p
                                class="mt-1 truncate text-xs text-muted-foreground"
                            >
                                Turned in for review
                            </p>
                        </CardContent>
                    </Card>
                    <Card
                        class="surface-card h-full min-w-0 gap-0 rounded-none border-0 bg-transparent p-3.5 shadow-none sm:p-5"
                    >
                        <CardHeader class="p-0">
                            <CardTitle
                                class="dash-label text-xs font-medium text-muted-foreground"
                                >Graded</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="p-0 pt-2">
                            <p
                                class="dash-metric text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {{ gradedCount }}
                            </p>
                            <p
                                class="mt-1 truncate text-xs text-muted-foreground"
                            >
                                Evaluated by teacher
                            </p>
                        </CardContent>
                    </Card>
                    <Card
                        class="surface-card col-span-2 h-full min-w-0 gap-0 rounded-none border-0 bg-transparent p-3.5 shadow-none sm:col-span-1 sm:p-5"
                    >
                        <CardHeader class="p-0">
                            <CardTitle
                                class="dash-label text-xs font-medium text-muted-foreground"
                                >Completion</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="p-0 pt-2">
                            <div
                                class="flex items-baseline justify-between gap-2"
                            >
                                <p
                                    class="dash-metric text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                                >
                                    {{ completionRate }}%
                                </p>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ submittedCount }}/{{ totalCount }}</span
                                >
                            </div>
                            <Progress
                                :value="completionRate"
                                class="mt-3 h-1 w-full bg-muted"
                                indicator-class="bg-foreground"
                            />
                        </CardContent>
                    </Card>
                </section>

                <!-- Filters & Search Bar -->
                <div
                    data-tour="assignments-search"
                    class="assignment-desktop-only animate-section mb-6 hidden space-y-3 md:block"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <!-- Status Tabs - Mobile optimized with larger touch targets -->
                        <div
                            class="no-scrollbar flex items-center gap-1.5 overflow-x-auto rounded-full border border-border/50 bg-card p-1 sm:p-1"
                        >
                            <button
                                type="button"
                                @click="activeTab = 'all'"
                                class="dash-btn inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-3.5 sm:text-[13px]"
                                :class="
                                    activeTab === 'all'
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                            >
                                <span>All</span>
                                <span
                                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                                    :class="
                                        activeTab === 'all'
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ totalCount }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'pending'"
                                class="dash-btn inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-3.5 sm:text-[13px]"
                                :class="
                                    activeTab === 'pending'
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                            >
                                <span>Pending</span>
                                <span
                                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                                    :class="
                                        activeTab === 'pending'
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ pendingCount }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'submitted'"
                                class="dash-btn inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-3.5 sm:text-[13px]"
                                :class="
                                    activeTab === 'submitted'
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                            >
                                <span>Submitted</span>
                                <span
                                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                                    :class="
                                        activeTab === 'submitted'
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ submittedCount }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'graded'"
                                class="dash-btn inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-3.5 sm:text-[13px]"
                                :class="
                                    activeTab === 'graded'
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                "
                            >
                                <span>Graded</span>
                                <span
                                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                                    :class="
                                        activeTab === 'graded'
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ gradedCount }}
                                </span>
                            </button>
                        </div>

                        <!-- Search & Dropdowns -->
                        <div
                            class="flex flex-wrap items-center gap-2.5 sm:gap-3"
                        >
                            <!-- Search Input -->
                            <div
                                class="relative min-w-48 flex-1 sm:w-64 sm:flex-none"
                            >
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                                    aria-hidden="true"
                                />
                                <Input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Search assignments..."
                                    class="h-10 rounded-full border-border/60 bg-card pr-8 pl-9.5 text-xs sm:text-sm"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''"
                                    type="button"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground/60 hover:text-foreground"
                                    aria-label="Clear search"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Course Filter (if multiple exist) -->
                            <div
                                v-if="availableCourses.length > 1"
                                class="relative"
                            >
                                <select
                                    v-model="selectedCourseId"
                                    class="h-10 cursor-pointer appearance-none rounded-full border border-border/60 bg-card py-1.5 pr-8 pl-3.5 text-xs font-medium text-foreground transition-colors outline-none hover:bg-muted/40 focus:border-primary/40 focus:ring-2 focus:ring-primary/20 sm:text-[13px]"
                                    aria-label="Filter by subject"
                                >
                                    <option value="all">All subjects</option>
                                    <option
                                        v-for="c in availableCourses"
                                        :key="c.id"
                                        :value="String(c.id)"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/60"
                                />
                            </div>

                            <!-- Month Filter -->
                            <div class="relative">
                                <select
                                    v-model="selectedMonth"
                                    class="h-10 cursor-pointer appearance-none rounded-full border border-border/60 bg-card py-1.5 pr-8 pl-3.5 text-xs font-medium text-foreground transition-colors outline-none hover:bg-muted/40 focus:border-primary/40 focus:ring-2 focus:ring-primary/20 sm:text-[13px]"
                                    aria-label="Filter by month"
                                >
                                    <option
                                        v-for="m in months"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label }}
                                    </option>
                                </select>
                                <Calendar
                                    class="pointer-events-none absolute top-1/2 right-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/60"
                                />
                            </div>

                            <!-- Sort -->
                            <div class="relative">
                                <select
                                    v-model="sortBy"
                                    class="h-10 cursor-pointer appearance-none rounded-full border border-border/60 bg-card py-1.5 pr-8 pl-3.5 text-xs font-medium text-foreground transition-colors outline-none hover:bg-muted/40 focus:border-primary/40 focus:ring-2 focus:ring-primary/20 sm:text-[13px]"
                                    aria-label="Sort assignments"
                                >
                                    <option value="due_soon">
                                        Due soonest
                                    </option>
                                    <option value="due_late">Due latest</option>
                                    <option value="title">Title (A–Z)</option>
                                    <option value="newest">
                                        Recently added
                                    </option>
                                </select>
                                <Clock
                                    class="pointer-events-none absolute top-1/2 right-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/60"
                                />
                            </div>

                            <!-- Clear Filter Button -->
                            <button
                                v-if="hasActiveFilters"
                                @click="clearAllFilters"
                                type="button"
                                class="inline-flex h-10 items-center gap-1.5 rounded-full border border-border/60 px-3 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="mobile-assignment-mobile-list md:hidden"
                    data-tour="assignments-grid"
                >
                    <div class="mobile-assignment-list-heading">
                        <div>
                            <span class="mobile-dashboard-kicker"
                                >Your queue</span
                            >
                            <h2 class="mobile-dashboard-section-title">
                                All assignments
                            </h2>
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="mobile-assignment-reset"
                            @click="clearAllFilters"
                        >
                            Reset
                        </button>
                    </div>
                    <div class="mobile-assignment-filter-row">
                        <div
                            class="mobile-assignment-tabs"
                            role="tablist"
                            aria-label="Assignment status"
                        >
                            <button
                                type="button"
                                :class="{ 'is-active': activeTab === 'all' }"
                                @click="activeTab = 'all'"
                            >
                                All <span>{{ totalCount }}</span>
                            </button>
                            <button
                                type="button"
                                :class="{
                                    'is-active': activeTab === 'pending',
                                }"
                                @click="activeTab = 'pending'"
                            >
                                Pending <span>{{ pendingCount }}</span>
                            </button>
                            <button
                                type="button"
                                :class="{
                                    'is-active': activeTab === 'submitted',
                                }"
                                @click="activeTab = 'submitted'"
                            >
                                Done <span>{{ submittedCount }}</span>
                            </button>
                        </div>
                        <input
                            v-model="searchQuery"
                            class="mobile-assignment-search"
                            type="search"
                            placeholder="Search"
                            aria-label="Search assignments"
                        />
                    </div>
                    <div
                        v-if="filteredAssignments.length"
                        class="mobile-assignment-cards"
                    >
                        <article
                            v-for="assignment in filteredAssignments"
                            :key="assignment.id"
                            class="mobile-assignment-card"
                            :class="getCardBorderClass(assignment)"
                        >
                            <div class="mobile-assignment-card__topline">
                                <span class="mobile-assignment-course">{{
                                    assignment.course?.name || 'Assignment'
                                }}</span>
                                <span
                                    class="mobile-assignment-status"
                                    :class="getStatusBadge(assignment).classes"
                                    >{{
                                        getStatusBadge(assignment).label
                                    }}</span
                                >
                            </div>
                            <h3 class="mobile-assignment-card__title">
                                {{ assignment.title }}
                            </h3>
                            <p
                                v-if="assignment.description"
                                class="mobile-assignment-card__description"
                            >
                                {{ assignment.description }}
                            </p>
                            <div class="mobile-assignment-card__meta">
                                <span>{{ dueMeta(assignment).text }}</span>
                                <span v-if="pointsPossibleOf(assignment) > 0"
                                    >{{
                                        formatNumber(
                                            pointsPossibleOf(assignment),
                                        )
                                    }}
                                    pts</span
                                >
                            </div>
                            <div
                                v-if="
                                    assignment.incoming_invite &&
                                    !isGroupLocked(assignment)
                                "
                                class="mobile-assignment-invite"
                            >
                                <span
                                    >{{
                                        assignment.incoming_invite.inviter.name
                                    }}
                                    invited you</span
                                >
                                <div>
                                    <button
                                        type="button"
                                        @click="
                                            respondToIncomingInvite(
                                                assignment,
                                                'accept',
                                            )
                                        "
                                    >
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            respondToIncomingInvite(
                                                assignment,
                                                'decline',
                                            )
                                        "
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                            <div class="mobile-assignment-card__actions">
                                <button
                                    type="button"
                                    class="mobile-assignment-secondary-action"
                                    @click="openInstructions(assignment)"
                                >
                                    View details
                                </button>
                                <button
                                    v-if="!assignment.submission?.submitted"
                                    type="button"
                                    class="mobile-assignment-primary-action"
                                    :disabled="isClosed(assignment)"
                                    @click="openModalForAssignment(assignment)"
                                >
                                    Submit work
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="mobile-assignment-primary-action"
                                    @click="toggleGradeExpanded(assignment)"
                                >
                                    {{
                                        isGradeExpanded(assignment.id)
                                            ? 'Hide grade'
                                            : 'View grade'
                                    }}
                                </button>
                            </div>
                            <div
                                v-if="
                                    assignment.group &&
                                    !isGroupLocked(assignment)
                                "
                                class="mobile-assignment-group-row"
                            >
                                <span>Group activity</span>
                                <button
                                    v-if="
                                        isGroupCreator(assignment) &&
                                        canInviteMore(assignment)
                                    "
                                    type="button"
                                    @click="openInviteModal(assignment)"
                                >
                                    Invite
                                </button>
                                <button
                                    v-else-if="!isGroupCreator(assignment)"
                                    type="button"
                                    @click="requestLeaveGroup(assignment)"
                                >
                                    Leave
                                </button>
                            </div>
                        </article>
                    </div>
                    <MascotEmptyState
                        v-else
                        :size="120"
                        bare
                        :mascot="filteredAssignments.length ? 'assignments' : 'assignments'"
                        :title="
                            hasActiveFilters
                                ? 'No assignments found'
                                : 'No assignments yet'
                        "
                        :description="
                            hasActiveFilters
                                ? 'Try another status or search term.'
                                : 'New coursework will appear here once your teachers post it.'
                        "
                    />
                </div>

                <!-- Assignments Grid -->
                <div
                    data-tour="assignments-grid"
                    class="assignment-desktop-only animate-section"
                >
                    <div
                        v-if="filteredAssignments.length > 0"
                        class="assignment-desktop-only grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <Card
                            v-for="assignment in filteredAssignments"
                            :key="assignment.id"
                            class="surface-card group flex flex-col gap-3 overflow-hidden p-3.5 py-3.5 transition-all duration-300 hover:shadow-md sm:p-4 sm:py-4"
                            :class="getCardBorderClass(assignment)"
                        >
                            <!-- Incoming group invite: accept or decline right on the card -->
                            <div
                                v-if="
                                    assignment.incoming_invite &&
                                    !isGroupLocked(assignment)
                                "
                                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary/25 bg-primary/[0.06] px-2.5 py-2"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <Avatar class="size-7 shrink-0">
                                        <AvatarImage
                                            v-if="
                                                assignment.incoming_invite
                                                    .inviter.avatar
                                            "
                                            :src="
                                                assignment.incoming_invite
                                                    .inviter.avatar
                                            "
                                            :alt="
                                                assignment.incoming_invite
                                                    .inviter.name ?? 'Inviter'
                                            "
                                        />
                                        <AvatarFallback
                                            class="bg-primary/10 text-[9px] font-bold text-primary"
                                        >
                                            {{
                                                initials(
                                                    assignment.incoming_invite
                                                        .inviter.name,
                                                )
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <p
                                        class="min-w-0 text-[12px] leading-snug text-foreground sm:text-[13px]"
                                    >
                                        <span class="font-semibold">{{
                                            assignment.incoming_invite.inviter
                                                .name
                                        }}</span>
                                        invited you to their group
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 items-center gap-1 rounded-full bg-emerald-600 px-3 text-[11px] font-semibold text-white transition-colors hover:bg-emerald-600/90 active:scale-95 disabled:opacity-60 sm:text-xs"
                                        :disabled="inviteRespondLoading"
                                        @click="
                                            respondToIncomingInvite(
                                                assignment,
                                                'accept',
                                            )
                                        "
                                    >
                                        <Loader2
                                            v-if="inviteRespondLoading"
                                            class="h-3 w-3 animate-spin"
                                        />
                                        <CheckCircle2 v-else class="h-3 w-3" />
                                        <span>Accept</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 items-center gap-1 rounded-full border border-border/60 bg-card px-3 text-[11px] font-semibold text-muted-foreground transition-colors hover:text-foreground active:scale-95 disabled:opacity-60 sm:text-xs"
                                        :disabled="inviteRespondLoading"
                                        @click="
                                            respondToIncomingInvite(
                                                assignment,
                                                'decline',
                                            )
                                        "
                                    >
                                        <X class="h-3 w-3" />
                                        <span>Decline</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Title + status — the only things you need first -->
                            <div class="flex items-start justify-between gap-3">
                                <h2
                                    class="line-clamp-2 min-w-0 text-[15px] font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-[16px]"
                                >
                                    {{ assignment.title }}
                                </h2>
                                <span
                                    class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold sm:py-0.5 sm:text-xs"
                                    :class="getStatusBadge(assignment).classes"
                                >
                                    {{ getStatusBadge(assignment).label }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="assignment.course?.name"
                                    class="inline-flex items-center gap-1 text-[12px] font-medium text-muted-foreground"
                                >
                                    <BookOpen class="h-3 w-3" />
                                    {{ assignment.course.name }}
                                </span>

                                <span
                                    v-if="
                                        !assignment.submission?.submitted &&
                                        pointsPossibleOf(assignment) > 0
                                    "
                                    class="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 sm:text-xs dark:text-amber-400"
                                >
                                    <Award class="h-3 w-3" />
                                    Worth
                                    {{
                                        formatNumber(
                                            pointsPossibleOf(assignment),
                                        )
                                    }}
                                    pts
                                </span>
                            </div>

                            <!-- Timeline: due, submitted, graded — always visible -->
                            <dl
                                class="space-y-1.5 rounded-xl border border-border/50 bg-muted/20 px-3 py-2.5"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt
                                        class="shrink-0 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Due
                                    </dt>
                                    <dd
                                        class="min-w-0 text-right text-[12px] leading-snug font-medium sm:text-[13px]"
                                        :class="
                                            assignment.submission?.submitted
                                                ? 'text-foreground'
                                                : dueMeta(assignment).color
                                        "
                                    >
                                        <template
                                            v-if="
                                                !assignment.submission
                                                    ?.submitted
                                            "
                                        >
                                            {{ dueMeta(assignment).text }}
                                            <span
                                                v-if="assignment.due_date"
                                                class="mt-0.5 block text-[11px] font-normal text-muted-foreground"
                                            >
                                                {{
                                                    formatFullDateTime(
                                                        assignment.due_date,
                                                    )
                                                }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            {{
                                                formatFullDateTime(
                                                    assignment.due_date,
                                                ) || 'No due date'
                                            }}
                                        </template>
                                    </dd>
                                </div>

                                <div
                                    v-if="assignment.submission?.submitted"
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt
                                        class="shrink-0 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Submitted
                                    </dt>
                                    <dd
                                        class="min-w-0 text-right text-[12px] leading-snug font-medium text-foreground tabular-nums sm:text-[13px]"
                                    >
                                        {{
                                            formatFullDateTime(
                                                assignment.submission
                                                    .submitted_at,
                                            ) || '—'
                                        }}
                                        <span
                                            v-if="wasSubmittedLate(assignment)"
                                            class="mt-0.5 block text-[11px] font-medium text-red-600 dark:text-red-400"
                                        >
                                            After the deadline
                                        </span>
                                    </dd>
                                </div>

                                <div
                                    v-if="
                                        isGraded(assignment.submission) &&
                                        assignment.submission?.graded_at
                                    "
                                    class="flex items-start justify-between gap-3"
                                >
                                    <dt
                                        class="shrink-0 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Graded
                                    </dt>
                                    <dd
                                        class="min-w-0 text-right text-[12px] leading-snug font-medium text-foreground tabular-nums sm:text-[13px]"
                                    >
                                        {{
                                            formatFullDateTime(
                                                assignment.submission.graded_at,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <!-- View instructions: opens a modal (bottom sheet on
                                 mobile) with the full assignment instructions. -->
                            <button
                                type="button"
                                class="inline-flex h-8 w-fit max-w-full items-center gap-1.5 self-start rounded-full border border-border/60 bg-card px-3 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted active:scale-95 sm:text-xs"
                                @click="openInstructions(assignment)"
                            >
                                <BookOpen
                                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                />
                                <span class="truncate">View instructions</span>
                            </button>

                            <!-- Group Activity Section: student-formed groups share
                                 one submission file. Read-only once graded. -->
                            <div
                                v-if="
                                    assignment.group ||
                                    (!isGroupLocked(assignment) &&
                                        (assignment.group_rules?.max === null ||
                                            assignment.group_rules?.max ===
                                                undefined ||
                                            assignment.group_rules.max > 1))
                                "
                                class="rounded-xl border border-primary/15 bg-primary/[0.04] p-2.5"
                            >
                                <!-- Header: a static label when there is no
                                     group yet, or a disclosure toggle (summary
                                     + chevron) when a roster exists to hide. -->
                                <div
                                    v-if="!assignment.group"
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Users
                                        class="h-3.5 w-3.5 shrink-0 text-primary"
                                    />
                                    <span
                                        class="text-[10px] font-bold tracking-wide text-foreground/70 uppercase sm:text-[11px]"
                                    >
                                        Group
                                    </span>
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="-mx-0.5 flex w-full items-center gap-1.5 rounded-md px-0.5 py-0.5 text-left transition-colors hover:bg-primary/[0.06] focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
                                    :aria-expanded="
                                        isGroupExpanded(assignment.id)
                                    "
                                    :aria-label="
                                        isGroupExpanded(assignment.id)
                                            ? 'Hide group members'
                                            : 'Show group members'
                                    "
                                    @click="toggleGroupExpanded(assignment)"
                                >
                                    <Users
                                        class="h-3.5 w-3.5 shrink-0 text-primary"
                                    />
                                    <span
                                        class="text-[10px] font-bold tracking-wide text-foreground/70 uppercase sm:text-[11px]"
                                    >
                                        Group
                                    </span>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ groupMembers(assignment).length }}
                                        member{{
                                            groupMembers(assignment).length ===
                                            1
                                                ? ''
                                                : 's'
                                        }}
                                        <template
                                            v-if="
                                                pendingInviteCount(assignment) >
                                                0
                                            "
                                        >
                                            ·
                                            {{ pendingInviteCount(assignment) }}
                                            waiting
                                        </template>
                                    </span>
                                    <ChevronDown
                                        class="ml-auto h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform"
                                        :class="
                                            isGroupExpanded(assignment.id)
                                                ? 'rotate-180'
                                                : ''
                                        "
                                    />
                                </button>

                                <!-- Collapsible roster: members + pending
                                     invitees. Hidden by default so a full
                                     group doesn't crowd the card. -->
                                <div
                                    v-if="
                                        assignment.group &&
                                        isGroupExpanded(assignment.id)
                                    "
                                    class="mt-1.5 flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        v-for="member in groupMembers(
                                            assignment,
                                        )"
                                        :key="member.id"
                                        class="inline-flex items-center gap-1 rounded-full border border-border/60 bg-card py-0.5 pr-1.5 pl-0.5 text-[11px] font-medium text-foreground"
                                    >
                                        <Avatar class="size-5">
                                            <AvatarImage
                                                v-if="member.avatar"
                                                :src="member.avatar"
                                                :alt="
                                                    member.name ??
                                                    'Group member'
                                                "
                                            />
                                            <AvatarFallback
                                                class="bg-primary/10 text-[8px] font-bold text-primary"
                                            >
                                                {{ initials(member.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span
                                            class="max-w-24 truncate sm:max-w-32"
                                            >{{ member.name }}</span
                                        >
                                        <span
                                            v-if="
                                                member.id ===
                                                assignment.group.created_by
                                            "
                                            class="hidden text-[9px] font-semibold text-primary sm:inline"
                                        >
                                            creator
                                        </span>
                                        <button
                                            v-if="
                                                !isGroupLocked(assignment) &&
                                                isGroupCreator(assignment) &&
                                                member.id !== currentUserId
                                            "
                                            type="button"
                                            class="flex h-4 w-4 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            :title="`Remove ${member.name}`"
                                            :aria-label="`Remove ${member.name}`"
                                            @click.stop="
                                                requestRemoveMember(
                                                    assignment,
                                                    member,
                                                )
                                            "
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                    <!-- Pending invitees: greyed until they respond -->
                                    <span
                                        v-for="invite in assignment.group
                                            .pending_invites ?? []"
                                        :key="`invite-${invite.id}`"
                                        class="inline-flex items-center gap-1 rounded-full border border-dashed border-border/70 bg-muted/40 py-0.5 pr-1.5 pl-0.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        <Avatar class="size-5">
                                            <AvatarImage
                                                v-if="invite.user.avatar"
                                                :src="invite.user.avatar"
                                                :alt="
                                                    invite.user.name ??
                                                    'Invitee'
                                                "
                                            />
                                            <AvatarFallback
                                                class="bg-muted text-[8px] font-bold text-muted-foreground"
                                            >
                                                {{ initials(invite.user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span
                                            class="max-w-24 truncate sm:max-w-32"
                                            >{{ invite.user.name }}</span
                                        >
                                        <span
                                            class="hidden text-[9px] font-semibold tracking-wide text-muted-foreground/70 uppercase sm:inline"
                                        >
                                            waiting
                                        </span>
                                        <button
                                            v-if="isGroupCreator(assignment)"
                                            type="button"
                                            class="flex h-4 w-4 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            :title="`Cancel invite for ${invite.user.name}`"
                                            :aria-label="`Cancel invite for ${invite.user.name}`"
                                            :disabled="groupActionLoading"
                                            @click.stop="
                                                cancelPendingInvite(
                                                    assignment,
                                                    invite.id,
                                                )
                                            "
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </div>

                                <!-- Group actions (locked once graded) -->
                                <div
                                    v-if="!isGroupLocked(assignment)"
                                    class="mt-1.5 flex flex-wrap items-center gap-1.5"
                                >
                                    <button
                                        v-if="!assignment.group"
                                        type="button"
                                        class="inline-flex h-6 items-center gap-1 rounded-full bg-primary/10 px-2.5 text-[11px] font-semibold text-primary transition-colors hover:bg-primary/15"
                                        :disabled="groupActionLoading"
                                        @click="openInviteModal(assignment)"
                                    >
                                        <UserPlus class="h-3 w-3" />
                                        <span>Form a group</span>
                                    </button>
                                    <template v-else>
                                        <button
                                            v-if="
                                                isGroupCreator(assignment) &&
                                                canInviteMore(assignment)
                                            "
                                            type="button"
                                            class="inline-flex h-6 items-center gap-1 rounded-full bg-primary/10 px-2.5 text-[11px] font-semibold text-primary transition-colors hover:bg-primary/15"
                                            :disabled="groupActionLoading"
                                            @click="openInviteModal(assignment)"
                                        >
                                            <UserPlus class="h-3 w-3" />
                                            <span>Invite members</span>
                                        </button>
                                        <button
                                            v-if="!isGroupCreator(assignment)"
                                            type="button"
                                            class="inline-flex h-6 items-center gap-1 rounded-full border border-border/60 bg-card px-2.5 text-[11px] font-semibold text-muted-foreground transition-colors hover:border-destructive/30 hover:bg-destructive/5 hover:text-destructive"
                                            :disabled="groupActionLoading"
                                            @click="
                                                requestLeaveGroup(assignment)
                                            "
                                        >
                                            <LogOut class="h-3 w-3" />
                                            <span>Leave group</span>
                                        </button>
                                    </template>
                                    <span
                                        v-if="groupRulesLabel(assignment)"
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ groupRulesLabel(assignment) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Submitted File Block: only when there's a submission.
                                 Collapsed by default — students tap "View grade"
                                 to see file, points, feedback, etc. -->
                            <div
                                v-if="assignment.submission?.submitted"
                                class="space-y-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        @click="toggleGradeExpanded(assignment)"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-full border border-border/60 bg-card px-3 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted sm:text-xs"
                                        :aria-expanded="
                                            isGradeExpanded(assignment.id)
                                        "
                                    >
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="
                                                isGradeExpanded(assignment.id)
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                        />
                                        <span>{{
                                            isGradeExpanded(assignment.id)
                                                ? 'Hide details'
                                                : 'View grade'
                                        }}</span>
                                    </button>

                                    <!-- Unread feedback pulse: clears when the
                                         student expands the details above. -->
                                    <span
                                        v-if="hasUnseenFeedback(assignment)"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 text-[11px] font-semibold text-emerald-700 sm:text-xs dark:text-emerald-400"
                                    >
                                        <span class="relative flex h-2 w-2">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:hidden"
                                            />
                                            <span
                                                class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                                            />
                                        </span>
                                        New feedback
                                    </span>

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="dash-btn h-8 rounded-full border-border/60 px-3 text-[11px] sm:text-xs"
                                        :disabled="
                                            isGraded(assignment.submission) ||
                                            isClosed(assignment)
                                        "
                                        :title="
                                            isGraded(assignment.submission)
                                                ? 'This assignment has already been graded and cannot be resubmitted.'
                                                : isClosed(assignment)
                                                  ? 'This assignment is closed and no longer accepts submissions.'
                                                  : undefined
                                        "
                                        @click="
                                            openModalForAssignment(assignment)
                                        "
                                    >
                                        <FileUp class="h-3.5 w-3.5" />
                                        <span>Resubmit</span>
                                    </Button>
                                </div>

                                <div
                                    v-if="isGradeExpanded(assignment.id)"
                                    class="space-y-2 rounded-xl border border-border/60 bg-muted/20 p-2.5 shadow-xs"
                                >
                                    <!-- File Row -->
                                    <div class="flex gap-2.5">
                                        <div
                                            class="relative flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/50 bg-card shadow-sm"
                                        >
                                            <img
                                                v-if="
                                                    isImageFile(
                                                        assignment.submission
                                                            .file_extension,
                                                    ) &&
                                                    assignment.submission
                                                        .file_url
                                                "
                                                :src="
                                                    assignment.submission
                                                        .file_url
                                                "
                                                :alt="
                                                    getFileName(
                                                        assignment.submission
                                                            .file_path,
                                                    )
                                                "
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                            />
                                            <div
                                                v-else-if="
                                                    isPdfFile(
                                                        assignment.submission
                                                            .file_extension,
                                                    )
                                                "
                                                class="flex h-full w-full flex-col items-center justify-center gap-1 bg-red-500/10 text-red-600 dark:text-red-400"
                                            >
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <div
                                                v-else
                                                class="flex h-full w-full flex-col items-center justify-center gap-1 bg-primary/10 text-primary"
                                            >
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <span
                                                class="absolute right-0 bottom-0 left-0 bg-black/70 px-1 py-0.5 text-center text-[8px] font-bold tracking-wide text-white uppercase sm:text-[9px]"
                                            >
                                                {{
                                                    assignment.submission
                                                        .file_extension ||
                                                    'FILE'
                                                }}
                                            </span>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-[12px] leading-tight font-semibold text-foreground sm:text-[13px]"
                                                :title="
                                                    getFileName(
                                                        assignment.submission
                                                            .file_path,
                                                    )
                                                "
                                            >
                                                {{
                                                    getFileName(
                                                        assignment.submission
                                                            .file_path,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-[10px] leading-tight text-muted-foreground sm:text-[11px]"
                                            >
                                                {{
                                                    formatDateTime(
                                                        assignment.submission
                                                            .submitted_at,
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        assignment.submission
                                                            .submitted_by_name
                                                    "
                                                >
                                                    · Submitted by
                                                    {{
                                                        assignment.submission
                                                            .submitted_by_name
                                                    }}
                                                </span>
                                            </p>

                                            <!-- Grade / Points / XP Pills -->
                                            <div
                                                class="mt-2 flex flex-wrap gap-1 sm:gap-1.5"
                                            >
                                                <span
                                                    v-if="
                                                        assignment.submission
                                                            .grade
                                                    "
                                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 sm:px-2.5 sm:text-[11px] dark:text-emerald-400"
                                                >
                                                    <Award
                                                        class="h-3 w-3 shrink-0"
                                                    />
                                                    <span class="truncate">{{
                                                        assignment.submission
                                                            .grade
                                                    }}</span>
                                                </span>
                                                <span
                                                    v-if="
                                                        pointsLabel(
                                                            assignment,
                                                        ) !== null
                                                    "
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 sm:px-2.5 sm:text-[11px] dark:text-amber-400"
                                                >
                                                    <TrendingUp
                                                        class="h-3 w-3 shrink-0"
                                                    />
                                                    <span class="truncate">{{
                                                        pointsLabel(assignment)
                                                    }}</span>
                                                </span>
                                                <span
                                                    v-if="
                                                        Number(
                                                            assignment
                                                                .submission
                                                                .xp_earned,
                                                        ) > 0
                                                    "
                                                    class="inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-2 py-0.5 text-[10px] font-semibold text-violet-700 sm:px-2.5 sm:text-[11px] dark:text-violet-400"
                                                >
                                                    <Zap
                                                        class="h-3 w-3 shrink-0"
                                                    />
                                                    +{{
                                                        assignment.submission
                                                            .xp_earned
                                                    }}
                                                    XP
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Teacher Feedback -->
                                    <div
                                        v-if="assignment.submission.feedback"
                                        class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.07] p-3"
                                    >
                                        <div class="flex items-start gap-2.5">
                                            <div
                                                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-700 sm:h-6 sm:w-6 dark:text-emerald-400"
                                            >
                                                <MessageSquareText
                                                    class="h-4 w-4 sm:h-3.5 sm:w-3.5"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-[10px] font-bold tracking-wide text-emerald-800 uppercase sm:text-[11px] dark:text-emerald-300"
                                                >
                                                    Teacher Feedback
                                                </p>
                                                <p
                                                    class="mt-1.5 text-[12px] leading-relaxed text-foreground sm:text-[13px]"
                                                >
                                                    {{
                                                        assignment.submission
                                                            .feedback
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        assignment.submission
                                                            .graded_at
                                                    "
                                                    class="mt-2 text-[10px] text-muted-foreground sm:text-[11px]"
                                                >
                                                    Graded
                                                    {{
                                                        formatDateTime(
                                                            assignment
                                                                .submission
                                                                .graded_at,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Preview + Download -->
                                    <div
                                        class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap"
                                    >
                                        <a
                                            v-if="
                                                assignment.submission
                                                    .file_url ||
                                                assignment.submission.file_path
                                            "
                                            :href="
                                                assignment.submission
                                                    .file_url ??
                                                `/storage/${assignment.submission.file_path}`
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl border border-border/60 bg-card px-3 text-[13px] font-semibold text-foreground shadow-xs transition-all hover:bg-muted active:scale-[0.98] sm:h-9 sm:text-xs"
                                        >
                                            <Eye
                                                class="h-4 w-4 sm:h-3.5 sm:w-3.5"
                                            />
                                            <span>Preview</span>
                                        </a>
                                        <a
                                            v-if="
                                                assignment.submission
                                                    .file_url ||
                                                assignment.submission.file_path
                                            "
                                            :href="
                                                assignment.submission
                                                    .file_url ??
                                                `/storage/${assignment.submission.file_path}`
                                            "
                                            :download="
                                                getFileName(
                                                    assignment.submission
                                                        .file_path,
                                                )
                                            "
                                            class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-foreground px-3 text-[13px] font-semibold text-background shadow-xs transition-all hover:bg-foreground/90 active:scale-[0.98] sm:h-9 sm:text-xs"
                                        >
                                            <Download
                                                class="h-4 w-4 sm:h-3.5 sm:w-3.5"
                                            />
                                            <span>Download</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    !assignment.submission?.submitted &&
                                    !isClosed(assignment)
                                "
                                class="mt-1 flex items-center justify-end gap-2 border-t border-border/50 pt-2.5"
                            >
                                <Button
                                    variant="default"
                                    size="sm"
                                    class="dash-btn h-9 gap-1.5 rounded-xl bg-[#D97757] px-4 text-xs font-semibold text-white shadow-xs hover:bg-[#D97757]/90"
                                    @click="openModalForAssignment(assignment)"
                                >
                                    <FileUp class="h-3.5 w-3.5" />
                                    <span>Submit</span>
                                </Button>
                            </div>
                        </Card>
                    </div>

                    <!-- Empty State: No results found after filters/search -->
                    <Card
                        v-else-if="totalCount > 0"
                        class="surface-card border-dashed py-14 text-center sm:py-20"
                    >
                        <CardContent
                            class="flex flex-col items-center justify-center p-6"
                        >
                            <div
                                class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted/50 text-muted-foreground"
                            >
                                <Search class="h-6 w-6" />
                            </div>
                            <h3
                                class="text-lg font-semibold tracking-tight text-foreground"
                            >
                                No matching assignments
                            </h3>
                            <p
                                class="mt-1.5 max-w-sm text-sm text-muted-foreground"
                            >
                                We couldn't find any assignments matching your
                                search or active filters.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="dash-btn mt-5 rounded-full px-5"
                                @click="clearAllFilters"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                                <span>Clear all filters</span>
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Empty State: No assignments at all -->
                    <Card v-else class="surface-card border-dashed">
                        <CardContent class="flex justify-center">
                            <MascotEmptyState
                                mascot="assignments"
                                title="No assignments yet"
                                description="Your teachers haven't posted any assignments yet. When coursework is assigned, it will appear here with clear instructions and deadlines — the fox will be waiting."
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <!-- Onboarding Tour -->
        <OnboardingTour
            tour-id="assignments"
            :steps="assignmentsTourSteps"
            :can-start="isBooted && !showUploadModal"
            :start-delay="900"
        />

        <!-- Upload & Submit Assignment Modal -->
        <ResponsiveModal
            :open="showUploadModal"
            custom-header
            content-class="sm:max-w-lg"
            @close="closeModal"
        >
            <template #header>
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 space-y-1">
                        <span class="text-xs font-medium text-[#D97757]">
                            Assignment submission
                        </span>
                        <h2 class="text-lg font-bold text-foreground">
                            {{
                                selectedAssignment
                                    ? selectedAssignment.title
                                    : 'Submit assignment'
                            }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                selectedAssignment?.course?.name
                                    ? `Subject: ${selectedAssignment.course.name}`
                                    : 'Upload your completed file for grading.'
                            }}
                        </p>
                    </div>
                </div>
            </template>

            <div class="space-y-4 pt-2">
                <!-- Assignment Description / Details Callout -->
                <div
                    v-if="selectedAssignment?.description"
                    class="rounded-xl border border-border/60 bg-muted/20 p-3.5 text-xs leading-relaxed text-muted-foreground"
                >
                    <p class="mb-1 font-medium text-foreground">
                        Instructions:
                    </p>
                    <p class="line-clamp-3">
                        {{ selectedAssignment.description }}
                    </p>
                </div>

                <!-- Group Share Note -->
                <div
                    v-if="selectedAssignment?.group"
                    class="rounded-xl border border-primary/20 bg-primary/5 p-3.5"
                >
                    <p
                        class="flex items-center gap-1.5 text-xs font-semibold text-foreground"
                    >
                        <Users class="h-3.5 w-3.5 text-primary" />
                        Group submission —
                        {{ groupMembers(selectedAssignment).length }}
                        member{{
                            groupMembers(selectedAssignment).length === 1
                                ? ''
                                : 's'
                        }}
                    </p>
                    <p
                        class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                    >
                        The file you upload will be shared with everyone in your
                        group: {{ groupMemberNames(selectedAssignment) }}
                    </p>
                </div>

                <!-- Drag & Drop Upload Zone -->
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-foreground">
                        Upload file
                    </label>

                    <div
                        @click="fileInput?.click()"
                        @dragover.prevent="isDraggingFile = true"
                        @dragleave.prevent="isDraggingFile = false"
                        @drop.prevent="handleFileDrop"
                        class="group relative flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed p-6 text-center transition-all"
                        :class="[
                            isDraggingFile
                                ? 'border-[#D97757] bg-[#D97757]/5'
                                : 'border-border/70 hover:border-primary/50 hover:bg-muted/20',
                            !selectedAssignmentId
                                ? 'pointer-events-none opacity-50'
                                : '',
                        ]"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg"
                            @change="handleFileInputChange"
                        />

                        <div
                            v-if="!form.file"
                            class="flex flex-col items-center gap-2.5"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground transition-transform duration-300 group-hover:scale-105 group-hover:bg-[#D97757]/10 group-hover:text-[#D97757]"
                            >
                                <UploadCloud class="h-6 w-6" />
                            </div>
                            <div>
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Click to upload or drag &amp; drop
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    PDF, Word (docx), PowerPoint (pptx), Excel
                                    (xls/xlsx), or Images (jpg/png) — Max 10 MB
                                </p>
                            </div>
                        </div>

                        <!-- Selected File Preview -->
                        <div
                            v-else
                            class="flex w-full items-center justify-between rounded-xl border border-border/60 bg-card p-3 shadow-xs"
                            @click.stop
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <FileText class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 text-left">
                                    <p
                                        class="truncate text-xs font-semibold text-foreground"
                                    >
                                        {{ form.file.name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ formatFileSize(form.file.size) }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="removeSelectedFile"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                title="Remove file"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Client / Server Error Feedback -->
                    <div
                        v-if="fileError || form.errors.file"
                        class="flex items-center gap-2 rounded-xl border border-destructive/20 bg-destructive/5 px-3.5 py-2.5 text-xs text-destructive"
                    >
                        <AlertTriangle class="h-4 w-4 shrink-0" />
                        <span>{{ fileError || form.errors.file }}</span>
                    </div>
                </div>

                <!-- Progress Indicator -->
                <div v-if="form.processing" class="space-y-1.5">
                    <div
                        class="flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1.5 font-medium">
                            <Loader2
                                class="h-3.5 w-3.5 animate-spin text-[#D97757]"
                            />
                            Uploading assignment...
                        </span>
                        <span>Please wait</span>
                    </div>
                    <Progress
                        :value="form.progress?.percentage ?? 60"
                        class="h-1.5 w-full bg-muted"
                        indicator-class="bg-[#D97757]"
                    />
                </div>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="dash-btn w-full rounded-xl sm:w-auto"
                        :disabled="form.processing"
                        @click="closeModal"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="dash-btn w-full rounded-xl bg-[#D97757] text-white hover:bg-[#D97757]/90 sm:w-auto"
                        :disabled="
                            !form.file ||
                            !selectedAssignmentId ||
                            form.processing
                        "
                        @click="submitAssignment"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                        />
                        <FileUp v-else class="h-4 w-4" />
                        <span>{{
                            form.processing
                                ? 'Submitting...'
                                : 'Submit assignment'
                        }}</span>
                    </Button>
                </div>
            </template>
        </ResponsiveModal>

        <!-- View Instructions Modal -->
        <ResponsiveModal
            :open="!!instructionsAssignment"
            custom-header
            content-class="sm:max-w-2xl"
            @close="closeInstructions"
        >
            <template #header>
                <div class="min-w-0 space-y-1">
                    <span class="text-xs font-medium text-[#D97757]">
                        Assignment instructions
                    </span>
                    <h2
                        class="text-lg font-bold tracking-tight text-foreground"
                    >
                        {{ instructionsAssignment?.title }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="instructionsAssignment?.course?.name">
                            {{ instructionsAssignment.course.name }}
                        </template>
                        <template
                            v-if="
                                instructionsAssignment?.course?.name &&
                                instructionsAssignment?.due_date
                            "
                        >
                            ·
                        </template>
                        <template v-if="instructionsAssignment?.due_date">
                            Due
                            {{ formatDueDate(instructionsAssignment.due_date) }}
                        </template>
                        <template v-if="!instructionsAssignment?.due_date">
                            No due date
                        </template>
                    </p>
                </div>
            </template>

            <div class="space-y-4 pt-2">
                <!-- At-a-glance meta: status + what the work is worth -->
                <div
                    v-if="instructionsAssignment"
                    class="flex flex-wrap items-center gap-1.5"
                >
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold sm:text-xs"
                        :class="getStatusBadge(instructionsAssignment).classes"
                    >
                        {{ getStatusBadge(instructionsAssignment).label }}
                    </span>

                    <span
                        v-if="
                            !instructionsAssignment.submission?.submitted &&
                            pointsPossibleOf(instructionsAssignment) > 0
                        "
                        class="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 sm:text-xs dark:text-amber-400"
                    >
                        <Award class="h-3 w-3 shrink-0" />
                        Worth
                        {{
                            formatNumber(
                                pointsPossibleOf(instructionsAssignment),
                            )
                        }}
                        pts
                    </span>

                    <span
                        v-if="
                            instructionsAssignment.submission?.submitted &&
                            pointsLabel(instructionsAssignment) !== null
                        "
                        class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 sm:text-xs dark:text-amber-400"
                    >
                        <TrendingUp class="h-3 w-3 shrink-0" />
                        {{ pointsLabel(instructionsAssignment) }}
                    </span>
                </div>

                <!-- Full instructions body -->
                <div
                    class="rounded-xl border border-border/60 bg-muted/20 p-4 sm:p-5"
                >
                    <p
                        class="mb-2 flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-foreground/70 uppercase sm:text-xs"
                    >
                        <BookOpen class="h-3.5 w-3.5 shrink-0" />
                        Instructions
                    </p>
                    <div
                        v-if="instructionsAssignment?.description"
                        class="max-h-[50vh] overflow-y-auto overscroll-contain pr-1 text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground sm:text-[15px]"
                    >
                        {{ instructionsAssignment.description }}
                    </div>
                    <p
                        v-else
                        class="text-sm leading-relaxed text-muted-foreground"
                    >
                        No instructions were provided for this assignment.
                    </p>
                </div>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="dash-btn w-full rounded-xl sm:w-auto"
                        @click="closeInstructions"
                    >
                        Close
                    </Button>
                    <Button
                        v-if="
                            instructionsAssignment &&
                            !instructionsAssignment.submission?.submitted &&
                            !isClosed(instructionsAssignment)
                        "
                        type="button"
                        class="dash-btn w-full rounded-xl bg-[#D97757] text-white hover:bg-[#D97757]/90 sm:w-auto"
                        @click="openUploadFromInstructions"
                    >
                        <FileUp class="h-4 w-4" />
                        <span>Submit assignment</span>
                    </Button>
                </div>
            </template>
        </ResponsiveModal>

        <!-- Invite Members Modal (step 1: pick, step 2: sent) -->
        <ResponsiveModal
            :open="showInviteModal"
            custom-header
            content-class="sm:max-w-md"
            @close="closeInviteModal"
        >
            <template #header>
                <div class="space-y-1">
                    <span class="text-xs font-medium text-[#D97757]">
                        Group activity
                    </span>
                    <h2 class="text-lg font-bold text-foreground">
                        {{
                            inviteStep === 'sent'
                                ? 'Invites sent'
                                : inviteAssignment?.group
                                  ? 'Invite members'
                                  : 'Form a group'
                        }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{
                            inviteStep === 'sent'
                                ? 'Your classmates will get a notification and can accept right from their assignments page.'
                                : inviteAssignment?.title
                                  ? `Group for: ${inviteAssignment.title}`
                                  : 'Invite classmates to your group'
                        }}
                        <template
                            v-if="
                                inviteStep === 'select' &&
                                inviteAssignment &&
                                groupRulesLabel(inviteAssignment)
                            "
                        >
                            ·
                            {{ groupRulesLabel(inviteAssignment) }}
                        </template>
                    </p>
                </div>
            </template>

            <!-- Step 2: confirmation -->
            <div
                v-if="inviteStep === 'sent'"
                class="flex flex-col items-center py-6 text-center"
            >
                <div
                    class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                    <CheckCircle2 class="h-7 w-7" />
                </div>
                <p class="text-sm font-semibold text-foreground">
                    <template v-if="inviteSelection.length === 1"
                        >1 invite was sent</template
                    >
                    <template v-else-if="inviteSelection.length > 1"
                        >{{ inviteSelection.length }} invites were
                        sent</template
                    >
                    <template v-else>Your invites were sent</template>
                </p>
                <p
                    class="mt-1.5 max-w-xs text-xs leading-relaxed text-muted-foreground"
                >
                    You can submit for the group any time — no need to wait for
                    everyone. Members who accept later still see the shared
                    file.
                </p>
            </div>

            <!-- Step 1: pick classmates -->
            <div v-else class="space-y-3 pt-2">
                <!-- Search input -->
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="inviteSearchQuery"
                        placeholder="Search classmates by name..."
                        class="h-10 rounded-xl pl-10"
                    />
                </div>

                <!-- Selected chips -->
                <div
                    v-if="inviteSelection.length"
                    class="flex flex-wrap gap-1.5"
                >
                    <span
                        v-for="selected in inviteSelection"
                        :key="selected.id"
                        class="inline-flex items-center gap-1 rounded-full border border-primary/25 bg-primary/10 py-0.5 pr-1 pl-2 text-[11px] font-semibold text-primary"
                    >
                        {{ selected.name }}
                        <button
                            type="button"
                            class="flex h-4 w-4 items-center justify-center rounded-full transition-colors hover:bg-primary/20"
                            :aria-label="`Remove ${selected.name}`"
                            @click="toggleCandidate(selected)"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </span>
                </div>

                <!-- Results -->
                <div
                    v-if="inviteSearching"
                    class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground"
                >
                    <Loader2 class="h-4 w-4 animate-spin text-[#D97757]" />
                    Searching...
                </div>

                <div
                    v-else-if="inviteError"
                    class="flex items-center gap-2 rounded-xl border border-destructive/20 bg-destructive/5 px-3.5 py-2.5 text-xs text-destructive"
                >
                    <AlertTriangle class="h-4 w-4 shrink-0" />
                    <span>{{ inviteError }}</span>
                </div>

                <div
                    v-else-if="inviteCandidates.length === 0"
                    class="rounded-xl border border-dashed border-border/70 px-4 py-8 text-center"
                >
                    <div
                        class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-muted/50 text-muted-foreground"
                    >
                        <Users class="h-5 w-5" />
                    </div>
                    <p class="text-sm font-semibold text-foreground">
                        No classmates available
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Everyone in your sections is already grouped or has a
                        pending invite. Try a different search.
                    </p>
                </div>

                <div
                    v-else
                    class="no-scrollbar max-h-72 space-y-1.5 overflow-y-auto pr-1"
                >
                    <button
                        v-for="candidate in inviteCandidates"
                        :key="candidate.id"
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-xl border p-2.5 text-left transition-colors active:scale-[0.99]"
                        :class="
                            isCandidateSelected(candidate)
                                ? 'border-primary/40 bg-primary/10'
                                : 'border-border/60 bg-card hover:bg-muted/40'
                        "
                        :disabled="groupActionLoading"
                        @click="toggleCandidate(candidate)"
                    >
                        <Avatar class="size-8">
                            <AvatarImage
                                v-if="candidate.avatar"
                                :src="candidate.avatar"
                                :alt="candidate.name"
                            />
                            <AvatarFallback
                                class="bg-primary/10 text-[10px] font-bold text-primary"
                            >
                                {{ initials(candidate.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-foreground"
                            >
                                {{ candidate.name }}
                            </p>
                            <p
                                v-if="candidate.sections.length"
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ candidate.sections.join(', ') }}
                            </p>
                        </div>
                        <span
                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                isCandidateSelected(candidate)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-primary/10 text-primary'
                            "
                        >
                            <CheckCircle2
                                v-if="isCandidateSelected(candidate)"
                                class="h-3.5 w-3.5"
                            />
                            <UserPlus v-else class="h-3.5 w-3.5" />
                        </span>
                    </button>
                </div>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="dash-btn w-full rounded-xl sm:w-auto"
                        :disabled="groupActionLoading"
                        @click="closeInviteModal"
                    >
                        {{ inviteStep === 'sent' ? 'Close' : 'Cancel' }}
                    </Button>
                    <Button
                        v-if="inviteStep === 'select'"
                        type="button"
                        class="dash-btn w-full rounded-xl bg-[#D97757] text-white hover:bg-[#D97757]/90 sm:w-auto"
                        :disabled="
                            groupActionLoading || inviteSelection.length === 0
                        "
                        @click="sendInvites"
                    >
                        <Loader2
                            v-if="groupActionLoading"
                            class="h-4 w-4 animate-spin"
                        />
                        <UserPlus v-else class="h-4 w-4" />
                        <span
                            >Send {{ inviteSelection.length || '' }} invite{{
                                inviteSelection.length === 1 ? '' : 's'
                            }}</span
                        >
                    </Button>
                </div>
            </template>
        </ResponsiveModal>

        <!-- Remove member / Leave group confirmation -->
        <ResponsiveModal
            :open="!!groupConfirm"
            custom-header
            content-class="sm:max-w-md"
            @close="groupConfirm = null"
        >
            <template #header>
                <div class="space-y-1">
                    <span class="text-xs font-medium text-[#D97757]">
                        Group activity
                    </span>
                    <h2 class="text-lg font-bold text-foreground">
                        {{
                            groupConfirm?.member
                                ? 'Remove member?'
                                : 'Leave group?'
                        }}
                    </h2>
                </div>
            </template>

            <div class="pt-2">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    <template v-if="groupConfirm?.member">
                        Remove
                        <span class="font-semibold text-foreground">{{
                            groupConfirm.member.name
                        }}</span>
                        from the group for
                        <span class="font-semibold text-foreground">{{
                            groupConfirm.assignment.title
                        }}</span
                        >? They will no longer see the submitted file.
                    </template>
                    <template v-else>
                        Leave the group for
                        <span class="font-semibold text-foreground">{{
                            groupConfirm?.assignment.title
                        }}</span
                        >? Your submission file will be removed from your
                        assignment and you will no longer see the group's file.
                    </template>
                </p>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="dash-btn w-full rounded-xl sm:w-auto"
                        :disabled="groupActionLoading"
                        @click="groupConfirm = null"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="dash-btn w-full rounded-xl bg-destructive text-white hover:bg-destructive/90 sm:w-auto"
                        :disabled="groupActionLoading"
                        @click="confirmGroupAction"
                    >
                        <Loader2
                            v-if="groupActionLoading"
                            class="h-4 w-4 animate-spin"
                        />
                        <span>{{
                            groupConfirm?.member
                                ? 'Remove member'
                                : 'Leave group'
                        }}</span>
                    </Button>
                </div>
            </template>
        </ResponsiveModal>
    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
