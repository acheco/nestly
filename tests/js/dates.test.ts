import { describe, expect, it } from 'vite-plus/test';
import { formatRelativeDate, todayIn } from '@/lib/dates';

// Spec 001, R8 (D-020, D-093): calendar dates (YYYY-MM-DD) are relative up to 6 days and
// absolute from 7, in es-DO. "Today" is passed in, so no test depends on the clock or on TZ.
const today = '2026-11-17';

// 21:00 on 2026-11-16 in Santo Domingo (UTC-4), already 2026-11-17 in UTC.
const nineAtNightInSantoDomingo = new Date('2026-11-17T01:00:00Z');

describe('formatRelativeDate()', () => {
    it('shows today as "Hoy"', () => {
        expect(formatRelativeDate('2026-11-17', today)).toBe('Hoy');
    });

    it('shows yesterday as "Ayer"', () => {
        expect(formatRelativeDate('2026-11-16', today)).toBe('Ayer');
    });

    it.each([
        ['2026-11-15', 'Hace 2 días'],
        ['2026-11-14', 'Hace 3 días'],
        ['2026-11-13', 'Hace 4 días'],
        ['2026-11-12', 'Hace 5 días'],
        ['2026-11-11', 'Hace 6 días'],
    ])('shows %s as "%s"', (date, expected) => {
        expect(formatRelativeDate(date, today)).toBe(expected);
    });

    it('shows the absolute date from 7 days on', () => {
        expect(formatRelativeDate('2026-11-10', today)).toBe('10 nov 2026');
    });

    it('counts days across a year change', () => {
        expect(formatRelativeDate('2025-12-31', '2026-01-01')).toBe('Ayer');
    });

    it('starts with an upper-case letter', () => {
        expect(formatRelativeDate('2026-11-17', today)).toBe('Hoy');
        expect(formatRelativeDate('2026-11-16', today)).toBe('Ayer');
        expect(formatRelativeDate('2026-11-14', today)).toBe('Hace 3 días');
    });

    // Team members can be in different time zones, so one member's today can be another's
    // tomorrow: a date after today is shown as an absolute date, never "Mañana".
    it('shows a date after today as an absolute date', () => {
        expect(formatRelativeDate('2026-11-18', today)).toBe('18 nov 2026');
    });
});

describe('todayIn()', () => {
    it('returns the local date in the given time zone', () => {
        expect(
            todayIn('America/Santo_Domingo', nineAtNightInSantoDomingo),
        ).toBe('2026-11-16');
        expect(
            todayIn('America/Santo_Domingo', new Date('2026-11-17T04:00:00Z')),
        ).toBe('2026-11-17');
    });

    it('returns the UTC date for UTC', () => {
        expect(todayIn('UTC', nineAtNightInSantoDomingo)).toBe('2026-11-17');
    });

    it('shows yesterday as "Ayer" at 21:00 local time in Santo Domingo', () => {
        const localToday = todayIn(
            'America/Santo_Domingo',
            nineAtNightInSantoDomingo,
        );

        expect(formatRelativeDate('2026-11-15', localToday)).toBe('Ayer');
    });
});
