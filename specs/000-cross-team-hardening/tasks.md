# Tasks: cross-team hardening

Rama: `test/cross-team-hardening`. Se abre cuando `chore/adopt-sdd` esté terminada (D-014 y arreglos de CI) y `composer ci:check` pase en verde.

## Reglas

- El actor es el rol más privilegiado que puede hacer la acción; no se prueban owner y admin a la vez.
- Mutation check solo en las tareas 1 y 2.

## Tareas

1. [x] Admin de ambos teams en transactions (con mutation check)
2. [x] PATCH con `account_id` y `category_id` ajenos (con mutation check)
3. [x] Actor admin en POST/PATCH/DELETE de transactions y transfers
4. [ ] Fix de `TeamMemberController::destroy` a 404, con el test primero
5. [ ] Invitaciones, miembros y acciones sobre teams ajenos
6. [ ] Policies de Account y RecurringExpense
7. [x] Evaluar FKs compuestas (`account_id`, `team_id`) y (`category_id`, `team_id`) para que PostgreSQL garantice que nada apunte a otro team
