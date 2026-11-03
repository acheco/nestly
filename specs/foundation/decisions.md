# Decisiones de Nestly

Registro de decisiones de negocio y técnicas. **Este archivo, junto con el código, manda sobre el PRD, el TRD y el ERD** cuando haya contradicción.

Formato: cada decisión tiene ID, fecha, contexto breve, decisión y consecuencias. No se borran decisiones: si una cambia, se marca como `Reemplazada por D-XXX`.

---

## Teams y roles

### D-001 · Team personal por defecto
- **Fecha:** anterior a nov 2026 (PRD v2.5)
- **Decisión:** al registrarse se crea un Team con el usuario como owner y `is_personal = true`. `is_personal` se fija al crear el team y **no** se recalcula según la cantidad de miembros.
- **Consecuencia:** se descarta la idea de personal/family como estado derivado del conteo de miembros.

### D-002 · Naming: se mantiene `Team`
- **Decisión:** se conserva el modelo `Team` del starter kit y la columna `team_id`. No se renombra a `Workspace`.

### D-003 · Matriz de roles
- **Decisión:**
    - **owner:** hace y ve todo.
    - **admin:** gestiona todo, excepto eliminar el team.
    - **member:** solo registra transacciones y edita las propias. No gestiona cuentas, budgets ni categorías.
    - **viewer:** solo lectura.

### D-004 · Eliminar un team usa soft delete
- **Fecha:** 2026-11-02
- **Contexto:** ERD §4.3.10 y PRD §1.6.4 hablan de cascade ("se elimina con todo su contenido"), pero el código usa soft delete y conserva cuentas y categorías.
- **Decisión:** se mantiene el soft delete. Son datos financieros y deben poder recuperarse y auditarse.
- **Consecuencia:** corregir ERD §4.3.10 y PRD §1.6.4. Una purga definitiva, si llega a hacer falta, será un proceso explícito aparte.

---

## Cuentas

### D-010 · No hay finanzas privadas dentro de un team
- **Decisión:** los budgets y los datos financieros pertenecen al team. Quien quiera llevar finanzas propias crea un team aparte donde esté solo.

### D-011 · Cuentas privadas: solo para transferencias familiares
- **Decisión:** dentro de un team familiar puede haber cuentas privadas, pero **solo** para registrar transferencias. No admiten ingresos ni gastos sueltos.
- Su saldo solo lo ve su dueño, ni siquiera el owner del team.
- Las puede crear cualquier rol excepto viewer.

### D-012 · Dirección de las transferencias con cuentas privadas (14 oct 2026)
- **Decisión:** solo team → privada o privada → team. **Nunca** privada → privada.

### D-013 · No hay cuentas privadas en teams personales (14 oct 2026)
- **Decisión:** en un team con `is_personal = true` todas las cuentas son de tipo `team`.

### D-014 · Dónde se aplica D-013: action, no policy
- **Fecha:** 2026-11-02
- **Contexto:** los docs la ponían como 422 en la action, pero un test la exigía en `AccountPolicy::create`.
- **Decisión:** es una regla de negocio, no de autorización.
    - `AccountPolicy` **no** mira `is_personal`.
    - La action de crear cuenta rechaza con **422** una cuenta privada en un team personal.
    - El controller expone a la UI el flag `can.createPrivateAccount = ! $team->is_personal && Gate::allows(...)`.
- **Consecuencia:** el test "private accounts cannot be created in a personal team" comprueba el flag en false. El 422 se cubre con un test propio.
- **Estado:** parcial. La policy y el flag están implementados. El 422 está pendiente porque la action de crear cuenta aún no existe (spec `002-accounts`); su test está marcado con `->todo()`.

### D-015 · Eliminar o archivar cuentas (23 oct 2026)
- **Decisión:** una cuenta solo se elimina si no tiene movimientos. Si tiene, se archiva.

### D-016 · Cuentas privadas al salir del team (23 oct 2026)
- **Decisión:** al salir del team o ser removido, las cuentas privadas del miembro se archivan automáticamente.

### D-017 · Cuentas privadas al degradar un member a viewer
- **Fecha:** 2026-11-02
- **Contexto:** hoy el viewer sigue viendo el saldo, pero no puede gestionar la cuenta, y los docs no cubren el caso.
- **Decisión:** al degradar a viewer, sus cuentas privadas se archivan, igual que en D-016. Es consistente con D-011, porque un viewer no puede tener cuentas privadas.
- **Consecuencia:** si vuelve a member, puede reactivarlas.

### D-018 · Validación de `accounts_type_valid`
- **Fecha:** 2026-11-02
- **Decisión:** la violación del CHECK `accounts_type_valid` se traduce a **422**, igual que `categories_kind_valid`.
- **Consecuencia:** documentar el CHECK en ERD §4.4.3.

---

## Transacciones y transferencias

### D-020 · No hay transferencias con fecha futura (9 oct 2026)
- **Decisión:** solo se permiten fechas de hoy o anteriores, porque un registro futuro no refleja la cuenta real y puede no ocurrir.
- "Hoy" es el día en la zona horaria del usuario que registra.

### D-021 · Quién edita o borra transferencias con cuenta privada (12 oct 2026)
- **Decisión:** la pueden editar o borrar el creador, el dueño de la cuenta privada y el admin, siempre que tengan el permiso correspondiente.

### D-022 · Las transferencias privadas cuentan en los KPIs (12 oct 2026)
- **Decisión:** las transferencias desde o hacia cuentas privadas **sí** cuentan como ingresos o gastos en los KPIs del team familiar. Team → team no cuenta.
- La pata del lado team lleva una categoría opcional.
- No se agrega `updated_by`; la trazabilidad se lleva en `audit_logs`.

---

## Reconciliación

### D-030 · La reconciliación solo reporta (20 oct 2026)
- **Decisión:** el job de reconciliación de saldos solo reporta desviaciones. La corrección es un paso manual explícito (`nestly:reconcile --fix`), nunca automático.

---

## Presupuestos

### D-040 · `Budget.spent` se calcula al leer
- **Fecha:** 2026-11-02
- **Contexto:** TRD §3.8.1 lo recalcula y persiste, pero la columna no existe en el ERD y la API lo calcula al leer.
- **Decisión:** se calcula al leer, sin columna. Solo se optimiza (columna o cache) si una medición lo justifica.
- **Consecuencia:** corregir TRD §3.8.1.

---

## Gastos recurrentes

### D-050 · Zona horaria de los recurrentes
- **Fecha:** 2026-11-02
- **Contexto:** el recurrente es del team, pero la timezone es del usuario. No estaba definido qué zona marca el "hoy" de la ejecución.
- **Decisión:** el recurrente guarda su propia columna `timezone`, tomada del creador en el momento de crearlo. El job usa esa zona.

### D-051 · Recurrente cuando su creador sale del team
- **Fecha:** 2026-11-02
- **Decisión:** el recurrente pertenece al team y sigue ejecutándose. El creador queda solo como referencia histórica (ver anonimización).

### D-052 · Recurrente con cuenta archivada
- **Fecha:** 2026-11-02
- **Decisión:** si la cuenta del recurrente se archiva, el recurrente se **pausa** automáticamente y se notifica a los owners y admins del team.

### D-053 · Días de aviso de recurrentes
- **Contexto:** TRD §3.8.2 dice "config por usuario", pero la columna está en cada recurrente.
- **Decisión:** se configura por recurrente, como en el esquema actual.
- **Consecuencia:** corregir TRD §3.8.2.

---

## Usuarios

### D-060 · Borrar usuario = anonimizar (23 oct 2026)
- **Decisión:** borrar un usuario lo anonimiza en vez de eliminar su fila.
- **Estado:** pendiente de implementar (spec `003-user-anonymization`).
- **Mitigación temporal (2026-11-02):** mientras no exista la anonimización, el borrado de cuenta se bloquea con un mensaje claro si el usuario tiene cuentas privadas, recurrentes creados o es el único owner de un team compartido. Así se evitan el 500 y los teams sin owner.

---

## Transversal

### D-070 · Offline en el MVP es solo lectura
- **Fecha:** 2026-11-02
- **Contexto:** el PRD pide una cola de mutaciones offline y el TRD habla de "offline básico".
- **Decisión:** en el MVP no se encolan mutaciones offline. Encolar movimientos de dinero choca con los locks y con el saldo en tiempo real.
- **Consecuencia:** corregir el PRD. Una cola de mutaciones se reevaluaría en una versión futura.

### D-071 · Fuente de verdad de la documentación
- **Fecha:** 2026-11-02
- **Decisión:** si el código y este archivo contradicen al PRD, el TRD o el ERD, mandan el código y este archivo. Los docs no se reescriben completos: se marcan las secciones obsoletas con una nota que remite a la decisión correspondiente.

### D-072 · Dependencias del TRD no instaladas
- **Fecha:** 2026-11-02
- **Decisión:** Pinia, zod, la librería de gráficos y vite-plugin-pwa **no** se instalan por estar en el TRD. Cada una se añade solo cuando una spec la justifique.

### D-073 · Un recurso de otro team responde 404
- **Fecha:** 2026-11-03
- **Contexto:** la regla solo estaba en `CLAUDE.md`. Responder 403 confirmaría que el recurso existe en otro team.
- **Decisión:** todo id recibido en una request se resuelve a través del team de la ruta. Si no pertenece a ese team, la respuesta es **404**, sea cual sea el rol del actor.
    - Aplica al recurso de la URL y a los ids del cuerpo de la request (`account_id`, `category_id`, ...).
    - Aplica también a un usuario que no es miembro del team cuando se le gestiona como miembro.
    - El filtro por team se aplica antes de cualquier comprobación de rol o de autoría.
    - Un usuario que no es miembro del team de la URL sigue recibiendo **403** de `EnsureTeamMembership`.
- **Consecuencia:** corregir `TeamMemberController::destroy`, que hoy responde con éxito a un usuario ajeno al team. Los tests cross-team usan como actor el rol más privilegiado que puede hacer la acción.

### D-074 · PostgreSQL garantiza que las referencias no crucen de team
- **Fecha:** 2026-11-03
- **Estado:** aprobada, pendiente de implementar. Se implementa después de la tarea 6 de `000-cross-team-hardening`, como tarea aparte con su propia rama.
- **Contexto:** D-073 protege estas referencias solo en la aplicación. Una FK simple comprueba que la fila exista, no que sea del mismo team, así que un bug en una action podría escribir una referencia a otro team sin que la base lo impida.
- **Decisión:** toda referencia entre tablas con `team_id` usa una FK compuesta `(columna, team_id)` hacia `(id, team_id)`.
    - Tablas referenciadas, con un `UNIQUE (id, team_id)` nuevo: `accounts`, `categories`, `recurring_expenses`.
    - FKs que se reemplazan: `transactions` (`account_id`, `category_id`, `recurring_id`), `recurring_expenses` (`account_id`, `category_id`), `budgets` (`category_id`), `categories` (`parent_id`).
    - Se conserva `ON DELETE RESTRICT`. `transactions.recurring_id` usa `ON DELETE SET NULL (recurring_id)`, para no anular también `team_id`.
    - Los filtros de D-073 se mantienen: siguen dando el 404, y la FK es la última red.
- **Consecuencias:**
    - Una sola migración nueva, sin editar las existentes. Antes de cambiar nada, aborta si encuentra filas cruzadas.
    - `ConstraintViolations` no cambia: estas violaciones no las puede provocar un usuario, y si ocurren son un bug y deben dar 500.
    - Se añaden tests de esquema en `tests/Feature/Database` para las 7 relaciones.
    - Quedan fuera `owner_id` y `created_by`, que apuntan a `users`, no a una tabla con `team_id`.

---

## Pendientes

### P-001 · SQLSTATE de RESTRICT
- Los docs dicen `23503` y en local se observó `23001`. Hay que verificar de dónde sale el `23001` (¿un trigger o un `RAISE` propio en alguna migración?) y qué devuelve Postgres 16 en CI antes de escribir la traducción del error.
