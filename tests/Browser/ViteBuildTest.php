<?php

use Illuminate\Support\Facades\Vite;

// Browser tests must render the compiled assets in public/build, as CI does. If Laravel followed
// public/hot, a running `composer dev` would make them test the dev server, and an orphaned hot file
// (left behind on Windows) would break them. The suite points Vite at a hot file outside public/.
it('ignores public/hot in the browser suite', function () {
    // Laravel builds the default as public_path('/hot'), which on Windows mixes separators
    // (public\/hot), so paths are normalized before comparing.
    $normalize = fn (string $path): string => rtrim((string) preg_replace('#[\\\\/]+#', '/', $path), '/');

    expect($normalize(Vite::hotFile()))->not->toStartWith($normalize(public_path()).'/')
        ->and(Vite::isRunningHot())->toBeFalse();
});
