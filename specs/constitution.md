# Constitución de Nestly

Principios y reglas que no cambian de una spec a otra. Este documento resume y remite a las decisiones de
`specs/foundation/decisions.md`; no las repite. Si una regla tiene decisión, el detalle está allí.

## 1. Propósito y jerarquía

Nestly es una app de finanzas personales y familiares. Un usuario lleva sus finanzas en un team personal y puede
compartir otras con su familia en teams compartidos, sin perder la privacidad de lo que es solo suyo.

Cuando dos fuentes se contradicen, manda la de más arriba:

1. `specs/foundation/decisions.md` y el código (D-071).
2. Esta constitución.
3. Los docs: PRD, TRD y ERD. Sus secciones obsoletas llevan una nota que remite a la decisión.

`CLAUDE.md` es la guía operativa: comandos, arquitectura y convenciones del día a día. No crea reglas de negocio.

## 2. Principios técnicos

- **Monolito:** Laravel + Inertia + Vue 3 con TypeScript, sin una API REST aparte.
- **Capas separadas:** el controller solo orquesta (Form Request → `Gate::authorize` → DTO → action). Las policies
  responden solo preguntas de rol y de propiedad. Las reglas de negocio viven en las actions y responden 422 (D-014).
  Seeders, jobs y tests usan las mismas actions.
- **Multi-tenancy estricto:** todo dato pertenece a un team. Todo id de una request se resuelve a través del team de la
  ruta (D-073), y PostgreSQL impide referencias entre teams (D-074).
- **Dinero exacto y consistente:** BCMath, locks ordenados y saldo desnormalizado que solo cambian las actions (D-078).
  Cada cuenta tiene su moneda y no se convierte entre monedas (D-084).
- **Integridad en dos capas:** los Form Requests validan la entrada y PostgreSQL garantiza los invariantes con `CHECK` y
  restricciones de exclusión. Las violaciones que un usuario puede provocar se traducen a 422.
- **Plataforma fija:** PostgreSQL 18 (D-075) y PHP 8.5 (D-086), iguales en local, CI y producción.
- **Dependencias justificadas:** no se instala nada por estar en el TRD; cada dependencia la justifica una spec (D-072).
- **Idioma:** la interfaz está en español y sus textos van en archivos de traducción; el código, los comentarios y los
  commits van en inglés (D-085). El frontend traduce con el helper `t()` (D-091).
- **Sin escrituras offline en el MVP** (D-070).

## 3. Reglas no negociables

Ninguna spec puede romper estas reglas sin una decisión nueva que las reemplace.

**Aislamiento entre teams**

- Un recurso de otro team responde 404, nunca 403, sea cual sea el rol del actor (D-073).

**Dinero**

- Nunca se usan floats para montos (D-078).
- `current_balance` solo cambia con `Account::applyTransaction()` dentro de una action (D-078).
- Toda escritura de dinero bloquea sus cuentas dentro de una transacción, en orden por `id` (D-078).
- Una transferencia son siempre dos patas con el mismo `transfer_group_id`, y solo la cambian sus actions (D-078).
- No se registran movimientos con fecha futura (D-020).
- La reconciliación solo reporta; corregir es un paso manual explícito (D-030).

**Privacidad**

- El saldo de una cuenta privada solo lo ve su dueño, ni siquiera el owner del team (D-011).
- La pata de una transferencia en una cuenta privada solo la ve su dueño (D-080).
- Las cuentas privadas solo reciben transferencias, nunca de privada a privada, y no existen en teams personales
  (D-011, D-012, D-013).
- Solo el dueño saca dinero de su cuenta privada (D-079).
- Los datos financieros pertenecen al team; no hay finanzas privadas dentro de un team (D-010).

**Teams**

- Un team tiene exactamente un owner (D-089), que no puede salir de él (D-081).
- Los teams personales no se eliminan ni se abandonan (D-082).

**Proceso**

- No se implementa ninguna regla de negocio que no esté en `decisions.md` o en una spec aprobada.
- Si los docs y el código se contradicen y `decisions.md` no lo resuelve, se pregunta antes de elegir.

## 4. Matriz de permisos por rol

Resume las policies y las decisiones de roles (D-003, D-077). Donde una celda depende de una condición, la condición
está en la columna de notas.

| Acción                                                                                    | Owner | Admin |  Member  | Viewer | Notas                                                            |
| ----------------------------------------------------------------------------------------- | :---: | :---: | :------: | :----: | ---------------------------------------------------------------- |
| Ver el team y sus datos financieros                                                       |  Sí   |  Sí   |    Sí    |   Sí   | Salvo lo privado de otros (D-011, D-080)                         |
| Ver nombre y moneda de cualquier cuenta                                                   |  Sí   |  Sí   |    Sí    |   Sí   | También de las privadas (D-084)                                  |
| Ver saldo de una cuenta privada                                                           |   —   |   —   |    —     |   —    | Solo su dueño (D-011)                                            |
| Editar el team                                                                            |  Sí   |  Sí   |    No    |   No   |                                                                  |
| Eliminar el team                                                                          |  Sí   |  No   |    No    |   No   | Nunca un team personal (D-082)                                   |
| Salir del team                                                                            |  No   |  Sí   |    Sí    |   Sí   | Nunca de un team personal (D-081, D-082)                         |
| Invitar miembros                                                                          |  Sí   |  Sí   |    No    |   No   | Admin: solo member y viewer. Nadie como owner (D-077)            |
| Cancelar invitaciones                                                                     |  Sí   |  Sí   |    No    |   No   | Admin: solo de member y viewer (D-077)                           |
| Cambiar el rol de un miembro                                                              |  Sí   |  Sí   |    No    |   No   | Admin: solo entre member y viewer. Nadie el del owner            |
| Eliminar miembros                                                                         |  Sí   |  Sí   |    No    |   No   | Admin: solo member y viewer (D-077)                              |
| Gestionar cuentas del team                                                                |  Sí   |  Sí   |    No    |   No   | Eliminar solo sin movimientos; si no, archivar (D-015)           |
| Crear una cuenta privada                                                                  |  Sí   |  Sí   |    Sí    |   No   | No en teams personales (D-013)                                   |
| Gestionar una cuenta privada                                                              |   —   |   —   |    —     |   —    | Solo su dueño, mientras sea al menos member                      |
| Gestionar categorías                                                                      |  Sí   |  Sí   |    No    |   No   | Las de sistema solo se archivan (D-083)                          |
| Gestionar budgets y recurrentes                                                           |  Sí   |  Sí   |    No    |   No   |                                                                  |
| Registrar transacciones y transferencias                                                  |  Sí   |  Sí   |    Sí    |   No   | Desde una cuenta privada, solo su dueño (D-079)                  |
| Editar o borrar transacciones                                                             |  Sí   |  Sí   | Propias  |   No   |                                                                  |
| Editar una transferencia que sale de una cuenta privada                                   |   —   |   —   |    —     |   —    | Solo su dueño, ni owner ni admin (D-079)                         |
| Editar una transferencia hacia una cuenta privada, o borrar cualquiera con cuenta privada |  Sí   |  Sí   | Ver nota |   No   | Member: si es el creador o el dueño de la cuenta privada (D-021) |

## 5. Criterios de calidad

- **Tests primero.** Todo cambio de código empieza por un test que falla.
- **Tests de feature con Pest** sobre PostgreSQL real, nunca SQLite.
- **Frontend y E2E:** cada criterio se prueba en el nivel más barato: Vitest para funciones puras, Pest feature para
  HTTP y tests de navegador solo para lo que necesita layout o JavaScript (D-094).
- **Accesibilidad:** toda la UI cumple WCAG 2.2 AA, y se verifica con tests, no solo con axe (D-095).
- **Rutas de error:** cada test de error comprueba que saldos y filas quedaron intactos.
- **Mutation checks:** los tests que protegen seguridad o dinero se validan rompiendo a propósito el código que
  protegen, confirmando que fallan y revirtiendo.
- **Tests cross-team** con el rol más privilegiado como actor, para que un chequeo de rol no esconda un filtro de team
  ausente (D-073).
- **Sin umbral de cobertura** (D-088).
- **CI siempre verde.** `composer ci:check` pasa antes de cada commit con código; los commits de solo documentación
  pasan `npm run check` (D-087). El trabajo pendiente se marca con `->todo()` o `->skip()`.
- **Trazabilidad:** un commit por tarea, que cita su spec o su decisión.

## 6. Cómo se cambia esta constitución

- Las reglas nuevas nacen como decisiones: se proponen en el formato de `decisions.md` (ID, contexto, decisión,
  consecuencias) y solo se añaden con aprobación explícita.
- Esta constitución cambia cuando una decisión aprobada cambia un principio, una regla no negociable o la matriz de
  permisos, en el mismo cambio o justo después.
- Una decisión nunca se borra: se marca como reemplazada por la nueva.
- Las features siguen spec → plan → tareas → implementación, y no se pasa de fase sin aprobación.

## Pendiente

Temas que la constitución todavía no fija, a la espera de su spec:

- Varios owners y transferencia de ownership: spec `003`.
- Seguridad base: spec `012`.
- Tests de concurrencia: `specs/backlog.md`.
