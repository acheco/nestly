import { describe, expect, it } from 'vite-plus/test';
import { readShadcnMapping, readThemeTokens } from './support/themeCss';

// Spec 001, R4/R5: the reader behind the contrast guard (contrast.test.ts).
// app.css has three blocks with custom properties: :root (light), .dark and
// the shared `:root, .dark` mapping. Each read must see only its own block,
// and only top-level rules: a theme selector nested in an at-rule (@layer,
// @media) is not the theme. The @source glob holds "/*" inside a string, as
// in app.css: it does not open a comment. The @custom-variant statement right
// before :root must not become part of its selector.
const css = `
@source '../../storage/framework/views/*.php';

@theme inline {
    /* Not a theme. */
    --color-background: var(--background);
}

/* :root { --nestly-bg: #000000; } */
@custom-variant dark (&:is(.dark *));
:root {
    --nestly-bg: #FFFFFF;
    --nestly-text: #0E0F12;
    --radius: 0.5rem;
}

.dark {
    --nestly-bg: #0E0F12;
    --nestly-text: #ECEEF2;
}

:root,
.dark {
    --nestly-bg: #123456;
    --background: var(--nestly-bg);
    --input: var(--nestly-input-border);
}

@layer base {
    .dark {
        --nestly-bg: #FF0000;
    }
}

@media (prefers-contrast: more) {
    :root {
        --nestly-bg: #777777;
    }
}
`;

describe('readThemeTokens', () => {
    it('reads only the --nestly-* tokens of the exact :root block', () => {
        expect(readThemeTokens(css, ':root')).toEqual({
            bg: '#FFFFFF',
            text: '#0E0F12',
        });
    });

    it('reads only the --nestly-* tokens of the exact .dark block', () => {
        expect(readThemeTokens(css, '.dark')).toEqual({
            bg: '#0E0F12',
            text: '#ECEEF2',
        });
    });

    it('returns an empty object for a selector that does not exist', () => {
        expect(readThemeTokens(css, '.sepia')).toEqual({});
    });

    it('is not polluted by the same token in another block', () => {
        expect(readThemeTokens(css, ':root').bg).toBe('#FFFFFF');
        expect(readThemeTokens(css, '.dark').bg).toBe('#0E0F12');
    });
});

describe('readShadcnMapping', () => {
    it('reads only the variables of the `:root, .dark` block', () => {
        expect(readShadcnMapping(css)).toEqual({
            '--nestly-bg': '#123456',
            '--background': 'var(--nestly-bg)',
            '--input': 'var(--nestly-input-border)',
        });
    });

    it('returns an empty object when the block does not exist', () => {
        expect(readShadcnMapping(':root { --background: #FFFFFF; }')).toEqual(
            {},
        );
    });
});
