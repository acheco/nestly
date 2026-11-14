<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { t } from '@/lib/i18n';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

defineProps<Props>();
</script>

<template>
    <div data-test="team-invitation-alert">
        <Alert
            class="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <Info class="size-4" />
            <AlertDescription class="text-blue-900 dark:text-blue-100">
                {{
                    action === 'Log in'
                        ? t('Log in to join the ":team" team.', {
                              team: invitation.teamName,
                          })
                        : t('Register to join the ":team" team.', {
                              team: invitation.teamName,
                          })
                }}
            </AlertDescription>
        </Alert>
    </div>
</template>
