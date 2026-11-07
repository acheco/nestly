<?php

// Spike for spec 001: checks that pest-plugin-browser runs on native Windows.

it('renders the login page in a real browser', function () {
    visit('/login')
        ->assertSee('Log in to your account')
        ->assertNoJavaScriptErrors();
});
