<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Finds the translation calls in a PHP source: the global helpers __(), trans() and trans_choice(),
 * and the Lang facade's get() and choice(). Methods, static calls, declarations and instantiations
 * that share a name (`$translator->trans()`, `function __()`) are not calls to the helpers.
 *
 * @return list<array{line: int, key: string|null, code: string}>
 */
function phpTranslationCalls(string $source): array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($source),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));

    $helpers = ['__', 'trans', 'trans_choice'];
    $notACall = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW];
    $calls = [];

    foreach ($tokens as $index => $token) {
        $openParen = null;

        $isHelper = ($token->is(T_STRING) && in_array($token->text, $helpers, true))
            || ($token->is(T_NAME_FULLY_QUALIFIED) && in_array(ltrim($token->text, '\\'), $helpers, true));

        if ($isHelper && ! ($tokens[$index - 1] ?? null)?->is($notACall)) {
            $openParen = $index + 1;
        }

        $isLangFacade = $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && in_array(ltrim($token->text, '\\'), ['Lang', 'Illuminate\Support\Facades\Lang'], true)
            && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON)
            && ($tokens[$index + 2] ?? null)?->is(T_STRING)
            && in_array($tokens[$index + 2]->text, ['get', 'choice'], true);

        if ($isLangFacade) {
            $openParen = $index + 3;
        }

        if ($openParen === null || ($tokens[$openParen] ?? null)?->text !== '(') {
            continue;
        }

        $argument = $tokens[$openParen + 1] ?? null;
        $afterArgument = $tokens[$openParen + 2] ?? null;
        $isLiteral = $argument?->is(T_CONSTANT_ENCAPSED_STRING) && in_array($afterArgument?->text, [',', ')'], true);

        $calls[] = [
            'line' => $token->line,
            'key' => $isLiteral ? decodePhpStringLiteral($argument->text) : null,
            'code' => implode('', array_map(
                fn (PhpToken $part): string => $part->text,
                array_slice($tokens, $index, $openParen - $index + 3),
            )),
        ];
    }

    return $calls;
}

/**
 * Decodes a T_CONSTANT_ENCAPSED_STRING token, which never interpolates, without eval().
 */
function decodePhpStringLiteral(string $literal): string
{
    $body = substr($literal, 1, -1);

    return $literal[0] === "'"
        ? strtr($body, ['\\\\' => '\\', "\\'" => "'"])
        : stripcslashes($body);
}

/**
 * Why a literal key has no translation, or null when it has one. A `group.key` key is looked up
 * in lang/es/<group>.php; any other key in lang/es.json.
 *
 * @param  array<string, string>  $json
 */
function missingTranslation(string $key, array $json): ?string
{
    if (preg_match('/^[a-z_]+(\.[a-z0-9_]+)+$/', $key) === 1) {
        [$group, $item] = explode('.', $key, 2);
        $path = lang_path("es/{$group}.php");

        if (! file_exists($path)) {
            return "group \"{$group}\" has no lang/es/{$group}.php: \"{$key}\"";
        }

        return Arr::has(require $path, $item) ? null : "missing in lang/es/{$group}.php: \"{$key}\"";
    }

    return array_key_exists($key, $json) ? null : "missing in lang/es.json: \"{$key}\"";
}

/**
 * @return list<string>
 */
function scannedAppFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = Str::after(str_replace('\\', '/', $file->getPathname()), str_replace('\\', '/', base_path()).'/');
        }
    }

    sort($files);

    return $files;
}

// Spec 001, R1 (D-091): __() falls back to the English key at runtime, so this guard is what breaks
// CI when a backend key is missing from lang/es.json (or its lang/es group file) or is not a literal.
it('scans the app files', function () {
    expect(scannedAppFiles())->toContain('app/Rules/TeamName.php');

    $keys = array_column(phpTranslationCalls(file_get_contents(app_path('Rules/TeamName.php'))), 'key');

    expect($keys)->toContain('This team name is reserved and cannot be used.');
});

it('uses only literal keys that have a Spanish translation', function () {
    /** @var array<string, string> $json */
    $json = json_decode(file_get_contents(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);

    $problems = [];

    foreach (scannedAppFiles() as $path) {
        foreach (phpTranslationCalls(file_get_contents(base_path($path))) as ['line' => $line, 'key' => $key, 'code' => $code]) {
            $problem = $key === null
                ? "key is not a string literal: {$code}"
                : missingTranslation($key, $json);

            if ($problem !== null) {
                $problems[] = "{$path}:{$line} {$problem}";
            }
        }
    }

    expect($problems)->toBe([]);
});
