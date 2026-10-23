<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    BookOpen,
    ChevronRight,
    Clock,
    Download,
    ExternalLink,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    Library,
    Loader2,
    Presentation,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
import MascotEmptyState from '@/components/MascotEmptyState.vue';
import PageSkeleton from '@/components/PageSkeleton.vue';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface Material {
    id: number;
    title: string;
    description: string | null;
    category: Category | null;
    file_name: string | null;
    file_extension: string | null;
    file_size: number | null;
    file_url: string | null;
    cover_image: string | null;
    is_downloadable: boolean;
    view_count: number;
    download_count: number;
    sections: { id: number; name: string }[];
    created_at: string | null;
}

const props = defineProps<{
    materials: Material[];
    categories: Category[];
    filters: { search: string; category: string };
}>();

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);
if (!isLoaderVisible.value) isBooted.value = true;
watch(
    isLoaderVisible,
    (v) => {
        if (!v) isBooted.value = true;
    },
    { immediate: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Library Hub', href: '/library' },
];

const searchQuery = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || 'all');
const previewMaterial = ref<Material | null>(null);
const isPreviewLoading = ref(true);

const filteredMaterials = computed(() => {
    let list = [...props.materials];
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (m) =>
                m.title.toLowerCase().includes(q) ||
                (m.description && m.description.toLowerCase().includes(q)) ||
                (m.file_name && m.file_name.toLowerCase().includes(q)),
        );
    }
    if (selectedCategory.value !== 'all') {
        list = list.filter(
            (m) =>
                m.category?.slug === selectedCategory.value ||
                String(m.category?.id) === selectedCategory.value,
        );
    }
    return list;
});

function formatFileSize(bytes: number | null): string {
    if (!bytes || bytes === 0) return '—';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return '';
    }
}

function previewUrl(material: Material): string {
    return `/library/${material.id}/file`;
}

function downloadUrl(material: Material): string {
    return `/library/${material.id}/file?download=1`;
}

function openPreview(material: Material) {
    previewMaterial.value = material;
    isPreviewLoading.value = true;
    if (typeof document !== 'undefined') {
        document.body.style.overflow = 'hidden';
    }
}

function closePreview() {
    previewMaterial.value = null;
    isPreviewLoading.value = false;
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
}

function onIframeLoad() {
    isPreviewLoading.value = false;
}

function getNormalizedExt(material: Material | null | undefined): string {
    if (!material) return '';
    const ext = material.file_extension || '';
    if (ext) return ext.toLowerCase().replace('.', '');
    const name = material.file_name || '';
    const dotIdx = name.lastIndexOf('.');
    return dotIdx !== -1 ? name.substring(dotIdx + 1).toLowerCase() : 'pdf';
}

function isPdf(material: Material | null | undefined): boolean {
    const ext = getNormalizedExt(material);
    return ext === 'pdf';
}

function isImage(material: Material | null | undefined): boolean {
    const ext = getNormalizedExt(material);
    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext);
}

function isOfficeDoc(material: Material | null | undefined): boolean {
    const ext = getNormalizedExt(material);
    return ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'].includes(ext);
}

function getFileTypeIcon(ext: string | null | undefined) {
    const clean = (ext || '').toLowerCase().replace('.', '');
    if (clean === 'pdf') return FileText;
    if (['doc', 'docx'].includes(clean)) return FileText;
    if (['ppt', 'pptx'].includes(clean)) return Presentation;
    if (['xls', 'xlsx', 'csv'].includes(clean)) return FileSpreadsheet;
    return BookOpen;
}

function getFallbackCoverGradient(ext: string | null | undefined): string {
    const clean = (ext || '').toLowerCase().replace('.', '');
    if (clean === 'pdf') {
        return 'from-rose-500/15 via-primary/10 to-amber-500/10 text-rose-500 dark:from-rose-950/40 dark:via-background dark:to-muted';
    }
    if (['doc', 'docx'].includes(clean)) {
        return 'from-sky-500/15 via-primary/10 to-indigo-500/10 text-sky-500 dark:from-sky-950/40 dark:via-background dark:to-muted';
    }
    if (['ppt', 'pptx'].includes(clean)) {
        return 'from-amber-500/15 via-primary/10 to-orange-500/10 text-amber-500 dark:from-amber-950/40 dark:via-background dark:to-muted';
    }
    if (['xls', 'xlsx', 'csv'].includes(clean)) {
        return 'from-emerald-500/15 via-primary/10 to-teal-500/10 text-emerald-500 dark:from-emerald-950/40 dark:via-background dark:to-muted';
    }
    return 'from-primary/15 via-primary/5 to-muted text-primary';
}

const onEsc = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && previewMaterial.value) {
        closePreview();
    }
};

onMounted(() => {
    window.addEventListener('keydown', onEsc);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onEsc);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Head title="Library Hub" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template v-if="!isBooted">
            <div
                class="mobile-ui-page relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 md:p-10"
            >
                <PageSkeleton
                    :hero="true"
                    :stats="3"
                    variant="minimal"
                    wrapperClass="z-10 mb-4"
                />
                <div
                    class="z-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-64 animate-pulse rounded-xl border border-border/10 bg-card/30"
                    ></div>
                </div>
            </div>
        </template>

        <template v-if="isBooted">
            <div
                class="mobile-ui-page relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 md:p-10"
            >
                <!-- Hero — hidden on mobile, mobile heading lives in catalog -->
                <Motion
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.6, easing: [0.16, 1, 0.3, 1] }"
                    class="relative z-10 hidden overflow-hidden rounded-2xl border border-border/20 bg-gradient-to-br from-primary/[0.06] via-muted/[0.03] to-transparent p-6 md:block md:p-8"
                >
                    <div
                        class="pointer-events-none absolute -top-20 -right-20 h-40 w-40 rounded-full bg-primary/5 blur-[60px]"
                    ></div>
                    <div class="relative flex flex-col gap-3">
                        <FoxCompanion
                            v-if="filteredMaterials.length > 0"
                            class="min-w-0"
                            mascot="library"
                            :size="120"
                            message="Let’s find something useful to explore."
                            label="Show library fox message"
                            compact
                            :initially-open="false"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                        ><Library class="h-5 w-5"
                                    /></span>
                                    <span
                                        class="text-[10px] font-black tracking-[0.3em] text-primary/60 uppercase"
                                        >Free Resources</span
                                    >
                                </div>
                                <h1
                                    class="text-2xl font-black tracking-tight md:text-3xl"
                                >
                                    Library Hub
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
                                >
                                    Free PDF learning materials curated for your
                                    sections.
                                </p>
                            </div>
                        </FoxCompanion>
                        <div v-else>
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                    ><Library class="h-5 w-5"
                                /></span>
                                <span
                                    class="text-[10px] font-black tracking-[0.3em] text-primary/60 uppercase"
                                    >Free Resources</span
                                >
                            </div>
                            <h1
                                class="text-2xl font-black tracking-tight md:text-3xl"
                            >
                                Library Hub
                            </h1>
                            <p
                                class="max-w-2xl text-sm leading-relaxed text-muted-foreground"
                            >
                                Free PDF learning materials curated for your
                                sections.
                            </p>
                        </div>
                        <div
                            class="mt-1 flex flex-wrap gap-2 text-[11px] text-muted-foreground"
                        >
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-border/40 bg-background/60 px-2.5 py-1"
                                ><BookOpen class="h-3 w-3" />
                                {{ materials.length }} materials</span
                            >
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-border/40 bg-background/60 px-2.5 py-1"
                                ><Filter class="h-3 w-3" />
                                {{ categories.length }} categories</span
                            >
                        </div>
                    </div>
                </Motion>

                <!-- Mobile catalog — mirrors Courses mobile -->
                <section
                    class="mobile-course-catalog md:hidden"
                    aria-label="Library catalog"
                >
                    <div class="mobile-course-catalog__heading">
                        <FoxCompanion
                            v-if="filteredMaterials.length > 0"
                            class="min-w-0 flex-1"
                            mascot="library"
                            :size="82"
                            message="Let’s find something useful to explore."
                            label="Show library fox message"
                            compact
                            :initially-open="false"
                        >
                            <div>
                                <span class="mobile-dashboard-kicker"
                                    >Free resources</span
                                >
                                <h1 class="mobile-dashboard-title">
                                    Library Hub
                                </h1>
                            </div>
                        </FoxCompanion>
                        <div v-else>
                            <span class="mobile-dashboard-kicker"
                                >Free resources</span
                            >
                            <h1 class="mobile-dashboard-title">Library Hub</h1>
                        </div>
                        <span class="mobile-course-count"
                            >{{ filteredMaterials.length }} items</span
                        >
                    </div>
                    <label class="mobile-course-search">
                        <Search class="h-4 w-4" />
                        <span class="sr-only">Search materials</span>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search materials"
                        />
                    </label>
                    <div
                        class="mobile-course-filters"
                        role="tablist"
                        aria-label="Categories"
                    >
                        <button
                            type="button"
                            :class="{ 'is-active': selectedCategory === 'all' }"
                            @click="selectedCategory = 'all'"
                        >
                            All
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.slug"
                            type="button"
                            :class="{
                                'is-active': selectedCategory === cat.slug,
                            }"
                            @click="selectedCategory = cat.slug"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                    <div
                        v-if="filteredMaterials.length"
                        class="mobile-course-list"
                    >
                        <button
                            v-for="material in filteredMaterials"
                            :key="material.id"
                            class="mobile-course-row"
                            @click="openPreview(material)"
                        >
                            <span class="mobile-course-cover">
                                <img
                                    v-if="material.cover_image"
                                    :src="material.cover_image"
                                    :alt="material.title"
                                    class="h-full w-full object-cover"
                                    @error="
                                        (e) =>
                                            ((
                                                e.target as HTMLElement
                                            ).style.display = 'none')
                                    "
                                />
                                <component
                                    v-else
                                    :is="
                                        getFileTypeIcon(material.file_extension)
                                    "
                                    class="h-5 w-5"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong class="mobile-course-row__title">{{
                                    material.title
                                }}</strong>
                                <span class="mobile-course-row__meta"
                                    >{{
                                        material.category?.name ||
                                        'Uncategorized'
                                    }}
                                    ·
                                    {{
                                        (
                                            material.file_extension || 'PDF'
                                        ).toUpperCase()
                                    }}
                                    ·
                                    {{
                                        formatFileSize(material.file_size)
                                    }}</span
                                >
                                <span class="mobile-course-row__meta"
                                    >{{
                                        material.sections
                                            .map((s) => s.name)
                                            .join(', ') || 'No section'
                                    }}
                                    <template v-if="!material.is_downloadable">
                                        · View only</template
                                    ></span
                                >
                            </span>
                            <span
                                class="flex shrink-0 items-center gap-1 text-muted-foreground"
                            >
                                <Eye
                                    v-if="!material.is_downloadable"
                                    class="h-4 w-4"
                                />
                                <Download v-else class="h-4 w-4" />
                                <ChevronRight class="h-4 w-4" />
                            </span>
                        </button>
                    </div>
                    <MascotEmptyState
                        v-else
                        class="mx-auto w-full max-w-2xl"
                        :size="120"
                        bare
                        mascot="library"
                        :title="
                            materials.length
                                ? 'No materials found'
                                : 'The library is empty… for now'
                        "
                        :description="
                            materials.length
                                ? 'Try another search or category.'
                                : 'Your teachers haven’t published materials for your section yet.'
                        "
                    />
                </section>

                <!-- Desktop Search + Filters -->
                <Motion
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="{
                        duration: 0.6,
                        delay: 0.1,
                        easing: [0.16, 1, 0.3, 1],
                    }"
                    class="courses-desktop-filter relative z-10 hidden md:block"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="relative max-w-md flex-1">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground/40"
                            />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search titles, descriptions, filenames..."
                                class="w-full rounded-xl border border-border/40 bg-background/60 py-2.5 pr-9 pl-10 text-sm outline-none placeholder:text-muted-foreground/40 focus:border-primary/30 focus:ring-2 focus:ring-primary/10"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded-lg p-0.5 text-muted-foreground/40 hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="cat in [
                                    { id: 0, name: 'All', slug: 'all' },
                                    ...categories,
                                ]"
                                :key="cat.slug"
                                @click="selectedCategory = cat.slug"
                                class="rounded-full border px-3 py-1.5 text-[11px] font-bold transition-all"
                                :class="
                                    selectedCategory === cat.slug
                                        ? 'border-brand bg-brand text-white shadow-sm'
                                        : 'border-border/40 bg-background/60 text-muted-foreground/70 hover:border-primary/30 hover:text-foreground'
                                "
                            >
                                {{ cat.name }}
                            </button>
                        </div>
                    </div>
                </Motion>

                <!-- Desktop Grid -->
                <div
                    v-if="filteredMaterials.length > 0"
                    class="courses-desktop-grid relative z-10 hidden grid-cols-2 gap-4 md:grid md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
                >
                    <Motion
                        v-for="(material, idx) in filteredMaterials"
                        :key="material.id"
                        :initial="{ opacity: 0, y: 20 }"
                        :in-view="{ opacity: 1, y: 0 }"
                        :in-view-options="{ once: true, margin: '-30px' }"
                        :transition="{
                            duration: 0.45,
                            delay: Math.min(idx * 0.03, 0.3),
                            easing: [0.16, 1, 0.3, 1],
                        }"
                    >
                        <div
                            class="group/card flex h-full flex-col overflow-hidden rounded-xl border border-border/50 bg-card transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5"
                        >
                            <!-- Cover -->
                            <div
                                class="relative aspect-[16/10] w-full cursor-pointer overflow-hidden bg-muted/30 select-none"
                                @click="openPreview(material)"
                                :title="'Preview ' + material.title"
                            >
                                <template v-if="material.cover_image">
                                    <!-- Ambient blurred glow background -->
                                    <img
                                        :src="material.cover_image"
                                        alt=""
                                        aria-hidden="true"
                                        class="pointer-events-none absolute inset-0 h-full w-full scale-110 object-cover opacity-25 blur-md select-none dark:opacity-20"
                                    />
                                    <!-- Sharp full-fit cover image -->
                                    <img
                                        :src="material.cover_image"
                                        :alt="material.title"
                                        class="relative z-10 h-full w-full object-contain p-1 transition-transform duration-500 group-hover/card:scale-[1.03]"
                                        loading="lazy"
                                        @error="
                                            (e) =>
                                                ((
                                                    e.target as HTMLElement
                                                ).style.display = 'none')
                                        "
                                    />
                                    <!-- Subtle vignette gradient -->
                                    <div
                                        class="pointer-events-none absolute inset-0 z-10 bg-gradient-to-t from-black/50 via-transparent to-black/30"
                                    ></div>
                                </template>
                                <!-- Fallback cover when no custom cover image is uploaded -->
                                <template v-else>
                                    <div
                                        class="relative flex h-full w-full flex-col items-center justify-center bg-gradient-to-br p-3"
                                        :class="
                                            getFallbackCoverGradient(
                                                material.file_extension,
                                            )
                                        "
                                    >
                                        <div
                                            class="pointer-events-none absolute -right-2 -bottom-2 opacity-10"
                                        >
                                            <BookOpen class="h-20 w-20" />
                                        </div>
                                        <div
                                            class="relative z-10 flex flex-col items-center justify-center rounded-xl border border-white/20 bg-background/80 px-3 py-2 shadow-xs backdrop-blur-sm transition-transform duration-300 group-hover/card:scale-105"
                                        >
                                            <component
                                                :is="
                                                    getFileTypeIcon(
                                                        material.file_extension,
                                                    )
                                                "
                                                class="h-6 w-6 text-primary"
                                            />
                                            <span
                                                class="mt-0.5 text-[9px] font-black tracking-wider text-foreground uppercase"
                                            >
                                                {{
                                                    (
                                                        material.file_extension ||
                                                        'PDF'
                                                    ).toUpperCase()
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Category badge (top-left) -->
                                <div
                                    class="absolute top-2 left-2 z-20 flex max-w-[calc(100%-4.5rem)] gap-1"
                                >
                                    <span
                                        v-if="material.category"
                                        class="truncate rounded-md bg-background/85 px-1.5 py-0.5 text-[9px] font-bold tracking-tight text-foreground shadow-xs backdrop-blur-md"
                                        :title="material.category.name"
                                    >
                                        {{ material.category.name }}
                                    </span>
                                </div>

                                <!-- File Type badge (top-right) -->
                                <div class="absolute top-2 right-2 z-20">
                                    <span
                                        class="rounded-md bg-primary/90 px-1.5 py-0.5 text-[9px] font-black tracking-wider text-primary-foreground uppercase shadow-xs backdrop-blur-md"
                                    >
                                        {{
                                            (
                                                material.file_extension || 'PDF'
                                            ).toUpperCase()
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex flex-1 flex-col p-3 sm:p-3.5">
                                <h3
                                    class="line-clamp-2 cursor-pointer text-xs leading-snug font-bold tracking-tight text-foreground transition-colors group-hover/card:text-primary sm:text-sm"
                                    @click="openPreview(material)"
                                    :title="material.title"
                                >
                                    {{ material.title }}
                                </h3>

                                <p
                                    v-if="material.description"
                                    class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted-foreground/75"
                                >
                                    {{ material.description }}
                                </p>

                                <!-- Section Badges -->
                                <div
                                    v-if="material.sections?.length"
                                    class="mt-2 flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="sec in material.sections"
                                        :key="sec.id"
                                        class="truncate rounded-md border border-border/40 bg-muted/40 px-1.5 py-0.5 text-[9px] font-semibold text-muted-foreground"
                                    >
                                        <Users
                                            class="mr-0.5 inline h-2.5 w-2.5"
                                        />{{ sec.name }}
                                    </span>
                                </div>

                                <!-- File Name & Size -->
                                <div
                                    class="mt-2 flex items-center gap-1.5 text-[10px] text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex min-w-0 items-center gap-1 truncate"
                                    >
                                        <FileText class="h-3 w-3 shrink-0" />
                                        <span class="truncate">{{
                                            material.file_name || 'document'
                                        }}</span>
                                    </span>
                                    <span class="shrink-0 opacity-40">·</span>
                                    <span class="shrink-0 font-medium">{{
                                        formatFileSize(material.file_size)
                                    }}</span>
                                </div>

                                <!-- Footer: Date & Counters -->
                                <div
                                    class="mt-2 flex items-center justify-between text-[10px] text-muted-foreground/60"
                                >
                                    <span
                                        v-if="material.created_at"
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Clock class="h-2.5 w-2.5" />
                                        {{ formatDate(material.created_at) }}
                                    </span>
                                    <span
                                        class="ml-auto inline-flex items-center gap-1"
                                    >
                                        <span
                                            class="inline-flex items-center gap-0.5"
                                        >
                                            <Eye class="h-2.5 w-2.5" />
                                            {{ material.view_count }}
                                        </span>
                                        <span class="opacity-40">·</span>
                                        <span
                                            class="inline-flex items-center gap-0.5"
                                        >
                                            <Download class="h-2.5 w-2.5" />
                                            {{ material.download_count }}
                                        </span>
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div
                                    class="mt-2.5 flex items-center gap-1.5 border-t border-border/40 pt-2"
                                >
                                    <button
                                        @click="openPreview(material)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-primary px-2 py-1.5 text-xs font-bold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                                    >
                                        <Eye class="h-3.5 w-3.5" /> Preview
                                    </button>
                                    <a
                                        v-if="material.is_downloadable"
                                        :href="downloadUrl(material)"
                                        class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-border/50 bg-background px-2 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                        Download
                                    </a>
                                    <span
                                        v-else
                                        class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-dashed border-border/40 bg-muted/30 px-2 py-1.5 text-[11px] font-medium text-muted-foreground"
                                        title="Download disabled by teacher"
                                    >
                                        <Download
                                            class="h-3.5 w-3.5 opacity-40"
                                        />
                                        View only
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Motion>
                </div>

                <!-- Desktop Empty filtered -->
                <Motion
                    v-else-if="materials.length > 0"
                    :initial="{ opacity: 0 }"
                    :animate="{ opacity: 1 }"
                    class="courses-desktop-grid relative z-10 hidden w-full flex-col items-center justify-center self-stretch py-16 md:flex"
                >
                    <MascotEmptyState
                        class="w-full"
                        mascot="library"
                        :size="110"
                        bare
                        title="No matches"
                        description="Try another keyword or category."
                    >
                        <button
                            @click="
                                searchQuery = '';
                                selectedCategory = 'all';
                            "
                            class="rounded-xl border border-border/40 px-4 py-2 text-xs font-bold hover:border-primary/30 hover:text-primary"
                        >
                            Clear filters
                        </button>
                    </MascotEmptyState>
                </Motion>

                <!-- Desktop Empty all -->
                <Motion
                    v-else
                    :initial="{ opacity: 0 }"
                    :animate="{ opacity: 1 }"
                    class="courses-desktop-grid relative z-10 hidden w-full flex-col items-center justify-center self-stretch md:flex"
                >
                    <MascotEmptyState
                        class="w-full"
                        mascot="library"
                        title="The library is empty… for now"
                        description="Your teachers haven’t published any learning materials for your section yet. Grab a book and check back soon — new readings will appear here."
                    />
                </Motion>

                <!-- Preview Modal -->
                <Teleport to="body">
                    <div
                        v-if="previewMaterial"
                        class="fixed inset-0 z-50 flex flex-col bg-background/95 backdrop-blur-md"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="'Preview ' + previewMaterial.title"
                    >
                        <!-- Modal Top Bar -->
                        <div
                            class="flex items-center justify-between border-b border-border/40 bg-card/50 px-4 py-2.5 sm:px-6"
                        >
                            <div class="min-w-0 flex-1 pr-4">
                                <div class="flex items-center gap-2">
                                    <h2
                                        class="truncate text-sm font-bold text-foreground sm:text-base"
                                    >
                                        {{ previewMaterial.title }}
                                    </h2>
                                    <span
                                        class="hidden rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary uppercase sm:inline-flex"
                                    >
                                        {{
                                            (
                                                previewMaterial.file_extension ||
                                                'PDF'
                                            ).toUpperCase()
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        previewMaterial.file_name || 'document'
                                    }}
                                    ·
                                    {{
                                        formatFileSize(
                                            previewMaterial.file_size,
                                        )
                                    }}
                                    <template v-if="previewMaterial.category">
                                        · {{ previewMaterial.category.name }}
                                    </template>
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <!-- Open in new tab link -->
                                <a
                                    :href="previewUrl(previewMaterial)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-lg border border-border/50 bg-background px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                                    title="Open in a new tab"
                                >
                                    <ExternalLink class="h-3.5 w-3.5" />
                                    <span class="hidden sm:inline"
                                        >New tab</span
                                    >
                                </a>

                                <!-- Download button -->
                                <a
                                    v-if="previewMaterial.is_downloadable"
                                    :href="downloadUrl(previewMaterial)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    <span>Download</span>
                                </a>

                                <!-- Close button -->
                                <button
                                    @click="closePreview"
                                    class="cursor-pointer rounded-lg border border-border/40 bg-background p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    title="Close (Esc)"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Modal Content Area -->
                        <div
                            class="relative flex-1 overflow-hidden bg-muted/20"
                        >
                            <!-- Loading indicator for iframe -->
                            <div
                                v-if="
                                    isPreviewLoading && isPdf(previewMaterial)
                                "
                                class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-background/80 backdrop-blur-xs"
                            >
                                <Loader2
                                    class="h-8 w-8 animate-spin text-primary"
                                />
                                <p
                                    class="text-xs font-semibold text-muted-foreground"
                                >
                                    Loading preview…
                                </p>
                            </div>

                            <!-- PDF Viewer (iframe) -->
                            <template v-if="isPdf(previewMaterial)">
                                <div class="flex h-full w-full flex-col">
                                    <iframe
                                        :src="previewUrl(previewMaterial)"
                                        @load="onIframeLoad"
                                        class="h-full w-full border-0 bg-white dark:bg-zinc-900"
                                        title="Document preview"
                                    ></iframe>
                                </div>
                            </template>

                            <!-- Image Viewer -->
                            <template v-else-if="isImage(previewMaterial)">
                                <div
                                    class="flex h-full w-full items-center justify-center overflow-auto p-4"
                                >
                                    <img
                                        :src="previewUrl(previewMaterial)"
                                        :alt="previewMaterial.title"
                                        class="max-h-full max-w-full rounded-lg object-contain shadow-lg"
                                    />
                                </div>
                            </template>

                            <!-- Office Document Notice (Word, PowerPoint, Excel) -->
                            <template v-else-if="isOfficeDoc(previewMaterial)">
                                <div
                                    class="flex h-full w-full flex-col items-center justify-center p-6 text-center"
                                >
                                    <div
                                        class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-xs"
                                    >
                                        <component
                                            :is="
                                                getFileTypeIcon(
                                                    previewMaterial.file_extension,
                                                )
                                            "
                                            class="h-8 w-8"
                                        />
                                    </div>
                                    <h3
                                        class="mt-4 text-base font-bold text-foreground"
                                    >
                                        {{ previewMaterial.file_name }}
                                    </h3>
                                    <p
                                        class="mt-1.5 max-w-md text-xs leading-relaxed text-muted-foreground"
                                    >
                                        This is an Office document ({{
                                            (
                                                previewMaterial.file_extension ||
                                                ''
                                            ).toUpperCase()
                                        }}). Browsers cannot render Office files
                                        directly in a web preview. Download the
                                        file to view it in Microsoft Office,
                                        Google Docs, or your preferred software.
                                    </p>
                                    <div class="mt-5 flex items-center gap-3">
                                        <a
                                            v-if="
                                                previewMaterial.is_downloadable
                                            "
                                            :href="downloadUrl(previewMaterial)"
                                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                        >
                                            <Download class="h-4 w-4" />
                                            Download document ({{
                                                formatFileSize(
                                                    previewMaterial.file_size,
                                                )
                                            }})
                                        </a>
                                        <span
                                            v-else
                                            class="rounded-xl border border-dashed border-border px-4 py-2 text-xs font-medium text-muted-foreground"
                                        >
                                            Download disabled by instructor
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <!-- Generic fallback viewer for other formats -->
                            <template v-else>
                                <div class="flex h-full w-full flex-col">
                                    <iframe
                                        :src="previewUrl(previewMaterial)"
                                        @load="onIframeLoad"
                                        class="h-full w-full border-0"
                                        title="Document preview"
                                    ></iframe>
                                </div>
                            </template>
                        </div>
                    </div>
                </Teleport>
            </div>
        </template>
    </AppLayout>
</template>
