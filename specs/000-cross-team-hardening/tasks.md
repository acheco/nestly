# Tasks: cross-team hardening

Rama: `test/cross-team-hardening`. Se abre cuando `chore/adopt-sdd` esté terminada (D-014 y arreglos de CI) y `composer ci:check` pase en verde.

## Reglas

- El actor es el rol más privilegiado que puede hacer la acción; no se prueban owner y admin a la vez.
- Mutation check solo en las tareas 1 y 2.

## Tareas

1. [x] Admin de ambos teams en transactions (con mutation check)
2. [x] PATCH con `account_id` y `category_id` ajenos (con mutation check)
3. [x] Actor admin en POST/PATCH/DELETE de transactions y transfers
4. [x] Fix de `TeamMemberController::destroy` a 404, con el test primero
5. [x] Invitaciones, miembros y acciones sobre teams ajenos
6. [x] Policies de Account y RecurringExpense
7. [x] Evaluar FKs compuestas (`account_id`, `team_id`) y (`category_id`, `team_id`) para que PostgreSQL garantice que nada apunte a otro team
8. [x] Unificar PostgreSQL en la 18 (D-075): imagen de CI, README, `CLAUDE.md` y notas en TRD y PRD
9. [x] Cerrar P-001 con el SQLSTATE que devuelva el CI en la 18 y ajustar la traducción del error si hace falta

## Plan de implementación de D-074

Va en su propia rama, después de esta. No forma parte de las tareas anteriores.

1. Tests de esquema en `tests/Feature/Database`, primero en rojo: cada una de las 7 relaciones rechaza una referencia a otro team.
2. Test de `category_id` nulo: una transacción sin categoría se sigue aceptando con la FK compuesta.
3. Migración única con los `UNIQUE` y las FKs de D-074, que aborta si encuentra filas cruzadas.
4. Mutation check: sin el filtro de la action, la escritura falla por la FK.

## Quick fixes

Rama: `fix/quick-fixes`. Se abre desde `main` actualizado, después del merge del PR #11 (D-074).

1. [x] MustVerifyEmail
2. [x] AuditLog
3. [ ] Validación de icon
4. [ ] D-060: bloqueo temporal del borrado de usuario
5. [ ] PR y merge
