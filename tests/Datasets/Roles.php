<?php

use App\Enums\TeamRole;

/*
| Shared across policy tests. Pest loads tests/Datasets automatically,
| so a file-local dataset('roles') would only duplicate this one.
*/
dataset('roles', [
    'owner' => [TeamRole::Owner],
    'admin' => [TeamRole::Admin],
    'member' => [TeamRole::Member],
    'viewer' => [TeamRole::Viewer],
]);

/*
| Role → expected result for abilities reserved to owner and admin
| (PRD §1.6.2: budgets, categories, recurring expenses, team accounts).
*/
dataset('only owner and admin', [
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
    'viewer' => [TeamRole::Viewer, false],
]);
