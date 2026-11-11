<?php

// Spec 001, R1 (D-085): the interface is in Spanish, so the server HTML declares it.
it('renders pages with the Spanish lang attribute', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="es"', false);
});
