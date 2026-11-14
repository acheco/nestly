import { describe, expect, it } from 'vite-plus/test';
import { findTranslationCalls } from './support/translationCalls';

// Spec 001, R2 (D-091): the extractor behind the key test (translationKeys.test.ts).
// It parses TypeScript and Vue templates instead of using a regex, so comments,
// strings and member calls are not mistaken for t() calls.
const lines = (...source: string[]): string => source.join('\n');

const keysOf = (source: string, fileName = 'example.ts') =>
    findTranslationCalls(source, fileName).calls.map(({ line, key }) => ({
        line,
        key,
    }));

describe('t() calls in TypeScript', () => {
    it('finds a literal key and its line', () => {
        expect(keysOf(lines('const a = 1;', "t('Log in');"))).toEqual([
            { line: 2, key: 'Log in' },
        ]);
    });

    it('returns the key without escapes', () => {
        expect(keysOf("t('You\\'ve been invited');")).toEqual([
            { line: 1, key: "You've been invited" },
        ]);
    });

    it('flags a key in a variable as non-literal', () => {
        expect(keysOf('t(key);')).toEqual([{ line: 1, key: null }]);
    });

    it('flags template literals, with and without substitutions', () => {
        expect(keysOf(lines('t(`Log in`);', 't(`Hi ${name}`);'))).toEqual([
            { line: 1, key: null },
            { line: 2, key: null },
        ]);
    });

    it('flags a concatenation as non-literal', () => {
        expect(keysOf("t('a' + b);")).toEqual([{ line: 1, key: null }]);
    });

    it('flags a call without arguments', () => {
        expect(keysOf('t();')).toEqual([{ line: 1, key: null }]);
    });

    it('ignores comments, strings, member calls and declarations', () => {
        const source = lines(
            "// t('comment')",
            'const s = "t(\'string\')";',
            "obj.t('member');",
            'function t(key: string) { return key; }',
        );

        expect(keysOf(source)).toEqual([]);
    });
});

describe('t() calls in Vue files', () => {
    it('finds calls in <script setup> with the file line', () => {
        const source = lines(
            '<script setup lang="ts">',
            "import { t } from '@/lib/i18n';",
            "const title = t('Log in');",
            '</script>',
        );

        expect(keysOf(source, 'Page.vue')).toEqual([
            { line: 3, key: 'Log in' },
        ]);
    });

    it('finds calls in interpolations, bindings and event handlers', () => {
        const source = lines(
            '<template>',
            "    <p>{{ t('A') }}</p>",
            '    <Input :label="t(\'B\')" />',
            '    <button @click="toast(t(\'C\'))">x</button>',
            '</template>',
        );

        expect(keysOf(source, 'Page.vue')).toEqual([
            { line: 2, key: 'A' },
            { line: 3, key: 'B' },
            { line: 4, key: 'C' },
        ]);
    });

    it('ignores static attributes and survives apostrophes in text', () => {
        const source = lines(
            '<template>',
            '    <Input label="t(\'D\')" />',
            "    <p>Don't (x)</p>",
            '</template>',
        );

        expect(keysOf(source, 'Page.vue')).toEqual([]);
    });

    it('finds calls in v-if and in the list of a v-for', () => {
        const source = lines(
            '<template>',
            '    <p v-if="t(\'E\')">x</p>',
            '    <p v-for="item in [t(\'F\')]" :key="item">{{ item }}</p>',
            '</template>',
        );

        expect(keysOf(source, 'Page.vue')).toEqual([
            { line: 2, key: 'E' },
            { line: 3, key: 'F' },
        ]);
    });
});

describe('t is reserved for the translation helper', () => {
    const reservedLines = (source: string, fileName = 'example.ts'): number[] =>
        findTranslationCalls(source, fileName)
            .evasions.filter(({ reason }) => reason === 'reserved')
            .map(({ line }) => line);

    it('flags t passed or assigned as a value', () => {
        expect(
            reservedLines(lines("['A', 'B'].map(t);", 'const tr = t;')),
        ).toEqual([1, 2]);
    });

    it('flags a parameter named t and its uses', () => {
        expect(reservedLines('teams.map((t) => t.name);')).toEqual([1, 1]);
    });

    it('flags destructured and shorthand t', () => {
        expect(
            reservedLines(lines('const { t } = x;', 'const o = { t };')),
        ).toEqual([1, 2]);
    });

    it('flags functions, variables and classes named t', () => {
        expect(
            reservedLines(
                lines('function t() {}', 'const t = 1;', 'class t {}'),
            ),
        ).toEqual([1, 2, 3]);
    });

    it('flags a v-for alias named t and its uses in the template', () => {
        const source = lines(
            '<template>',
            '    <p v-for="t in teams" :key="t.id">{{ t.name }}</p>',
            '</template>',
        );

        expect(reservedLines(source, 'Page.vue')).toEqual([2, 2, 2]);
    });

    it('flags a v-slot parameter named t', () => {
        const source = lines(
            '<template>',
            '    <Teams>',
            '        <template #default="{ t }">x</template>',
            '    </Teams>',
            '</template>',
        );

        expect(reservedLines(source, 'Page.vue')).toEqual([3]);
    });

    it('ignores property names and imports', () => {
        const source = lines(
            "obj.t('member');",
            'const o = { t: 1 };',
            'class C { t() {} }',
            'const { t: x } = obj;',
            "import { t } from '@/lib/i18n';",
        );

        expect(findTranslationCalls(source, 'example.ts').evasions).toEqual([]);
    });

    it('counts t(...) as a call, not as a reserved use', () => {
        const scan = findTranslationCalls("t('Log in');", 'example.ts');

        expect(scan.evasions).toEqual([]);
        expect(scan.calls.map(({ key }) => key)).toEqual(['Log in']);
    });
});

describe('imports that hide t()', () => {
    it('flags renamed and namespace imports, not the plain one', () => {
        const source = lines(
            '<script setup lang="ts">',
            "import { t } from '@/lib/i18n';",
            "import { t as translate } from '@/lib/i18n';",
            "import * as i18n from '../lib/i18n';",
            '</script>',
        );

        expect(
            findTranslationCalls(source, 'Page.vue').evasions.map(
                ({ line }) => line,
            ),
        ).toEqual([3, 4]);
    });
});
