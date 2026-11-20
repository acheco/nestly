// Spec 001, R4/R5 (D-090, D-095): reads the design tokens out of app.css and
// computes WCAG contrast, for the contrast guard (contrast.test.ts).

/** The block that points the shadcn variables at the Nestly tokens. */
export const SHADCN_MAPPING_SELECTOR = ':root, .dark';

const normalizeSelector = (selector: string): string =>
    selector
        .split(',')
        .map((part) => part.trim().replace(/\s+/g, ' '))
        .join(', ');

/**
 * The rules at the top level of the stylesheet, with their selector and the
 * text of their body at depth 1. Walks the CSS counting brace depth, so a
 * rule nested in an at-rule (`@layer`, `@media`, `@theme`) is never a
 * top-level rule, and the contents of a nested block are left out of its
 * body. Comments are skipped and strings are copied whole in the same pass,
 * so a "/*" or a brace inside a string (the @source globs of app.css) does
 * not open a comment or change the depth.
 */
const topLevelRules = (
    css: string,
): Array<{ selector: string; body: string }> => {
    const rules: Array<{ selector: string; body: string }> = [];
    let depth = 0;
    let prelude = '';
    let body = '';

    const append = (text: string): void => {
        if (depth === 0) {
            prelude += text;
        } else if (depth === 1) {
            body += text;
        }
    };

    for (let index = 0; index < css.length; index++) {
        const character = css[index];

        if (character === '/' && css[index + 1] === '*') {
            const end = css.indexOf('*/', index + 2);
            index = end === -1 ? css.length : end + 1;
        } else if (character === '"' || character === "'") {
            let end = index + 1;

            while (end < css.length && css[end] !== character) {
                end += css[end] === '\\' ? 2 : 1;
            }

            append(css.slice(index, end + 1));
            index = end;
        } else if (character === '{') {
            if (depth === 0) {
                body = '';
            }

            depth++;
        } else if (character === '}') {
            depth--;

            if (depth === 0) {
                rules.push({ selector: prelude, body });
                prelude = '';
            }
        } else if (depth === 0 && character === ';') {
            // A statement at-rule (@import, @source, @custom-variant).
            prelude = '';
        } else {
            append(character);
        }
    }

    return rules;
};

/**
 * Custom properties of the top-level rules whose selector is exactly
 * `selector` (`:root` does not match `:root, .dark`). The same selector
 * inside an at-rule such as `@layer` or `@media` does not count.
 */
const readDeclarations = (
    css: string,
    selector: string,
): Record<string, string> => {
    const wanted = normalizeSelector(selector);
    const declarations: Record<string, string> = {};

    for (const rule of topLevelRules(css)) {
        if (normalizeSelector(rule.selector) !== wanted) {
            continue;
        }

        for (const [, name, value] of rule.body.matchAll(
            /(--[\w-]+)\s*:\s*([^;]+);/g,
        )) {
            declarations[name] = value.trim();
        }
    }

    return declarations;
};

/** The `--nestly-*` tokens of one theme block, keyed without the prefix. */
export const readThemeTokens = (
    css: string,
    selector: string,
): Record<string, string> =>
    Object.fromEntries(
        Object.entries(readDeclarations(css, selector))
            .filter(([name]) => name.startsWith('--nestly-'))
            .map(([name, value]) => [name.slice('--nestly-'.length), value]),
    );

/** The variables of the `:root, .dark` block, keyed with their `--` name. */
export const readShadcnMapping = (css: string): Record<string, string> =>
    readDeclarations(css, SHADCN_MAPPING_SELECTOR);

/**
 * The `--nestly-*` token behind a variable of app.css (`--popover`,
 * `--color-success`, …), following `var()` chains through the `:root, .dark`
 * block and `@theme inline`. Undefined when the chain does not end in a token.
 */
export const resolveToken = (
    css: string,
    variable: string,
): string | undefined => {
    const mapping = {
        ...readDeclarations(css, '@theme inline'),
        ...readShadcnMapping(css),
    };
    let current = variable;

    for (let step = 0; step < 10; step++) {
        const target = /^var\((--[\w-]+)\)$/.exec(mapping[current] ?? '')?.[1];

        if (target === undefined) {
            return undefined;
        }

        if (target.startsWith('--nestly-')) {
            return target.slice('--nestly-'.length);
        }

        current = target;
    }

    return undefined;
};

const toLinear = (channel: number): number =>
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

const fromLinear = (channel: number): number =>
    channel <= 0.0031308
        ? 12.92 * channel
        : 1.055 * channel ** (1 / 2.4) - 0.055;

const toOklab = (hex: string): [number, number, number] => {
    const [red, green, blue] = [1, 3, 5].map((offset) =>
        toLinear(parseInt(hex.slice(offset, offset + 2), 16) / 255),
    ) as [number, number, number];
    const l = Math.cbrt(
        0.4122214708 * red + 0.5363325363 * green + 0.0514459929 * blue,
    );
    const m = Math.cbrt(
        0.2119034982 * red + 0.6806995451 * green + 0.1073969566 * blue,
    );
    const s = Math.cbrt(
        0.0883024619 * red + 0.2817188376 * green + 0.6299787005 * blue,
    );

    return [
        0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s,
    ];
};

const fromOklab = ([lightness, a, b]: [number, number, number]): string => {
    const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3;

    return (
        '#' +
        [
            4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
        ]
            .map((channel) =>
                Math.round(Math.min(1, Math.max(0, fromLinear(channel))) * 255)
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
};

/**
 * CSS `color-mix(in oklab, first percent%, second)` of two `#RRGGBB` colors,
 * as `#RRGGBB` (both opaque, so the result is opaque too).
 */
export const mixOklab = (
    first: string,
    percent: number,
    second: string,
): string => {
    const [a, b] = [toOklab(first), toOklab(second)];
    const weight = percent / 100;

    return fromOklab(
        a.map((value, index) => value * weight + b[index]! * (1 - weight)) as [
            number,
            number,
            number,
        ],
    );
};

/** WCAG 2.x relative luminance of a `#RRGGBB` color. */
export const relativeLuminance = (hex: string): number => {
    const [red, green, blue] = [1, 3, 5].map((offset) => {
        const channel = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
};

/** WCAG contrast ratio between two `#RRGGBB` colors, from 1 to 21. */
export const contrastRatio = (first: string, second: string): number => {
    const [lighter, darker] = [
        relativeLuminance(first),
        relativeLuminance(second),
    ].sort((a, b) => b - a);

    return (lighter + 0.05) / (darker + 0.05);
};
