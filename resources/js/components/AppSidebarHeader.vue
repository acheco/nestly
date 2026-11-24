<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <!--
        Spec 001, R9: below md the sidebar is never shown, so its trigger hides; the header stays
        as a second row under AppMobileHeader only with two or more breadcrumbs, since a single
        one repeats the page title and the active tab.
    -->
    <header
        data-test="breadcrumb-header"
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        :class="{ 'max-md:hidden': breadcrumbs.length < 2 }"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1 max-md:hidden" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
