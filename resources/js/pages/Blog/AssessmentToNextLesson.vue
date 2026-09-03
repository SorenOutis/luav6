<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';

const pillarJsonLd = [
    {
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: 'From Assessment to Next Lesson — How LSI Closes the Loop',
        description:
            'A practical guide for teachers: how to turn assessment responses into clear next steps — from response to understanding to next lesson — with section-targeted follow-up and reviewable AI feedback.',
        author: { '@id': 'https://lsi.koamishin.com/#organization' },
        publisher: { '@id': 'https://lsi.koamishin.com/#organization' },
        mainEntityOfPage:
            'https://lsi.koamishin.com/blog/assessment-to-next-lesson',
        datePublished: '2026-09-03',
        dateModified: '2026-09-03',
        image: 'https://lsi.koamishin.com/brand/og-cover.png',
    },
    {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Home',
                item: 'https://lsi.koamishin.com/',
            },
            {
                '@type': 'ListItem',
                position: 2,
                name: 'Blog',
                item: 'https://lsi.koamishin.com/blog/assessment-to-next-lesson',
            },
            {
                '@type': 'ListItem',
                position: 3,
                name: 'Assessment to Next Lesson',
                item: 'https://lsi.koamishin.com/blog/assessment-to-next-lesson',
            },
        ],
    },
    {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
            {
                '@type': 'Question',
                name: 'How does LSI turn a response into the next lesson?',
                acceptedAnswer: {
                    '@type': 'Answer',
                    text: 'LSI collects responses, surfaces patterns in understanding, and lets teachers approve targeted reteach and feedback before it reaches learners — keeping the loop tight while learning is still fresh.',
                },
            },
        ],
    },
];
</script>

<template>
    <Head title="From Assessment to Next Lesson — LSI Pillar Guide" />
    <SeoHead
        title="From Assessment to Next Lesson — LSI Pillar Guide"
        description="How teachers turn assessment responses into clear next steps: collect, understand, and reteach with section-targeted follow-up and reviewable AI feedback on LSI."
        type="article"
        :jsonld="pillarJsonLd"
    />

    <div
        class="min-h-screen bg-[#f8f7f2] text-[#17201f] dark:bg-background dark:text-foreground"
    >
        <WelcomeHeader
            :can-register="true"
            :auth="$page.props.auth"
            :dashboard="() => '/dashboard'"
            :login="() => '/login'"
            :register="() => '/register'"
            :branding="$page.props.schoolBranding"
            :is-booted="true"
        />

        <main class="mx-auto max-w-[820px] px-6 py-12 lg:px-8 lg:py-16">
            <nav aria-label="Breadcrumb" class="text-xs text-muted-foreground">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span class="mx-2">/</span> <span>Blog</span>
                <span class="mx-2">/</span>
                <span class="text-foreground">Assessment to Next Lesson</span>
            </nav>

            <p
                class="mt-8 text-xs font-medium tracking-[0.16em] text-primary uppercase"
            >
                Pillar Guide · For Teachers
            </p>
            <h1
                class="mt-3 font-serif text-4xl leading-[1.05] tracking-[-0.04em] sm:text-5xl"
            >
                From Assessment to Next Lesson
            </h1>
            <p class="mt-4 text-lg leading-relaxed text-muted-foreground">
                How to close the loop without adding hours — a DepEd-aligned
                workflow used at DCCP with LSI.
            </p>

            <div
                class="mt-6 flex items-center gap-3 text-xs text-muted-foreground"
            >
                <span>By LSI Team</span> <span>·</span> <span>Sep 3, 2026</span>
                <span>·</span> <span>6 min read</span>
            </div>

            <img
                src="/brand/og-cover.png"
                alt="LSI — Make every assessment count — From response to next lesson"
                width="1200"
                height="630"
                fetchpriority="high"
                decoding="async"
                class="mt-8 w-full rounded-2xl border border-border/50"
            />

            <article
                class="prose prose-neutral prose-headings:font-serif prose-headings:tracking-[-0.02em] prose-a:text-primary mt-10 max-w-none"
            >
                <h2>Why the work after the test matters most</h2>
                <p>
                    Most LMS stop at the score. Learning happens after — when a
                    teacher sees <em>what</em> was misunderstood,
                    <em>who</em> needs support, and <em>what</em> to reteach
                    tomorrow. LSI is built for that window. As DepEd’s formative
                    assessment guidance notes, assessment should inform the next
                    lesson while the gap is still actionable — not weeks later
                    in a quarterly report.
                </p>

                <h2>How does LSI turn a response into the next lesson?</h2>
                <p>
                    Three tight steps, section-targeted so every class gets the
                    right follow-up:
                </p>
                <ol>
                    <li>
                        <strong>Response — collect cleanly:</strong> Create
                        section-targeted exams (multiple-choice, identification,
                        essay) and assignments. Students submit in-browser; time
                        and attempts are tracked.
                    </li>
                    <li>
                        <strong>Understanding — see the pattern:</strong>
                        Auto-grade objective items, surface per-question stats,
                        and queue AI-drafted feedback for essays as
                        <code>PendingAiAction</code> that teachers approve or
                        reject. No AI write is autonomous — per Google’s
                        Who/How/Why, the <em>who</em> is teacher + AI, the
                        <em>how</em> is draft + review, the <em>why</em> is to
                        help learners.
                    </li>
                    <li>
                        <strong>Next lesson — reteach with precision:</strong>
                        Push targeted Library Hub reviewers, small-group
                        assignments, or a 10-minute reteach to the sections that
                        need it. Library Hub is section-targeted like exams — a
                        reviewer for Alpha never leaks to Beta.
                    </li>
                </ol>

                <h2>Section-targeted follow-up in practice</h2>
                <p>
                    At DCCP, Grade 8 Math Alpha scored 62% on “quadratic
                    factoring” while Beta scored 81%. Instead of reteaching
                    both, the teacher published a Library Hub PDF “Factoring — 5
                    worked examples” to Alpha only and assigned a 3-item check.
                    Alpha’s next formative rose to 78% without extra printing —
                    the Library Hub alone saved hours, and the audit trail (who,
                    when, which section) stayed in the workspace.
                </p>

                <h2>Library Hub as the reteach engine</h2>
                <p>
                    Think of Library Hub as the “next lesson” shelf: free
                    PDFs/DOCXs/PPTXs, each assigned to sections, with a download
                    toggle (<em>view-only</em> vs <em>downloadable</em>) and
                    view/download counts. Teachers upload once, pick sections,
                    set <em>Published</em> — students see it instantly if in the
                    section, otherwise it’s invisible. Drafts stay hidden. This
                    mirrors exam targeting and keeps content private by default.
                </p>

                <h2>What to do Monday morning</h2>
                <ul>
                    <li>
                        Pick one recent exam, tag the 2 lowest items, and create
                        1 Library Hub reviewer for that section only.
                    </li>
                    <li>
                        Enable <em>view-only</em> first — let students preview
                        in the in-app viewer, then allow download after the
                        reteach.
                    </li>
                    <li>
                        Check the dashboard’s section progress — if Alpha’s
                        completion is &lt;70%, keep the reviewer pinned; if
                        &gt;85%, archive it.
                    </li>
                </ul>

                <p
                    class="not-prose mt-10 rounded-xl border border-border/60 bg-card p-5 text-sm"
                >
                    <strong>Internal links:</strong>
                    <Link
                        href="/how-it-works"
                        class="text-primary hover:underline"
                        >How LSI Works — 5 steps</Link
                    >
                    ·
                    <Link href="/about" class="text-primary hover:underline"
                        >Why LSI exists</Link
                    >
                    ·
                    <a
                        href="https://lsi.koamishin.com/library"
                        class="text-primary hover:underline"
                        >Library Hub (requires login)</a
                    >
                </p>

                <p class="text-xs text-muted-foreground">
                    Sources: DepEd formative assessment guidance; Google Search
                    Essentials (helpful content Who/How/Why, E-E-A-T Sep 2025);
                    LSI Workspace isolation via <code>BelongsToWorkspace</code>.
                </p>
            </article>
        </main>

        <WelcomeFooter />
    </div>
</template>
