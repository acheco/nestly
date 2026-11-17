import { describe, expect, it } from 'vite-plus/test';
import { formatMoney } from '@/lib/money';

// Spec 001, R8 (D-078, D-093): amounts arrive as decimal strings and are formatted with the
// es-DO locale and the account's currency, without going through a JavaScript number.
describe('formatMoney()', () => {
    it('formats the largest decimal(14,2) amount exactly in DOP', () => {
        expect(formatMoney('999999999999.99', 'DOP')).toBe(
            'RD$999,999,999,999.99',
        );
    });

    it('formats the largest decimal(14,2) amount exactly in USD', () => {
        expect(formatMoney('999999999999.99', 'USD')).toBe(
            'US$999,999,999,999.99',
        );
    });

    it('keeps the sign that Intl produces for negative amounts', () => {
        expect(formatMoney('-1234.5', 'DOP')).toBe('-RD$1,234.50');
        expect(formatMoney('-1234.5', 'USD')).toBe('-US$1,234.50');
    });

    it('shows negative zero without a sign', () => {
        expect(formatMoney('-0.00', 'DOP')).toBe('RD$0.00');
    });

    it('shows zero without a sign', () => {
        expect(formatMoney('0.00', 'DOP')).toBe('RD$0.00');
    });

    // Above decimal(14,2) on purpose: a double still holds every amount in the domain to the
    // cent, so only a value past 2^53 fails if the amount goes through Number() or parseFloat.
    it('does not convert the amount to a number', () => {
        expect(formatMoney('9007199254740993.01', 'DOP')).toBe(
            'RD$9,007,199,254,740,993.01',
        );
    });

    // Regex, not an exact string: the space after the code (U+00A0 today) depends on the ICU version.
    it('shows EUR with its code', () => {
        expect(formatMoney('1234.5', 'EUR')).toMatch(/^EUR\s1,234\.50$/u);
    });
});
