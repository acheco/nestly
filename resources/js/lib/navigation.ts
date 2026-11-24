import { House } from '@lucide/vue';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/** Where an entry goes on mobile: the bottom bar or the "Más" panel (D-096). */
export type NavPlacement = 'bar' | 'more';

export type NavigationEntry = NavItem & { placement: NavPlacement };

/**
 * The navigation entries, shared by the desktop sidebar and the mobile bar (spec 001, R9;
 * D-096). The menu links only to pages that exist: each spec adds its entry when its screen
 * exists and declares whether it goes in the bar or in "Más". The bar holds at most 3 entries
 * (tests/js/navigation.test.ts), and moving one from the bar to "Más" needs the user's approval.
 */
export function navigationEntries(teamSlug: string | null): NavigationEntry[] {
    return [
        {
            title: t('Dashboard'),
            href: teamSlug ? dashboard(teamSlug).url : '/',
            icon: House,
            placement: 'bar',
        },
    ];
}
