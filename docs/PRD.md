---
title: "Nestly — Documentación Técnica Completa"
subtitle: "PRD · TRD · ERD · Design System · Operaciones"
author: "Equipo Nestly"
date: "2026"
version: "2.9"
lang: es
geometry: margin=2.5cm
fontsize: 11pt
toc: true
toc-depth: 3
numbersections: true
colorlinks: true
---

# Nestly — Documentación Técnica Completa

**Versión:** 2.9
**Fecha:** 2026
**Stack:** Laravel 13 (PHP 8.3+) · Vue 3 · TypeScript · Tailwind · shadcn-vue · PostgreSQL 16
**Nota:** la versión vigente es PostgreSQL 18; ver D-075 en `specs/foundation/decisions.md`.
**Estado:** Draft
**Nota de versión 2.9:** categorías (sesión del 23 oct). (1) Las
**categorías del sistema** (las que se siembran al crear cada team) no se
editan ni se eliminan nunca, pero **sí se pueden archivar**, para que una
familia oculte del selector las que no usa. Cada team tiene su propia
copia, así que archivar una solo afecta a ese team. (2) Una categoría
normal se **elimina solo si no tiene nada registrado** (transacciones,
presupuestos ni gastos recurrentes); si lo tiene, se archiva. Ver §1.6.5.
**Nota de versión 2.8:** ciclo de vida de cuentas y usuarios (sesión del
23 oct, validado con un usuario final). (1) **Eliminar una cuenta:** solo
si no tiene transacciones ni gastos recurrentes; si los tiene, se
archiva. Así se puede limpiar una cuenta creada por error sin poner
nunca en riesgo el historial (RF-03.7). (2) **Miembro que sale o es
removido de un team:** sus cuentas privadas en ese team se **archivan
automáticamente** y conservan a su dueño; el historial del team queda
intacto, nadie ve su saldo y, si vuelve al team, puede reactivarlas
(§1.6.3). Se descartó convertirlas en cuentas `team` porque expondría su
saldo. (3) **Borrado de cuenta de usuario (RF-01.5):** el usuario se
**anonimiza** en vez de borrar su fila (nombre "Usuario eliminado",
email aleatorio, sin credenciales), para que el historial compartido
(transferencias, autoría, auditoría) siga siendo válido (§1.6.4). Se
implementa con RF-01.5 en el cierre del MVP.
**Nota de versión 2.7:** transferencias y dashboard (sesión del 12 oct).
(1) **Quién edita o borra una transferencia:** su creador, el dueño de
una cuenta privada involucrada o un admin+, siempre con rol al menos
member. Al editar, los datos nuevos cumplen las mismas reglas que al
crear (nadie saca dinero de una cuenta privada ajena). El dueño de la
cuenta privada puede editar una transferencia que no creó; la
trazabilidad de quién cambió qué la da la auditoría (RF-02.6), no un
campo `updated_by`. Ver §1.6.3. (2) **Perímetro del team en los KPIs:**
el perímetro son las cuentas `team`. Una transferencia entre dos
cuentas `team` no es ingreso ni gasto; una que sale hacia una cuenta
privada es un **gasto** del team y una que entra desde una cuenta
privada es un **ingreso**. Así el balance total y los KPIs siempre
cuadran (RF-08.1). (3) **Categoría opcional** en las transferencias que
cruzan el perímetro, para que un reembolso consuma el presupuesto de su
categoría (RF-04.2).
**Nota de versión 2.6:** redefinición del modelo de permisos y
privacidad (sesión del 30 sep). (1) **Dentro de un team no hay
privacidad de datos**: presupuestos, categorías, recurrentes y
transacciones son del team. Quien quiera llevar finanzas propias usa su
team personal o crea otro team donde esté solo. Se elimina el scope
personal/compartido de presupuestos (RF-05.5). (2) Se mantienen las
**cuentas privadas**, pero con un propósito acotado: existen dentro de
un team familiar solo para registrar **transferencias** entre las
finanzas familiares y las de un miembro (ej. un reembolso). Su nombre es
visible para todos, pero su **saldo solo lo ve su dueño**, ni siquiera
el owner del team. (3) Nueva matriz de roles: los members solo crean y
editan sus propias transacciones (y su cuenta privada); cuentas
compartidas, presupuestos, categorías y recurrentes los gestionan owner
y admin. Ver §1.6.2 y §1.6.3.
**Nota de versión 2.5:** revisión de §1.6.1 tras comparar contra el
starter kit real de Laravel (Jetstream/Fortify + Teams). Se descarta el
campo `type` (`personal|family`) propuesto en v2.4 (derivarlo del conteo
de miembros) a favor de usar directamente **`is_personal`**, el booleano
que ya trae el starter kit: una bandera **fija al crear el team**, no
recalculada por cantidad de miembros, cuyo único propósito es
autorización (protege al team por defecto del usuario de ser eliminado o
abandonado). Si la UI necesita mostrar "Familiar", se calcula en el
momento contando miembros — no se persiste. Ver §1.6.1. También se
confirma que todas las tablas (incluida `users`) usan **UUID** como
primary key en vez de IDs autoincrementales, decisión tomada pensando en
la apertura de la API a terceros en v1.4 (ver TRD §3.1.1).
**Nota de versión 2.4:** renombramiento de la entidad **Workspace →
Team** en toda la documentación (PRD/TRD/ERD/API Spec), para alinear el
código con el modelo nativo del starter kit de Laravel (Jetstream/Fortify
con Teams) en vez de introducir un rename artificial. Columna FK
`workspace_id` → `team_id`; tabla pivote de membresía →
`team_members`; middleware `EnsureWorkspaceMember` → `EnsureTeamMember`;
segmento de ruta `/w/{workspace}` → `/t/{team}`.
**Nota de versión 2.3:** cambio de tecnología mobile — se reemplaza
Capacitor por **React Native (Expo)**, decisión tomada porque Anthony
tiene más dominio de React que de Vue, y mobile ya trae su propia curva
de aprendizaje. Se decide explícitamente **construir el app de
escritorio/web por completo primero** (MVP + v1.1) y recién después
enfocarse en mobile, en vez de intercalarlos. Ver §1.3.2 y §2.2.
(Cambios de v2.2: MVP recortado para portafolio — Pots movido a v1.1,
import CSV bajado a nice-to-have.)

---

## Índice general

- **Parte I — Producto**
  - 1. PRD — Product Requirements Document
  - 2. Personas y Roadmap
- **Parte II — Técnico**
  - 3. TRD — Technical Requirements Document
  - 4. ERD — Modelo Entidad-Relación
  - 5. API Spec — Contratos REST
- **Parte III — Diseño**
  - 6. Design System
  - 7. Tokens CSS
- **Parte IV — Operaciones**
  - 8. Runbook Operativo
  - 9. Threat Model (STRIDE)
- **Apéndices**
  - A. Glosario
  - B. Referencias

---

# Parte I — Producto

## 1. PRD — Product Requirements Document

**Versión:** 2.9
**Fecha:** 2026
**Estado:** Draft
**Stack objetivo:** Laravel 13 (PHP 8.3+) + Vue 3 + TypeScript + Tailwind + shadcn-vue + PostgreSQL

### 1.1 Resumen Ejecutivo

Nestly es una plataforma web (Laravel + Inertia + Vue, instalable como
PWA) que más adelante se extiende a mobile con una app nativa en React
Native, para gestionar finanzas personales y familiares. Los usuarios pueden crear
"equipos" (teams) personales o familiares donde varios miembros
registran y consultan cuentas, transacciones, presupuestos y gastos
recurrentes, con un dashboard analítico unificado.

Este proyecto se desarrolla como pieza de portafolio de desarrollador,
por lo que el criterio de priorización del MVP no es "mínimo viable para
lanzar rápido", sino "profundidad y coherencia de arquitectura demostrable"
en un número acotado de dominios, en vez de cobertura superficial de todos
los dominios posibles.

#### 1.1.1 Problema

- Herramientas actuales son single-user (Excel, apps personales).
- Las familias no tienen visibilidad consolidada sin exponer cuentas individuales.
- Falta separación entre "lo mío" y "lo compartido".

#### 1.1.2 Solución

Un modelo de **teams**, donde la separación entre "lo mío" y "lo
compartido" es la frontera del team:

- **Team personal**: las finanzas propias del usuario; solo él lo ve.
- **Team familiar**: todo es compartido y transparente para sus
  miembros, según su rol. Cada miembro puede tener una **cuenta
  privada** dentro del team solo para registrar transferencias con las
  finanzas familiares (ej. reembolsos), sin exponer su saldo.
- **Dashboard unificado** por team.

#### 1.1.3 Objetivos de Negocio

- Habilitar adopción personal Y familiar con el mismo producto.
- Ser la "app financiera del hogar" (hábito compartido).
- Sentar base para features premium (Open Banking, IA, forecasting, Pots).
- Servir como pieza central de portafolio: demostrar autorización compleja
  (roles + policies), jobs/queues (recurrentes), y un dashboard analítico
  completo con buena cobertura de tests.

### 1.2 Personas

#### 1.2.1 Primary: "Ana, la planificadora" (28-40)

- Profesional con ingresos estables.
- Quiere ahorrar para metas concretas (viaje, auto, emergencia).
- Empieza sola, luego invita a su pareja.

#### 1.2.2 Primary: "Familia Ramírez" (2 adultos + hijos)

- 1 cuenta conjunta en el team familiar; cada adulto lleva sus finanzas
  personales en su propio team personal.
- Quieren presupuesto familiar y transparencia total dentro del team
  familiar.
- Necesitan registrar el dinero que entra y sale entre lo familiar y lo
  de cada uno (reembolsos, aportes) sin exponer sus saldos personales.

#### 1.2.3 Secondary: "Carlos, el freelancer" (25-45)

- Ingresos variables.
- Reportes y proyecciones.

### 1.3 Alcance (Scope)

#### 1.3.1 In Scope (MVP v1.0)

- Auth: email/password + verificación + reset.
- Teams (Personal / Familiar) con roles (owner, admin, member, viewer).
- Cuentas bancarias (CRUD + transferencias).
- Transacciones (CRUD + filtros).
- Categorías y subcategorías.
- Presupuestos por categoría y periodo.
- Gastos recurrentes con auto-generación (jobs/queues).
- Dashboard con KPIs y gráficos por team.
- PWA instalable + responsive.
- Notificaciones in-app y por email.

#### 1.3.2 Out of Scope (v1.0)

- **Pots estilo Monzo** — documentado en §RF-06 y en el ERD/API Spec, pero
  movido a v1.1. Se decidió no incluirlo en el MVP: conceptualmente se
  superpone con Presupuestos (ambos son "monto objetivo + progreso") y
  restarle tiempo a Teams+Roles o Gastos Recurrentes reduce la
  profundidad demostrable de esos dos dominios, que se priorizaron para
  el portafolio.
- **Import CSV de transacciones (RF-04.5)** — baja de "Should" a
  nice-to-have opcional dentro del MVP; se implementa solo si el resto de
  los dominios core ya está completo y con buena cobertura de tests.
- **Presupuestos personales dentro de un team familiar** — eliminado en
  v2.6: un presupuesto propio se lleva en el team personal.
- **Transferencias entre teams distintos** — no existen; una
  transferencia mueve dinero entre dos cuentas del mismo team. El vínculo
  con las finanzas personales se modela con la cuenta privada (§1.6.3).
- Open Banking (planeado v1.3).
- MFA (planeado v1.1).
- App móvil nativa (React Native + Expo) — planeada para v1.2, **después**
  de completar el app de escritorio/web (MVP + v1.1). Se descartó
  Capacitor: envolver el monolito Inertia en un webview no aporta valor
  de portafolio ni buena UX; se prefiere un cliente separado consumiendo
  la API pública (Sanctum) que de todos modos hay que construir.
- Multidivisa (decisión: monodivisa por team).
- Inversiones, cripto, facturación.
- Pagos / PCI-DSS (no aplica en v1).

### 1.4 Requisitos Funcionales

#### RF-01: Auth y Usuarios

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-01.1 | Registro con email + password (verificación email) | Must |
| RF-01.2 | Login / logout / reset password | Must |
| RF-01.3 | Editar perfil (nombre, avatar, timezone, locale) | Must |
| RF-01.4 | MFA (TOTP) | Should (v1.1) |
| RF-01.5 | Borrado de cuenta y export de datos (GDPR). El borrado **anonimiza** al usuario en vez de eliminar su fila (§1.6.4) | Must |

#### RF-02: Teams y Roles

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-02.1 | Crear team (el autogenerado al registro nace `is_personal=true`; cualquier otro nace `is_personal=false` — ver §1.6.1) | Must |
| RF-02.2 | Invitar miembros por email con rol | Must |
| RF-02.3 | Roles: owner, admin, member, viewer (matriz en §1.6.2) | Must |
| RF-02.4 | Switch entre teams | Must |
| RF-02.5 | Cuenta privada por miembro, solo para transferencias, con saldo visible únicamente a su dueño (§1.6.3) | Must |
| RF-02.6 | Auditoría: quién hizo qué (incluye quién editó una transferencia; reemplaza a un campo `updated_by`) | Should |
| RF-02.7 | Al salir o ser removido de un team, las cuentas privadas del miembro en ese team se archivan automáticamente (§1.6.3) | Must |

#### RF-03: Cuentas

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-03.1 | CRUD cuenta (nombre, tipo, saldo inicial, moneda del team) | Must |
| RF-03.2 | Tipos: corriente, ahorro, efectivo, crédito, inversión | Must |
| RF-03.3 | Transferencias entre cuentas del mismo team (atómicas) | Must |
| RF-03.4 | Archivar cuenta (soft delete) | Must |
| RF-03.5 | Saldo actual + histórico | Must |
| RF-03.6 | Visibilidad: `team` (compartida) o `private` (§1.6.3) | Must |
| RF-03.7 | Eliminar cuenta solo si no tiene transacciones ni gastos recurrentes; si los tiene, la única opción es archivarla | Must |

#### RF-04: Transacciones

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-04.1 | CRUD (ingreso, gasto, transferencia) | Must |
| RF-04.2 | Categoría + subcategoría (en transferencias: opcional, solo si cruzan el perímetro del team, §1.6.3) | Must |
| RF-04.3 | Notas y adjuntos | Should |
| RF-04.4 | Filtros: fecha, cuenta, categoría, tipo, monto, miembro | Must |
| RF-04.5 | Import CSV con mapeo | Nice-to-have (opcional, no bloqueante) |
| RF-04.6 | Búsqueda full-text en descripción | Should |

#### RF-05: Presupuestos

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-05.1 | Crear por categoría y periodo (semanal/mensual/anual) | Must |
| RF-05.2 | Límite por monto | Must |
| RF-05.3 | Alertas al 80% y 100% | Must |
| RF-05.4 | Rollover opcional | Should |
| ~~RF-05.5~~ | ~~Scope: personal o compartido~~ — eliminado en v2.6: todo presupuesto es del team | — |

#### RF-06: Pots (estilo Monzo) — diferido a v1.1

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-06.1 | Crear pot con nombre, meta, fecha objetivo, icono, color | v1.1 |
| RF-06.2 | Vincular pot a cuenta origen | v1.1 |
| RF-06.3 | Aportar / retirar (genera transacción) | v1.1 |
| RF-06.4 | Progreso visual (barra + %) | v1.1 |
| RF-06.5 | Aportes recurrentes automáticos | v1.1 |
| RF-06.6 | Archivar pot | v1.1 |

*Especificación completa (tablas, endpoints) se mantiene en el ERD y API
Spec como referencia para cuando se construya en v1.1.*

#### RF-07: Gastos Recurrentes

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-07.1 | CRUD (nombre, monto, frecuencia, próxima fecha) | Must |
| RF-07.2 | Frecuencias: diaria, semanal, quincenal, mensual, anual, custom | Must |
| RF-07.3 | Auto-crear transacción al vencer | Must |
| RF-07.4 | Notificación previa (configurable N días) | Must |
| RF-07.5 | Pausar / reanudar / cancelar | Must |

#### RF-08: Dashboard

| ID | Requisito | Prioridad |
|----|-----------|-----------|
| RF-08.1 | KPIs: balance total (solo cuentas `team`), ingresos, gastos, ahorro neto, tasa de ahorro. El perímetro son las cuentas `team`: las transferencias team → team no cuentan; team → privada cuenta como gasto y privada → team como ingreso, para que la variación del balance cuadre con ingresos − gastos | Must |
| RF-08.2 | Gráfico flujo caja (líneas) | Must |
| RF-08.3 | Donut gastos por categoría | Must |
| RF-08.4 | Progreso presupuestos | Must |
| RF-08.5 | ~~Progreso pots~~ (diferido a v1.1 junto con RF-06) | v1.1 |
| RF-08.6 | Próximos recurrentes | Must |
| RF-08.7 | Filtros: periodo, cuenta, miembro, categoría | Must |
| RF-08.8 | Comparativa periodo anterior | Should |

### 1.5 Requisitos No Funcionales

| Categoría | Requisito |
|-----------|-----------|
| Rendimiento | Dashboard < 2s p95, API < 300ms p95 |
| Escalabilidad | 10k usuarios activos, 100k transacciones/mes por team |
| Seguridad | OWASP Top 10, TLS 1.3, AES-256 en reposo, bcrypt/argon2 |
| Disponibilidad | 99.9% uptime |
| Accesibilidad | WCAG 2.1 AA |
| Privacidad | GDPR + LFPDPPP, export/delete self-service |
| Compatibilidad | Chrome, Safari, Firefox, Edge (últimas 2 versiones); iOS 15+, Android 10+ |
| i18n | ES/EN, formatos de moneda y fecha por locale |
| Offline (PWA) | Cache de lectura, cola de mutaciones |

### 1.6 Modelo de Team y Permisos

#### 1.6.1 Tipos de team

> **Actualización (v2.5):** ver nota de versión al inicio del documento.
> No existe un campo `type` (`personal|family`) — se usa directamente
> `is_personal`, el booleano del starter kit. Es una bandera **fija al
> crear el team**, no un estado que se recalcula por cantidad de
> miembros.

- **`is_personal = true`**: el team que se crea automáticamente al
  registrarse, con el usuario como owner (comportamiento nativo del
  starter kit). Solo existe un team con esta bandera por usuario — el
  autogenerado al registro.
- **`is_personal = false`**: cualquier team creado explícitamente
  después (ej. "Familia Ramírez"). Nace con 1 miembro (el creador como
  owner) y crece al invitar gente, igual que el personal.
- **`is_personal` no cambia nunca después de creado el team**, sin
  importar cuántos miembros tenga. Su único propósito es autorización:
  el team personal del usuario no puede eliminarse ni abandonarse (ver
  `TeamPolicy::leave()`/`delete()`), justamente porque es el espacio por
  defecto que no debería quedar huérfano. Cualquier otro team sí puede
  eliminarse o abandonarse según el rol de quien lo intente.
- Un usuario puede tener múltiples teams simultáneamente: su team
  personal (`is_personal = true`) más cualquier cantidad de teams no
  personales que cree o a los que sea invitado (ej. el team familiar de
  su pareja).
- **No existe una etiqueta "Personal"/"Familiar" persistida ni validada
  por el backend.** Si el frontend quiere mostrarla como texto
  descriptivo, la infiere en el momento contando miembros del team
  (`> 1` → "Familiar") — es puramente cosmético, nunca una condición de
  autorización. La autorización real corre por rol (§1.6.2).
- **La privacidad es la frontera del team (v2.6).** Dentro de un team
  todo es visible para sus miembros, salvo el saldo de las cuentas
  privadas (§1.6.3). Para llevar finanzas propias con presupuestos,
  categorías y recurrentes propios, el usuario usa su team personal o
  crea otro team donde esté solo.

#### 1.6.2 Roles

| Rol | Ver | Transacciones | Cuentas compartidas (`team`) | Cuenta privada propia | Presupuestos, categorías, recurrentes | Invitar | Eliminar team |
|-----|-----|---------------|------------------------------|-----------------------|----------------------------------------|---------|---------------|
| owner | Todo, excepto saldos de cuentas privadas ajenas | Crear y editar todas | Crear, editar, archivar, eliminar (sin movimientos) | Crear y gestionar | Crear, editar, eliminar | Sí | Sí |
| admin | Todo, excepto saldos de cuentas privadas ajenas | Crear y editar todas | Crear, editar, archivar, eliminar (sin movimientos) | Crear y gestionar | Crear, editar, eliminar | Sí | No |
| member | Todo, excepto saldos de cuentas privadas ajenas | Crear; editar solo las propias (y las transferencias que tocan su cuenta privada, §1.6.3) | Solo ver | Crear y gestionar | Solo ver | No | No |
| viewer | Todo, excepto saldos de cuentas privadas ajenas | Solo ver | Solo ver | No | Solo ver | No | No |

"Ver" incluye el **nombre** de todas las cuentas del team, también las
privadas ajenas; lo único oculto es su saldo. Ningún rol, ni siquiera
owner o admin, puede editar, archivar ni eliminar la cuenta privada de
otro miembro.

#### 1.6.3 Cuentas privadas

- `team`: cuenta compartida; nombre y saldo visibles a todos los
  miembros.
- `private`: cuenta de **un miembro** dentro de un team familiar.
  - **Propósito único:** registrar transferencias entre las finanzas
    familiares y las del miembro. Solo admite **transferencias**; no
    admite ingresos ni gastos sueltos.
  - **Nombre visible a todos** (para que la transferencia se lea
    "Cuenta Conjunta → Ahorro Luis"). **Saldo visible solo a su
    dueño**, incluido frente al owner del team.
  - La crea cualquier rol excepto viewer, y su dueño es quien la crea.
    Solo su dueño la edita, archiva o elimina (eliminar, igual que
    cualquier cuenta, solo sin movimientos: RF-03.7).
  - **Sacar** dinero de una cuenta privada solo puede hacerlo su dueño;
    **meter** dinero (ej. un reembolso) puede hacerlo cualquier rol con
    permiso de crear transacciones.
  - **Editar o borrar una transferencia (v2.7):** pueden hacerlo su
    creador, el dueño de una cuenta privada involucrada o un admin+,
    todos con rol al menos member. Al editar, los datos nuevos cumplen
    las reglas de creación: nadie puede cambiar el origen a una cuenta
    privada ajena ni usar una cuenta archivada. Así quien registró un
    reembolso por error puede corregirlo, y el dueño de la cuenta
    privada conserva el control sobre lo que entra en ella.
  - **Efecto en el dashboard (v2.7):** las cuentas `team` son el
    perímetro de las finanzas familiares. Un reembolso hacia una cuenta
    privada es un **gasto** del team y un aporte desde una cuenta privada
    es un **ingreso**; una transferencia entre dos cuentas `team` no
    cuenta en los KPIs. Estas transferencias pueden llevar una
    **categoría opcional** (ej. "Transporte" en un reembolso de
    gasolina), que se aplica a la pata de la cuenta `team` y consume el
    presupuesto de esa categoría.
  - **Si el dueño sale o es removido del team (v2.8):** sus cuentas
    privadas en ese team se **archivan automáticamente** y conservan a
    su dueño. Sus transferencias son parte del historial del team, así
    que no se borran. Nadie ve su saldo (el dueño ya no es miembro) y,
    si vuelve al team, puede reactivarlas. No se convierten en cuentas
    `team`, porque eso expondría su saldo.
  - Ejemplo de uso (reembolso): Luis paga gasolina con su tarjeta
    personal y registra el gasto en su team personal. En el team
    familiar se le transfiere el reembolso desde la Cuenta Conjunta a su
    cuenta privada, con ese comentario y la categoría "Transporte".

#### 1.6.4 Borrado de usuarios (v2.8)

Borrar una cuenta de usuario (RF-01.5) **anonimiza** al usuario en vez de
eliminar su fila: el nombre pasa a "Usuario eliminado", el email se
reemplaza por uno aleatorio y se eliminan sus credenciales (password,
passkeys, sesiones). Su team personal se elimina con todo su contenido
y, en los teams compartidos, sale como miembro (sus cuentas privadas se
archivan, §1.6.3).

Motivo: su historial en los teams compartidos sigue perteneciendo a esos
teams (transferencias hacia y desde sus cuentas privadas, transacciones
que registró, entradas de auditoría). Borrar la fila rompería ese
historial: las cuentas privadas no pueden quedarse sin dueño, y las
patas del lado team de sus transferencias perderían su contraparte. Se
implementa junto con RF-01.5 en el cierre del MVP.

#### 1.6.5 Categorías (v2.9)

- Al crear un team se siembran sus **categorías del sistema**
  (`is_system = true`). Son filas propias de ese team: archivar una
  solo la oculta en ese team, nunca en otros.
- **Del sistema:** no se editan ni se eliminan; solo se archivan (y se
  pueden reactivar).
- **Normales:** owner y admin las crean, editan y archivan. Se eliminan
  solo si no tienen transacciones, presupuestos ni gastos recurrentes; si
  tienen, la única opción es archivarlas.
- Una categoría archivada no aparece en el selector, pero sigue
  mostrándose en los registros históricos que la usan.

### 1.7 Métricas de Éxito

- Activación: 70% crean ≥1 cuenta + ≥1 presupuesto en semana 1.
- Adopción familiar: 30% de teams con 2+ miembros invitan ≥1 miembro adicional.
- Retención D7 ≥ 40%, D30 ≥ 20%.
- Engagement: ≥ 3 sesiones/semana.
- NPS ≥ 40.

*(Nota: al ser un proyecto de portafolio sin usuarios reales en el corto
plazo, estas métricas se conservan como especificación de producto —
demuestran capacidad de pensar en éxito medible — pero no se instrumenta
analítica real hasta que el proyecto tenga tráfico.)*

### 1.8 Riesgos

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Complejidad de permisos | Alto | Matriz clara (§1.6.2) + tests de matriz por rol + E2E por rol |
| Filtración del saldo de una cuenta privada (dashboard, listados, props de Inertia) | Alto | Saldo omitido en el serializado para quien no es dueño; balance total del dashboard solo con cuentas `team`; tests específicos |
| Sync móvil/offline | Alto | PWA con cola de mutaciones + conflictos last-write-wins |
| Datos financieros sensibles | Crítico | Cifrado, auditoría, pentesting |
| Adopción familiar | Medio | Onboarding guiado para invitar miembros |
| Alcance disperso entre demasiados dominios (riesgo de portafolio) | Alto | MVP recortado a 6 dominios (§1.3), Pots diferido a v1.1 |

### 1.9 Compliance

- **GDPR** (UE): consentimiento, derecho al olvido (vía anonimización,
  §1.6.4), export.
- **LFPDPPP** (México): aviso de privacidad.
- **PCI-DSS**: no aplica (no procesamos pagos en v1).
- **Retención**: transacciones 7 años (fiscal), logs 1 año.
- **Cifrado**: TLS 1.3 + AES-256 en reposo.
- **Auditoría**: tabla `audit_logs` para acciones sensibles.

---

## 2. Personas y Roadmap

### 2.1 Personas detalladas

#### Ana — La planificadora

- **Edad:** 32
- **Ocupación:** Diseñadora UX, ingreso fijo
- **Objetivos:** Ahorrar para viaje a Japón, controlar gastos hormiga
- **Pain points:** No sabe a dónde se va el dinero, presupuestos manuales en Excel
- **Device:** iPhone, usa la app 3-4 veces/semana
- **Quote:** "Quiero saber en 5 segundos cómo voy este mes."

#### Familia Ramírez

- **Miembros:** Luis (38, padre), Mariana (36, madre), 2 hijos
- **Objetivos:** Presupuesto familiar, ahorro para colegiaturas, control de gastos compartidos
- **Pain points:** Cada quien tiene su cuenta, no hay visibilidad consolidada
- **Device:** Android (Luis), iPhone (Mariana)
- **Quote:** "Queremos saber cuánto llevamos gastado en el mes sin peleas."

#### Carlos — El freelancer

- **Edad:** 29
- **Ocupación:** Desarrollador freelance
- **Objetivos:** Proyectar flujo de caja, separar impuestos
- **Pain points:** Ingresos irregulares, difícil planear
- **Device:** Desktop + Android
- **Quote:** "Necesito ver venir los meses flacos."

### 2.2 Roadmap

| Fase | Alcance | Entregables clave |
|------|---------|-------------------|
| MVP v1.0 (Desktop/Web) | RF-01, RF-02, RF-03, RF-04 (sin import CSV), RF-05, RF-07, RF-08 (sin progreso de pots) | App funcional con teams+roles, cuentas, transacciones, presupuestos, recurrentes, dashboard — con tests y policies sólidas |
| v1.1 (Desktop/Web) | RF-06 Pots + MFA | Cierra el alcance funcional del app de escritorio/web antes de tocar mobile |
| v1.2 (Mobile) | API pública (Sanctum, JSON agnóstico de cliente) + cliente mobile React Native (Expo) + push | Primera versión de la app móvil, sobre la misma API que luego se abre a terceros |
| v1.3 | Import CSV avanzado + auto-categorización + búsqueda semántica | Reglas, vector search con pgvector |
| v1.4 | Open Banking (Plaid/Belvo) + apertura formal de la API a terceros | Sync automático, documentación pública de la API construida en v1.2 |
| v2.0 | IA (forecast, insights) con Laravel AI SDK | Predicciones, recomendaciones |

### 2.3 Métricas por fase

| Fase | Métrica principal | Objetivo |
|------|-------------------|----------|
| MVP | Activación (cuenta + presupuesto en sem 1) | 70% |
| v1.1 | Uso de Pots entre usuarios activos | 30% |
| v1.2 | Adopción móvil (instalaciones de la app React Native) | 40% de usuarios web |
| v1.3 | Uso de auto-categorización | 60% de transacciones |
| v1.4 | Cuentas conectadas | 30% de usuarios activos |
| v2.0 | Retención D30 con IA | 35% |

---

**Fin de la Parte 1 de 5.**
