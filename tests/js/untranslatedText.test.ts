/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';
import { findUntranslatedText } from './support/untranslatedText';

// Spec 001, R1 (D-091): every text a template shows goes through t(). This
// guard fails on text nodes and static text attributes (placeholder,
// aria-label, title, alt, label, description) left in plain text.
const root = fileURLToPath(new URL('../../resources/js', import.meta.url));

// shadcn-vue vendor code, translated after v1.0 (specs/backlog.md).
const excludedFolders = ['components/ui/'];

const scannedFiles = (
    readdirSync(root, { recursive: true, encoding: 'utf8' }) as string[]
)
    .map((path) => path.replaceAll('\\', '/'))
    .filter((path) => path.endsWith('.vue'))
    .filter((path) => !excludedFolders.some((dir) => path.startsWith(dir)))
    .sort();

describe('untranslated text in resources/js templates', () => {
    it('scans the app templates and skips the excluded folders', () => {
        expect(scannedFiles).toContain('pages/teams/Edit.vue');
        expect(
            scannedFiles.filter((path) => path.startsWith('components/ui/')),
        ).toEqual([]);
    });

    it('shows no text outside t()', () => {
        const problems = scannedFiles.flatMap((path) =>
            findUntranslatedText(
                readFileSync(`${root}/${path}`, 'utf8'),
                path,
            ).map(({ line, text }) => `${path}:${line} ${text}`),
        );

        expect(problems).toEqual([]);
    });
});
