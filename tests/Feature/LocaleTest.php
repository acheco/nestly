<?php

// Spec 001, R1 (D-085): the interface is in Spanish, so the server HTML declares it.
it('renders pages with the Spanish lang attribute', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="es"', false);
});

// Spec 001, R3 (D-085, D-092): framework validation messages, field names included, are in Spanish.
it('shows framework validation errors in Spanish', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors([
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
});
