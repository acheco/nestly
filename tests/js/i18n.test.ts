import { describe, expect, it } from 'vite-plus/test';
import { createTranslator, t } from '@/lib/i18n';

// Spec 001, R2 (D-091): t() reads lang/es.json and replaces placeholders like Laravel's
// Translator::makeReplacements(), which builds :key, :Key and :KEY variants and applies strtr().
const translate = createTranslator({
    'Hello :name': 'Hola :name',
    'Welcome :Name': 'Bienvenido, :Name',
    'Shout :NAME': 'Grita :NAME',
    'Both :name and :names': 'Ambos :name y :names',
    ':a.b and :axb': ':a.b y :axb',
    Empty: '',
});

describe('t() replacements', () => {
    it('replaces :name as is', () => {
        expect(translate('Hello :name', { name: 'ana' })).toBe('Hola ana');
    });

    it('replaces :Name with the first letter in upper case', () => {
        expect(translate('Welcome :Name', { name: 'ana' })).toBe(
            'Bienvenido, Ana',
        );
    });

    it('replaces :NAME in upper case', () => {
        expect(translate('Shout :NAME', { name: 'ana' })).toBe('Grita ANA');
    });

    it('replaces longer keys first so :name does not eat :names', () => {
        expect(
            translate('Both :name and :names', { name: 'ana', names: 'todos' }),
        ).toBe('Ambos ana y todos');
    });

    it('replaces in a single pass, like strtr', () => {
        expect(translate('Hello :name', { name: ':other', other: 'x' })).toBe(
            'Hola :other',
        );
    });

    it('leaves a placeholder without replacement untouched', () => {
        expect(translate('Hello :name')).toBe('Hola :name');
    });

    it('escapes regex characters in replacement keys', () => {
        expect(translate(':a.b and :axb', { 'a.b': 'uno' })).toBe('uno y :axb');
    });

    it('upper-cases an accented first letter for :Name', () => {
        expect(translate('Welcome :Name', { name: 'élite' })).toBe(
            'Bienvenido, Élite',
        );
    });
});

describe('t() missing keys', () => {
    it('returns the key, with replacements, when it has no translation', () => {
        expect(translate('Bye :name', { name: 'ana' })).toBe('Bye ana');
    });

    it('does not read keys inherited from Object.prototype', () => {
        expect(translate('constructor')).toBe('constructor');
    });

    it('treats an empty translation as missing, like $line ?: $key', () => {
        expect(translate('Empty')).toBe('Empty');
    });
});

describe('t() over lang/es.json', () => {
    it('translates with the real dictionary', () => {
        expect(t('Invitation accepted.')).toBe('Invitación aceptada.');
    });
});
