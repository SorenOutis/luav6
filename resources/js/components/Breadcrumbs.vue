<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb class="max-w-[150px] sm:max-w-none">
        <BreadcrumbList class="flex-nowrap">
            <!-- Mobile: only show the last element or limited elements -->
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <template v-if="breadcrumbs.length > 2">
                    <!-- On mobile, hide middle items -->
                    <template v-if="index === 0 || index === breadcrumbs.length - 1">
                        <BreadcrumbItem class="shrink-0">
                            <template v-if="index === breadcrumbs.length - 1">
                                <BreadcrumbPage class="truncate max-w-[80px] sm:max-w-none">{{ item.title }}</BreadcrumbPage>
                            </template>
                            <template v-else>
                                <BreadcrumbLink as-child>
                                    <Link :href="item.href" class="truncate max-w-[50px] sm:max-w-none">{{ item.title }}</Link>
                                </BreadcrumbLink>
                            </template>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index === 0" class="sm:flex" />
                        <BreadcrumbSeparator v-if="index === 0" class="flex sm:hidden">
                            <span class="opacity-50">...</span>
                        </BreadcrumbSeparator>
                    </template>
                    <!-- On desktop, show middle items -->
                    <template v-else>
                        <BreadcrumbItem class="hidden sm:flex">
                            <BreadcrumbLink as-child>
                                <Link :href="item.href">{{ item.title }}</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator class="hidden sm:flex" />
                    </template>
                </template>
                <template v-else>
                    <!-- Normal view if 2 or fewer items -->
                    <BreadcrumbItem class="shrink-0">
                        <template v-if="index === breadcrumbs.length - 1">
                            <BreadcrumbPage class="truncate max-w-[120px] sm:max-w-none">{{ item.title }}</BreadcrumbPage>
                        </template>
                        <template v-else>
                            <BreadcrumbLink as-child>
                                <Link :href="item.href" class="truncate max-w-[60px] sm:max-w-none">{{ item.title }}</Link>
                            </BreadcrumbLink>
                        </template>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                </template>
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
