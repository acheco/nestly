<?php

// Spec 001, R13: proves the browser test stack (pest-plugin-browser + Playwright)
// runs locally and in CI. Selectors only, so the test survives the translation in phase 2.
it('renders the login form in a real browser', function () {
    visit('/login')
        ->assertVisible('input[name="email"]')
        ->assertVisible('input[name="password"]')
        ->assertNoJavaScriptErrors();
});
