<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    BookOpen,
    ChevronRight,
    Clock,
    Download,
    Eye,
    FileText,
    Filter,
    Library,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
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

function previewUrl(material: Material): string {
    return `/library/${material.id}/file`;
}

function downloadUrl(material: Material): string {
    return `/library/${material.id}/file?download=1`;
}

function openPreview(material: Material) {
    previewMaterial.value = material;
}

function closePreview() {
    previewMaterial.value = null;
}

onMounted(() => {
    const onEsc = (e: KeyboardEvent) => {
        if (e.key === 'Escape') closePreview();
    };
    window.addEventListener('keydown', onEsc);
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
                            sections. Preview inline — download only when your
                            teacher allows it.
                        </p>
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
                        <div>
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
                                />
                                <BookOpen v-else class="h-5 w-5" />
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
                    <div v-else class="mobile-course-empty">
                        <BookOpen class="h-5 w-5" />
                        <strong>No materials found</strong>
                        <span>Try another search or category.</span>
                    </div>
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
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
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
                    class="courses-desktop-grid relative z-10 hidden grid-cols-1 gap-6 md:grid md:grid-cols-2 lg:grid-cols-3"
                >
                    <Motion
                        v-for="(material, idx) in filteredMaterials"
                        :key="material.id"
                        :initial="{ opacity: 0, y: 30 }"
                        :in-view="{ opacity: 1, y: 0 }"
                        :in-view-options="{ once: true, margin: '-50px' }"
                        :transition="{
                            duration: 0.6,
                            delay: idx * 0.05,
                            easing: [0.16, 1, 0.3, 1],
                        }"
                    >
                        <div
                            class="group/card flex h-full flex-col overflow-hidden rounded-2xl border border-border/40 bg-card transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/5"
                        >
                            <!-- Cover -->
                            <div
                                class="relative h-36 overflow-hidden bg-gradient-to-br from-primary/15 via-primary/5 to-muted"
                            >
                                <img
                                    v-if="material.cover_image"
                                    :src="material.cover_image"
                                    :alt="material.title"
                                    class="h-full w-full object-cover transition duration-700 group-hover/card:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex h-full items-center justify-center"
                                >
                                    <BookOpen
                                        class="h-10 w-10 text-primary/20"
                                    />
                                </div>
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"
                                ></div>
                                <div class="absolute top-3 left-3 flex gap-2">
                                    <span
                                        v-if="material.category"
                                        class="rounded-full bg-background/80 px-2.5 py-1 text-[10px] font-black tracking-wide backdrop-blur"
                                        >{{ material.category.name }}</span
                                    >
                                    <span
                                        class="rounded-full bg-primary px-2.5 py-1 text-[10px] font-black tracking-wide text-primary-foreground"
                                        >{{
                                            (
                                                material.file_extension || 'PDF'
                                            ).toUpperCase()
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="absolute right-3 bottom-3 flex items-center gap-1 rounded-full bg-black/60 px-2 py-1 text-[10px] font-semibold text-white backdrop-blur"
                                >
                                    <Eye class="h-3 w-3" />
                                    {{ material.view_count }}
                                    <span class="mx-1 opacity-40">·</span>
                                    <Download class="h-3 w-3" />
                                    {{ material.download_count }}
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="flex flex-1 flex-col p-5">
                                <h3
                                    class="line-clamp-2 text-[15px] leading-snug font-bold tracking-tight transition-colors group-hover/card:text-primary"
                                >
                                    {{ material.title }}
                                </h3>
                                <p
                                    v-if="material.description"
                                    class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground/70"
                                >
                                    {{ material.description }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="sec in material.sections"
                                        :key="sec.id"
                                        class="rounded-full border border-border/40 bg-muted/40 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                        ><Users class="mr-1 inline h-3 w-3" />{{
                                            sec.name
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground"
                                >
                                    <span class="inline-flex items-center gap-1"
                                        ><FileText class="h-3.5 w-3.5" />
                                        {{
                                            material.file_name || 'document'
                                        }}</span
                                    >
                                    <span>·</span>
                                    <span>{{
                                        formatFileSize(material.file_size)
                                    }}</span>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <button
                                        @click="openPreview(material)"
                                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground transition hover:bg-primary/90"
                                    >
                                        <Eye class="h-4 w-4" /> Preview
                                    </button>
                                    <a
                                        v-if="material.is_downloadable"
                                        :href="downloadUrl(material)"
                                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-border/40 bg-background px-3 py-2 text-xs font-bold hover:bg-muted"
                                    >
                                        <Download class="h-4 w-4" /> Download
                                    </a>
                                    <span
                                        v-else
                                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-dashed border-border/40 bg-muted/30 px-3 py-2 text-xs font-semibold text-muted-foreground"
                                        title="Download disabled by teacher"
                                    >
                                        <Download class="h-4 w-4 opacity-40" />
                                        View only
                                    </span>
                                </div>
                                <div
                                    v-if="material.created_at"
                                    class="mt-3 flex items-center gap-1 text-[10px] text-muted-foreground/50"
                                >
                                    <Clock class="h-3 w-3" />
                                    {{
                                        new Date(
                                            material.created_at,
                                        ).toLocaleDateString()
                                    }}
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
                    class="courses-desktop-grid relative z-10 hidden flex-col items-center justify-center py-16 md:flex"
                >
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-border/40 bg-muted/10"
                    >
                        <Search class="h-6 w-6 text-muted-foreground/30" />
                    </div>
                    <h3 class="text-lg font-bold text-muted-foreground/70">
                        No matches
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground/50">
                        Try another keyword or category.
                    </p>
                    <button
                        @click="
                            searchQuery = '';
                            selectedCategory = 'all';
                        "
                        class="mt-4 rounded-xl border border-border/40 px-4 py-2 text-xs font-bold hover:border-primary/30 hover:text-primary"
                    >
                        Clear filters
                    </button>
                </Motion>

                <!-- Desktop Empty all -->
                <Motion
                    v-else
                    :initial="{ opacity: 0 }"
                    :animate="{ opacity: 1 }"
                    class="courses-desktop-grid relative z-10 hidden flex-col items-center justify-center py-20 md:flex"
                >
                    <div
                        class="mb-5 flex h-20 w-20 items-center justify-center rounded-3xl border border-dashed border-border/40 bg-muted/10"
                    >
                        <Library class="h-8 w-8 text-muted-foreground/30" />
                    </div>
                    <h3 class="text-lg font-bold text-muted-foreground/70">
                        No materials yet
                    </h3>
                    <p
                        class="mt-1 max-w-md text-center text-sm text-muted-foreground/50"
                    >
                        Your teachers haven’t published any learning materials
                        for your section. Check back later.
                    </p>
                </Motion>

                <!-- Preview Modal -->
                <div
                    v-if="previewMaterial"
                    class="fixed inset-0 z-50 flex flex-col bg-background"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/40 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-sm font-bold">
                                {{ previewMaterial.title }}
                            </h2>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ previewMaterial.file_name }} ·
                                {{
                                    (
                                        previewMaterial.file_extension || ''
                                    ).toUpperCase()
                                }}
                            </p>
                        </div>
                        <div class="ml-3 flex items-center gap-2">
                            <a
                                v-if="previewMaterial.is_downloadable"
                                :href="downloadUrl(previewMaterial)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground"
                                ><Download class="h-4 w-4" /> Download</a
                            >
                            <button
                                @click="closePreview"
                                class="rounded-xl border border-border/40 p-2 hover:bg-muted"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 bg-muted/20">
                        <iframe
                            :src="previewUrl(previewMaterial)"
                            class="h-full w-full border-0"
                            title="Preview"
                        ></iframe>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
