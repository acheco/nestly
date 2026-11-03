---
version: "2.20"
---

**Nota de versión 2.20:** sesiones del 23 y 26 oct, Fase 2 (autorización
transversal, PRD v2.9, ERD v2.15):

1. **Policies alineadas con la matriz de roles** (§3.6.1): `AccountPolicy`
   separa `view` (nombre, todo miembro) de `viewBalance` (privada → solo
   su dueño mientras siga en el team); `create(User, Team,
   AccountVisibility)`; gestionar una privada es solo de su dueño.
   `RecurringExpensePolicy` pasa a admin+ sin privilegio por autoría y
   suma `pause`/`resume`. `BudgetPolicy` y `CategoryPolicy` ya cumplían;
   ahora todas tienen tests de matriz por rol.
2. **Errores de constraint → 422** (§3.6.2): `App\Support\ConstraintViolations`
   + `$exceptions->map()`. Solo constraints que la entrada del usuario
   puede violar; los que solo viola un bug siguen en 500.
3. **`RemoveTeamMemberAction`** (§3.6.3): salir o ser removido de un team
   archiva las cuentas privadas del miembro en ese team (RF-02.7).
4. **`categories_kind_valid`**: `CHECK` de `categories.kind` en una
   migración nueva (no se edita la original, ya publicada).
5. Datasets compartidos de roles en `tests/Datasets/Roles.php`.

**Nota de versión 2.19:** sesiones del 20 al 22 oct, reconciliación
implementada (§3.8.3): `BalanceReconciler` (`check()` con drifts, grupos
rotos y transacciones sueltas; `fixBalance()` con lock → recálculo),
comando `nestly:reconcile {--team=} {--fix}` y
`ReconcileAccountBalancesJob` semanal que solo escribe en el log. Cierra la
Fase 1 de la Ruta al MVP. Helper de test `driftBalance()` en
`tests/Pest.php`.

**Nota de versión 2.18:** sesión del 20 oct, diseño de la reconciliación
(§3.8.3, ERD v2.12):

1. **El job solo reporta (opción A).** Una desviación de saldo es la
   huella de un bug; corregirla en silencio lo esconde y lo deja
   repetirse. Corregir es un paso humano explícito:
   `php artisan nestly:reconcile --fix`. Resuelve la contradicción con el
   ERD §4.5, que decía "detecta y corrige".
2. **El saldo esperado se calcula en SQL, no con `signedAmount()`.** El
   verificador tiene que ser un cálculo independiente del código que
   verifica: si `signedAmount()` o `applyTransaction()` tuvieran un bug,
   un reconciliador basado en ellos coincidiría consigo mismo. `numeric`
   de PostgreSQL es exacto (sin float) y la consulta devuelve solo las
   cuentas desviadas. La regla del signo queda en dos lugares a
   propósito (enum y `CASE`); un test con datos creados por las actions
   detecta si divergen.

**Nota de versión 2.17:** sesiones del 16 y 19 oct, edición de
transferencias (ERD v2.11):

1. **`TransferRules`** (`App\Actions\Transactions\Support`, §3.15.9):
   clase `final` inyectada por constructor con las reglas que comparten
   crear y editar transferencias. `assertValidInput()` corre antes de la
   transacción de BD (origen ≠ destino, sin fecha futura);
   `assertAccounts()` y `resolveCategoryIds()` corren con las cuentas
   bloqueadas (privada ajena 403, privada → privada 422, archivadas 422,
   categoría). Es una clase y no un trait porque todo le llega por
   parámetro: no depende del estado de quien la usa.
2. **`UpdateTransferAction`** (§3.15.9): edita las dos filas existentes
   del grupo. Se descartó "borrar + crear": cambiaría `transfer_group_id`
   (rompe la URL y la auditoría) y `created_by` (el creador perdería el
   permiso del caso A). Bloquea las patas y luego hasta 4 cuentas (viejas
   y nuevas) en una sola consulta por `id`; el permiso del caso A se
   evalúa contra las cuentas **viejas**; todas las validaciones corren
   antes de mutar.
3. **Endpoint `PATCH /{current_team}/transfers/{transfer_group_id}`**
   (§3.5.2, §3.15.9) con `whereUuid`, `UpdateTransferRequest` y el gate
   de rol `TransactionPolicy::updateTransfer()` (§3.6).
4. Helpers de test `updateTransferData()` y `transferLegs()` en
   `tests/Pest.php`.
5. **Deuda cerrada:** la regla de fecha futura, que estaba copiada en
   `CreateTransactionAction`, `UpdateTransactionAction` y
   `TransferRules`, vive ahora en `TransactionDateRule`
   (`App\Actions\Transactions\Support`), inyectada en los tres (§3.15.3).

**Nota de versión 2.16:** sesión del 14 oct, transferencias entre
cuentas privadas (ERD v2.10):

1. **Privada → privada prohibida** (§3.15.4, paso 6): una cuenta privada
   solo mueve dinero a través del perímetro del team. Si origen y destino
   son `private` → `ValidationException` (422) en `to_account_id`,
   también si las dos son del mismo dueño. Aplica en cualquier team, sin
   mirar `is_personal`.
2. **Orden:** se evalúa con las cuentas bloqueadas (necesita la
   `visibility` de ambas), **después** del 403 de privada ajena y
   **antes** de las archivadas. Así sacar de la privada de otro hacia
   una privada da 403: el usuario no aprende nada sobre el destino de
   una operación que de todos modos no puede hacer.
3. **Categoría en transferencias** (§3.15.4) sin cambios: team → team con
   categoría sigue siendo 422; con privada → privada prohibida no queda
   un cuarto caso.
4. Dos tests nuevos en `CreateTransferActionTest`.
6. **Categoría en transferencias implementada** (§3.15.4, paso 8):
   tenancy (404) → perímetro (422) → `kind` de la pata `team` (422). La
   pata `team` se deduce de la `visibility` del origen, sin un `if` por
   dirección. Tests de action y HTTP.
5. **Sin cuentas privadas en un team personal** (ERD §4.3.8, §3.6): la
   action de crear cuentas responde 422 en `visibility` si el team tiene
   `is_personal = true`. Se ata a `is_personal` (fijo) y no al conteo de
   miembros. Pendiente de implementar con el CRUD de cuentas.

**Nota de versión 2.15:** sesión del 12 oct, borrado de transferencias y
reglas de transferencias (PRD v2.7, ERD v2.9):

1. **`DeleteTransferAction`** (§3.15.8): bloquea las dos patas del grupo
   y luego ambas cuentas por `id`; `count() !== 2` → 404 (incluye una
   transferencia incompleta); permiso de creador / dueño de una cuenta
   involucrada / admin+ → 403; cuenta archivada → 422 en `account_id`;
   revierte ambas patas y las borra.
2. **Excepción justificada a "la autorización va en la policy":** no hay
   modelo `Transfer` que bindear. `TransactionPolicy::deleteTransfer(User, Team)`
   solo decide el rol (member+); la regla del caso A se decide en la
   action con las filas bloqueadas, porque decidirla antes del lock
   autorizaría contra datos que otra escritura podría cambiar
   (*time-of-check / time-of-use*). Precedente: el 403 de cuenta privada
   de `CreateTransferAction` (§3.6).
3. **Regla del caso A** (editar y borrar transferencias): creador, dueño
   de una cuenta privada involucrada o admin+, todos member+. Al editar,
   los datos nuevos pasan por las reglas de creación. Sin `updated_by`:
   la trazabilidad la da `audit_logs` (§3.15.5).
4. **Perímetro del team en los KPIs** (§3.5.3): reemplaza "los KPIs
   excluyen toda fila con `transfer_group_id`".
5. **Categoría opcional en transferencias que cruzan el perímetro**
   (§3.15.4): decidida, pendiente de implementar.
6. Helpers de test `recordTransfer()` y `teamUser()` en `tests/Pest.php`.

**Nota de versión 2.14:** sesión del 10 oct, borrado de transacciones
(§3.15.7):

1. **`DeleteTransactionAction`**: bloquea transacción y cuenta (vía team),
   revierte el efecto y borra. Rechaza patas de transferencia y cuentas
   archivadas (422). No necesita chequeo de cuenta privada: en una privada
   solo existen patas de transferencia.
2. **Borrado de transferencias desde la UI** (opción 1): el grid es único;
   el frontend elige el endpoint según `transfer_group_id` de la fila
   (`DELETE /transactions/{id}` o `DELETE /transfers/{transfer_group_id}`)
   y el modal avisa que se borran ambos movimientos. El 422 del backend es
   una guarda defensiva que un usuario normal no ve. Se descartó que
   `DELETE /transactions/{id}` delegue en silencio (borraría dos filas
   cuando se pidió una).
3. Los DTOs de transacciones se mueven a `App\DTOs\Transactions`.

**Nota de versión 2.13:** sesión del 9 oct, edición de transacciones
(§3.15.6):

1. **Estrategia de saldo: "revertir el efecto viejo + aplicar el nuevo"**
   (opción A). `current_balance` es un total acumulado, así que editar una
   transacción vieja no afecta a las posteriores. Se descartaron:
   recalcular desde el ledger en cada escritura (B), no guardar el saldo
   (C, contradice ERD §4.3.13) y guardar saldo corrido por fila (D).
2. **Las patas de una transferencia no se editan como transacciones**
   (422): el formulario de transacción tiene una sola cuenta y no puede
   describir una transferencia. Se editarán con `UpdateTransferAction`.
3. **Cuenta archivada:** no se edita ninguna transacción de una cuenta
   archivada (hay que reactivarla) ni se mueve una transacción hacia ella.
4. **Locks:** transacción primero, luego cuentas vieja y nueva en una sola
   consulta ordenada por `id`.
5. **`TransactionPolicy::update()`** exige rol member+ y ser el creador, o
   admin+.

**Nota de versión 2.12:** sesión del 9 oct, **no se permiten fechas
futuras** en transacciones ni transferencias (cambia la decisión de la
v2.10, que las permitía):

1. **Motivo:** el saldo de la app refleja lo que ya pasó en la cuenta
   real. Un registro a futuro es una intención que puede no ocurrir y
   obligaría al usuario a acordarse de borrarlo. Lo que todavía no pasó
   se modela con gastos recurrentes (el job crea la transacción el día
   que toca).
2. **"Hoy" es el día en la zona horaria del usuario**, no el del servidor
   (UTC): nueva columna `users.timezone` (default `UTC`) y
   `User::localToday()` (§3.6).
3. **Dos capas** (§3.6): trait `ValidatesTransactionDate` en ambos Form
   Requests y verificación en `CreateTransactionAction` /
   `CreateTransferAction` antes de abrir la transacción de BD (§3.15.3,
   §3.15.4). Tests con `travelTo()` cubren el borde de zona horaria.
4. `DemoSeeder` recorta a hoy las fechas relativas al mes actual.

**Nota de versión 2.11:** sincronización con la sesión del 8 oct
(endpoint de transacciones):

1. **`StoreTransactionRequest`** y **`TransactionController@store`**
   (§3.6): mismas convenciones que transferencias; `type` con
   `Rule::enum(TransactionType::class)` y `TransactionType::from()` en el
   controller.
2. **Campos que pone el sistema** (`transfer_group_id`, `recurring_id`)
   nunca tienen regla en `rules()`: `validated()` los descarta aunque
   lleguen en el request. Cubierto con un test que intenta forjar un
   `transfer_group_id` para saltarse la regla de cuenta privada (§3.6).
3. **Cuenta archivada** también se rechaza en `CreateTransactionAction`
   (422 en `account_id`, §3.15.3), igual que en transferencias.
4. **`Money::MAX`** y **`Money::fromInput()`** (§3.15.2): tope de
   `decimal(14,2)` en un solo lugar para ambos Form Requests, y conversión
   del monto validado a `numeric-string` comprobada con `is_numeric()`.

**Nota de versión 2.10:** sincronización con la sesión del 7 oct
(Form Request de transferencias y cuentas archivadas):

1. **`StoreTransferRequest`** (§3.6): reglas de formato por campo. Sin
   `exists` en los IDs de cuenta: la tenancy la resuelve solo la action
   (404 documentado, sin consulta duplicada que pueda desincronizarse).
   `uuid` sin versión (los IDs son v7, ver §3.4.1). Monto con
   `numeric|decimal:0,2|min:0.01|max:999999999999.99`. Se permiten fechas
   futuras.
2. **Cuenta archivada** (§3.15.4, paso 7): no se puede transferir desde ni
   hacia una cuenta archivada → `ValidationException` (422) en el campo de
   esa cuenta. Se verifica después del lock, no como filtro de la
   consulta, para distinguirlo del 404 de tenancy.
3. Corregido §3.4.1: `HasUuids` genera UUID v7, no `Str::orderedUuid()`.
4. **Rutas reales: `/{current_team}/...`, sin prefijo `/t/`** (§3.5.2).
   Se mantiene la convención del starter kit en vez de agregar `/t/`: el
   dashboard, los redirects (`RedirectsToCurrentTeam`), los URL defaults
   (`SetTeamUrlDefaults`) y Wayfinder ya están construidos sobre
   `{current_team}`. El middleware real es `EnsureTeamMembership`.
5. **`TransactionPolicy::create(User, Team)`** (§3.6): la policy solo
   decide el rol en el team (member+); tenancy, privacidad y cuenta
   archivada quedan en la action. Antes recibía una `Account`, lo que
   obligaba al controller a consultar la cuenta fuera del lock.
6. **`TransferController@store`** (§3.5.1): Post-Redirect-Get con
   `back()->with('success', …)`.

**Nota de versión 2.9:** sincronización con la implementación de las
sesiones del 3 y 6 oct (Fase 1b, transferencias):

1. **`CreateTransferAction` implementada** (§3.15.4): validación origen ≠
   destino antes de abrir la transacción, un solo `SELECT … FOR UPDATE`
   sobre ambas cuentas **en orden por `id`** para evitar deadlocks, 404
   vía `ModelNotFoundException` si alguna cuenta no es del team, 403 vía
   `AuthorizationException` si se saca dinero de una cuenta privada ajena,
   y resultado tipado en el DTO de salida `TransferResultData`.
2. **`Account::applyTransaction()`** centraliza la actualización de
   `current_balance` (§3.15.2); la usan las dos actions.
3. **`App\Support\Money::SCALE`** reemplaza la escala `2` escrita a mano
   en `TransactionType` y `Account` (§3.15.2).
4. **Límite del testing secuencial** documentado (§3.9): el orden de
   bloqueo no lo cubre ningún test; lo protege un comentario en el código.
   Nuevo riesgo en §3.13.
5. La antigua §3.15.4 (seeders y eventos de modelo) pasa a §3.15.5.

**Nota de versión 2.8:** alineado con PRD v2.6 y ERD v2.7 (privacidad y
permisos, sesión del 30 sep). La privacidad es la frontera del team; las
cuentas privadas solo admiten transferencias y su saldo solo lo ve su
dueño. Impacto técnico en §3.3.1 (serializado de saldos), §3.6
(autorización) y §3.15.3 (`CreateTransactionAction` rechaza cuentas
privadas; reglas de `CreateTransferAction`).

**Nota de versión 2.7:** sincronización con las decisiones de
implementación de las sesiones del 26–29 sep:

1. **Riesgo de versión de PHP cerrado** (§3.13). El proyecto declara
   `"php": "^8.3"` y fija `config.platform.php = 8.3.0` en
   `composer.json`, de modo que Composer resuelva siempre las
   dependencias como si corriera PHP 8.3 aunque el entorno local use una
   versión mayor (8.5). Verificado con `composer why-not php 8.3`: ningún
   paquete instalado exige más de 8.3.
2. **Manejo de dinero con BCMath** (`ext-bcmath` como requisito explícito
   en `composer.json`). Montos como `string` de punta a punta: columna
   `decimal`, cast `decimal:2` y funciones `bc*` con escala explícita.
   Nunca `float`. Ver §3.15.
3. **Actions de transacciones** con DTO de entrada y contexto explícito
   (`Team`, `User`), tenancy verificada con las relaciones del team,
   bloqueo pesimista de la cuenta y saldo actualizado en tiempo real.
   Ver §3.15.
4. **`teams` ampliada** con `currency`, `icon` y `color` (con defaults) y
   un `CHECK` de formato hex para `color`. Ver §3.4.1.
5. **Seeders sin `WithoutModelEvents`**: el `TeamObserver` que siembra las
   categorías por defecto es una invariante de dominio y debe ejecutarse
   también durante el seed. Ver §3.15.5 (antes §3.15.4).
6. Corregida la ruta del ejemplo de test de §3.9 (`/w/{id}` → `/t/{slug}`),
   pendiente del renombramiento de la v2.5.

**Nota de versión 2.6:** ajuste tras revisar el modelo real generado por
el starter kit (`app/Models/Team.php`, migraciones, `TeamPolicy`):

1. **UUID en vez de IDs autoincrementales**, en todas las tablas
   (incluida `users`), vía el trait `HasUuids` de Laravel. El starter kit
   trae `$table->id()` por defecto; se adapta a
   `$table->uuid('id')->primary()` + `foreignUuid()` en las FKs. Ver
   §3.4.1.
2. **No existe campo `type` en `Team`.** Se usa `is_personal` (booleano
   real del starter kit) tal cual viene: fijo al crear el team, sin
   recalcularse por cantidad de miembros. Ver PRD v2.5 §1.6.1.
3. **El pivote real se llama `team_members`** (no `team_user`) y usa un
   modelo Eloquent propio (`Membership`, vía `->using(Membership::class)`
   en `Team::members()`), no un pivote implícito. `TeamInvitation` es
   también un modelo aparte, con columna `code` (no `token`).
4. **La autorización corre por `TeamPolicy`** (`viewAny`, `view`,
   `update`, `leave`, `addMember`, `updateMember`, `removeMember`,
   `inviteMember`, `cancelInvitation`, `delete`), apoyada en
   `User::belongsToTeam()` / `User::hasTeamPermission()` — no en un
   middleware de autorización aparte. `EnsureTeamMember` (si se agrega)
   solo resolvería el team activo, no permisos.
5. `Team` usa `slug` como route key (`getRouteKeyName()`), no el `id` —
   las rutas `/teams/{team}` resuelven por slug.

**Nota de versión 2.5:** renombramiento de entidad **Workspace → Team**
(ver PRD v2.4 para la justificación completa). Impacto técnico: modelo
Eloquent `Team` (nativo del starter kit de Laravel con Jetstream/Fortify
+ Teams, sin rename); columna FK `workspace_id` → `team_id`; middleware
`EnsureWorkspaceMember` → `EnsureTeamMember`; trait/scope
`BelongsToWorkspace`/`WorkspaceScope` → `BelongsToTeam`/`TeamScope`;
segmento de ruta `/w/{workspace}/...` → `/{current_team}/...`; header futuro de
la API con Sanctum `X-Workspace-Id` → `X-Team-Id`. Ver §3.1.1, §3.3, §3.4
y §3.5.

**Nota de versión 2.3:** cambio de arquitectura de fondo respecto a v2.2 —
se reemplaza el modelo "Laravel API REST + Vue SPA/PWA desacoplada" por
**Laravel como monolito con Inertia.js + Vue**, que es el stack real que
se va a usar. Esto afecta autenticación, routing, estado en frontend, y
reencuadra el "API Spec" (ERD/API Spec §5) como diseño de la futura API
pública en vez de cómo funciona el MVP.

**Nota de versión 2.4:** decisión de mobile cerrada — se reemplaza
Capacitor por **React Native (Expo)** (Anthony domina más React que
Vue, y mobile ya trae curva de aprendizaje propia). Ya no es una
decisión condicional entre "webview vs API": se construye un cliente
mobile separado desde el inicio, consumiendo la API con Sanctum. Esa API
se construye en **v1.2**, después de cerrar el alcance del app de
escritorio/web (MVP + v1.1), no en paralelo. Ver §3.1.1, §3.1.2 y §3.2.

---

# Parte II — Técnico

## 3. TRD — Technical Requirements Document

**Versión:** 2.20
**Stack:** Laravel 13 (PHP 8.3+) + Inertia.js + Vue 3 + TypeScript + Tailwind + shadcn-vue + Wayfinder + PostgreSQL

### 3.1 Arquitectura General

#### 3.1.1 Estilo

- **Monolito Laravel + Inertia**: un único proyecto Laravel sirve tanto la
  lógica de dominio como las páginas — no hay una API JSON separada que
  el frontend consuma para el flujo principal.
- **Backend**: Laravel 13, controllers que devuelven `Inertia::render()`
  en vez de `JsonResource`.
- **Frontend**: Vue 3 + TypeScript + Vite, montado por Inertia
  (`createInertiaApp`). Sin Vue Router — la navegación la resuelve Inertia
  contra las rutas de Laravel.
- **Rutas tipadas**: **Wayfinder** genera funciones TS a partir de las
  rutas/controllers de Laravel, así el frontend llama
  `store()`/`update()` etc. con tipos, sin URLs ni parámetros a mano.
- **Auth**: guard `web` estándar (sesión + cookie), no Sanctum, para el
  flujo del monolito. CSRF se maneja automáticamente vía la cookie
  `XSRF-TOKEN` que Inertia ya envía en cada request.
- **Teams**: se usa el starter kit oficial de Laravel 13 con soporte de
  Teams (`laravel new --vue --teams` o equivalente). El scaffolding trae:
  modelo `Team` (slug como route key, flag `is_personal`, soft deletes),
  modelo pivote `Membership` (tabla `team_members`, con `role` string
  respaldado por el enum `TeamRole`), modelo `TeamInvitation` (tabla
  `team_invitations`, columna `code` en vez de `token`), y `TeamPolicy`
  con autorización granular vía el enum `TeamPermission`
  (`UpdateTeam`, `AddMember`, `UpdateMember`, `RemoveMember`,
  `CreateInvitation`, `CancelInvitation`, `DeleteTeam`) resuelta con
  `User::hasTeamPermission()`. Al registrarse, el starter kit crea
  automáticamente un team con `is_personal = true` y el usuario como
  owner; ver §3.3 para el modelo de multi-tenancy sobre esta base y
  §3.4.1 para el ajuste a IDs UUID.
- **UI**: Tailwind CSS + shadcn-vue + Radix Vue.
- **DB**: PostgreSQL 16 (pgvector se añade en v1.2, no en el MVP).
  **Nota:** la versión vigente es PostgreSQL 18; ver D-075 en `specs/foundation/decisions.md`.
- **Cache/Queue**: Redis (cache + queue driver). Horizon opcional en el MVP.
- **Storage**: S3-compatible (comprobantes, avatares).
- **Realtime**: fuera del MVP — Reverb se evalúa junto con mobile (v1.2),
  para push real hacia la app React Native.
- **PWA**: vite-plugin-pwa sobre el mismo build de Inertia. Es la única
  "presencia mobile" hasta v1.2.
- **Mobile nativo**: **React Native + Expo**, en **v1.2**, después de
  cerrar el alcance del app de escritorio/web (MVP + v1.1). Cliente
  separado desde el inicio — no un webview del monolito. Ver §3.1.2.
- **Jobs**: Laravel Scheduler + Queue (necesario para RF-07 desde el MVP).

#### 3.1.2 Sanctum — rol acotado

Sanctum **no desaparece del proyecto**, pero su alcance cambia:

| Consumidor | Auth | Cuándo |
|---|---|---|
| Monolito web (Inertia) | Sesión/cookie (guard `web`) | MVP |
| Cliente mobile (React Native + Expo) | Sanctum (tokens) | v1.2 |
| API pública para terceros | Sanctum (tokens) | v1.4 (reutiliza la misma API construida para mobile en v1.2) |

Decisión cerrada: el cliente mobile es una app React Native separada que
consume la API vía tokens — no un webview del monolito. Por eso la API
con Sanctum se construye una sola vez en v1.2 y sirve tanto a mobile como,
más adelante, a terceros.

#### 3.1.3 Diagrama de alto nivel (MVP)

```
┌─────────────────────────────────────────────────────┐
│  Navegador (Vue 3 hidratado por Inertia, vía Vite)  │
└───────────────────────┬─────────────────────────────┘
                        │ Visits de Inertia (XHR con
                        │ header X-Inertia, misma sesión)
┌───────────────────────▼─────────────────────────────┐
│  Laravel 13 (routes/web.php)                        │
│  ├── Controllers → Inertia::render('Pagina', props) │
│  ├── Form Requests (validación, errores compartidos │
│  │   automáticamente por Inertia al formulario)     │
│  ├── Actions + DTOs (casos de uso, ver §3.15)       │
│  ├── Policies (autorización por team/rol)           │
│  ├── Jobs con #[Queue] / #[Tries] attributes        │
│  └── Events / Listeners / Observers                 │
└────┬───────────────┬────────────────┬───────────────┘
     │               │                │
┌────▼────┐   ┌──────▼──────┐   ┌─────▼──────┐
│ Postgres│   │   Redis     │   │  Storage   │
│         │   │ (cache+queue)│   │   (S3)     │
└─────────┘   └─────────────┘   └────────────┘
```

*(Una API JSON con Sanctum, separada de este flujo, se añade en v1.2 para
el cliente mobile y se abre a terceros en v1.4 — ver §3.1.2.)*

### 3.2 Stack Tecnológico

| Capa | Tecnología | Fase | Justificación |
|------|-----------|------|---------------|
| Backend | Laravel 13 (PHP 8.3+) | MVP | Última versión LTS-adyacente, soporte hasta 2028 |
| Aritmética monetaria | BCMath (`ext-bcmath`) | MVP | Operaciones con montos como strings, sin imprecisión de `float` (ver §3.15.2) |
| Bridge SPA | Inertia.js | MVP | Monolito con UX de SPA, sin mantener una API separada |
| Rutas tipadas | Wayfinder | MVP | Llamadas TS tipadas a rutas/controllers, sin cliente API hecho a mano |
| Auth (web) | Sesión/cookie (guard `web`) | MVP | Estándar para monolito con Inertia en el mismo dominio |
| Auth (API futura) | Laravel Sanctum | v1.2/v1.4 | v1.2 para el cliente mobile (React Native); v1.4 para terceros, reutilizando la misma API |
| Autorización | Policies + Gates + PHP Attributes | MVP | Autorización declarativa |
| ORM | Eloquent + Attributes nativos | MVP | Menos boilerplate |
| Migraciones | Laravel Migrations | MVP | Versionado DB |
| Validación | Form Requests + reglas custom | MVP | Errores se comparten automáticamente al formulario vía Inertia |
| Frontend | Vue 3 + TS + Vite | MVP | Reactividad, tipado |
| Estado | Props de Inertia + `usePage()`; Pinia solo para UI local | MVP | TanStack Query/estado global pasan a opcionales — Inertia ya gestiona los datos de cada página |
| Routing cliente | Ninguno (Inertia + rutas Laravel) | MVP | Vue Router se retira: no aplica en monolito Inertia |
| Formularios | `useForm` de Inertia + zod (validación espejo opcional) | MVP | `useForm` ya maneja estado, errores y progreso de envío |
| UI | Tailwind + shadcn-vue | MVP | Design System |
| Gráficos | ApexCharts / vue-chartjs | MVP | Maduros |
| PWA | vite-plugin-pwa + Workbox | MVP | Instalable, offline básico |
| Queue | Redis + Queue Routing | MVP | Jobs recurrentes (RF-07) |
| Testing | Pest (+ helpers de Inertia testing) + Vitest + Playwright | MVP | Unit + E2E, aserciones sobre props de Inertia |
| CI/CD | GitHub Actions | MVP | Automatización |
| Observabilidad | Sentry + Telescope (dev) | MVP | Errores, debug local |
| Hosting | VPS/Forge o Fly.io + managed Postgres | MVP | Zero-ops |
| Horizon | Dashboard de colas | MVP (opcional) | Se agrega si el tiempo lo permite |
| API con Sanctum | Laravel Sanctum, JSON puro (agnóstico de cliente) | v1.2 | Base para el cliente mobile; se reutiliza en v1.4 para terceros |
| Mobile | React Native + Expo | v1.2 | Cliente separado, decisión cerrada — ver §3.1.2 |
| Realtime | Laravel Reverb | v1.2 | Push real hacia la app React Native |
| Observabilidad prod | Laravel Pulse | v1.2 | Métricas de producción (útil ya con mobile en juego) |
| Vector/Semantic Search | pgvector | v1.3 | Búsqueda semántica en descripciones |
| API pública abierta a terceros | Contratos REST/JSON:API completos (ver ERD/API Spec §5) | v1.4 | La API ya existe desde v1.2 (mobile); aquí se documenta y abre formalmente |
| AI (futuro) | Laravel AI SDK | v2.0 | Insights, forecasting |

### 3.3 Modelo de Multi-tenancy

Sin cambios respecto a v2.2 — el modelo de `team_id` + scoping es
independiente de si el transporte es Inertia o JSON puro.

#### 3.3.1 Estrategia

**Single database, shared schema, `team_id`:**

- Todas las tablas de dominio llevan `team_id`.
- Scoping global vía `BelongsToTeam` trait + `TeamScope`.
- Policies validan pertenencia y rol.
- Middleware `EnsureTeamMembership` (del starter kit) resuelve el team
  del segmento de ruta `{current_team}` (por slug), responde 403 si el
  usuario no es miembro (opcionalmente exige un rol mínimo) y, si el team
  de la URL no es el activo, hace `switchTeam()` para sincronizar
  `users.current_team_id` — no desde un header `X-Team-Id` como en una
  API pura. El `switchTeam()` solo ocurre si el parámetro se llama
  `current_team`; por eso todas las rutas de dominio usan ese nombre.
- El scoping y la autorización dependen únicamente de `team_id` +
  membresía/rol, nunca de `is_personal` — `is_personal` es una bandera
  de autorización específica (protege al team por defecto de
  eliminarse/abandonarse, ver `TeamPolicy` y PRD §1.6.1), no una
  condición general de scoping.
- Todo ID que llega del usuario (`account_id`, `category_id`, …) se
  resuelve **a través de las relaciones del team** (`$team->accounts()`,
  `$team->categories()`, …), nunca con `Model::find()` global. Así una
  referencia a un recurso de otro team da 404 en vez de un IDOR. Ver
  §3.15.3.
- **Saldo de cuentas privadas (ERD §4.3.8):** el nombre de una cuenta
  `private` se comparte con todo el team, pero `initial_balance` y
  `current_balance` se omiten (`null`) al serializarla para cualquier
  usuario distinto de `owner_id`, incluido el owner del team. El balance
  total del dashboard suma solo cuentas `team`. Ningún prop de Inertia ni
  respuesta debe incluir el saldo privado ajeno; se cubre con tests.

#### 3.3.2 Flujo de autorización

1. Sesión autentica usuario (guard `web`).
2. Middleware resuelve team activo (sesión o segmento de ruta).
3. Verifica membresía.
4. Policy evalúa acción + rol + visibilidad de recurso.
5. Global scope filtra queries por `team_id`.

### 3.4 Estructura del Proyecto

```
/app
  /Actions           # Casos de uso (CreateTransactionAction, CreateTransferAction, etc.)
    /Accounts               # BalanceReconciler (§3.8.3)
    /Transactions/Support   # Reglas compartidas entre actions (TransferRules, TransactionDateRule)
  /DTOs              # Entrada y salida de las actions (final readonly), por dominio: /DTOs/Transactions
  /Console/Commands  # nestly:reconcile (§3.8.3)
  /Enums             # AccountType, TransactionType, TeamRole, BrokenTransferReason...
  /Events
  /Exceptions
  /Http
    /Controllers      # Devuelven Inertia::render(), no JsonResource
    /Middleware       # EnsureTeamMembership, SetTeamUrlDefaults, HandleInertiaRequests
    /Requests
  /Jobs               # ReconcileAccountBalancesJob; con #[Queue], #[Tries], #[Timeout] attributes
  /Listeners
  /Models             # Con #[Table], #[Fillable], #[Hidden] attributes
  /Observers          # TeamObserver (categorías por defecto)
  /Policies
  /Services
  /Support            # DefaultCategories, Money (escala de BCMath), etc.
/database
  /migrations         # Sin migración de `pots`/`pot_contributions` en el MVP
  /seeders
  /factories
/resources
  /js
    /Pages            # Componentes Vue = destino de Inertia::render('Pages/X')
    /Layouts          # Layouts persistentes de Inertia (Sidebar, Auth, etc.)
    /Components       # ui/ (shadcn-vue) + dominio
    /composables
    /stores           # Pinia, solo estado de UI local (no datos de dominio)
    /lib              # format, validators, helpers
    /types
    /actions          # Generado por Wayfinder — no editar a mano
    app.ts            # createInertiaApp()
  /css
/routes
  web.php             # Único set de rutas para el monolito
  # api.php se añade en v1.2 (mobile API) y se amplía en v1.4 (pública)
/tests
  Pest.php            # Configuración + helpers compartidos (transactionData(), transferData(), updateTransactionData(), updateTransferData(), makeCategory(), recordExpense(), recordTransfer(), teamUser(), transferLegs(), driftBalance())
  /Feature            # assertInertia() sobre props devueltas; tests de actions
  /Unit
```

#### 3.4.1 Convención de IDs (UUID)

El starter kit trae IDs autoincrementales por defecto
(`$table->id()`). Se adapta cada tabla de dominio para usar **UUID**
como primary key, apoyándose en el trait nativo de Laravel `HasUuids`
(genera **UUID v7**, ordenados por tiempo — buenos para índices, a
diferencia de un UUID v4 puro; verificado el 7 oct). Por eso la regla de
validación es `uuid` a secas, nunca `uuid:4`:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Team extends Model
{
    use GeneratesUniqueTeamSlugs, HasFactory, HasUuids, SoftDeletes;
    // ...
}
```

El trait ya resuelve `getIncrementing(): false` y `getKeyType(): 'string'`
— no hace falta sobreescribirlos a mano. Se aplica a **todos** los
modelos de dominio (`User`, `Team`, `Membership`, `TeamInvitation`,
`Account`, `Category`, `Transaction`, `Budget`, `RecurringExpense`,
`AuditLog`), no solo a `Team`. Un modelo sin el trait cuya tabla tenga
`uuid('id')->primary()` falla al insertar con *not-null violation* en
`id`, porque Eloquent no genera el valor.

En las migraciones, cada tabla cambia de `$table->id()` a
`$table->uuid('id')->primary()`, y cada FK de `$table->foreignId(...)` a
`$table->foreignUuid(...)`:

```php
// teams
$table->uuid('id')->primary();
$table->string('name');
$table->string('slug')->unique();
$table->boolean('is_personal')->default(false);
$table->string('currency', 3)->default('USD');
$table->string('icon')->default('user-round-group');
$table->string('color', 7)->default('#4F7CFF');
$table->timestamps();
$table->softDeletes();
// Después del Schema::create('teams', ...):
// DB::statement('ALTER TABLE teams ADD CONSTRAINT teams_color_hex CHECK (color ~ \'^#[0-9a-fA-F]{6}$\')');

// team_members
$table->uuid('id')->primary();
$table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
$table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
$table->string('role');
$table->timestamps();
$table->unique(['team_id', 'user_id']);

// team_invitations
$table->uuid('id')->primary();
$table->string('code', 64)->unique();
$table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
$table->string('email');
$table->string('role');
$table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
$table->timestamp('expires_at')->nullable();
$table->timestamp('accepted_at')->nullable();
$table->timestamps();

// users.current_team_id
$table->foreignUuid('current_team_id')
    ->nullable()
    ->after('password')
    ->constrained('teams')
    ->nullOnDelete();
```

**Importante:** la tabla `users` del starter kit también nace con
`$table->id()` — hay que convertirla a UUID en la misma migración base
(el proyecto es nuevo, sin datos, así que se edita la migración
directamente en vez de agregar una migración de alteración). Toda FK que
apunte a `users.id` (`invited_by`, `created_by`, `owner_id`, etc.) debe
declararse con `foreignUuid()` para que los tipos coincidan.

**Defaults de `teams`:** `icon` y `color` usan valores con significado
(ícono de Lucide y el color primario del Design System, §6.2.1) en vez de
`null` o `''`, para que el frontend siempre reciba un ícono y un color
válidos. `currency`, `icon` y `color` están en `#[Fillable]` de `Team`;
`is_personal` también lo está (viene del starter kit), por lo que los
Form Requests de teams **nunca** incluyen `is_personal` en `rules()` y los
controllers usan siempre `$request->validated()`.

**Constraints de base de datos:** Laravel 13 no ofrece un método del
schema builder para `CHECK` constraints, así que se declaran con
`DB::statement()` después del `Schema::create()`, con nombre
`<tabla>_<columna>_<regla>`. Ver ERD §4.4.3 para la lista completa.

### 3.5 Diseño de rutas y páginas (Inertia)

#### 3.5.1 Convenciones

- Cada acción de controller que renderiza una vista devuelve
  `Inertia::render('Pages/Ruta', $props)`.
- Los errores de validación de un `Form Request` se comparten
  automáticamente al formulario Vue vía `useForm()` — no hay que armar un
  formato `{ message, errors }` a mano como en una API JSON pura (aunque
  internamente Inertia usa una forma similar bajo el capó).
- Datos compartidos entre todas las páginas (usuario actual, team
  activo, flash messages) van en `HandleInertiaRequests::share()`.
- Redirecciones tras un `POST`/`PATCH`/`DELETE` siguen el patrón
  Post-Redirect-Get propio de Inertia, no una respuesta JSON con el
  recurso creado. La razón de fondo es **no duplicar escrituras**: si el
  `POST` respondiera con `Inertia::render()`, un F5 o "volver" reenviaría
  el `POST` (una segunda transferencia); con el redirect (303) lo último
  en el historial es un `GET`. Lo habitual es
  `back()->with('success', …)`: vuelve a la página del formulario, que
  recarga sus props (saldos, listados). Un JSON plano no sirve: Inertia
  lo trata como respuesta inválida.
- **Rutas de dominio dentro del grupo `{current_team}`** de
  `routes/web.php`. El controller recibe el team como `Team $current_team`:
  el route model binding implícito empareja por nombre de parámetro, y
  `Team` resuelve por `slug`. Los URL defaults (`URL::defaults(['current_team' => …])`)
  hacen que Wayfinder y `route()` rellenen el team sin pasarlo a mano.
- Recargas parciales (`router.reload({ only: ['budgets'] })`) reemplazan
  la mayoría de los casos donde antes se haría un fetch a un endpoint
  JSON específico (ej. refrescar solo la lista de presupuestos tras
  crear una transacción).

#### 3.5.2 Páginas principales (equivalentes a lo que antes eran endpoints)

```
# Auth
GET/POST  /register
GET/POST  /login
POST      /logout
GET/POST  /forgot-password
GET/POST  /reset-password/{token}

# Teams
GET       /teams                       -> Pages/Teams/Index
POST      /teams
GET       /teams/{team}/settings   -> Pages/Teams/Settings
PATCH     /teams/{team}
DELETE    /teams/{team}
POST      /teams/{team}/invitations
POST      /invitations/{code}/accept
PATCH     /teams/{team}/members/{user}
DELETE    /teams/{team}/members/{user}

# Accounts
GET       /{current_team}/accounts            -> Pages/Accounts/Index
POST      /{current_team}/accounts
PATCH     /{current_team}/accounts/{account}
DELETE    /{current_team}/accounts/{account}
POST      /{current_team}/accounts/{account}/archive
POST      /{current_team}/transfers
PATCH     /{current_team}/transfers/{transfer_group_id}   # whereUuid (§3.15.9)
DELETE    /{current_team}/transfers/{transfer_group_id}   # whereUuid (§3.15.8)

# Transactions
GET       /{current_team}/transactions        -> Pages/Transactions/Index (con filtros por query string)
POST      /{current_team}/transactions
PATCH     /{current_team}/transactions/{transaction}
DELETE    /{current_team}/transactions/{transaction}
POST      /{current_team}/transactions/import   # nice-to-have

# Categories
GET/POST/PATCH/DELETE  /{current_team}/categories...

# Budgets
GET/POST/PATCH/DELETE  /{current_team}/budgets...

# Recurring
GET/POST/PATCH/DELETE  /{current_team}/recurring-expenses...
POST      /{current_team}/recurring-expenses/{recurring}/pause
POST      /{current_team}/recurring-expenses/{recurring}/resume

# Dashboard
GET       /{current_team}/dashboard           -> Pages/Dashboard/Index

# Notifications
GET       /notifications
POST      /notifications/{notification}/read
```

*(Pots (v1.1) seguiría el mismo patrón `/{current_team}/pots/...` cuando se
implemente.)*

"Transferencia" es una opción del formulario de transacciones en la UI
(RF-04.1), pero se envía a `POST /{current_team}/transfers`, no a
`/transactions`: no existe un `type = transfer` persistido (ERD §4.3.3).

#### 3.5.3 Props de ejemplo: `Pages/Dashboard/Index`

```php
// DashboardController@index
return Inertia::render('Dashboard/Index', [
    'period' => ['from' => $from, 'to' => $to],
    'totals' => $summaryService->totals($team, $from, $to),
    'budgets' => BudgetSummaryData::collect($budgets),
    'upcomingRecurring' => $upcoming,
]);
```

```ts
// Pages/Dashboard/Index.vue — recibe las props directamente, tipadas
defineProps<{
  period: { from: string; to: string }
  totals: DashboardTotals
  budgets: BudgetSummary[]
  upcomingRecurring: RecurringExpenseSummary[]
}>()
```

No hay una capa intermedia de "consumir la API" — el controller entrega
exactamente lo que la página necesita, tipado de punta a punta si se
generan los tipos TS desde los DTOs/Data objects del backend (ej. con
`spatie/laravel-data` + su generador de TS, opcional).

**KPIs de ingresos y gastos: perímetro del team (v2.15, ERD §4.3.3).**
El perímetro son las cuentas `team`, las mismas que suma el balance
total. Los KPIs suman solo filas de cuentas `team`, y de ellas cuentan:
las transacciones normales (`transfer_group_id IS NULL`) y las patas de
transferencias cuya otra pata está en una cuenta `private` (team →
privada = gasto, privada → team = ingreso). Una transferencia team →
team no cuenta. Con esto la variación del balance del periodo es igual a
ingresos − gastos. La versión anterior excluía toda fila con
`transfer_group_id`, lo que hacía que un reembolso hacia una cuenta
privada bajara el balance sin aparecer como gasto. Se implementa en la
fase del dashboard; la consulta necesita saber la visibilidad de la
cuenta de la otra pata (join por `transfer_group_id`).

#### 3.5.4 API con Sanctum (v1.2 mobile → v1.4 pública) — fuera del MVP

Los contratos REST/JSON:API completos ya documentados en **ERD/API Spec
§5** (`/api/v1/...`, headers `Authorization`/`X-Team-Id`, formato
`{ data, meta }`, etc.) **no describen el MVP** — describen el diseño de
la API que en **v1.2** empieza a existir para servir al cliente mobile
(React Native) y que en **v1.4** se documenta y abre formalmente a
terceros, sin cambios de contrato entre una fase y otra. Esa API convive
con el monolito pero es una superficie aparte: rutas bajo `/api/v1`,
autenticada con Sanctum, sin sesión ni Inertia de por medio. No se
construye hasta v1.2, y no antes de cerrar el alcance del app de
escritorio/web (MVP + v1.1).

### 3.6 Seguridad

- **Passwords**: bcrypt (12 rounds) o argon2id.
- **Sesión**: cookies `HttpOnly` + `Secure` + `SameSite=Lax`; regeneración
  de sesión en login/logout.
- **CSRF**: middleware estándar de Laravel + cookie `XSRF-TOKEN` que
  Inertia envía automáticamente en cada visit — no requiere manejo manual
  en el frontend.
- **Rate limiting**: `throttle` en rutas de login/registro (5/min);
  `throttle:api` (60/min) se aplica desde v1.2, cuando exista la API con Sanctum.
- **CORS**: no aplica al monolito (mismo origen); se configura desde v1.2
  para la API (mobile), y se amplía en v1.4 para terceros.
- **Validación**: Form Requests + reglas custom + PHP Attributes. Siempre
  `$request->validated()`, nunca `$request->all()`: los campos sin regla
  (ej. `is_personal`, `transfer_group_id`, `recurring_id`) nunca llegan al
  modelo aunque estén en `#[Fillable]`.
- **Validación en dos capas (aplicación):** el Form Request valida
  formato y UX (tipos, `uuid`, `different`, montos, fechas) con errores
  por campo y todos a la vez, sin tocar la BD; la action valida las
  invariantes de dominio (tenancy, privacidad, origen ≠ destino,
  privada → privada, cuenta archivada), porque también la llaman el
  seeder, los jobs y, desde v1.2, la API móvil, que no pasan por el Form
  Request.
- **Sin `exists` en IDs de recursos del team:** un `exists` sin filtro
  responde 422 o 404 según el recurso exista o no (enumeración), y uno
  filtrado por team cambia el 404 documentado a 422 y duplica la regla de
  tenancy de la action. La existencia y la tenancy las resuelve solo la
  action.
- **Reglas de monto:** `required|numeric|decimal:0,2|min:0.01|max:'.Money::MAX`
  (`Money::MAX = '999999999999.99'`, compartido por todos los Form
  Requests que escriben en columnas `decimal(14,2)`).
  `numeric` hace que `min`/`max` comparen valor y no longitud;
  `decimal:0,2` evita el redondeo silencioso de `decimal(14,2)`; `max` es
  el tope exacto de la columna (evita `numeric field overflow`). No usar
  `max_digits`: rechaza el punto decimal.
- **`StoreTransferRequest`:** `from_account_id` →
  `required|different:to_account_id|uuid` (el `different` va solo en el
  campo donde la action pone el error); `to_account_id` → `required|uuid`;
  `category_id` → `nullable|uuid`; `description` →
  `nullable|string|max:255`; `date` → `$this->transactionDateRules()`
  (ver abajo). `authorize()` devuelve `true`: el rol lo valida la policy
  desde el controller. **`UpdateTransferRequest`** repite las mismas
  reglas (editar es reenviar el formulario completo, el DTO exige todos
  los campos) en una clase propia, no heredada: si mañana divergen, no
  se rompe `store`. Las reglas de dominio sí se comparten, vía
  `TransferRules` (§3.15.9).
- **`StoreTransactionRequest`:** `account_id` → `required|uuid`;
  `category_id` → `nullable|uuid`; `type` →
  `['required', Rule::enum(TransactionType::class)]` (el enum es la única
  fuente de verdad de los valores); monto con las reglas de arriba;
  `date` → `$this->transactionDateRules()`; `description` y `notes` →
  `nullable|string|max:255`. El controller convierte con
  `TransactionType::from()` (no `tryFrom()`: no devuelve `null`).
- **Sin fechas futuras, contra el "hoy" del usuario:** ninguna transacción
  ni transferencia puede tener fecha posterior a hoy. El trait
  `App\Http\Requests\Concerns\ValidatesTransactionDate` da
  `['required', 'date', 'before_or_equal:'.$user->localToday()]` a los
  Form Requests de transacciones y transferencias; `User::localToday()` devuelve
  `CarbonImmutable::now($this->timezone)->toDateString()`. No sirve
  `before_or_equal:today`: compara contra hoy en UTC (a las 22:00 en
  UTC−4 el servidor ya está en el día siguiente). Se compara contra la
  **fecha** (`Y-m-d`), no contra el instante, para que "hoy" sea válido
  a cualquier hora. `$this->user()` es `?User`: el trait lo comprueba con
  `instanceof` (lanza `AuthenticationException`), así PHPStan deduce el
  tipo sin un `@var`. La misma regla vive en las actions (§3.15.3,
  §3.15.4) para cubrir seeder, job de recurrentes y la futura API.
- **`users.timezone`** (string, default `UTC` en la columna **y** en
  `User::$attributes`): el default de la columna lo aplica PostgreSQL al
  insertar, pero Eloquent no relee la fila, así que sin el default del
  modelo una instancia recién creada tendría `timezone = null`. Todavía
  no está en `#[Fillable]`: se agregará con el formulario de perfil
  (RF-01.3) y su regla `timezone`.
- **Campos que pone el sistema nunca llevan regla:** `transfer_group_id`
  (lo genera `CreateTransferAction`) y `recurring_id` (lo pone
  `ProcessRecurringExpensesJob`) no aparecen en `rules()`, y el controller
  los pasa como `null`. Así `validated()` los descarta aunque alguien los
  envíe a mano. Si tuvieran regla, un `transfer_group_id` inventado
  saltaría la regla "cuenta privada = solo transferencias"; hay un test
  HTTP que lo intenta y espera 422.
- **Defensa en profundidad**: toda regla de integridad importante tiene
  dos capas — Form Request (aplicación) + constraint en PostgreSQL (base
  de datos). Ver ERD §4.4.3.
- **IDOR / tenancy**: IDs de recursos resueltos por las relaciones del
  team + `findOrFail()` → 404 (no 403, para no confirmar que el recurso
  existe en otro team).
- **Excepciones de dominio, no HTTP:** las actions lanzan
  `ModelNotFoundException` (404), `AuthorizationException` (403) y
  `ValidationException` (422), nunca `abort()`. El handler de Laravel las
  traduce a HTTP; fuera de un request (seeder, job) suben como
  excepciones normales.
- **Cuentas privadas solo fuera del team personal (ERD §4.3.8, v2.16,
  pendiente):** la action de crear cuentas rechaza `visibility = private`
  con 422 en `visibility` si `$team->is_personal`. Es una de las pocas
  reglas que miran `is_personal`: aquí no decide autorización sino si el
  dato tiene sentido. Se valida en la action (no solo ocultando la opción
  en la UI) porque la API móvil de v1.2 no pasa por el formulario.
- **Matriz de roles (PRD §1.6.2):** owner y admin gestionan cuentas
  `team`, presupuestos, categorías y recurrentes; member crea
  transacciones, edita solo las propias y gestiona su cuenta privada;
  viewer solo lee. Se decide con `TeamRole::isAtLeast()` en las policies.
- **Policy vs action en la creación de transacciones:**
  `TransactionPolicy::create(User $user, Team $team)` solo responde si el
  rol en el team es al menos `Member` (viewer → 403). No recibe una
  `Account`: tenancy (404), privacidad (403) y cuenta archivada (422) los
  decide la action con las filas bloqueadas, y resolver la cuenta en el
  controller duplicaría esa consulta fuera del lock. Se invoca con
  `Gate::authorize('create', [Transaction::class, $current_team])`: el
  primer elemento elige la policy; el usuario lo inyecta el Gate. Sirve
  igual para transacciones y transferencias.
- **Editar y borrar transferencias (caso A, v2.15, ERD §4.3.15):**
  pueden hacerlo el creador (`created_by`), el dueño (`owner_id`) de
  cualquiera de las dos cuentas o un admin+, todos con rol al menos
  member. Al editar, los datos nuevos pasan por las reglas de creación
  (sacar de una privada ajena → 403, privada → privada → 422, archivada →
  422, origen = destino → 422). **Reparto policy/action:** como no existe
  un modelo `Transfer` que bindear,
  `TransactionPolicy::deleteTransfer(User $user, Team $team)` y
  `TransactionPolicy::updateTransfer(User $user, Team $team)` solo deciden
  el rol (member+, sin tocar la BD) y se invocan con
  `Gate::authorize('deleteTransfer' | 'updateTransfer', [Transaction::class, $current_team])`
  (habilidad en camelCase, igual al nombre del método). Son dos métodos
  aunque hoy tengan el mismo cuerpo: reutilizar `deleteTransfer` para
  editar haría que el nombre mintiera.
  La regla creador/dueño/admin vive en la action, con las filas ya
  bloqueadas: decidirla antes autorizaría contra datos que una edición
  concurrente podría cambiar entre el chequeo y el borrado (*time-of-check
  / time-of-use*) y duplicaría la consulta. Al editar, se evalúa contra
  las cuentas **viejas** (§3.15.9). La comparación
  `owner_id === $user->id` es segura con cuentas `team` (`owner_id`
  nulo) porque `$user` siempre es un modelo persistido con `id`; la
  action no debe llamarse con un usuario sin guardar.
#### 3.6.1 Policies de dominio (v2.20)

Todas siguen la matriz del PRD §1.6.2 y tienen tests de matriz por rol en
`tests/Feature/Policies` (datasets `roles` y `only owner and admin` en
`tests/Datasets/Roles.php`), incluido el caso "no miembro" y el que
permite, no solo el que deniega.

- **`AccountPolicy`**: `view` = cualquier miembro (nivel nombre, también
  privadas ajenas); `viewBalance` = cuenta `team` → cualquier miembro,
  `private` → solo su dueño **y solo mientras siga en el team** (ni el
  owner ve un saldo privado ajeno); `create(User, Team,
  AccountVisibility)` = `team` → admin+, `private` → member+ (la policy
  recibe solo el dato que necesita: al crear, la cuenta aún no existe);
  `update`/`archive`/`delete` vía `canManage` = `team` → admin+,
  `private` → su dueño si sigue siendo member+. "Sin movimientos" para
  eliminar es un 422 de la action (ERD §4.3.18), no de la policy. Se
  resuelve el rol una vez y se sale temprano si es `null`.
- **`RecurringExpensePolicy`**: `create`/`update`/`delete`/`pause`/`resume`
  = admin+. **Sin privilegio por autoría**: con "o el creador", un admin
  degradado a member conservaría el permiso sobre lo que creó. Cuando un
  permiso depende del rol, mira solo el rol actual. (Las transacciones sí
  dan permiso por autoría a propósito: un member edita las suyas.)
- **`BudgetPolicy`**: ver = miembro; gestionar = admin+; `created_by` es
  autoría, no propiedad.
- **`CategoryPolicy`**: ver = miembro; crear/editar/archivar/eliminar =
  admin+; una categoría del sistema no se edita ni elimina, ni siquiera
  por el owner (`is_system` gana sobre el rol), pero sí se archiva. Cada
  team tiene sus propias filas de categorías del sistema, así que archivar
  es por team (ERD §4.3.19).

#### 3.6.2 Errores de constraint → 422 (v2.20)

Cuando un dato inválido llega a la BD (por ejemplo, saltándose el Form
Request), el constraint es la segunda capa y el usuario debe ver un error
de campo, no un 500.

- **Dónde:** `bootstrap/app.php`,
  `$exceptions->map(fn (QueryException $e) => ConstraintViolations::toValidationException($e) ?? $e)`.
  `map` convierte la excepción antes de renderizarla; Laravel muestra la
  `ValidationException` como siempre (redirect con `errors` en Inertia,
  422 en JSON). Lo no traducido conserva su comportamiento (500).
- **Qué se traduce:** `App\Support\ConstraintViolations` (clase `final`,
  estática, pura) solo actúa con SQLSTATE `23514` (check) o `23P01`
  (exclusion) y un **mapa explícito** nombre de constraint → campo y
  mensaje: `transactions_amount_positive`,
  `recurring_expenses_amount_positive`, `budgets_limit_amount_positive`,
  `budgets_no_overlap`, `teams_color_hex`, `categories_kind_valid`.
- **Qué no se traduce, a propósito:** constraints que solo un bug puede
  violar (`accounts_visibility_owner_consistent`: el `owner_id` lo pone la
  action). Un bug debe dar 500 y quedar en el log, no disfrazarse de error
  de formulario. El `23503` (FK `RESTRICT` al borrar) se agregará con las
  actions de borrado de cuentas y categorías, que responden 422 antes.
  **Nota:** en PostgreSQL 18 (D-075) este borrado devuelve `23001`, no `23503`. Ver P-001 en `specs/foundation/decisions.md`.
- **Cómo se identifica el constraint:** los mensajes de PostgreSQL están
  **localizados** según `lc_messages` (en español: «restricción»
  «teams_color_hex»), así que no se parsean: se busca cuál de los nombres
  del mapa aparece, porque los identificadores nunca se traducen. Solo en
  la **primera línea** del mensaje de la `PDOException` original
  (`getPrevious()`): la línea `DETAIL` y el SQL con sus bindings pueden
  contener datos del usuario.
- **Tests** (`tests/Feature/Support/ConstraintViolationsTest.php`): cada
  entrada del mapa; mensaje en español; un nombre mapeado dentro de la
  línea `DETAIL` no cuenta; constraint no mapeado y otros SQLSTATE →
  `null`; errores reales de PostgreSQL (check y exclusion) que atan la
  detección al formato real; HTTP Inertia (redirect con error), JSON
  (422) y no mapeado (500). El mensaje localizado lo detectaron los tests
  contra la BD real: los fabricados solo prueban lo que uno supone.

#### 3.6.3 Salir de un team (v2.20)

`App\Actions\Teams\RemoveTeamMemberAction::execute(Team, User)` la usan
`TeamController@leave` y `TeamMemberController@destroy` (antes cada uno
borraba la membresía por su cuenta). En una transacción: borra la
membresía y archiva las cuentas privadas del miembro **en ese team**
(conservan `owner_id`; ERD §4.3.8). El `switchTeam` sigue en cada
controller (estado de sesión, distinto en cada camino).

Es una action y no un observer de `Membership` porque la membresía se
borra con un `delete()` masivo, que no dispara eventos de modelo: un
observer no se ejecutaría. El archivado es un `update()` masivo (no toca
saldos ni ledger); cuando exista la auditoría (Fase 9) hay que
registrarlo explícitamente.

- **Autorización**: `#[Authorize]` attribute en controllers para checks declarativos.
- **SQL injection**: Eloquent + bindings.
- **XSS**: escape por defecto de Vue + CSP.
- **Secretos**: `.env` + config cacheado; nunca en repo.
- **Cifrado**: TLS 1.3; columnas sensibles con `encrypted` cast.
- **Auditoría**: `audit_logs` (user_id, team_id, action, model, model_id, changes, ip, ua). Reemplaza a un `updated_by`: guarda todo el historial de ediciones, no solo la última. El observer de auditoría debe cubrir la edición de transferencias (las dos patas).
- **GDPR**: rutas export/delete; anonimización en cascada.
- **Backups**: diarios + PITR en Postgres.

### 3.7 Rendimiento

- **Índices**: ver ERD §4.4.2.
- **Cache Redis**: dashboard summary TTL 60s por team+periodo.
- **Eager loading**: `with()` para evitar N+1 — especialmente relevante
  porque cada visita de Inertia arma todas las props de la página en un
  solo request; un N+1 aquí pega directo al tiempo de respuesta percibido.
- **Paginación**: cursor en listados grandes; Inertia soporta pasar meta
  de paginación como prop normal.
- **Assets**: code-splitting por página de Inertia vía Vite (lazy-loading
  automático de cada componente en `/Pages`).
- **PWA**: cache-first para assets, network-first para visitas de Inertia.
- **Objetivos**: p95 render de página < 300ms, LCP < 2s (aspiracional para
  portafolio, validado con pruebas sintéticas, no tráfico real).

### 3.8 Jobs y Recurrencia

Sin cambios respecto a v2.2 — los jobs son independientes del transporte
Inertia/API.

#### 3.8.1 `ProcessRecurringExpensesJob` (diario 00:05 por timezone) — MVP

```php
#[Queue('recurring')]
#[Tries(3)]
#[Timeout(120)]
#[Backoff([60, 300, 900])]
class ProcessRecurringExpensesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // 1. Buscar RecurringExpense con next_run_date <= today y active = true
        // 2. Crear Transaction vinculada vía CreateTransactionAction
        //    (actualiza Account.current_balance en la misma transacción de BD)
        // 3. Recalcular Budget.spent
        // 4. Emitir RecurringExpenseProcessed -> notificación
        // 5. Recalcular next_run_date
    }
}
```

#### 3.8.2 `SendUpcomingRecurringNotificationsJob` (diario) — MVP

Notificar recurrentes que vencen en N días (config por usuario).

#### 3.8.3 `ReconcileAccountBalancesJob` (semanal) — MVP, implementado

Red de seguridad, no el mecanismo principal: el saldo se mantiene en
tiempo real desde las actions (§3.15). **Detecta y reporta; no corrige**
(v2.18): una desviación es la huella de un bug (o de una escritura
directa a la BD), y corregirla sola lo escondería. Si el propio job
tuviera un bug, además "corregiría" saldos buenos en todos los teams.
Corregir es un paso humano explícito: `nestly:reconcile --fix`.

**`BalanceReconciler`** (`app/Actions/Accounts`, clase `final` inyectable):
la lógica, sin depender del transporte; la usan el comando y el job.

- `check(Team $team): ReconciliationReport` orquesta tres métodos
  privados, cada uno una consulta SQL → una lista tipada:
  1. `driftsFor()` → `list<BalanceDrift>`: `current_balance` ≠
     `initial_balance + Σ income − Σ expense`.
  2. `brokenGroupsFor()` → `list<BrokenTransferGroup>`: una consulta
     agrupada por `transfer_group_id` con `COUNT(*)`,
     `COUNT(DISTINCT type|amount|date)` y `BOOL_AND(visibility = private)`;
     `reasonsFor()` traduce cada fila a una lista de
     `BrokenTransferReason` (`LegCount`, `SameType`, `AmountMismatch`,
     `DateMismatch`, `PrivateToPrivate`). Un grupo puede romper varias
     reglas a la vez; con una sola pata no se reporta `SameType` (ya lo
     cubre `LegCount`).
  3. `orphanPrivateTransactionsFor()` → `list<string>`: ids de
     transacciones sin `transfer_group_id` en cuentas `private`.
- `fixBalance(Team $team, string $accountId): ?BalanceDrift`: dentro de
  `DB::transaction()`, `$team->accounts()->lockForUpdate()->findOrFail()`
  (404 si es de otro team), recalcula con `expectedBalanceOf()`
  **después** del lock, compara con `bccomp(…, Money::SCALE)`, guarda y
  devuelve el drift corregido (`actual` = valor anterior), o `null` si ya
  cuadraba. Recalcular antes del lock dejaría que una transferencia
  simultánea entre en medio y se pierda al guardar el valor viejo (*lost
  update*). Los grupos rotos y las transacciones sueltas no tienen
  corrección automática segura (el que está mal es el ledger): solo se
  reportan.

**DTOs** (`App\DTOs\Accounts`, `final readonly`): `BalanceDrift`
(`account_id`, `expected`, `actual`), `BrokenTransferGroup`
(`transfer_group_id`, `reasons`) y `ReconciliationReport` (`drifts`,
`brokenGroups`, `orphanPrivateTransactions`, `isClean()`).

**Saldo esperado en SQL, no en PHP (v2.18).** La expresión vive en la
constante `BalanceReconciler::EXPECTED_SQL` y se reutiliza en las dos
consultas (por team con `HAVING`, y por cuenta en `expectedBalanceOf()`):

```sql
a.initial_balance + COALESCE(SUM(CASE WHEN t.type = ? THEN t.amount ELSE -t.amount END), 0)
```

- El tipo se enlaza con `TransactionType::Income->value`, nunca como
  literal; cada uso de la constante aporta su propio `?`.
- Las consultas usan heredoc (`<<<SQL` con `{$expected}`) en vez de
  concatenar: concatenar ya produjo un `HAVINGa.initial_balance` por un
  espacio perdido. Interpolar es seguro porque `$expected` es una
  constante de la clase; los datos siempre entran por bindings.
- **`COALESCE(…, 0)` es imprescindible:** con `LEFT JOIN`, una cuenta sin
  transacciones da `SUM = NULL`, `initial_balance + NULL = NULL` y
  `NULL <> current_balance` no es verdadero, así que el `HAVING` ocultaría
  justo la cuenta más fácil de verificar.
- **Motivos:** (1) independencia: un verificador que reutiliza
  `signedAmount()` no detecta un bug en `signedAmount()`; (2) exactitud:
  `numeric` de PostgreSQL no usa float; (3) escala: no carga
  transacciones en memoria. La regla del signo vive en dos lugares a
  propósito, como la validación en dos capas (§3.6).

**Comando `nestly:reconcile {--team=} {--fix}`**
(`App\Console\Commands\ReconcileBalancesCommand`):

- `--team` con un slug inexistente → error y exit code 1, antes de
  recorrer nada.
- Recorre los teams con `chunkById(100)`; los teams limpios no imprimen
  nada; los demás imprimen `Team {slug}` y una tabla por lista no vacía.
- Con `--fix`, llama a `fixBalance()` por cada drift e imprime lo
  corregido (`Fixed {id}: {anterior} → {nuevo}`).
- **Exit code = "¿quedó todo bien?":** sin `--fix`, `FAILURE` si algún
  team no está limpio; con `--fix`, `FAILURE` solo si quedan grupos rotos
  o transacciones sueltas (los drifts ya se corrigieron). Sirve como
  chequeo en CI o deploy.

**Job** (`App\Jobs\ReconcileAccountBalancesJob`, `ShouldQueue`):
`Schedule::job(new ReconcileAccountBalancesJob)->weekly()` en
`routes/console.php`. Recorre los teams con `chunkById(100)`, llama a
`check()` y, si no está limpio, escribe `Log::warning('Balance
reconciliation found issues', [...])` con contexto estructurado: `team`
(slug), `drifts` (ids de cuenta), `broken_groups` (ids de grupo) y
`orphan_private_transactions` (ids). Nunca llama a `fixBalance()`. Sin
notificación a usuarios en el MVP: no pueden hacer nada con ella.

**Cobertura** (`tests/Feature/Accounts`):

- `BalanceReconcilerTest`: un team escrito solo por las actions
  (incluidas transferencias que cruzan el perímetro) da `isClean()` (atrapa
  una divergencia entre el enum y el `CASE`); drift; un test por cada
  `BrokenTransferReason` y por la transacción suelta, corrompiendo datos
  con `DB::table()` para saltarse las actions; `fixBalance` corrige y
  deja `check()` limpio, devuelve `null` sin escribir (`updated_at`
  intacto) cuando no hay desviación, devuelve una cuenta sin
  transacciones a su saldo inicial, corrige una cuenta privada, no toca
  otras cuentas y da 404 con una cuenta de otro team.
- `ReconcileBalancesCommandTest`: limpio → éxito sin salida; drift sin
  `--fix` → fallo y saldo intacto; con `--fix` → éxito y saldo
  corregido; grupo roto o transacción suelta con `--fix` → fallo;
  `--team` (los dos teams desviados: el indicado se corrige, el otro no
  aparece ni cambia); `--team` inexistente → fallo.
- Job: limpio no escribe en el log; drift y transacción suelta escriben
  un warning con el contexto correcto (`Log::spy()`); no corrige; está
  programado (`schedule:list`).
- Mutaciones verificadas: invertir el `CASE` → falla "is clean";
  anular `BOOL_AND` con `(FALSE AND …)` → falla solo privada → privada;
  quitar el `continue` del job → falla "clean teams are not logged". Una
  mutación debe romper la lógica, no la sintaxis: quitar un `?` sin su
  binding hace fallar todos los tests y no enseña nada.
- El orden lock → recálculo de `fixBalance` no está cubierto (§3.9).

#### 3.8.4 `SnapshotTeamKpisJob` (mensual) — nice-to-have

No bloqueante para el MVP.

### 3.9 Testing

| Tipo | Cobertura | Herramienta |
|------|-----------|-------------|
| Unit (backend) | 80% dominio | Pest |
| Feature (páginas Inertia + actions con BD) | Flujos críticos | Pest + `assertInertia()` + RefreshDatabase |
| Policies | Matriz de roles | Pest |
| Unit (frontend) | Composables | Vitest |
| E2E | Flujos core por rol | Playwright |
| Load | p95 | k6 (opcional, sintético) |

Las actions que tocan la base de datos (transacciones, bloqueos,
constraints) se prueban en `tests/Feature`, porque `tests/Unit` no arranca
la aplicación. Los helpers compartidos entre archivos de test viven en
`tests/Pest.php` (se carga una sola vez), no como funciones globales en
cada archivo, para evitar *cannot redeclare*. Los helpers que arman DTOs
(`transactionData()`, `transferData()`) reciben un array de overrides y
dejan los IDs en `null` por defecto: si un test olvida pasarlos, falla con
`TypeError` al construir el DTO en vez de probar otra cosa en silencio.

Cada test de un camino de error verifica también la **ausencia de efectos
secundarios** (saldo intacto, `assertDatabaseCount`), no solo que se lance
la excepción. Como hábito de mutation testing manual, se rompe la lógica a
propósito para confirmar que los tests correctos fallan. Una mutación que
sobrevive señala un caso sin test: en `DeleteTransferAction`, cambiar
`count() !== 2` por `isEmpty()` dejaba la suite en verde hasta que se
agregó el test de una transferencia con una sola pata (12 oct).

Los tests HTTP de un endpoint cubren lo que agrega la capa HTTP (cada
tipo de error mapeado a su respuesta: login, 403 del gate, 403 de la
action, 404 de `whereUuid`, 404 de la action, errores de validación en
sesión), no repiten cada regla ya cubierta en el test de la action.

Los tests de policies prueban siempre el caso que deniega **y** el que
permite: una policy que devuelva `false` siempre pasaría un test que solo
verifica el "no".

**Límite conocido:** la suite corre en un solo proceso y una sola
conexión, sin concurrencia, así que no puede detectar regresiones en
garantías de concurrencia. Quitar el `orderBy('id')` del bloqueo de
`CreateTransferAction` deja la suite en verde (verificado con mutation
testing el 6 oct), y lo mismo pasa al quitar el `lockForUpdate()` de la
transacción en `UpdateTransactionAction` (verificado el 10 oct). Esas garantías se protegen con un comentario en el
código que explica el porqué y advierte que no hay test que las cubra;
un test de concurrencia real queda fuera de la suite unitaria.

Ejemplo de test de página:

```php
it('muestra el resumen del dashboard', function () {
    $this->actingAs($user)
        ->get("/{$team->slug}/dashboard")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('totals.balance')
        );
});
```

### 3.10 CI/CD

```yaml
# .github/workflows/main.yml (resumen)
on: [push, pull_request]
jobs:
  backend:
    - composer install
    - php artisan test
    - ./vendor/bin/pint --test
    - ./vendor/bin/phpstan analyse
  frontend:
    - pnpm install
    - pnpm lint && pnpm typecheck
    - pnpm test
  e2e:
    - pnpm playwright test
  deploy:
    - preview en PR (Laravel Forge / Fly preview)
    - production en merge a main
    - migraciones automáticas
```

El job de backend corre sobre **PHP 8.3**, la versión mínima declarada,
para detectar cualquier uso accidental de sintaxis o funciones de
versiones posteriores (§3.13).

### 3.11 Observabilidad

- **Logs**: JSON estructurado, canal dedicado.
- **Métricas**: Telescope (dev). Horizon si se implementa; Pulse para v1.2.
- **Errores**: Sentry (backend + frontend, incluye errores de hidratación de Inertia).
- **Alertas**: aspiracional; sin tráfico real no hay alerting productivo activo.

### 3.12 Entornos

| Entorno | Propósito | Datos |
|---------|-----------|-------|
| local | Dev (Laravel Herd, PHP 8.5; Composer resuelve como 8.3) | Seed demo (`DemoSeeder`) |
| preview | PR review | Efímero |
| staging | QA | Anonimizado |
| production | Live (PHP 8.3+) | Reales |

### 3.13 Riesgos Técnicos

| Riesgo | Mitigación |
|--------|------------|
| Complejidad de scoping por team | Trait + global scope + tests por rol; IDs resueltos por relaciones del team |
| Filtración del saldo de cuentas privadas | Serializado que omite saldos ajenos; balance del dashboard solo con cuentas `team`; tests específicos (§3.3.1) |
| Cálculos de balance inconsistentes | Saldo actualizado en tiempo real por las actions vía `Account::applyTransaction()` (BCMath + `lockForUpdate()`), más `ReconcileAccountBalancesJob` como red de seguridad |
| Imprecisión de `float` en montos | BCMath con escala `Money::SCALE`; montos como `string` en columna, cast y DTO (§3.15.2) |
| *Lost update* por escrituras concurrentes al saldo | `lockForUpdate()` sobre la cuenta dentro de `DB::transaction()` (§3.15.3) |
| Deadlock entre transferencias cruzadas (A→B y B→A al mismo tiempo) | Ambas cuentas se bloquean en un orden global por `id` en una sola consulta (§3.15.4). No lo cubre ningún test (§3.9): lo protege un comentario en el código y la revisión de PRs |
| Autorizar contra datos viejos (*time-of-check / time-of-use*) al borrar o editar transferencias | La regla creador/dueño/admin se decide en la action con las filas bloqueadas, no en la policy (§3.6, §3.15.8, §3.15.9) |
| Timezone en recurrentes | Guardar UTC + tz usuario |
| Sobre-cargar props de una página Inertia (todo en un solo request) | Diseñar props por página con lo mínimo necesario; usar recargas parciales para secciones pesadas |
| Confundir el diseño de la futura API con Sanctum (v1.2/v1.4) con cómo funciona el MVP | Separación explícita en §3.5.4; rutas `/api/v1` no existen hasta v1.2 |
| Empezar mobile antes de cerrar el app de escritorio/web y diluir el enfoque | Secuencia explícita: MVP + v1.1 (desktop/web) completos antes de v1.2 (mobile) |
| ~~PHP 8.3 vs 8.4 en Laravel 13.3+~~ **Cerrado (v2.7)** | Se mantiene PHP 8.3 como mínimo. `composer why-not php 8.3` confirmó que ningún paquete instalado exige más. `composer.json` fija `"php": "^8.3"` + `config.platform.php = 8.3.0` para que futuras actualizaciones no introduzcan dependencias de 8.4+ en silencio; PhpStorm usa language level 8.3 y CI corre en 8.3. |

### 3.14 Features de Laravel 13 relevantes para Nestly

| Feature | Uso potencial | Fase |
|---------|---------------|------|
| Laravel AI SDK | Categorización automática de transacciones por NLP, forecasting de gastos, insights | v2.0 |
| Vector/Semantic Search (pgvector) | Búsqueda semántica en descripciones | v1.2+ |
| Sanctum | Auth del cliente mobile (v1.2) y de la API pública (v1.4) | v1.2/v1.4 |
| Passkeys (starter kits) | Autenticación sin password, más segura | v1.1 |
| Teams en starter kits | Se usa directamente como la entidad `Team` del proyecto (owner, invitaciones, roles vía `team_members`) — ver TRD §3.1.1 | MVP |

### 3.15 Actions de transacciones, dinero y saldos

#### 3.15.1 Patrón Action + DTO

Los casos de uso viven en `app/Actions` y reciben:

- **Contexto explícito** como modelos: `Team $team`, `User $user`. Nunca
  vienen del formulario.
- **Datos del usuario** como DTO `final readonly` en `app/DTOs`
  (ej. `CreateTransactionData`, `CreateTransferData`), con tipos fuertes:
  `TransactionType` en vez de `string`, `CarbonImmutable` para fechas,
  `?string` para campos nullable según el ERD.

```php
public function execute(Team $team, User $user, CreateTransactionData $data): Transaction
public function execute(Team $team, User $user, CreateTransferData $data): TransferResultData
```

Cuando una action devuelve más de un modelo, lo hace con un **DTO de
salida** `final readonly` (ej. `TransferResultData`), no con un array ni
una `Collection`: queda tipado para PHPStan y quien lo usa no tiene que
adivinar claves ni filtrar. Las propiedades de todos los DTOs van en
snake_case.

La action no depende del transporte: la llaman el controller (DTO armado
desde `$request->validated()`), `ProcessRecurringExpensesJob` y el
`DemoSeeder`. El DTO solo contiene campos del alcance vigente (sin
`pot_contribution_id` hasta v1.1).

Cuando dos actions comparten reglas de dominio, se extraen a una clase
`final` en `app/Actions/<Dominio>/Support` inyectada por constructor (ej.
`TransferRules`, §3.15.9), no a un trait: las reglas reciben todo por
parámetro y no dependen del estado de la action. Un trait se reserva para
código que sí usa `$this` del que lo incluye (ej.
`ValidatesTransactionDate` usa `$this->user()` del Form Request).

#### 3.15.2 Dinero con BCMath

- Montos siempre como `string` con 2 decimales: columna `decimal`, cast
  `'decimal:2'` en el modelo, `string` en el DTO (`numeric-string` en
  PHPDoc para PHPStan).
- **Entrada desde el request:** los controllers convierten el monto
  validado con `Money::fromInput(mixed): string` (`@return numeric-string`),
  que hace `(string)` y comprueba `is_numeric()`. PHPStan deduce el tipo
  por esa comprobación (no por un `@var` que solo pide confianza), y si
  alguien quita `numeric` de las reglas el error aparece ahí y no dentro
  de BCMath. Acepta el monto como número (JSON de Inertia) o string.
- Aritmética solo con funciones `bc*` y **escala explícita**, tomada de
  `App\Support\Money::SCALE` (`2`, la escala de las columnas
  `decimal(14,2)`). No se escribe `2` a mano ni se acepta la escala como
  parámetro: es una propiedad del dato, no una opción de quien llama. El
  `php.ini` trae `bcmath.scale = 0`, que trunca centavos en silencio si se
  omite el tercer argumento.
- El signo lo da el tipo, no el monto: `TransactionType::signedAmount(string $amount): string`
  devuelve `$amount` para `Income` y `bcsub('0', $amount, Money::SCALE)`
  para `Expense`. Es la única fuente de verdad del signo; la reutilizan las
  actions de crear/editar/borrar y el job de reconciliación.
- **Actualización del saldo en un solo lugar:**
  `Account::applyTransaction(TransactionType $type, string $amount): static`
  hace `bcadd(current_balance, $type->signedAmount($amount), Money::SCALE)`
  **solo en memoria** y devuelve `$this`. Quien llama hace `save()`
  (`$account->applyTransaction($type, $amount)->save()`), lo que permite
  combinar otros cambios en un único `UPDATE`. Precondición documentada en
  el docblock: la fila debe estar bloqueada con `lockForUpdate()` dentro
  de una transacción de BD; el método no puede verificarlo.
- `TransactionType::getSymbol()` (`+`/`-`) es solo presentación en la UI.

#### 3.15.3 `CreateTransactionAction`

0. **Sin fechas futuras, antes de abrir la transacción de BD:** si
   `$data->date->toDateString() > $user->localToday()` → `ValidationException`
   en `date` (422). No necesita la BD (§3.6). El chequeo vive en
   `TransactionDateRule::assertNotInFuture()` (clase `final` en
   `Actions/Transactions/Support`, inyectada por constructor), que
   comparten `CreateTransactionAction`, `UpdateTransactionAction` y
   `TransferRules`: es la contraparte de dominio del trait
   `ValidatesTransactionDate` del Form Request.

Dentro de un único `DB::transaction()`:

1. **Bloquea la cuenta** con `$team->accounts()->lockForUpdate()->findOrFail(...)`
   — resuelve tenancy y evita *lost updates* entre requests concurrentes
   sobre la misma cuenta.
2. **Verifica tenancy** de cada ID opcional solo cuando tiene valor:
   `category_id` y `recurring_id` vía `$team->categories()` /
   `$team->recurringExpenses()` + `findOrFail()` (404). Se verifican
   también los IDs que hoy solo pone el sistema (`recurring_id`), porque
   la action es un punto de entrada que en v1.2/v1.3 tendrá más
   llamadores.
3. **Cuenta archivada**: si `archived = true` → `ValidationException` en
   `account_id` (422), con la cuenta ya bloqueada (mismo criterio que
   §3.15.4).
4. **Cuenta privada = solo transferencias** (ERD §4.3.15): si la cuenta
   es `private` y el DTO no trae `transfer_group_id`, lanza
   `ValidationException` en `account_id` (422), **también para el dueño**.
5. **Coherencia categoría ↔ tipo**: si `category.kind` no coincide con
   `type` (comparando `->value`, porque son enums distintos) lanza
   `ValidationException::withMessages(['category_id' => ...])` → 422 en
   `form.errors.category_id`, y la transacción de BD se revierte.
6. Crea la transacción y actualiza el saldo con
   `$account->applyTransaction($data->type, $data->amount)->save()`.

`transfer_group_id` no se verifica (no es FK: lo genera la action de
transferencias). La edición seguirá la regla "revertir el efecto viejo
(`getOriginal()`) + aplicar el nuevo", que cubre cambios de monto, cuenta
y tipo sin casos especiales.

`UpdateTransactionAction` en §3.15.6, `DeleteTransactionAction` en
§3.15.7, `DeleteTransferAction` en §3.15.8 y `UpdateTransferAction` en
§3.15.9.

#### 3.15.4 `CreateTransferAction`

```php
public function execute(Team $team, User $user, CreateTransferData $data): TransferResultData
```

Las reglas de los pasos 1, 5, 6 y 7 y la categoría viven en
`TransferRules` (§3.15.9), compartidas con `UpdateTransferAction`.

1. **Origen ≠ destino, antes de abrir la transacción**: si
   `from_account_id === to_account_id`, lanza `ValidationException` en
   `from_account_id` (422). Va antes porque no necesita la BD, y porque sin
   ella el `whereIn` del paso 2 devolvería una sola fila y el error sería
   un 404 confuso en vez de un 422 claro. Justo después, **sin fechas
   futuras**: si la fecha es posterior a `$user->localToday()` →
   `ValidationException` en `date` (422), también sin tocar la BD (§3.6).
2. Dentro de `DB::transaction()`, **bloquea ambas cuentas en una sola
   consulta y en orden global por `id`**:
   `$team->accounts()->lockForUpdate()->whereIn('id', [...])->orderBy('id')->get()`.
   PostgreSQL toma los locks de fila en el orden en que devuelve las
   filas, así que dos transferencias cruzadas (A→B y B→A) intentan
   bloquear primero la misma cuenta y la segunda espera sin tener nada
   tomado: no se forma el ciclo de espera (deadlock, `SQLSTATE 40P01`).
   El criterio solo necesita ser un **orden total y determinista** que
   todos los procesos compartan; que el `id` sea un UUID sin significado
   cronológico no importa. `created_at` no sirve: puede empatar.
3. **Tenancy**: si la consulta no devuelve 2 filas, alguna cuenta no es
   del team → `ModelNotFoundException` (404), igual que `findOrFail`.
4. Separa origen y destino con `keyBy('id')`.
5. **Privacidad** (ERD §4.3.15): si el origen es `private` y `owner_id`
   no es el usuario → `AuthorizationException` (403). Meter dinero en una
   cuenta privada ajena (destino) está permitido.
6. **Privada → privada → 422** en `to_account_id` (ERD §4.3.15, v2.16):
   una cuenta privada solo mueve dinero a través del perímetro (team →
   privada o privada → team). Se rechaza también entre dos cuentas del
   mismo dueño y en cualquier team (no depende de `is_personal`).
   Necesita la `visibility` de ambas, así que va con las filas
   bloqueadas. Va **después** del 403: si alguien intenta sacar de la
   privada de otro hacia una privada, recibe 403 y no aprende nada sobre
   el destino de una operación que de todos modos no puede hacer. El
   error va en `to_account_id` porque, con un origen privado, lo que
   hay que corregir es el destino.
7. **Cuenta archivada**: si el origen o el destino tiene `archived = true`
   → `ValidationException` (422) en `from_account_id` o `to_account_id`.
   Es 422 y no 404 porque la cuenta es del team y el usuario puede verla
   archivada; no hay nada que ocultar. Se verifica aquí, con las filas ya
   bloqueadas, y no como `where('archived', false)` en la consulta del
   paso 2: filtrarla haría que faltara una fila y caería en el 404 de
   tenancy. Va después del 403, igual que `authorize()` corre antes que
   `rules()` en un Form Request.
8. Genera `transfer_group_id` (`Str::uuid()->toString()`) y crea las dos
   filas (`expense` en el origen, `income` en el destino, mismo monto,
   fecha, descripción y `created_by`).
9. Aplica `applyTransaction()` + `save()` a ambas cuentas.
10. Devuelve `TransferResultData(transfer_group_id, expense_transaction, income_transaction)`.

**Decisión: filas creadas a mano en vez de llamar dos veces a
`CreateTransactionAction`.** Reutilizarla habría funcionado: los locks de
fila pertenecen a la transacción, así que volver a pedir `FOR UPDATE`
sobre una cuenta ya bloqueada no espera, y el `DB::transaction()` anidado
es un *savepoint* dentro de la misma transacción. Se descartó para evitar
dos `SELECT` redundantes sobre cuentas que ya están en memoria; la
duplicación que eso implicaba (la aritmética del saldo) se eliminó
extrayéndola a `Account::applyTransaction()`.

**Categoría opcional (implementada el 14 oct, ERD §4.3.17):**
`CreateTransferData` tiene `?category_id` (`nullable|uuid` en
`StoreTransferRequest`). Se valida después de todos los chequeos de
cuentas (si las cuentas no sirven, la categoría no importa), en este
orden y con las cuentas bloqueadas:

1. **Tenancy** de la categoría vía `$team->categories()->findOrFail()` →
   404, el mismo patrón que `CreateTransactionAction`.
2. **Perímetro:** si origen y destino tienen la misma `visibility` → 422
   en `category_id`. Como privada → privada ya se rechazó en el paso 6,
   aquí solo puede ser team → team.
3. **`kind` de la pata `team`:** la pata `team` se deduce de la
   visibilidad del origen, sin un `if` por dirección: si el origen es
   `team`, el dinero sale del team y la pata `team` es la `expense`; si
   no, entra y es la `income`. Si `category.kind` no coincide → 422 en
   `category_id`.

La categoría se guarda solo en la fila de la pata `team`; la de la cuenta
privada queda con `category_id = null`. `UpdateTransferAction` reutiliza
las mismas reglas vía `TransferRules::resolveCategoryIds()`.

**Cobertura** (`tests/Feature/Transactions/CreateTransferActionTest.php`):
caso feliz (saldos, 2 filas, tipos, cuentas, `transfer_group_id`,
`amount`, `created_by`), misma cuenta → 422, origen y destino de otro
team → 404, sacar de una privada ajena → 403, el dueño saca de su privada
→ OK, meter en una privada ajena → OK, privada propia → privada ajena →
422 en `to_account_id`, privada ajena → privada propia → 403 (no 422),
origen o destino archivado → 422 en su campo; categoría: team → privada
la guarda solo en la `expense`, privada → team solo en la `income`, sin
categoría ambas `null`, team → team con categoría → 422, `kind` que no
coincide (dataset por dirección) → 422, categoría de otro team → 404.
HTTP: transferencia categorizada a una privada, team → team con
categoría → error en `category_id`, `category_id` no UUID → 422. En cada error: saldos
intactos y 0 transacciones. El orden de bloqueo **no** está cubierto
(§3.9).

#### 3.15.6 `UpdateTransactionAction`

```php
public function execute(Team $team, User $user, string $transactionId, UpdateTransactionData $data): Transaction
```

`UpdateTransactionData` tiene los campos editables de una transacción
normal (`account_id`, `category_id`, `type`, `amount`, `date`,
`description`, `notes`); no incluye `transfer_group_id` ni `recurring_id`,
que pone el sistema y no se editan.

**Estrategia de saldo: revertir + aplicar.** Primero se deshace el efecto
original (`applyTransaction($oldType->opposite(), $oldAmount)` sobre la
cuenta original) y luego se aplica el nuevo como si se creara. Los mismos
dos pasos cubren cambios de monto, tipo y cuenta sin casos especiales.
Como `current_balance` es un total acumulado (ERD §4.3, regla 2) y ninguna
fila guarda "el saldo después de mí", editar una transacción vieja no
obliga a tocar las posteriores. El saldo histórico (RF-03.5) se calcula
al leer con `SUM(...) WHERE date <= X`.

Pasos:

0. **Sin fechas futuras**, antes de abrir la transacción de BD (§3.6).
1. **Bloquea la transacción** con
   `$team->transactions()->lockForUpdate()->findOrFail($id)` (404 si es de
   otro team). Va primero: sin ese lock, dos ediciones simultáneas leerían
   el mismo original y lo revertirían dos veces (*lost update*), y además
   hace falta su `account_id` real para saber qué cuentas bloquear.
2. **Pata de transferencia → 422** en `account_id`: si tiene
   `transfer_group_id`, se edita como transferencia
   (`UpdateTransferAction`, §3.15.9), nunca fila por fila.
3. **Bloquea cuenta vieja y nueva en una sola consulta ordenada por `id`**
   (`whereIn` + `orderBy('id')`, mismo criterio que §3.15.4). Si falta
   alguna, 404. **Orden global de locks: transacción → cuentas por `id`.**
   `CreateTransactionAction` y `CreateTransferAction` solo bloquean
   cuentas, así que no forman ciclo con este orden.
4. **Cuenta vieja archivada → 422** (hay que reactivarla para editar su
   historial). **Cuenta nueva archivada o privada → 422.** Categoría:
   tenancy (404) y coherencia `kind` ↔ `type` (422), igual que al crear.
5. **Revertir + aplicar** y `save()` de las cuentas bloqueadas (si la
   cuenta no cambia, es la misma instancia y se guarda una vez).

**Endpoint:** `PATCH /{current_team}/transactions/{transaction}`
(`transactions.update`) con `scopeBindings()`: Laravel resuelve
`{transaction}` a través de `$current_team->transactions()`, así que una
transacción de otro team da 404 antes del controller. El controller llama
`Gate::authorize('update', $transaction)`: `TransactionPolicy::update()`
exige rol member+ en el team y, además, ser el creador o admin+.
`UpdateTransactionRequest` repite las reglas de `StoreTransactionRequest`.

**Cobertura** (`UpdateTransactionActionTest`, `TransactionControllerTest`):
cambio de monto, de tipo y de cuenta; edición de una transacción vieja sin
afectar las posteriores; pata de transferencia → 422; cuenta vieja o nueva
archivada → 422; cuenta nueva privada → 422; categoría incoherente → 422;
fecha futura → 422; transacción o cuenta de otro team → 404; creador OK,
otro member → 403, admin OK, viewer → 403. En cada error, saldos intactos.

#### 3.15.7 `DeleteTransactionAction`

```php
public function execute(Team $team, string $transactionId): void
```

Dentro de un único `DB::transaction()`:

1. **Bloquea la transacción** vía `$team->transactions()` (404 si no existe
   o es de otro team). Si dos usuarios borran la misma a la vez, el segundo
   espera el lock y, al liberarse, `findOrFail` ya no la encuentra → 404,
   sin código extra.
2. **Pata de transferencia → 422** en `account_id` ("no puede eliminarse
   por separado"). Las transferencias se borran completas con
   `DeleteTransferAction` (§3.15.8).
3. **Bloquea la cuenta** vía `$team->accounts()->lockForUpdate()` (evita
   *lost update* con escrituras concurrentes sobre el saldo; nunca
   `Account::find` global, §3.3.1).
4. **Cuenta archivada → 422** (reactivarla primero, §3.15.6).
5. **Revertir** (`applyTransaction($type->opposite(), $amount)`) y
   `delete()`. De "revertir + aplicar", borrar solo necesita la primera
   mitad.

No se valida cuenta privada: en una privada solo existen patas de
transferencia, ya rechazadas en el paso 2. No aplican fecha ni categoría
(no hay datos nuevos).

**Endpoint:** `DELETE /{current_team}/transactions/{transaction}`
(`transactions.destroy`) con `scopeBindings()`;
`TransactionPolicy::delete()` = misma regla que `update()`.

**UI (decisión 10 oct):** el grid es único y cada fila tiene una sola
acción "Eliminar". El frontend elige el endpoint por `transfer_group_id`:
fila normal → `DELETE /transactions/{id}`; pata de transferencia →
`DELETE /transfers/{transfer_group_id}`, con un modal que avisa que se
eliminan los dos movimientos. Opcional: en la vista general del team, cada
transferencia se muestra como una sola fila (como YNAB/Monarch) y sus
patas solo en la vista por cuenta.

**Cobertura** (`DeleteTransactionActionTest`, `TransactionControllerTest`):
borrar gasto e ingreso restaura el saldo; no toca transacciones
posteriores ni otras cuentas; pata de transferencia → 422; cuenta
archivada → 422; otro team o inexistente → 404; HTTP: invitado, creador,
otro member → 403, admin. El lock de la transacción no está cubierto
(límite §3.9).

#### 3.15.8 `DeleteTransferAction`

```php
public function execute(Team $team, User $user, string $transferGroupId): void
```

Dentro de un único `DB::transaction()`:

1. **Bloquea las dos patas** con
   `$team->transactions()->lockForUpdate()->where('transfer_group_id', …)->orderBy('id')->get()`.
   **Orden global de locks: transacciones → cuentas por `id`**, el mismo
   que update y delete de transacciones.
2. **Exactamente 2 filas, o 404** (`ModelNotFoundException` sobre
   `Transaction`). Cubre la transferencia inexistente, la de otro team y
   la **incompleta** (una sola pata por un borrado manual o un bug):
   sin este guard, la segunda pata sería `null` y la action fallaría con
   un 500. Con `isEmpty()` en vez de `count() !== 2` la suite seguía en
   verde hasta que se agregó el test de una sola pata.
3. **Identifica las patas por `type`**, no por posición
   (`firstWhere('type', Expense)` = origen, `Income` = destino): el orden
   por `id` (UUID v7) no garantiza el orden de creación dentro del mismo
   milisegundo.
4. **Bloquea ambas cuentas** en una sola consulta por `id`, vía
   `$team->accounts()`, y las separa con `keyBy('id')`.
5. **Permiso (caso A/B, §3.6):** creador (`created_by`), dueño
   (`owner_id`) de alguna de las dos cuentas o admin+ → si no,
   `AuthorizationException` (403). El rol member+ ya lo verificó
   `TransactionPolicy::deleteTransfer()` desde el controller. Va después
   de los locks para decidir con los datos que se van a borrar.
6. **Cuenta archivada (origen o destino) → 422** en `account_id`, la
   misma clave que `DeleteTransactionAction`, para que el grid lea el
   error de un solo lugar.
7. **Revertir ambas patas** (`applyTransaction($type->opposite(), $amount)->save()`)
   y borrar las dos filas.

No necesita la regla privada → privada: borrar no crea datos nuevos, y
esas transferencias ya no pueden crearse.

**Endpoint:** `DELETE /{current_team}/transfers/{transfer_group_id}`
(`transfers.destroy`) con `->whereUuid('transfer_group_id')`,
`Gate::authorize('deleteTransfer', [Transaction::class, $current_team])`,
`$request->user()` y Post-Redirect-Get. **Por qué `whereUuid`:** sin la
restricción, un id como `abc` llega a la action y PostgreSQL lo rechaza
contra la columna `uuid` con `SQLSTATE 22P02` (*invalid input syntax for
type uuid*), que Laravel convierte en `QueryException` → **500**. Con
`whereUuid` la ruta no coincide y Laravel responde **404** antes del
controller: para el usuario, un id malformado es un recurso que no
existe. Sin `scopeBindings()`: no hay modelo que resolver.

**Cobertura HTTP** (`TransferControllerTest`): invitado → login; creador
borra (redirect con `success`, 0 filas, saldos revertidos); viewer → 403
(policy); member ajeno → 403 (action); otro team → 404; `abc` → 404
(`whereUuid`); origen o destino archivado → error en `account_id`
(dataset). En cada error, filas y saldos intactos.

**Cobertura de la action** (`DeleteTransferActionTest`): creador borra y ambos saldos
vuelven; dueño de la privada destino (no creador) borra; admin (ni
creador ni dueño) borra; inexistente → 404; de otro team → 404; con una
sola pata → 404; member ajeno → 403; origen o destino archivado → 422;
policy: viewer denegado y member permitido. En cada error, saldos y filas
intactos. Mutaciones verificadas el 12 oct: sin `save()` en el destino →
3 fallos; sin `$isAdmin` → 1 fallo; `isEmpty()` → 1 fallo (test de una
sola pata).

#### 3.15.9 `TransferRules` y `UpdateTransferAction`

**`TransferRules`** (`App\Actions\Transactions\Support`, clase `final`
inyectada por constructor en `CreateTransferAction` y
`UpdateTransferAction`). Concentra las reglas de dominio de una
transferencia para que crear y editar no puedan divergir:

| Método | Cuándo corre | Qué valida |
|---|---|---|
| `assertValidInput(User, fromId, toId, date)` | Antes de `DB::transaction()` | Origen ≠ destino (422 en `from_account_id`), sin fecha futura (422 en `date`) |
| `assertAccounts(User, Account $from, Account $to)` | Con las cuentas bloqueadas | Sacar de una privada ajena (403), privada → privada (422 en `to_account_id`), origen o destino archivado (422 en su campo), en ese orden |
| `resolveCategoryIds(Team, $from, $to, ?categoryId)` | Después de `assertAccounts()` | Tenancy (404), perímetro (422), `kind` de la pata `team` (422). Devuelve `['expense' => ?id, 'income' => ?id]` |

`resolveCategoryIds()` asume que ya corrió `assertAccounts()`: con
privada → privada rechazada, "misma visibilidad" solo puede ser team →
team. Es una clase y no un trait porque todo le llega por parámetro
(§3.15.1).

**`UpdateTransferAction`**

```php
public function execute(Team $team, User $user, string $transferGroupId, UpdateTransferData $data): void
```

`UpdateTransferData` (`final readonly`) tiene `from_account_id`,
`to_account_id`, `amount`, `category_id`, `description` y `date`: el
formulario completo, como al crear.

**Decisión: editar muta las filas existentes, no "borrar + crear".**
Recrear la transferencia cambiaría el `transfer_group_id` (rompe la URL
del recurso y el rastro en `audit_logs`) y el `created_by` (quedaría como
creador quien edita, y el creador original perdería el permiso del caso
A).

Pasos:

0. **`assertValidInput()`** antes de abrir la transacción de BD: origen =
   destino o fecha futura → 422 sin tocar la BD (ni siquiera se busca la
   transferencia).
1. **Bloquea las patas** del grupo (`lockForUpdate()` + `orderBy('id')`,
   vía `$team->transactions()`). `count() !== 2` → 404, como en
   §3.15.8. Las patas se identifican por `type`.
2. **Bloquea hasta 4 cuentas en una sola consulta por `id`:** las viejas
   (de las patas) y las nuevas (del DTO), deduplicadas con
   `array_unique`. Si falta alguna → 404 (una cuenta nueva de otro team
   no se distingue de una inexistente). **Orden global de locks:
   transacciones → cuentas por `id`**, igual que el resto de actions.
3. **Permiso del caso A contra las cuentas viejas:** creador, dueño de
   una de las cuentas **viejas** o admin+ → si no, 403. Se evalúa con las
   viejas porque el permiso es sobre la transferencia que existe, no
   sobre la que se pide: con las nuevas, un member podría ganar permiso
   moviendo la transferencia a su propia cuenta privada (cubierto por el
   test del intruso).
4. **Cuentas viejas archivadas → 422** en `from_account_id` /
   `to_account_id`: para editar el historial de una cuenta archivada hay
   que reactivarla (mismo criterio que §3.15.6).
5. **Reglas de creación sobre las cuentas nuevas:**
   `assertAccounts()` + `resolveCategoryIds()`.
6. **Todas las validaciones antes de mutar.** Recién entonces revertir
   las patas viejas (`applyTransaction($type->opposite(), …)` sobre las
   cuentas viejas) y aplicar las nuevas (`Expense` en el nuevo origen,
   `Income` en el nuevo destino).
7. **Un solo `save()` por cuenta:** las cuentas vienen de la misma
   colección `keyBy('id')`, así que si una cuenta vieja y una nueva son la
   misma, son la **misma instancia** en memoria: el revert y el apply se
   acumulan sobre ella y `$accounts->each->save()` escribe una vez el
   resultado. Con dos instancias distintas de la misma fila, el segundo
   `save()` pisaría al primero (*lost update* dentro del mismo request).
8. **`fill()` + `save()` de ambas patas** con cuenta, monto, fecha,
   descripción y `category_id`. La categoría **siempre** se reasigna
   (aunque venga `null`), para que pasar de una transferencia que cruza
   el perímetro a team → team limpie la categoría vieja. No se tocan
   `transfer_group_id` ni `created_by`.

**Endpoint:** `PATCH /{current_team}/transfers/{transfer_group_id}`
(`transfers.update`) con `->whereUuid('transfer_group_id')` (mismo motivo
que §3.15.8), `UpdateTransferRequest` (§3.6),
`Gate::authorize('updateTransfer', [Transaction::class, $current_team])`
(rol member+), DTO armado como en `store` (`Money::fromInput()`,
`CarbonImmutable::parse()`) y Post-Redirect-Get con
`back()->with('success', …)`.

**Cobertura de la action** (`UpdateTransferActionTest`): cambio de monto
con las mismas cuentas (grupo y creador intactos); cambio de destino;
cambio de ambas cuentas (4 saldos consistentes); el dueño de la privada
vieja edita sin ser creador; admin+ edita (dataset); member ajeno → 403;
member que mueve la transferencia a su propia privada → 403 (intruso);
cuenta vieja archivada → 422 (dataset por campo); cuenta nueva archivada
→ 422; destino nuevo en una privada ajena → OK; origen nuevo en una
privada ajena → 403; privada → privada → 422; cuenta nueva de otro team →
404; de cruzar el perímetro a team → team limpia la categoría; `kind`
incorrecto → 422; fecha futura y origen = destino → 422 antes de buscar
la transferencia; una sola pata → 404. En cada error, saldos intactos.

**Cobertura HTTP** (`TransferControllerTest`): creador edita (redirect
con `success`, 2 filas, saldos nuevos); invitado → login; viewer → 403
(gate); member ajeno → 403 (action); `abc` → 404 (`whereUuid`); grupo
inexistente → 404; validación (dataset): misma cuenta, fecha futura y
monto faltante con error en su campo. En cada error, saldos intactos.

#### 3.15.5 Seeders y eventos de modelo

`DatabaseSeeder` **no** usa `WithoutModelEvents`: ese trait silencia todos
los observers de los seeders que llama con `$this->call()`, incluido
`TeamObserver`, que es una invariante de dominio (un team sin categorías
por defecto queda roto). Cuando exista el observer de auditoría, se
silencia de forma selectiva (`Model::withoutEvents()` por bloque,
variantes `*Quietly` o una bandera en el observer), nunca con el trait
global.

El `DemoSeeder` registra todas las transacciones a través de
`CreateTransactionAction`, con montos fijos como `string`, para que los
saldos del demo sean coherentes y verificables a mano.

---

**Fin de la Parte 2 de 5.**
