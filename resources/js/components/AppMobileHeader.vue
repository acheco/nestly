<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import { dashboard } from '@/routes';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
</script>

<template>
    <!-- Spec 001, R9: below md the header carries the brand and the team switcher. -->
    <header
        data-test="mobile-header"
        class="border-sidebar-border/70 flex h-14 shrink-0 items-center justify-between gap-2 border-b px-4 md:hidden"
    >
        <Link :href="dashboardUrl" class="flex min-w-0 items-center">
            <AppLogo />
        </Link>
        <TeamSwitcher in-header />
    </header>
</template>
