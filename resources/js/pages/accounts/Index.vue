<script setup lang="ts">
  import type {Account} from "@/types/accounts";
  import type {Team} from "@/types";
  import {index} from "@/routes/accounts";
  import {computed} from "vue";

  const props = defineProps<{
      accounts: Account[],
      currency: string,
      can: {
          createTeamAccount: boolean,
          createPrivateAccount: boolean
      }
  }>()

  defineOptions({
      layout: (props: { currentTeam?: Team | null }) => ({
          breadcrumbs: [
              {
                  title: 'Accounts',
                  href: props.currentTeam
                      ? index(props.currentTeam.slug)
                      : '/',
              },
          ],
      }),
  });

  const active = computed(() => {
     return props.accounts.filter(a => !a.archived)
  })
  const archived = computed(() => {
     return props.accounts.filter(a => a.archived)
  })

  const money = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.currency,
  })
</script>

<template>

</template>
