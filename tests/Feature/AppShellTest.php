<?php

// Spec 001, R4 (D-090): colors live only in resources/css/app.css, so the app shell
// (app.blade.php) carries no inline colors. The only inline <style> allowed is the
// block that @fonts emits when a Vite font manifest exists, and only with what
// laravel-vite-plugin generates there (an exact allowlist, so a change in it is
// reviewed): @font-face rules, `:root { --font-<slug>: "<Family>"; }` and
// `.font-<slug> { font-family: var(--font-<slug>); }`.
//
// This covers only pages rendered by app.blade.php. The static 503 and 500-fallback
// views (R10) carry inline colors on purpose, because they cannot rely on app.css.
it('renders the app shell without inline styles beyond @fonts', function () {
    // Drops the @vite tags so the page also renders without public/build. The fake
    // extends Vite without overriding fonts(), so @fonts still reads the real manifest.
    $this->withoutVite();

    $html = $this->get(route('login'))->assertOk()->getContent();

    preg_match_all('/<style\b[^>]*>(.*?)<\/style>/s', $html, $styles);

    foreach ($styles[1] as $css) {
        $leftover = trim(preg_replace([
            '/@font-face\s*\{[^}]*\}/',
            '/:root\s*\{(\s*--font-[a-z0-9-]+:\s*"[^"]+";)+\s*\}/',
            '/\.font-([a-z0-9-]+)\s*\{\s*font-family:\s*var\(--font-\1\);\s*\}/',
        ], '', $css));

        expect($leftover)->toBe('', "Inline <style> with more than the @fonts output: {$leftover}");
    }

    expect($html)
        ->not->toContain('oklch(')
        // R7: the inline script that applies the system theme before paint stays.
        ->toContain("window.matchMedia('(prefers-color-scheme: dark)')");
});
