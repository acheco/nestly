<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppMobileHeader from '@/components/AppMobileHeader.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BottomNav from '@/components/BottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <!--
        Spec 001, R9: --bottom-nav-height is the bar's height, defined only here. Below md the
        content reserves it plus the safe area, so the bar never covers the end of the page.
    -->
    <AppShell variant="sidebar" class="[--bottom-nav-height:4rem]">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-w-0 overflow-x-clip max-md:pb-[calc(var(--bottom-nav-height)+env(safe-area-inset-bottom))]"
        >
            <AppMobileHeader />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <BottomNav />
        <Toaster />
    </AppShell>
</template>
