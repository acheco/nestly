<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import TextLink from '@/components/TextLink.vue';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { edit as editTeam } from '@/routes/teams';
import type { DashboardInvitation, Team } from '@/types';

defineProps<{
    pendingInvitations?: DashboardInvitation[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: t('Dashboard'),
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

// EnsureTeamMembership switches the current team to the one in the URL, so this is the team being viewed.
const page = usePage();
const currentTeam = computed(() => page.props.currentTeam);
</script>

<template>
    <Head :title="t('Dashboard')" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <!-- R11: the empty home shows the team and links to its management; it announces no future features. -->
    <div v-if="currentTeam" class="flex flex-1 flex-col p-4">
        <Heading data-test="home-team-name" :title="currentTeam.name" />
        <div>
            <TextLink
                data-test="home-team-settings"
                :href="editTeam(currentTeam.slug)"
            >
                {{ t('Team settings') }}
            </TextLink>
        </div>
    </div>
</template>
