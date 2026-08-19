<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
    ArrowUpDown,
    AlertTriangle,
    GraduationCap,
    UploadCloud,
    ChevronDown,
    Loader2,
    TrendingUp,
    RotateCcw,
    Eye,
    Image as ImageIcon,
    Award,
    Zap,
    MessageSquareText,
} from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import PageSkeleton from '@/components/PageSkeleton.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
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

interface Assignment {
    id: number;
    title: string;
    description: string;
    due_date: string;
    course: {
        id: number;
        name: string;
    } | null;
    submission: {
        submitted: boolean;
        status: string;
        grade: string | null;
        file_path: string | null;
        file_url: string | null;
        submitted_at: string | null;
        points: number | string | null;
        xp_earned: number | string | null;
        feedback: string | null;
        graded_at: string | null;
        graded_by: number | null;
        file_extension: string | null;
    } | null;
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
        body: 'Review instructions, due dates, submission timestamps, grades, teacher feedback, points and file preview.',
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

const isOverdue = (dueDate: string | null) => {
    if (!dueDate) return false;
    const due = new Date(dueDate);
    if (Number.isNaN(due.getTime())) return false;
    return due.getTime() < Date.now();
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

const getFileTypeLabel = (ext: string | null | undefined) => {
    if (!ext) return 'File';
    const map: Record<string, string> = {
        pdf: 'PDF Document',
        doc: 'Word Document',
        docx: 'Word Document',
        ppt: 'PowerPoint',
        pptx: 'PowerPoint',
        xls: 'Excel',
        xlsx: 'Excel Spreadsheet',
        jpg: 'Image',
        jpeg: 'Image',
        png: 'Image',
    };
    return map[ext.toLowerCase()] || ext.toUpperCase();
};

// ─── Date Formatting & Helpers ──────────────────────────────────────────────
const formatDueDate = (dateStr: string | null) => {
    if (!dateStr) return 'No due date';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return 'No due date';
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDateTime = (dateStr: string | null) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

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

    const now = new Date();
    const diffMs = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (diffMs < 0) {
        const absDays = Math.abs(diffDays);
        return {
            text:
                absDays === 0
                    ? 'Overdue today'
                    : `Overdue by ${absDays} day${absDays === 1 ? '' : 's'}`,
            color: 'text-red-700 dark:text-red-400',
            isOverdue: true,
            isSubmitted: false,
        };
    }

    if (diffDays === 0) {
        return {
            text: 'Due today',
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    if (diffDays === 1) {
        return {
            text: 'Due tomorrow',
            color: 'text-amber-700 dark:text-amber-400',
            isOverdue: false,
            isSubmitted: false,
            isSoon: true,
        };
    }

    if (diffDays <= 7) {
        return {
            text: `Due in ${diffDays} days`,
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
                a.course?.name.toLowerCase().includes(query),
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

const openModalForAssignment = (assignment?: Assignment) => {
    fileError.value = null;
    form.reset();

    if (assignment) {
        selectedAssignmentId.value = assignment.id;
    } else {
        const firstPending = props.assignments.find(
            (a) => !a.submission?.submitted,
        );
        selectedAssignmentId.value = firstPending
            ? firstPending.id
            : (props.assignments[0]?.id ?? '');
    }

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

// ─── Animations ─────────────────────────────────────────────────────────────
let animationContext: ReturnType<typeof gsap.context> | null = null;

onBeforeUnmount(() => {
    animationContext?.revert();
});

onMounted(() => {
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
                class="student-ui container mx-auto max-w-[1600px] px-3 py-3 perspective-[1000px] sm:px-6 sm:py-6 lg:px-8 lg:py-8"
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
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="h-48 animate-pulse rounded-2xl border border-border/50 bg-card/40"
                    />
                </div>
            </div>
        </template>

        <!-- Real Content -->
        <template v-if="isBooted">
            <div
                ref="pageContainer"
                class="student-ui container mx-auto max-w-[1600px] px-3 py-3 perspective-[1000px] sm:px-6 sm:py-6 lg:px-8 lg:py-8"
            >
                <!-- Page Header -->
                <div
                    class="animate-section mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-start sm:justify-between"
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
                            Track your coursework, view deadlines, and submit
                            your work.
                        </p>
                    </div>

                    <div
                        class="flex w-full flex-wrap items-center gap-2.5 sm:w-auto sm:gap-3"
                    >
                        <Button
                            v-if="pendingCount > 0 || totalCount === 0"
                            data-tour="assignments-submit-btn"
                            @click="openModalForAssignment()"
                            class="dash-btn inline-flex h-12 w-full items-center justify-center gap-2 bg-[#D97757] px-4 text-[14px] font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 active:scale-[0.98] sm:h-11 sm:w-auto sm:justify-start sm:px-5 sm:text-[15px]"
                        >
                            <FileUp class="h-4 w-4 sm:h-4 sm:w-4" />
                            <span>Submit assignment</span>
                        </Button>
                    </div>
                </div>

                <!-- Stat Overview Cards -->
                <div
                    data-tour="assignments-overview"
                    class="animate-section mb-6 grid grid-cols-2 gap-3 sm:mb-8 sm:gap-4 lg:grid-cols-4"
                >
                    <!-- Pending Card -->
                    <Card class="surface-card gap-2 py-3 sm:gap-6 sm:py-5">
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 px-3.5 pb-1 sm:px-5 sm:pb-2"
                        >
                            <CardTitle class="dash-label">Pending</CardTitle>
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400"
                            >
                                <Clock class="h-4 w-4" />
                            </div>
                        </CardHeader>
                        <CardContent class="px-3.5 sm:px-5">
                            <div
                                class="dash-metric text-[26px] leading-none text-foreground sm:text-[32px]"
                            >
                                {{ pendingCount }}
                            </div>
                            <p
                                class="mt-1 text-[12px] sm:text-[13px]"
                                :class="
                                    overdueCount > 0
                                        ? 'font-medium text-red-600 dark:text-red-400'
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

                    <!-- Submitted Card -->
                    <Card class="surface-card gap-2 py-3 sm:gap-6 sm:py-5">
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 px-3.5 pb-1 sm:px-5 sm:pb-2"
                        >
                            <CardTitle class="dash-label">Submitted</CardTitle>
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400"
                            >
                                <FileText class="h-4 w-4" />
                            </div>
                        </CardHeader>
                        <CardContent class="px-3.5 sm:px-5">
                            <div
                                class="dash-metric text-[26px] leading-none text-foreground sm:text-[32px]"
                            >
                                {{ submittedCount }}
                            </div>
                            <p
                                class="mt-1 text-[12px] text-muted-foreground sm:text-[13px]"
                            >
                                Turned in for review
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Graded Card -->
                    <Card class="surface-card gap-2 py-3 sm:gap-6 sm:py-5">
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 px-3.5 pb-1 sm:px-5 sm:pb-2"
                        >
                            <CardTitle class="dash-label">Graded</CardTitle>
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                            >
                                <GraduationCap class="h-4 w-4" />
                            </div>
                        </CardHeader>
                        <CardContent class="px-3.5 sm:px-5">
                            <div
                                class="dash-metric text-[26px] leading-none text-emerald-700 sm:text-[32px] dark:text-emerald-400"
                            >
                                {{ gradedCount }}
                            </div>
                            <p
                                class="mt-1 text-[12px] text-muted-foreground sm:text-[13px]"
                            >
                                Evaluated by teacher
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Progress / Completion Card -->
                    <Card
                        class="surface-card col-span-2 gap-2 py-3 sm:col-span-1 sm:gap-6 sm:py-5"
                    >
                        <CardHeader
                            class="flex flex-row items-center justify-between space-y-0 px-3.5 pb-1 sm:px-5 sm:pb-2"
                        >
                            <CardTitle class="dash-label">Completion</CardTitle>
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <TrendingUp class="h-4 w-4" />
                            </div>
                        </CardHeader>
                        <CardContent class="px-3.5 sm:px-5">
                            <div class="flex items-baseline justify-between">
                                <div
                                    class="dash-metric text-[26px] leading-none text-foreground sm:text-[32px]"
                                >
                                    {{ completionRate }}%
                                </div>
                                <span
                                    class="text-[12px] text-muted-foreground sm:text-[13px]"
                                >
                                    {{ submittedCount }}/{{ totalCount }}
                                </span>
                            </div>
                            <Progress
                                :value="completionRate"
                                class="mt-2 h-1.5 w-full bg-muted"
                                :indicator-class="
                                    completionRate === 100
                                        ? 'bg-emerald-600 dark:bg-emerald-400'
                                        : 'bg-primary'
                                "
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Filters & Search Bar -->
                <div
                    data-tour="assignments-search"
                    class="animate-section mb-6 space-y-3"
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
                                <ArrowUpDown
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

                <!-- Assignments Grid -->
                <div data-tour="assignments-grid" class="animate-section">
                    <div
                        v-if="filteredAssignments.length > 0"
                        class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                    >
                        <Card
                            v-for="assignment in filteredAssignments"
                            :key="assignment.id"
                            class="surface-card group flex flex-col justify-between overflow-hidden p-4 transition-all duration-300 hover:shadow-md sm:p-5.5"
                            :class="getCardBorderClass(assignment)"
                        >
                            <div class="space-y-3 sm:space-y-3.5">
                                <!-- Top Row: Course + Status Badge + Relative Due Text -->
                                <div
                                    class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <!-- Course Pill -->
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[11px] font-semibold text-foreground/80 sm:py-0.5 sm:text-xs"
                                        >
                                            <BookOpen
                                                class="h-3 w-3 text-muted-foreground"
                                            />
                                            {{
                                                assignment.course?.name ||
                                                'General'
                                            }}
                                        </span>

                                        <!-- Status Badge -->
                                        <span
                                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold sm:py-0.5 sm:text-xs"
                                            :class="
                                                getStatusBadge(assignment)
                                                    .classes
                                            "
                                        >
                                            {{
                                                getStatusBadge(assignment).label
                                            }}
                                        </span>
                                    </div>

                                    <!-- Due Date / Relative Info -->
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] font-medium sm:text-xs"
                                        :class="
                                            getRelativeDueInfo(
                                                assignment.due_date,
                                                Boolean(
                                                    assignment.submission
                                                        ?.submitted,
                                                ),
                                                assignment.submission
                                                    ?.submitted_at,
                                            ).color
                                        "
                                    >
                                        <Clock
                                            v-if="
                                                !assignment.submission
                                                    ?.submitted
                                            "
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        <CheckCircle2
                                            v-else
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        <span class="leading-tight">
                                            {{
                                                getRelativeDueInfo(
                                                    assignment.due_date,
                                                    Boolean(
                                                        assignment.submission
                                                            ?.submitted,
                                                    ),
                                                    assignment.submission
                                                        ?.submitted_at,
                                                ).text
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Assignment Title -->
                                <div>
                                    <h2
                                        class="text-[16px] font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-[19px]"
                                    >
                                        {{ assignment.title }}
                                    </h2>

                                    <p
                                        class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground sm:text-sm"
                                        :class="{
                                            'line-clamp-3 sm:line-clamp-3':
                                                assignment.description?.length >
                                                160,
                                        }"
                                    >
                                        {{
                                            assignment.description ||
                                            'No additional instructions provided for this assignment.'
                                        }}
                                    </p>
                                </div>

                                <!-- Submitted File CARD with Preview + Points + Feedback - MOBILE OPTIMIZED -->
                                <div
                                    v-if="assignment.submission?.submitted"
                                    class="space-y-3 rounded-xl border border-border/60 bg-muted/20 p-3 shadow-xs sm:p-3.5"
                                >
                                    <!-- File Preview Row - Stack on mobile, side by side on sm -->
                                    <div class="flex gap-2.5 sm:gap-3">
                                        <!-- Thumbnail Preview - Responsive size -->
                                        <div
                                            class="relative flex h-[64px] w-[64px] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border/50 bg-card shadow-sm sm:h-[80px] sm:w-[80px]"
                                        >
                                            <!-- Image Preview -->
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
                                            <!-- PDF Preview Icon -->
                                            <div
                                                v-else-if="
                                                    isPdfFile(
                                                        assignment.submission
                                                            .file_extension,
                                                    )
                                                "
                                                class="flex h-full w-full flex-col items-center justify-center gap-1 bg-red-500/10 text-red-600 dark:text-red-400"
                                            >
                                                <FileText
                                                    class="h-6 w-6 sm:h-7 sm:w-7"
                                                />
                                            </div>
                                            <!-- Generic Doc Icon -->
                                            <div
                                                v-else
                                                class="flex h-full w-full flex-col items-center justify-center gap-1 bg-primary/10 text-primary"
                                            >
                                                <FileText
                                                    class="h-6 w-6 sm:h-7 sm:w-7"
                                                />
                                            </div>

                                            <!-- Extension Badge -->
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

                                        <!-- File Meta - Flexible -->
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
                                                    getFileTypeLabel(
                                                        assignment.submission
                                                            .file_extension,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-[10px] leading-tight text-muted-foreground sm:text-[11px]"
                                            >
                                                {{
                                                    formatDateTime(
                                                        assignment.submission
                                                            .submitted_at,
                                                    )
                                                }}
                                            </p>

                                            <!-- Points / XP / Grade Pills - Wrap on mobile -->
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
                                                        Number(
                                                            assignment
                                                                .submission
                                                                .points,
                                                        ) > 0
                                                    "
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 sm:px-2.5 sm:text-[11px] dark:text-amber-400"
                                                >
                                                    <TrendingUp
                                                        class="h-3 w-3 shrink-0"
                                                    />
                                                    +{{
                                                        assignment.submission
                                                            .points
                                                    }}
                                                    pts
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

                                    <!-- Image Large Preview on Mobile (if image file) - Tappable to expand -->
                                    <a
                                        v-if="
                                            isImageFile(
                                                assignment.submission
                                                    .file_extension,
                                            ) && assignment.submission.file_url
                                        "
                                        :href="assignment.submission.file_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="group relative block overflow-hidden rounded-xl border border-border/50 bg-card active:scale-[0.99] sm:hidden"
                                    >
                                        <img
                                            :src="
                                                assignment.submission.file_url
                                            "
                                            :alt="
                                                getFileName(
                                                    assignment.submission
                                                        .file_path,
                                                )
                                            "
                                            class="max-h-[240px] w-full object-cover object-center transition-transform group-active:scale-[1.02]"
                                            loading="lazy"
                                        />
                                        <div
                                            class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition-all group-hover:bg-black/10 group-hover:opacity-100"
                                        >
                                            <div
                                                class="rounded-full bg-black/60 p-2.5 text-white backdrop-blur-sm"
                                            >
                                                <Eye class="h-5 w-5" />
                                            </div>
                                        </div>
                                        <div
                                            class="absolute right-2 bottom-2 rounded-full bg-black/60 px-2.5 py-1 text-[10px] font-medium text-white backdrop-blur-sm"
                                        >
                                            Tap to expand
                                        </div>
                                    </a>

                                    <!-- Feedback Card - Mobile optimized -->
                                    <div
                                        v-if="assignment.submission.feedback"
                                        class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.07] p-3 sm:p-3"
                                    >
                                        <div
                                            class="flex items-start gap-2 sm:gap-2.5"
                                        >
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

                                    <!-- Preview + Download Buttons - Grid on mobile, flex on desktop for touch targets -->
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
                                        <!-- Full width indicator on mobile, inline on desktop -->
                                        <span
                                            v-if="
                                                isImageFile(
                                                    assignment.submission
                                                        .file_extension,
                                                )
                                            "
                                            class="col-span-2 inline-flex h-8 items-center justify-center gap-1.5 rounded-xl bg-muted px-2.5 text-[11px] text-muted-foreground sm:col-span-1 sm:h-9"
                                        >
                                            <ImageIcon class="h-3.5 w-3.5" />
                                            Tap image to expand preview
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Action Footer - Stack on mobile for better touch -->
                            <div
                                class="mt-4 flex flex-col gap-3 border-t border-border/50 pt-3.5 sm:mt-5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div
                                    class="text-[11px] text-muted-foreground sm:text-xs"
                                >
                                    <span v-if="assignment.due_date">
                                        Deadline:
                                        {{ formatDueDate(assignment.due_date) }}
                                    </span>
                                    <span v-else> Open assignment </span>
                                </div>

                                <div class="flex w-full sm:w-auto">
                                    <!-- Resubmit button - Full width on mobile for easy tap -->
                                    <Button
                                        v-if="assignment.submission?.submitted"
                                        variant="outline"
                                        size="sm"
                                        class="dash-btn h-11 w-full rounded-xl border-border/60 px-4 text-[13px] font-semibold active:scale-[0.98] sm:h-9 sm:w-auto sm:px-3.5 sm:text-xs"
                                        @click="
                                            openModalForAssignment(assignment)
                                        "
                                    >
                                        <FileUp
                                            class="h-4 w-4 sm:h-3.5 sm:w-3.5"
                                        />
                                        <span>Resubmit</span>
                                    </Button>

                                    <!-- Submit button - Full width on mobile -->
                                    <Button
                                        v-else
                                        variant="default"
                                        size="sm"
                                        class="dash-btn h-11 w-full gap-1.5 rounded-xl bg-[#D97757] px-4 text-[13px] font-semibold text-white shadow-xs hover:bg-[#D97757]/90 active:scale-[0.98] sm:h-9 sm:w-auto sm:px-4 sm:text-xs"
                                        @click="
                                            openModalForAssignment(assignment)
                                        "
                                    >
                                        <FileUp
                                            class="h-4 w-4 sm:h-3.5 sm:w-3.5"
                                        />
                                        <span>Submit</span>
                                    </Button>
                                </div>
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
                    <Card
                        v-else
                        class="surface-card border-dashed py-14 text-center sm:py-20"
                    >
                        <CardContent
                            class="flex flex-col items-center justify-center p-6"
                        >
                            <div
                                class="mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-muted/40 text-muted-foreground"
                            >
                                <BookOpen class="h-8 w-8" />
                            </div>
                            <h3
                                class="text-xl font-semibold tracking-tight text-foreground"
                            >
                                No assignments yet
                            </h3>
                            <p
                                class="mt-2 max-w-md text-sm text-muted-foreground"
                            >
                                Your teachers haven't posted any assignments
                                yet. When coursework is assigned, it will appear
                                here with clear instructions and deadlines.
                            </p>
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
                <!-- Assignment Selector (if multiple pending or changing selection) -->
                <div v-if="props.assignments.length > 1" class="space-y-1.5">
                    <label
                        for="assignment-select"
                        class="text-xs font-semibold text-foreground"
                    >
                        Select assignment
                    </label>
                    <div class="relative">
                        <select
                            id="assignment-select"
                            v-model="selectedAssignmentId"
                            class="h-11 w-full cursor-pointer appearance-none rounded-xl border border-border/60 bg-card px-3.5 text-sm font-medium text-foreground transition-colors outline-none hover:bg-muted/30 focus:border-primary/40 focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="" disabled>
                                Select an assignment to submit...
                            </option>
                            <option
                                v-for="a in props.assignments"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.title }}
                                {{ a.course ? `(${a.course.name})` : '' }}
                                {{
                                    a.submission?.submitted ? '· Submitted' : ''
                                }}
                            </option>
                        </select>
                        <ChevronDown
                            class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground/60"
                        />
                    </div>
                </div>

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
