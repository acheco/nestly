import { describe, expect, it } from 'vite-plus/test';
import { cn } from '@/lib/utils';

// Spec 001, R13: proves Vitest runs and resolves the `@` alias the app uses.
describe('vitest setup', () => {
    it('resolves the @ alias to resources/js', () => {
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });
});
