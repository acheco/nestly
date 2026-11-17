<?php

// Spec 001, R7: no theme flash. With the appearance cookie set to dark, the server HTML
// already carries the dark class on <html>; with light, it does not. The logic exists
// (HandleAppearance and app.blade.php), so these tests pass from the start: they guard
// R7 against future changes (tasks.md 3.4).
it('sets the dark class on <html> from the appearance cookie', function (string $appearance, bool $isDark) {
    $this->withoutVite();

    // The browser writes this cookie, so it is excluded from encryption (bootstrap/app.php).
    $html = $this->withUnencryptedCookie('appearance', $appearance)
        ->get(route('login'))
        ->assertOk()
        ->getContent();

    // Only the opening <html> tag: the inline theme script also mentions "dark".
    preg_match('/<html\b[^>]*>/', $html, $tag);
    $htmlTag = $tag[0] ?? '';
    preg_match('/\bclass="([^"]*)"/', $htmlTag, $class);
    $classes = preg_split('/\s+/', $class[1] ?? '', -1, PREG_SPLIT_NO_EMPTY);

    expect($htmlTag)->not->toBe('', 'No <html> tag in the response')
        ->and(in_array('dark', $classes, true))->toBe($isDark, "<html> tag: {$htmlTag}");
})->with([
    'dark' => ['dark', true],
    'light' => ['light', false],
]);
