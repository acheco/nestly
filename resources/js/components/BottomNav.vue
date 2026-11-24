<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Ellipsis, LogOut, Settings } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { t } from '@/lib/i18n';
import { navigationEntries } from '@/lib/navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

// Spec 001, R9 (D-096): the bar shows the "bar" entries plus "Más"; "Más" holds the rest,
// Ajustes and Cerrar sesión, but not the team switcher (the mobile header has it).
const entries = computed(() =>
    navigationEntries(page.props.currentTeam?.slug ?? null),
);
const barEntries = computed(() =>
    entries.value.filter(({ placement }) => placement === 'bar'),
);
const moreEntries = computed(() =>
    entries.value.filter(({ placement }) => placement === 'more'),
);

// The layout persists across visits, so the panel closes on every navigation, not only on
// clicks inside it.
const isMoreOpen = ref(false);
let stopListening: (() => void) | null = null;

onMounted(() => {
    stopListening = router.on('navigate', () => {
        isMoreOpen.value = false;
    });
});

onUnmounted(() => {
    stopListening?.();
});

const handleLogout = () => {
    router.flushAll();
};

const tabClass =
    'text-muted-foreground aria-[current=page]:text-primary flex min-h-6 min-w-6 flex-1 flex-col items-center justify-center gap-1 text-xs font-medium';
const panelItemClass =
    'hover:bg-accent flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-sm';
</script>

<template>
    <nav
        :aria-label="t('Main navigation')"
        data-test="bottom-nav"
        class="bg-background fixed inset-x-0 bottom-0 z-40 h-[calc(var(--bottom-nav-height)+env(safe-area-inset-bottom))] border-t pb-[env(safe-area-inset-bottom)] md:hidden"
    >
        <!-- The height includes the border and the safe area, so it matches the content's reserve. -->
        <div class="flex h-full items-stretch">
            <Link
                v-for="item in barEntries"
                :key="item.title"
                :href="item.href"
                :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                :class="tabClass"
                data-test="bottom-nav-entry"
            >
                <component :is="item.icon" class="size-5" />
                <span>{{ item.title }}</span>
            </Link>

            <Sheet v-model:open="isMoreOpen">
                <SheetTrigger as-child>
                    <button
                        type="button"
                        :class="tabClass"
                        data-test="bottom-nav-more"
                    >
                        <Ellipsis class="size-5" />
                        <span>{{ t('More') }}</span>
                    </button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    class="pb-[calc(1rem+env(safe-area-inset-bottom))]"
                    data-test="more-panel"
                >
                    <SheetHeader>
                        <SheetTitle>{{ t('More') }}</SheetTitle>
                        <SheetDescription class="sr-only">
                            {{ t('More sections, settings and log out') }}
                        </SheetDescription>
                    </SheetHeader>
                    <ul class="flex flex-col gap-1 px-2">
                        <li v-for="item in moreEntries" :key="item.title">
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isCurrentUrl(item.href) ? 'page' : undefined
                                "
                                :class="panelItemClass"
                            >
                                <component :is="item.icon" class="size-4" />
                                {{ item.title }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="edit()"
                                :class="panelItemClass"
                                data-test="more-panel-settings"
                            >
                                <Settings class="size-4" />
                                {{ t('Settings') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="logout()"
                                as="button"
                                :class="panelItemClass"
                                data-test="more-panel-logout"
                                @click="handleLogout"
                            >
                                <LogOut class="size-4" />
                                {{ t('Log out') }}
                            </Link>
                        </li>
                    </ul>
                </SheetContent>
            </Sheet>
        </div>
    </nav>
</template>
