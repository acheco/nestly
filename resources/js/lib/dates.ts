const DAY_IN_MS = 86_400_000;

// "Hoy" and "Ayer" need numeric 'auto', but 'auto' would turn 2 days into "anteayer", which
// R8 rules out, so 2 to 6 days use numeric 'always' ("hace 2 días").
const namedDays = new Intl.RelativeTimeFormat('es-DO', { numeric: 'auto' });
const countedDays = new Intl.RelativeTimeFormat('es-DO', { numeric: 'always' });

// The dates are UTC midnights (see utcMidnight), so they are formatted in UTC too.
const absoluteDate = new Intl.DateTimeFormat('es-DO', {
    dateStyle: 'medium',
    timeZone: 'UTC',
});

/**
 * Turns a calendar date (YYYY-MM-DD) into its UTC midnight. Never new Date('YYYY-MM-DD'),
 * which mixes calendar dates with instants (spec 001, R8).
 */
function utcMidnight(date: string): number {
    const [year, month, day] = date.split('-').map(Number);

    return Date.UTC(
        year ?? Number.NaN,
        (month ?? Number.NaN) - 1,
        day ?? Number.NaN,
    );
}

function capitalize(text: string): string {
    return text.charAt(0).toLocaleUpperCase('es-DO') + text.slice(1);
}

/**
 * Today's calendar date (YYYY-MM-DD) in the given time zone (D-020). `now` is an argument so
 * tests don't depend on the clock or on TZ.
 */
export function todayIn(timeZone: string, now: Date): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(now);

    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((candidate) => candidate.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}`;
}

/**
 * Formats a movement's calendar date relative to `today`, both YYYY-MM-DD (spec 001, R8;
 * D-093): "Hoy", "Ayer", "Hace N días" up to 6 days, the absolute date from 7. A date after
 * today is also absolute, since team members can be in different time zones. It always starts
 * with an upper-case letter, because it is shown on its own.
 */
export function formatRelativeDate(date: string, today: string): string {
    const days = (utcMidnight(today) - utcMidnight(date)) / DAY_IN_MS;

    if (days === 0 || days === 1) {
        return capitalize(namedDays.format(-days, 'day'));
    }

    if (days >= 2 && days <= 6) {
        return capitalize(countedDays.format(-days, 'day'));
    }

    return capitalize(absoluteDate.format(utcMidnight(date)));
}
