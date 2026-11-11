import messages from '../../../lang/es.json';

/**
 * Same shape as laravel-vue-i18n's ReplacementsInterface, so a future
 * migration to its trans() is a search and replace (D-091).
 */
export type Replacements = { [key: string]: string };

export type Translator = (key: string, replacements?: Replacements) => string;

function ucfirst(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Mirrors Laravel's Translator::makeReplacements(): each key yields :Key,
 * :KEY and :key variants, applied like strtr() in a single pass that tries
 * the longest placeholder first, so :name never eats :names and replaced
 * values are not replaced again.
 */
export function makeReplacements(
    line: string,
    replacements: Replacements = {},
): string {
    const variants = new Map<string, string>();

    for (const [key, value] of Object.entries(replacements)) {
        variants.set(`:${ucfirst(key)}`, ucfirst(value));
        variants.set(`:${key.toUpperCase()}`, value.toUpperCase());
        variants.set(`:${key}`, value);
    }

    if (variants.size === 0) {
        return line;
    }

    const pattern = new RegExp(
        [...variants.keys()]
            .sort((a, b) => b.length - a.length)
            .map(escapeRegExp)
            .join('|'),
        'g',
    );

    return line.replace(pattern, (match) => variants.get(match) ?? match);
}

/**
 * Builds a t() over a dictionary. A missing or empty translation falls back
 * to the key, like `$line ?: $key` in Laravel; the key test (task 2.4) is
 * what keeps missing keys out of CI.
 */
export function createTranslator(
    dictionary: Record<string, string>,
): Translator {
    return (key, replacements = {}) => {
        const line = Object.hasOwn(dictionary, key) ? dictionary[key] : '';

        return makeReplacements(line || key, replacements);
    };
}

/**
 * Translates a frontend string with lang/es.json, the same source as __()
 * (spec 001, R2; D-091). The key must be a string literal.
 */
export const t: Translator = createTranslator(messages);
