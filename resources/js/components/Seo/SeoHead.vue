<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    normalizeJsonLd,
    resolveCanonicalUrl,
    resolveOgImage,
    useSeoConfig,
} from '@/lib/seo';
import type { JsonLdObject } from '@/lib/seo';

const props = withDefaults(
    defineProps<{
        noindex?: boolean;
        /** Canonical overrides the auto-derived URL (rarely needed). */
        canonical?: string;
        description?: string;
        ogImage?: string;
        type?: string;
        jsonld?: JsonLdObject | JsonLdObject[];
    }>(),
    {
        noindex: false,
        canonical: '',
        description: '',
        ogImage: '',
        type: 'website',
        jsonld: () => [],
    },
);

const seo = useSeoConfig();

const metaDescription = computed(
    () => props.description || seo.description || '',
);
const robots = computed(() =>
    props.noindex ? 'noindex, nofollow' : 'index, follow',
);
const canonical = computed(
    () =>
        props.canonical ||
        resolveCanonicalUrl(String(usePage().url ?? '/'), seo),
);
const image = computed(() => resolveOgImage(props.ogImage, seo));
const jsonldNodes = computed(() => normalizeJsonLd(props.jsonld));
const lang = computed(() => seo.locale ?? 'en_US');
</script>

<template>
    <Head>
        <link rel="canonical" :href="canonical" />
        <meta name="robots" :content="robots" />
        <meta name="description" :content="metaDescription" />

        <meta property="og:type" :content="type" />
        <meta property="og:site_name" :content="seo.siteName || 'LSI'" />
        <meta
            property="og:title"
            :content="seo.tagline || seo.siteName || ''"
        />
        <meta property="og:description" :content="metaDescription" />
        <meta property="og:url" :content="canonical" />
        <meta v-if="image" property="og:image" :content="image" />
        <meta property="og:locale" :content="lang" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta
            name="twitter:title"
            :content="seo.tagline || seo.siteName || ''"
        />
        <meta name="twitter:description" :content="metaDescription" />
        <meta v-if="image" name="twitter:image" :content="image" />

        <script
            v-for="block in jsonldNodes"
            :key="JSON.stringify(block)"
            type="application/ld+json"
        >
            {{ JSON.stringify(block) }}
        </script>
    </Head>
</template>
