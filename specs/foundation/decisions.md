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
- **Estado:** parcialmente reemplazada por D-077 en lo que el admin puede hacer con los miembros.
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

### D-077 · Qué puede hacer el admin con los miembros
- **Fecha:** 2026-11-05
- **Estado:** aprobada. Reemplaza en parte a D-003.
- **Contexto:** D-003 dice que el admin "gestiona todo, excepto eliminar el team", pero en el código el admin no gestiona miembros y puede invitar con cualquier rol, incluido owner. Eso permite dos bugs en `main`: un admin invita a alguien como owner y el team acaba con dos owners, y un owner se cambia el rol a sí mismo y el team se queda sin owner.
- **Decisión:**
    - El admin invita y elimina members y viewers, y cambia el rol entre esos dos.
    - El admin no actúa sobre otros admins ni sobre el owner, y no puede ascender a nadie a admin.
    - El admin solo cancela invitaciones de member y de viewer.
    - Hasta la spec `003`, nadie puede invitar como owner ni cambiar el rol de un owner, tampoco el propio owner.
- **Consecuencias:**
    - `TeamPolicy` recibe también al miembro afectado y el rol asignado.
    - Se mantiene el orden de D-073: primero el filtro por team (404), después el rol.
    - D-016 y D-017 se aplican también cuando actúa un admin.
    - Se ajustan los tests que esperan que el admin no pueda hacer esto, y se corrigen los dos bugs con tests primero.

### D-081 · El owner no puede salir de su team
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** `TeamPolicy::leave` ya lo aplica, pero no había decisión.
- **Decisión:** el owner no puede salir de su team. Hoy su única salida es eliminar el team.
- **Consecuencia:** se revisa cuando exista la transferencia de ownership (spec `003`).

### D-082 · Los teams personales no se eliminan ni se abandonan
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** `TeamPolicy` ya lo aplica, pero no había decisión.
- **Decisión:** un team con `is_personal = true` no se puede eliminar ni abandonar.
- **Consecuencia:** qué pasa con él cuando se borra el usuario se decide en la spec `003`.

### D-089 · Un team tiene exactamente un owner
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** la constitución dice que un team siempre tiene un único owner, pero ninguna decisión lo fijaba. Se deducía de D-077 (nadie invita como owner ni cambia el rol de un owner) y de D-081 (el owner no puede salir). `main` lo incumple de dos formas: un admin puede invitar a alguien como owner, y un owner puede bajarse a sí mismo de rol.
- **Decisión:** hasta la spec `003`, todo team tiene exactamente un owner: el usuario que lo creó. Ninguna operación puede dejarlo con cero owners ni con más de uno: invitar, aceptar una invitación, cambiar un rol, eliminar a un miembro o salir del team.
- **Consecuencias:**
    - Lo aplican las policies y las actions de miembros (D-077, D-081).
    - Los dos bugs de `main` se corrigen en `fix/d-077-member-management`, con los tests primero.
    - Ese fix añade un índice único parcial en la tabla de membresías, para que PostgreSQL impida dos owners, con un test de esquema. El índice no puede impedir que quede cero.
    - La spec `003` la revisa si permite varios owners o transferir el ownership.

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
- **Consecuencias:**
    - Si vuelve a member, puede reactivarlas.
    - El dueño sigue viendo el saldo y el historial de sus cuentas archivadas mientras sea miembro del team.

### D-018 · Validación de `accounts_type_valid`
- **Fecha:** 2026-11-02
- **Decisión:** la violación del CHECK `accounts_type_valid` se traduce a **422**, igual que `categories_kind_valid`.
- **Consecuencia:** documentar el CHECK en ERD §4.4.3.

### D-084 · Moneda por cuenta
- **Fecha:** 2026-11-05
- **Estado:** aprobada. Se implementa en la spec `002-accounts`; el detalle está en `specs/backlog.md`.
- **Contexto:** `teams.currency` existe con USD por defecto, pero nadie la elige ni la valida, y solo el seeder la cambia. Una familia puede tener cuentas en varias monedas, por ejemplo DOP y USD, y una moneda por team no lo permite.
- **Decisión:**
    - **Moneda de la cuenta:** cada cuenta tiene una moneda ISO 4217, obligatoria al crearla. Solo se aceptan las de la lista `config('nestly.currencies')` (hoy DOP, USD y EUR), que se amplía añadiendo una línea. Quedan fuera las monedas con más de 2 decimales, porque los montos son `decimal(14,2)`.
    - **Inmutable con movimientos:** la moneda se puede cambiar mientras la cuenta no tenga movimientos. Después, la action rechaza el cambio con 422.
    - **Transferencias entre monedas:** cada pata guarda el monto en la moneda de su cuenta: `amount` en la de origen y `to_amount` en la de destino. La tasa queda implícita, no se guarda, y no se consulta ninguna API de cambio.
    - **Transferencias en la misma moneda:** `to_amount` es opcional. Si se omite, se copia `amount`; si llega distinto, la respuesta es 422.
    - **Sin conversión:** los KPIs y los presupuestos se agrupan por moneda. La conversión queda para v1.1.
    - **Presupuestos:** cada budget tiene moneda, y hay uno como máximo por categoría, moneda y periodo. `spent` suma solo las transacciones de cuentas en esa moneda.
    - **Cuentas privadas:** su moneda es visible para el team, como su nombre. No es un saldo (D-011).
    - **`teams.currency`:** se mantiene, sin renombrar, como moneda por defecto del team. Rellena la moneda al crear una cuenta y ordena los grupos de los KPIs. No es un invariante: se puede editar siempre y no afecta a las cuentas existentes. Los teams nuevos, incluido el personal, toman `config('nestly.default_currency')`.
- **Consecuencias:**
    - D-022 se lee así: en los KPIs cuenta el monto de la pata del lado team, en la moneda de su cuenta. Una transferencia team → team entre monedas sigue sin contar.
    - La reconciliación solo exige montos iguales en las dos patas si sus cuentas tienen la misma moneda.
    - `AccountController::index` deja de formatear todas las cuentas con la moneda del team.
    - ERD, TRD y PRD reciben una nota que remite aquí donde hablen de una moneda por team (D-071).

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

### D-079 · Solo el dueño saca dinero de su cuenta privada
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** está en PRD §1.6.3 y en `TransferRules`, pero no había decisión.
- **Decisión:** solo el dueño de una cuenta privada crea transferencias que salen de ella. Cualquier rol que pueda crear transacciones puede transferir hacia ella.
- **Consecuencias:**
    - Las ediciones también cumplen esta regla: si después de editar la transferencia sale de una cuenta privada, solo puede hacer la edición su dueño, aunque D-021 permita editar a otros.
    - Borrar una transferencia que sale de una cuenta privada sigue D-021 (creador, dueño de la cuenta privada o admin), porque borrarla no saca dinero de la cuenta: se lo devuelve.

### D-080 · La pata privada solo la ve su dueño
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** D-011 protege el saldo, pero `TransactionPolicy::view` solo exige poder ver el nombre de la cuenta.
- **Decisión:** la fila de una transferencia en una cuenta privada solo la ve el dueño de la cuenta, ni siquiera el owner del team. La pata del lado team sigue visible para el team (D-022).
- **Límite aceptado:** la pata del team muestra su monto en la moneda de su cuenta (D-084). Si las dos cuentas tienen la misma moneda, es el mismo monto que la pata privada, y un miembro puede sumar lo que entró y salió de la cuenta privada a través del team. Con monedas distintas no puede deducirlo sin conocer la tasa. En ambos casos el saldo real sigue oculto, porque `initial_balance` no es visible.
- **Consecuencia:** cambiar `TransactionPolicy::view` antes de que exista cualquier ruta de lectura de transacciones (hoy no hay ninguna).

---

## Reconciliación

### D-030 · La reconciliación solo reporta (20 oct 2026)
- **Decisión:** el job de reconciliación de saldos solo reporta desviaciones. La corrección es un paso manual explícito (`nestly:reconcile --fix`), nunca automático.

---

## Categorías

### D-083 · Categorías de sistema
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** `CategoryPolicy` ya lo aplica, pero no había decisión.
- **Decisión:** las categorías con `is_system = true` se pueden archivar, pero nadie puede editarlas ni eliminarlas, tampoco el owner.
- **Consecuencia:** ninguna en el código actual.

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

### D-076 · Ampliación de la mitigación de D-060
- **Fecha:** 2026-11-04
- **Estado:** aprobada.
- **Contexto:** borrar un usuario deja su team personal sin miembros y pone `created_by` en `NULL` en sus transacciones, de forma irreversible.
- **Decisión:** mientras no exista la anonimización, el borrado también se bloquea si el usuario creó transacciones en cualquier team.
- **Consecuencias:**
    - No se pierde autoría ni quedan datos inaccesibles con movimientos.
    - Puede seguir quedando un team personal vacío, sin datos financieros que perder.
    - Se retira cuando se implemente `003-user-anonymization`.

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
- **Estado:** implementada (migración `2026_11_03_120000_add_composite_team_foreign_keys`).
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
    - Quedan fuera `owner_id` y `created_by`, que apuntan a `users`, no a una tabla con `team_id`, y `audit_logs.model_id`, que es polimórfico (`model_type` + `model_id`) y no admite FK.

### D-075 · PostgreSQL 18 en local y en CI
- **Fecha:** 2026-11-03
- **Estado:** aprobada.
- **Contexto:** el entorno local corre PostgreSQL 18 y el CI la 16, y los docs declaran la 16. Una prueba que pasa en local no demuestra el comportamiento en CI. P-001 lo dejó en evidencia: en local, un `RESTRICT` devuelve `23001`, mientras los docs dicen `23503`, y no hay una versión de referencia para resolverlo.
- **Decisión:** el proyecto usa PostgreSQL 18 en local y en CI. Cambiar de versión mayor requiere una decisión nueva.
- **Consecuencias:**
    - La imagen de CI pasa a `postgres:18`.
    - README y `CLAUDE.md` declaran la 18. El TRD conserva su texto con una nota que remite a esta decisión (D-071).
    - Los SQLSTATE se verifican contra la 18 antes de escribir cualquier traducción de errores.

### D-078 · Reglas de dinero
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** estas reglas solo estaban en `CLAUDE.md`.
- **Decisión:**
    - Los montos son `decimal(14,2)` y se manejan como strings numéricos con BCMath a `Money::SCALE`. Nunca con floats.
    - `accounts.current_balance` solo cambia con `Account::applyTransaction()` dentro de una action. Revertir es aplicar el tipo opuesto.
    - Toda escritura bloquea las cuentas afectadas con `lockForUpdate()` dentro de `DB::transaction`. Varias cuentas se bloquean en una sola consulta ordenada por `id`.
    - Una transferencia son dos filas que comparten `transfer_group_id`, y solo la cambian sus propias actions.
- **Consecuencias:**
    - Ningún cambio de código.
    - `CLAUDE.md` remite a esta decisión.
    - La suite no cubre el orden de los locks; está anotado en `specs/backlog.md` (Concurrencia).

### D-085 · Interfaz en español en el MVP
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** la app mezcla español e inglés, `APP_LOCALE` es `en` y los textos en español están escritos en el código con `TODO(i18n)`.
- **Decisión:**
    - Toda la interfaz está en español en el MVP: pantallas, correos, mensajes de validación, toasts y el nombre del team personal.
    - Los textos del backend van en archivos de traducción (`lang/es`) y se usan con `__()`, no escritos en el código.
    - Cómo se traduce el frontend se decide en la spec `001`.
    - El código, los comentarios y los commits siguen en inglés.
- **Consecuencias:**
    - `APP_LOCALE` pasa a `es`, y se traducen las pantallas y correos del starter kit.
    - Los textos marcados con `TODO(i18n)` pasan a `lang/es`.
    - El multiidioma queda fuera del MVP.

### D-086 · PHP 8.5
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** el entorno local usa PHP 8.5 y el CI la 8.3, así que el CI no prueba el mismo entorno. `composer.json` permite desde la 8.3.
- **Decisión:** el proyecto usa PHP 8.5 en local, en CI y en producción. `composer.json` exige `^8.5`.
- **Consecuencias:**
    - Cambiar `tests.yml`, `composer.json` y `composer.lock`.
    - El TRD conserva su texto con una nota que remite aquí (D-071).

### D-087 · Commits de solo documentación
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** `vp check` también revisa los archivos `.md`, y eso hizo fallar el CI en el PR #12.
- **Decisión:** los commits que solo tocan documentación no ejecutan `composer ci:check`, pero sí `npm run check`.
- **Consecuencia:** corregir `CLAUDE.md`.

### D-088 · Sin umbral de cobertura
- **Fecha:** 2026-11-05
- **Estado:** aprobada.
- **Contexto:** la cobertura no se mide.
- **Decisión:** no hay umbral de cobertura. La calidad se protege con tests primero, rutas de error que comprueban que nada cambió y mutation checks.
- **Consecuencia:** el CI sigue con `coverage: none`.

---

## Frontend

### D-090 · Identidad visual propia
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** `docs/Design_System_Tokens_CSS.md` define una paleta (azul `#4F7CFF`, oscuro por defecto, Inter y JetBrains Mono) que nunca se aplicó: el código usa los tokens neutros del starter kit. El usuario diseñó en Figma una identidad propia.
- **Decisión:** la identidad visual de Nestly es la de la spec 001 (tokens de R4, fuentes y radios), con las capturas de `specs/001-frontend-foundation/design/` como referencia visual. Reemplaza a `docs/Design_System_Tokens_CSS.md`. Cualquier color nuevo se añade como token con valor para los dos temas y su contraste calculado.
- **Consecuencias:**
    - `docs/Design_System_Tokens_CSS.md` recibe una nota que remite a esta decisión (D-071).
    - La apariencia por defecto sigue siendo la del sistema.

### D-091 · Traducción del frontend
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** D-085 deja a la spec 001 decidir cómo se traduce el frontend. Hay un solo idioma en el MVP.
- **Decisión:** el frontend traduce con un helper propio, `t()`, sobre `lang/es.json`, la misma fuente que `__()`. Las claves son el texto en inglés, los reemplazos siguen la sintaxis de Laravel y la firma es compatible con `trans()` de laravel-vue-i18n. No hay pluralización. Una clave sin traducir rompe el CI.
- **Consecuencias:**
    - No se añade ninguna librería de i18n (D-072). Si llega el multiidioma, migrar a laravel-vue-i18n es buscar y reemplazar.
    - La constitución, en §2 "Idioma", remite a esta decisión.

### D-092 · Textos de Laravel en español por copia única
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** los mensajes de validación, autenticación y correos de Laravel y Fortify suman cientos de líneas. `laravel-lang` los trae traducidos, pero `lang:update` sobrescribe las correcciones a mano y el paquete depende de dos paquetes abandonados.
- **Decisión:** `lang/es` se genera una vez con `laravel-lang/lang` y `laravel-lang/starter-kits`, que se desinstalan en el mismo commit. A partir de ahí los archivos se mantienen a mano. Un test de paridad avisa cuando el framework añade claves a `auth`, `pagination`, `passwords` o `validation`.
- **Consecuencias:**
    - No queda dependencia en `composer.json`.
    - `lang/` lleva una nota de procedencia con versiones y licencia MIT.

### D-093 · Formato regional es-DO
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** la moneda por defecto es DOP (D-084) y hacía falta fijar cómo se muestran dinero y fechas.
- **Decisión:** el dinero y las fechas se formatean con el locale `es-DO`. Los montos se pasan a `Intl` como string y con `currencyDisplay: 'symbol'`. Las fechas de movimientos son relativas hasta 6 días y absolutas desde 7.
- **Consecuencia:** ningún componente formatea dinero o fechas por su cuenta: usan los helpers de la spec 001.

### D-094 · Estrategia de tests de frontend y E2E
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** la constitución deja la estrategia de tests de frontend y E2E para la spec 001.
- **Decisión:** cada criterio se prueba en el nivel más barato: Vitest (incluido en vite-plus) para funciones puras, Pest feature para lo que es HTTP y `pestphp/pest-plugin-browser` con Playwright solo para lo que necesita layout o JavaScript. No hay tests de componentes Vue. En CI se instala solo el headless shell de Chromium.
- **Consecuencias:**
    - Dos dependencias de desarrollo nuevas: `pestphp/pest-plugin-browser` y `playwright`.
    - El CI tarda unos 30 s más por run (PR #17, una medición).
    - La constitución resume esta decisión en §5 "Frontend y E2E", que deja de remitir a la spec 001, y el punto se quita de su sección "Pendiente".

### D-095 · Accesibilidad WCAG 2.2 AA
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** los docs piden WCAG 2.1 AA, pero ninguna decisión lo fija ni dice cómo se verifica.
- **Decisión:** toda la UI cumple WCAG 2.2 AA. El contraste se calcula con números para los dos temas. Foco visible, movimiento reducido, foco no tapado (2.4.11) y tamaño de objetivos (2.5.8) se prueban en el navegador. axe complementa esos tests; no los sustituye.
- **Consecuencias:**
    - Cada token de color nuevo trae su contraste calculado.
    - La constitución la añade a §5 como criterio de calidad.

### D-096 · Entradas de navegación
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** el menú del starter kit enlaza a recursos externos, y las capturas de Figma muestran secciones que todavía no existen.
- **Decisión:** el menú solo enlaza a páginas que existen. La barra inferior móvil tiene como máximo 3 secciones más "Más". Cada spec que añade una sección declara si va en la barra o en "Más". Mover una sección de la barra a "Más" requiere aprobación del usuario.
- **Consecuencia:** al cerrar la spec 001 el menú solo tiene Inicio.

### D-097 · Playwright fijado en 1.61.1 por Smart App Control
- **Fecha:** 2026-11-10
- **Estado:** aprobada.
- **Contexto:** el equipo de desarrollo tiene Smart App Control activado. Bloquea el Chromium de Playwright 1.65.0 (revisión 1254) por falta de reputación: Code Integrity registra el bloqueo (eventos 3033 y 3077) y Node lo reporta como `spawn UNKNOWN`. El Chromium de Playwright 1.61.1 (revisión 1228) sí arranca. En el spike del PR #17 la 1.65 corrió en Windows; lo más probable es que Smart App Control estuviera entonces en modo evaluación. `pest-plugin-browser` exige como mínimo Playwright 1.59.1, y Smart App Control no admite excepciones por archivo.
- **Decisión:** `playwright` se fija a la versión exacta `1.61.1` en `package.json`, sin `^`, en local y en CI.
- **Consecuencias:**
    - Antes de subir Playwright se comprueba que el Chromium de la nueva versión arranca con Smart App Control activado (`specs/backlog.md`).
    - El CI instala el headless shell de esa misma versión.
    - Dependabot solo vigila las GitHub Actions, así que no propondrá subirla.
    - Smart App Control no se desactiva: en Windows 11 no se puede volver a activar sin reinstalar.

---

## Pendientes

### P-001 · SQLSTATE de RESTRICT
- **Estado:** cerrada el 2026-11-03.
- **Pregunta original:** los docs dicen `23503` y en local se observó `23001`. Había que verificar de dónde sale el `23001` (¿un trigger o un `RAISE` propio en alguna migración?) y qué devuelve Postgres 16 en CI antes de escribir la traducción del error.
- **Resultado:** en PostgreSQL 18 (D-075), borrar una fila retenida por una FK `RESTRICT` devuelve `23001`, en local y en CI. Lo fija un test en `ConstraintViolationsTest`. No viene de código propio: las migraciones no tienen triggers, `RAISE` ni funciones.
- **Traducción:** no cambia. Ningún usuario puede provocar hoy este error y, si llega a la base, es un bug y debe dar 500. Si la action de borrado no lo rechaza antes (D-015), la traducción debe usar `23001`, no `23503`.
- **Sin verificar:** no se midió PostgreSQL 16, así que no se sabe si el `23001` viene del cambio de versión. Con D-075 ya no afecta a nada.
