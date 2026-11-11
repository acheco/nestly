<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

// Spec 001, R3 (D-092): lang/es is a one-off copy maintained by hand, so this test warns when the
// framework adds keys to its English files that our Spanish copy does not have yet.
it('has every framework translation key in Spanish', function (string $file) {
    $spanishPath = lang_path("es/{$file}.php");

    expect(file_exists($spanishPath))->toBeTrue("Missing {$spanishPath}");

    $frameworkKeys = array_keys(Arr::dot(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php")));
    $spanishKeys = array_keys(Arr::dot(require $spanishPath));

    // `custom` and `attributes` are placeholders for the application, not framework messages.
    $frameworkKeys = array_filter(
        $frameworkKeys,
        fn (string $key): bool => ! Str::startsWith($key, ['custom.', 'attributes']),
    );

    expect(array_values(array_diff($frameworkKeys, $spanishKeys)))->toBe([]);
})->with(['auth', 'pagination', 'passwords', 'validation']);
