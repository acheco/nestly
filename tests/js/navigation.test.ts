import { describe, expect, it } from 'vite-plus/test';
import { navigationEntries } from '@/lib/navigation';

// Spec 001, R9 (D-096): every entry appears once, and the mobile bar holds at most 3 sections
// plus "Más". Moving an entry from the bar to "Más" needs the user's approval, so a spec that
// adds a fourth bar entry has to stop here.
const entries = navigationEntries('team-slug');

describe('navigation entries', () => {
    it('lists every entry once', () => {
        const titles = entries.map(({ title }) => title);
        const hrefs = entries.map(({ href }) => href);

        expect(new Set(titles).size).toBe(titles.length);
        expect(new Set(hrefs).size).toBe(hrefs.length);
    });

    it('puts at most 3 entries in the mobile bar', () => {
        const inBar = entries.filter(({ placement }) => placement === 'bar');

        expect(
            inBar.length,
            `${inBar.length} bar entries: ${inBar.map(({ title }) => title).join(', ')}`,
        ).toBeLessThanOrEqual(3);
    });
});
