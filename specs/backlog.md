# Backlog

Trabajo identificado que todavía no tiene spec.

## Spec `002-accounts`

Además de crear, editar, archivar y eliminar cuentas (D-014, D-015), la spec implementa la moneda por cuenta de D-084.
Lo que hay que tocar:

**Configuración**

- `config/nestly.php` nuevo, con `currencies` (lista de monedas soportadas: DOP, USD, EUR) y `default_currency`, leída
  de `NESTLY_DEFAULT_CURRENCY`. `.env.example` la fija en DOP. `default_currency` tiene que estar en la lista.
- Validar el código de moneda contra esa lista en los Form Requests, sin dependencias nuevas (D-072).

**Teams**

- `teams.currency` se mantiene sin renombrar, como moneda por defecto. Los teams nuevos, incluido el personal del
  registro, toman `config('nestly.default_currency')`, no el `default('USD')` de la migración.
- Editable siempre, validada contra la lista. Cambiarla no afecta a las cuentas existentes.

**Cuentas**

- Migración: `accounts.currency char(3) NOT NULL` con `CHECK (currency ~ '^[A-Z]{3}$')`. Las filas existentes se
  rellenan con la moneda de su team.
- La action de crear cuenta exige la moneda; el formulario la rellena por defecto con `teams.currency`.
- La action de editar cuenta rechaza con 422 un cambio de moneda si la cuenta tiene movimientos.
- `AccountResource` expone `currency`, también en cuentas privadas: es visible para el team, como el nombre.
- `AccountController::index` deja de enviar `$current_team->currency`, e `accounts/Index.vue` formatea cada cuenta con
  su moneda.

**Transferencias**

- `StoreTransferRequest`, `UpdateTransferRequest`, `CreateTransferData` y `UpdateTransferData` añaden `to_amount`
  opcional, con las mismas reglas que `amount`.
- `TransferRules`: con la misma moneda, si se omite `to_amount` se copia `amount`, y si es distinto da 422. Con monedas
  distintas, `to_amount` es obligatorio. Al editar, la regla se valida contra las cuentas nuevas, porque cambiar una
  cuenta puede cambiar el par de monedas.
- `CreateTransferAction` y `UpdateTransferAction` escriben `to_amount` en la pata de ingreso y lo aplican al saldo de la
  cuenta destino. `DeleteTransferAction` ya revierte cada pata con su propio monto y no cambia.
- Helpers de `tests/Pest.php`: `transferData`, `recordTransfer` y `transferLegs`.

**Reconciliación**

- `BalanceReconciler::brokenGroupsFor` marca `AmountMismatch` solo si las dos cuentas tienen la misma moneda
  (`COUNT(DISTINCT a.currency) = 1 AND COUNT(DISTINCT t.amount) <> 1`). Sigue siendo SQL independiente de las actions.
- El cálculo de saldos no cambia: cada cuenta suma solo sus filas.

**Budgets** (todavía sin rutas)

- `budgets.currency char(3) NOT NULL`. La exclusión `budgets_no_overlap` añade `currency WITH =`: un budget por
  categoría, moneda y periodo. Revisar si su entrada en `ConstraintViolations::MAP` cambia.
- `spent` (D-040) suma solo transacciones de cuentas de la moneda del budget.

**Seeders y docs**

- `DemoSeeder` asigna la moneda de cada cuenta en vez de cambiar `teams.currency`.
- Notas en ERD, TRD y PRD donde hablen de una moneda por team (D-071).

## Concurrencia

Los locks de las acciones de dinero no tienen tests: la suite corre en un solo proceso. Quitar el orden por `id` de los
locks de una transferencia, o recalcular el saldo antes del lock en `BalanceReconciler::fixBalance`, deja la suite en
verde y solo falla bajo carga. Hace falta una spec que decida cómo probarlo, por ejemplo con varios procesos contra
PostgreSQL, y qué casos cubrir: transferencias cruzadas A→B y B→A, edición y borrado simultáneos, y `--fix` con
escrituras concurrentes.

## Spec `003-user-anonymization`

Idea para esa spec, todavía sin aprobar: D-060 pasaría a ser híbrida. Cambiaría el comportamiento actual, pero no se
implementa hasta entonces.

| Caso                                         | Qué pasa                                                                        |
| -------------------------------------------- | ------------------------------------------------------------------------------- |
| Teams donde está solo (incluido el personal) | Se borran con todo su contenido, tras 30 días de gracia                         |
| Teams compartidos que es owner               | Debe transferir el ownership o sacar a los miembros antes                       |
| Teams compartidos donde es miembro           | Sus datos se quedan y se anonimiza su autoría; sus cuentas privadas se archivan |

Choques de esta idea con lo que existe hoy, a resolver en la spec:

- **D-004:** "se borran con todo su contenido" contradice el soft delete de teams, que se decidió para poder recuperar y
  auditar datos financieros. D-004 admite una purga definitiva como proceso explícito aparte; hay que decidir si los 30
  días de gracia terminan en esa purga.
- **Transferir el ownership no existe:** `TeamRole::assignable()` excluye a `Owner`, y no hay ninguna ruta ni action
  para cambiarlo. La spec tiene que incluir esa función o depender de otra. Sacar a los miembros sí existe
  (`RemoveTeamMemberAction`).

Pendientes de la mitigación temporal de D-060 y D-076, a resolver en esa spec:

- **Team personal vacío:** borrar un usuario sin movimientos deja su team personal sin miembros. La fila sigue
  existiendo, junto con lo que contenga (por ejemplo, cuentas sin movimientos), y nadie puede acceder a ella.
- **Bloqueos sin salida:** no hay rutas para borrar cuentas privadas ni gastos recurrentes, y los movimientos
  registrados no pueden dejar de bloquear sin perder historial. Un usuario en alguno de estos casos no puede borrar su
  cuenta por su cuenta, y el mensaje de error dice el motivo, pero no qué hacer. Tampoco tiene salida si el bloqueo está
  en un team que dejó o que se borró.

## Auditoría completa (Fase 9)

El modelo `AuditLog` ya puede guardar registros, pero nada los crea todavía. Hace falta una spec que responda antes
estas preguntas:

1. **Qué se audita:** ¿todas las acciones o solo las sensibles?
2. **Privacidad:** ¿cómo evitar que `changes` exponga saldos de cuentas privadas a quien lea el log (D-011 a D-017)?
3. **Observer o actions:** el TRD habla de un observer, pero los `update()` y `delete()` masivos no disparan eventos de
   modelo. Por ejemplo, el archivado en `RemoveTeamMemberAction`, que tiene un `TODO(audit)`.
4. **Retención:** `team_id` y `user_id` quedan en `NULL` si se borra el team o el usuario, y el registro pierde el
   rastro. ¿Se acepta? Se cruza con D-060.

A resolver también: `audit_logs.user_agent` es `varchar(255)`, así que un user agent más largo daría error al insertar.
Hoy no pasa porque nada escribe en esa columna.

## Zona horaria del usuario

`users.timezone` existe (por defecto `UTC`) y `User::today()` la usa para D-020, pero nada la rellena: ni el registro
ni el perfil la piden. Hoy todos los usuarios están en UTC, así que en República Dominicana (UTC−4) "hoy" cambia de día
a las 20:00. La spec 001 deja fuera de dónde sale; sus helpers de fecha reciben la zona como argumento.

Hay que decidir de dónde sale (detectada en el navegador, elegida en el perfil o ambas) y quién la edita, en la primera
spec que muestre fechas de movimientos.

## Tests de navegador en Windows

- **Subir Playwright:** antes de subir, comprobar que el Chromium de la nueva versión arranca con Smart App Control
  activado (D-097).
- **`public/hot` huérfano:** en Windows, `public/hot` queda huérfano al cortar `composer dev` y rompe los tests de
  navegador en local. Evaluar que la suite `Browser` ignore el hot file.

## Landing pública para el portafolio

La spec 001 redirige `/` al login o al Inicio y borra la página de bienvenida del starter kit. Una portada pública
mínima, con capturas reales y acceso a la demo, serviría para enseñar el proyecto. Se evalúa cerca del despliegue.
