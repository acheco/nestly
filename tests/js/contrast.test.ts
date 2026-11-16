/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { afterAll, describe, expect, it } from 'vite-plus/test';
import {
    contrastRatio,
    readShadcnMapping,
    readThemeTokens,
    SHADCN_MAPPING_SELECTOR,
} from './support/themeCss';

// Spec 001, R4/R5 (D-090, D-095): every pair of the R5 tables, recomputed from
// the final values in app.css for both themes. A token that stops passing
// WCAG 2.2 AA breaks CI. `info` is not listed: it is an alias of primary-text
// (R4) and is covered by it.
const css = readFileSync(
    fileURLToPath(new URL('../../resources/css/app.css', import.meta.url)),
    'utf8',
);

const themes = [
    { theme: 'light', selector: ':root' },
    { theme: 'dark', selector: '.dark' },
].map(({ theme, selector }) => ({
    theme,
    tokens: readThemeTokens(css, selector),
}));

const tokenNames = [
    'bg',
    'surface',
    'border',
    'input-border',
    'text',
    'text-muted',
    'text-subtle',
    'primary',
    'primary-hover',
    'primary-foreground',
    'primary-soft',
    'primary-text',
    'danger',
    'success',
    'warning',
];

const TEXT = 4.5; // 1.4.3, normal text
const NON_TEXT = 3; // 1.4.11, control borders and focus indicator

const onPageAndSurface = (foreground: string, minimum: number) =>
    ['bg', 'surface'].map((background) => ({
        foreground,
        background,
        minimum,
    }));

const pairs = [
    ...[
        'text',
        'text-muted',
        'text-subtle',
        'primary-text',
        'danger',
        'success',
        'warning',
    ].flatMap((foreground) => onPageAndSurface(foreground, TEXT)),
    { foreground: 'primary-text', background: 'primary-soft', minimum: TEXT },
    { foreground: 'primary-foreground', background: 'primary', minimum: TEXT },
    {
        foreground: 'primary-foreground',
        background: 'primary-hover',
        minimum: TEXT,
    },
    // --destructive-foreground (plan 001, phase 3): text on danger.
    { foreground: 'bg', background: 'danger', minimum: TEXT },
    ...['input-border', 'primary', 'primary-hover'].flatMap((foreground) =>
        onPageAndSurface(foreground, NON_TEXT),
    ),
];

const HEX = /^#[0-9A-Fa-f]{6}$/;

const isHex = (value: string | undefined): value is string =>
    value !== undefined && HEX.test(value);

describe('R4 tokens in app.css', () => {
    it.each(themes)('$theme: every token is #RRGGBB', ({ theme, tokens }) => {
        const problems = tokenNames.flatMap((name) => {
            const value = tokens[name];

            if (value === undefined) {
                return [`Token --nestly-${name} (${theme}) missing`];
            }

            return HEX.test(value)
                ? []
                : [
                      `Token --nestly-${name} (${theme}) is "${value}", expected #RRGGBB`,
                  ];
        });

        expect(problems, problems.join('\n')).toEqual([]);
    });
});

describe('R5 contrast', () => {
    const cases = themes.flatMap(({ theme, tokens }) =>
        pairs.map((pair) => ({ ...pair, theme, tokens })),
    );

    it.each(cases)(
        '$theme: $foreground on $background needs $minimum:1',
        ({ theme, tokens, foreground, background, minimum }) => {
            const [front, back] = [tokens[foreground], tokens[background]];

            if (!isHex(front) || !isHex(back)) {
                expect.fail(
                    `${foreground} on ${background} (${theme}): a token is not #RRGGBB, see the R4 tokens test`,
                );
            }

            const ratio = contrastRatio(front, back);

            expect(
                ratio,
                `${foreground} on ${background} (${theme}): ${ratio.toFixed(2)}:1, needs ${minimum}:1`,
            ).toBeGreaterThanOrEqual(minimum);
        },
    );

    // The table the PR attaches (R5: recomputed with the final values).
    afterAll(() => {
        const ratioIn = (
            tokens: Record<string, string>,
            foreground: string,
            background: string,
        ): string => {
            const [front, back] = [tokens[foreground], tokens[background]];

            return isHex(front) && isHex(back)
                ? contrastRatio(front, back).toFixed(2)
                : 'n/a';
        };

        console.table(
            pairs.map(({ foreground, background, minimum }) => ({
                pair: `${foreground} on ${background}`,
                minimum: `${minimum}:1`,
                ...Object.fromEntries(
                    themes.map(({ theme, tokens }) => [
                        theme,
                        ratioIn(tokens, foreground, background),
                    ]),
                ),
            })),
        );
    });
});

describe('shadcn variables point at the R4 tokens', () => {
    const mapping = readShadcnMapping(css);

    it.each([
        { variable: '--foreground', token: 'text' },
        { variable: '--muted-foreground', token: 'text-muted' },
        { variable: '--primary-foreground', token: 'primary-foreground' },
        { variable: '--destructive-foreground', token: 'bg' },
        { variable: '--input', token: 'input-border' },
        { variable: '--ring', token: 'primary' },
    ])('$variable → --nestly-$token', ({ variable, token }) => {
        const expected = `var(--nestly-${token})`;
        const actual = mapping[variable];

        if (actual === undefined) {
            expect.fail(
                `${variable} is not mapped in "${SHADCN_MAPPING_SELECTOR}", expected ${expected}`,
            );
        }

        expect(
            actual,
            `${variable} maps to "${actual}", expected ${expected}`,
        ).toBe(expected);
    });
});
