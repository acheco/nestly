# Spec 001 · Base del frontend

- **Estado:** aprobada (2026-11-10).
- **Fecha:** 2026-11-07.
- **Referencias:** D-014, D-020, D-070, D-071, D-072, D-073, D-078, D-084, D-085, D-086, D-088, D-090 a D-096;
  constitución §2 y §5; capturas en `design/`; PR #17 (spike de tests de navegador).

## Objetivo

Dejar la base del frontend lista para las pantallas de dominio:

- identidad visual propia en claro y oscuro;
- toda la interfaz en español, con los textos en archivos de traducción (D-085);
- formato regional de dinero y fechas;
- navegación de escritorio y móvil;
- toasts y páginas de error dentro de la app;
- accesibilidad WCAG 2.2 AA;
- una estrategia de tests de frontend y E2E, que la constitución dejaba pendiente para esta spec y ahora queda en
  D-094.

## Precondición

D-086 está aplicada antes de empezar la implementación: el CI corre en PHP 8.5 y `composer.json` exige `^8.5`. Hoy
`tests.yml` usa PHP 8.3.

## Alcance

### Incluye

- Traducción completa de lo que ya existe: pantallas, correos, toasts y mensajes de Laravel, y los `TODO(i18n)` del
  backend.
- El helper `t()` para los textos del frontend.
- Los tokens de la identidad visual, las fuentes, los radios y una página de muestra solo para desarrollo.
- La apariencia (claro, oscuro, sistema) con los tokens nuevos.
- Los helpers de formato de dinero y fechas.
- La estructura de navegación: sidebar en escritorio y barra inferior en móvil.
- Toasts en todos los layouts y páginas de error.
- La limpieza de los restos del starter kit y el estado vacío de Inicio.
- Los criterios de accesibilidad de todo lo anterior.
- La configuración de Vitest y de los tests de navegador.

### No incluye

- Pantallas de dominio: cuentas (spec 002), movimientos, presupuestos y recurrentes.
- Métricas del dashboard.
- Los 8 colores de categoría.
- El botón flotante de nueva transacción.
- De dónde sale y quién edita `users.timezone` (va a `specs/backlog.md`).
- PWA y escrituras offline (D-070, D-072).
- Pinia, zod y librerías de gráficos (D-072).
- Multiidioma y pluralización (D-085).
- Tests de componentes Vue: ni `@vue/test-utils` ni un DOM simulado.
- Una landing pública (va a `specs/backlog.md`).

## Requisitos

Cada criterio indica cómo se verifica. Los niveles de test siguen la tabla de R13.

### R1 · Interfaz en español

- `APP_LOCALE=es` en `.env.example` y en `config/app.php`; el HTML del servidor trae `<html lang="es">`. _(Pest
  feature)_
- Paso manual: el `.env` local también pasa a `APP_LOCALE=es`. No se versiona, así que el PR lo indica en sus pasos de
  prueba.
- Las pantallas existentes (autenticación, ajustes, teams, Inicio), los correos (verificación, reseteo de contraseña,
  invitaciones) y los toasts están en español. _(Manual: recorrer las pantallas; correos según R3.)_
- Los textos marcados con `TODO(i18n)` pasan a `lang/es` y se usan con `__()` o `t()`. Al terminar,
  `git grep "TODO(i18n)"` no encuentra nada en el repositorio salvo en `vendor/`, `node_modules/` y `specs/`, que
  guarda registros históricos que citan la marca. La línea de `CLAUDE.md` que describe la marca se actualiza en el
  mismo cambio. _(grep en el PR)_

### R2 · Helper de traducción del frontend

- `t(key, replacements?)` lee `lang/es.json`, la misma fuente que `__()` en el backend. No añade dependencias (D-072).
- Las claves son el texto en inglés, igual que en el backend.
- Los reemplazos funcionan como en Laravel: `:name` se sustituye tal cual, `:Name` con la primera letra en mayúscula y
  `:NAME` en mayúsculas. _(Vitest)_
- La firma es compatible con `trans()` de laravel-vue-i18n, para que una migración futura sea buscar y reemplazar.
- `t()` solo recibe literales de texto como clave. Una clave dinámica (una variable, una plantilla o una
  concatenación) no se puede verificar.
- Un test falla si alguna clave usada en `resources/js` no existe en `lang/es.json`, y también si encuentra una
  llamada a `t()` cuya clave no sea un literal. Una clave sin traducir rompe el CI; no cae al texto en inglés en
  silencio. _(Vitest)_
- Sin pluralización hasta que una spec la necesite.
- Funciona igual con SSR y en el cliente. _(Pest feature: el HTML del servidor trae el texto traducido.)_

### R3 · Textos de Laravel en español

- `lang/es/auth.php`, `pagination.php`, `passwords.php` y `validation.php`, y los textos del framework y de Fortify en
  `lang/es.json`, salen de una **copia única** de `laravel-lang/lang` y `laravel-lang/starter-kits`: se instalan, se
  ejecuta `php artisan lang:add es`, se revisan los archivos y se desinstalan en el mismo commit.
- En ese commit, `composer.json` y `composer.lock` quedan idénticos a `main`. _(diff en el PR)_
- `lang/` incluye una nota de procedencia con los paquetes, sus versiones exactas y la licencia MIT con su aviso de
  copyright. _(revisión del PR)_
- Un test de paridad compara las claves de los cuatro archivos PHP con las de
  `vendor/laravel/framework/src/Illuminate/Translation/lang/en/`, sin publicar `lang/en`, y falla si falta alguna.
  _(Pest)_
- Los textos JSON del framework (correos, Fortify) no tienen test de paridad. Se aceptan a mano: con
  `MAIL_MAILER=log` se envían los correos de verificación, de reseteo de contraseña y de invitación, y se revisa que
  estén enteros en español. _(Manual)_

Motivo de la copia única: `laravel-lang/publisher` 16.9.0 sobrescribe en cada `lang:update` las traducciones del
paquete corregidas a mano (`Processor::store()` + `Arr::merge`), y el paquete arrastra dos dependencias abandonadas
(`dragon-code/support` y `dragon-code/contracts`).

### R4 · Identidad visual

Reemplaza a `docs/Design_System_Tokens_CSS.md` (D-090). Las capturas de `design/` son referencia de identidad visual:
colores, tipografía, radios, espaciado, tarjetas, montos y el patrón de sidebar en escritorio y barra inferior en
móvil. Los ítems de navegación y los datos que aparecen en ellas son de ejemplo. Los valores que mandan son los de esta
tabla, no los del archivo de Figma.

| Token                | Oscuro           | Claro            | Uso                                                |
| -------------------- | ---------------- | ---------------- | -------------------------------------------------- |
| `bg`                 | `#0E0F12`        | `#FFFFFF`        | Fondo de la página                                 |
| `surface`            | `#16181D`        | `#F6F7F9`        | Tarjetas, sidebar, menús                           |
| `border`             | `#2A2E36`        | `#E3E5EA`        | Bordes de tarjetas y separadores                   |
| `input-border`       | `#636975`        | `#878D99`        | Borde de controles (input, select, checkbox)       |
| `text`               | `#ECEEF2`        | `#0E0F12`        | Texto principal                                    |
| `text-muted`         | `#9BA1AC`        | `#5C626E`        | Texto secundario                                   |
| `text-subtle`        | `#7D838E`        | `#6B717C`        | Texto terciario                                    |
| `primary`            | `#3D6BEE`        | `#3D6BEE`        | Botón primario, anillo de foco                     |
| `primary-hover`      | `#2F5BD8`        | `#2F5BD8`        | Botón primario en hover y active                   |
| `primary-foreground` | `#FFFFFF`        | `#FFFFFF`        | Texto sobre `primary` y `primary-hover`            |
| `primary-soft`       | `#1C2541`        | `#E8EEFF`        | Fondo del ítem activo de navegación                |
| `primary-text`       | `#8AA8FF`        | `#2F5BD8`        | Enlaces y texto en color primario                  |
| `danger`             | `#E5636B`        | `#C93B44`        | Errores; gasto (token semántico `expense`)         |
| `success`            | `#4CC38A`        | `#1A7F4E`        | Confirmaciones; ingreso (token semántico `income`) |
| `warning`            | `#D29922`        | `#9A6700`        | Avisos                                             |
| `info`               | = `primary-text` | = `primary-text` | Información                                        |

- `text-subtle` se ajustó respecto a Figma para pasar AA como texto. `input-border` y `primary-foreground` no estaban
  en Figma: se añaden por accesibilidad (R5), y Figma se actualizará para coincidir.
- Los tokens se cargan en las variables de shadcn (`--background`, `--card`, `--foreground`, `--muted-foreground`,
  `--primary`, `--primary-foreground`, `--border`, `--input`, `--ring`, `--destructive`, …) dentro del `@theme` de
  Tailwind 4, con un valor por tema. El mapeo exacto se decide en el plan.
- Hay tokens semánticos `income` y `expense` para los montos, que apuntan a `success` y `danger`.
- Ningún archivo usa un color hex, `rgb()`, `hsl()` u `oklch()` directo salvo `resources/css/app.css`, donde se
  definen los tokens. La regla incluye `resources/js/components/ui`, que hoy no tiene ninguno. _(grep en el PR)_
- Excepción explícita: las vistas Blade estáticas del 503 y del fallback del 500 (R10) pueden llevar los colores en
  línea, porque no siempre pueden cargar `app.css`. Cada una lleva un comentario que remite a la tabla de R4, y sus
  valores coinciden con ella.
- Radios: 6 px en botones e inputs, 10 px en tarjetas y 16 px en contenedores.
- Fuentes: Inter (400, 500, 600) para la interfaz y JetBrains Mono 500 con `font-variant-numeric: tabular-nums` para
  los montos. Se cargan igual que hoy Instrument Sans: con `bunny()` en `vite.config.ts`, sin paquetes npm.

### R5 · Contraste

Umbrales de WCAG 2.2 AA: 4.5:1 para texto normal (1.4.3) y 3:1 para bordes de controles y el indicador de foco
(1.4.11). La tabla se calculó con la luminancia relativa de WCAG sobre los valores de R4. En la implementación se
recalcula con los valores finales de `app.css` y se adjunta al PR.

**Texto (mínimo 4.5:1)**

| Par                                        | Oscuro (bg / surface) | Claro (bg / surface) |
| ------------------------------------------ | --------------------- | -------------------- |
| `text`                                     | 16.50 / 15.29         | 19.17 / 17.88        |
| `text-muted`                               | 7.38 / 6.84           | 6.13 / 5.72          |
| `text-subtle`                              | 5.03 / 4.66           | 4.91 / 4.58          |
| `primary-text` (= `info`)                  | 8.33 / 7.72           | 5.82 / 5.43          |
| `danger`                                   | 5.78 / 5.35           | 5.01 / 4.67          |
| `success`                                  | 8.65 / 8.02           | 5.01 / 4.68          |
| `warning`                                  | 7.59 / 7.04           | 4.87 / 4.54          |
| `primary-text` sobre `primary-soft`        | 6.56                  | 5.02                 |
| `primary-foreground` sobre `primary`       | 4.63                  | 4.63                 |
| `primary-foreground` sobre `primary-hover` | 5.82                  | 5.82                 |

El token `text` no sirve sobre `primary`: da 3.99 en oscuro y 4.14 en claro. Por eso existe `primary-foreground`.

**Controles y foco (mínimo 3:1)**

| Par                                   | Oscuro (bg / surface) | Claro (bg / surface) |
| ------------------------------------- | --------------------- | -------------------- |
| `input-border`                        | 3.48 / 3.22           | 3.33 / 3.11          |
| Anillo de foco (`primary`)            | 4.14 / 3.84           | 4.63 / 4.32          |
| `primary-hover` como fondo de control | 3.29 / 3.05           | 5.82 / 5.43          |

`border` da 1.18–1.41:1 y por eso no se usa en controles, solo en tarjetas y separadores, que no lo necesitan.

### R6 · Página de muestra

- Una página solo para desarrollo muestra botones (todas las variantes y estados), tarjetas, montos positivos y
  negativos en DOP y USD, inputs con y sin error, y los cuatro tipos de toast, en los dos temas.
- Fuera del entorno `local` la ruta no existe y responde 404. _(Pest feature)_
- Aceptación: se compara con las capturas de `design/` en los dos temas y en móvil. _(Manual)_

### R7 · Apariencia

- Por defecto se usa la del sistema (`prefers-color-scheme`), como hoy. Ajustes → Apariencia permite fijar claro u
  oscuro.
- Sin parpadeo: con la cookie `appearance=dark`, el HTML del servidor trae la clase `dark` en `<html>`; con `light`,
  no la trae. _(Pest feature)_

### R8 · Formato de dinero y fechas

**Dinero**

- Locale fijo `es-DO`. Cada monto se formatea con la moneda de su cuenta (D-084).
- Los montos llegan como strings (D-078) y se pasan a `Intl.NumberFormat` como string, nunca como número ni con
  `parseFloat`.
- `currencyDisplay` por defecto (`symbol`), nunca `narrowSymbol`: RD$ y US$ siempre se distinguen. Para EUR se acepta
  el código (`EUR 1,234.50`).
- El signo negativo es el que produce `Intl`.
- Un test formatea `'999999999999.99'` en DOP y en USD y comprueba el resultado exacto, sin pérdida de precisión.
  _(Vitest)_

**Fechas**

- Las fechas sin hora (`YYYY-MM-DD`) se tratan como fechas de calendario. Nunca se construyen con
  `new Date('YYYY-MM-DD')`, que las interpreta en UTC.
- El helper recibe "hoy" o la zona horaria como argumento. Los tests no dependen de la variable `TZ`.
- Reglas: hoy → "Hoy"; ayer → "Ayer"; de 2 a 6 días → "Hace N días" (`numeric: 'always'`); 7 días o más → fecha
  absoluta (`7 nov 2026`). Mayúscula inicial cuando la fecha va sola.
- Un test con la zona `America/Santo_Domingo` (UTC−4) comprueba que la fecha de ayer se muestra "Ayer", ni "Anteayer"
  ni con un día menos, también a las 21:00 hora local. _(Vitest)_
- De dónde sale la zona horaria del usuario queda fuera (ver `specs/backlog.md`). Hasta entonces, `users.timezone` es
  `UTC` para todos (D-020).

### R9 · Navegación

**Reglas de entradas**

- El menú solo enlaza a páginas que existen. Cada spec añade su entrada cuando su pantalla existe; no hay enlaces a
  pantallas vacías.
- Al cerrar la 001, la única sección es Inicio. Los enlaces del starter kit al repositorio y a la documentación
  desaparecen.
- La barra inferior muestra como máximo 3 secciones más "Más". Cada spec que añade una entrada declara si va en la
  barra o en "Más". Mover una entrada existente de la barra a "Más" requiere aprobación del usuario.

**Escritorio y móvil**

- **Por debajo de `md` (ancho < 768 px) se muestra la barra inferior y el sidebar no se muestra. Desde `md` (ancho ≥
  768 px) se muestra el sidebar y la barra inferior no se muestra.** _(Navegador, a 767 y 768 px)_
- En escritorio, el sidebar lleva la marca, el selector de team, las secciones y el menú de usuario.
- En móvil, la cabecera lleva la marca y el selector de team. "Más" abre un panel con el resto de entradas, Ajustes y
  cerrar sesión; no repite el selector de team.
- La barra respeta `env(safe-area-inset-bottom)` y no tapa el final del contenido de la página. _(Navegador)_
- La pestaña o el ítem activo lleva `aria-current="page"`. _(Navegador)_
- Todo se puede usar con teclado: Tab llega a cada entrada, Enter la activa y "Más" se abre y se cierra con teclado.
  _(Navegador)_

### R10 · Toasts y páginas de error

**Toasts**

- Un único `<Toaster>` funciona en todos los layouts, incluidos los de autenticación. _(Navegador: un toast se ve en
  una pantalla de autenticación.)_
- Tipos success, error, info y warning, con los tokens de R4.
- Los errores de validación y las reglas de negocio con 422 (D-014) se siguen mostrando en su campo. El toast de error
  es solo para errores sin campo.

**Páginas de error**

- 403, 404 y 500 se renderizan con la página Inertia `errors/Error.vue`, en español y con un layout mínimo sin sidebar,
  para que funcione con y sin sesión. Ofrece un enlace a Inicio o al login.
- Toda respuesta de error conserva su código HTTP. _(Pest feature, por código)_
- 404: la respuesta a un recurso que no existe y la respuesta a un recurso de otro team son idénticas: mismo
  componente, mismas props y mismo texto (D-073). _(Pest feature, con owner como actor)_
- 500: la página se renderiza con props mínimas, sin depender de `share()` ni de la base de datos. Si renderizarla
  falla, cae a una vista Blade estática. _(Pest feature: un test simula el fallo.)_
- 503: se queda en Blade, estática, traducida y con los colores de los tokens, porque en modo mantenimiento la app no
  arranca. _(Pest feature con la app en mantenimiento)_
- 419: no muestra página de error; redirige atrás con un toast de sesión expirada. _(Pest feature)_
- En `local` y `testing` se sigue viendo la traza del error, salvo en los tests que fuerzan el entorno.

### R11 · Restos del starter kit e Inicio

- `/` redirige: un invitado va al login y un usuario con sesión al Inicio de su team actual. _(Pest feature, los dos
  casos)_
- Se borran `pages/Welcome.vue`, `layouts/app/AppHeaderLayout.vue`, `components/AppHeader.vue`,
  `layouts/auth/AuthCardLayout.vue`, `layouts/auth/AuthSplitLayout.vue`, `components/PlaceholderPattern.vue` y
  `components/NavFooter.vue`.
    - Antes de borrar cada archivo, el PR muestra la búsqueda que prueba que nada lo importa.
    - Después pasan `npm run build`, `vp check` y `php artisan wayfinder:generate`.
- El estado vacío de Inicio no anuncia funciones futuras. Muestra el nombre del team, mantiene el modal de invitaciones
  pendientes y enlaza a la gestión del team.

### R12 · Accesibilidad

Todo lo que construye o toca la 001 cumple WCAG 2.2 AA. Además del contraste (R5):

- Todo elemento interactivo muestra el anillo de foco (`primary`) cuando recibe el foco con teclado, mediante
  `:focus-visible`. _(Navegador: se recorre una página con Tab y cada elemento enfocado muestra el anillo.)_
- Con `prefers-reduced-motion: reduce` activo, los toasts y los paneles (por ejemplo, "Más" y los diálogos) aparecen
  y desaparecen sin animación. _(Navegador, emulando la preferencia.)_
- El foco nunca queda tapado por la barra inferior (2.4.11). Un test recorre una página con Tab en ancho móvil y
  comprueba que cada elemento enfocado queda visible por encima de la barra. _(Navegador)_
- Las pestañas de la barra inferior y los botones táctiles miden al menos 24×24 px (2.5.8). _(Navegador)_
- `assertNoAccessibilityIssues` (axe) se ejecuta en los tests de navegador como complemento; no sustituye a ninguno de
  los tests anteriores (ver R13).

### R13 · Estrategia de tests de frontend y E2E

Cada criterio se prueba en el nivel más barato posible:

| Nivel               | Herramienta                | Qué cubre en la 001                                                                                                                                                                                        |
| ------------------- | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Funciones puras     | Vitest (`vp test`)         | `t()` y sus reemplazos, claves sin traducir, formato de dinero, fechas relativas                                                                                                                           |
| HTTP                | Pest feature               | `<html lang>`, HTML traducido con SSR, paridad de `lang/es`, tema sin parpadeo, redirección de `/`, códigos y props de error, 404 idéntico, 419, 500 con fallback, 503, página de muestra fuera de `local` |
| Layout o JavaScript | Pest + pest-plugin-browser | Cambio en `md`, `aria-current`, teclado, `:focus-visible`, `prefers-reduced-motion`, foco no tapado, 24×24 px, Toaster en autenticación                                                                    |
| Visual              | Manual                     | Comparación con `design/`, correos con `MAIL_MAILER=log`                                                                                                                                                   |

- Vitest ya viene dentro de vite-plus: no es una dependencia nueva.
- Sin tests de componentes Vue en la 001: no se añaden `@vue/test-utils` ni un DOM simulado.
- Dependencias nuevas, solo de desarrollo (D-072): `pestphp/pest-plugin-browser` `^4.3` y `playwright` (npm). Se
  justifican porque los criterios de navegador de R9, R10 y R12 no se pueden verificar sin un navegador real. La
  restricción `^4.3` se escribe editando `composer.json` y ejecutando `composer update`: en Windows con Herd,
  `composer require "x:^y"` pierde el `^` y guarda una versión exacta.
- En CI se instala solo el headless shell de Chromium (`npx playwright install --with-deps --only-shell chromium`) y la
  extensión `sockets`. Los tests de navegador usan los assets del `npm run build` de `composer setup`.
- `assertNoAccessibilityIssues` (axe) complementa los tests de teclado; no los sustituye. Su nivel por defecto es `1`.
  En la implementación se verifica qué reglas cubre cada nivel, y no se da por hecho que cubra WCAG 2.2 AA.
- Sin umbral de cobertura (D-088).

**Evidencia del spike (PR #17, cerrado sin mergear):**

- `pestphp/pest-plugin-browser` v4.3.1 con Playwright 1.65 corre en Windows nativo, sin WSL ni Docker. El test
  trivial pasó en 4 de 5 ejecuciones (15,9 s la primera, unos 2 s las siguientes). La ejecución restante se colgó más
  de 300 s sin salida. Hipótesis: un `Select-String` del mismo comando se quedó esperando entrada; el test solo nunca
  se colgó.
- En CI añade unos 30 s por run, en una sola medición (97 → 125 s): instalar Chromium +12 s, setup +3 s y checks +17 s.
  El test tarda 0,82 s, así que los +17 s de checks no están explicados.
- `assertNoAccessibilityIssues` existe en la v4.3.1
  (`vendor/pestphp/pest-plugin-browser/src/Api/Concerns/MakesConsoleAssertions.php`, línea 95) y ejecuta
  `axe.run()`.

## Decisiones

Las decisiones que propuso esta spec se aprobaron el 2026-11-10. Su texto está en `specs/foundation/decisions.md`,
sección "Frontend":

- D-090 · Identidad visual propia.
- D-091 · Traducción del frontend.
- D-092 · Textos de Laravel en español por copia única.
- D-093 · Formato regional es-DO.
- D-094 · Estrategia de tests de frontend y E2E.
- D-095 · Accesibilidad WCAG 2.2 AA.
- D-096 · Entradas de navegación.

## Preguntas abiertas

Ninguna. Las tres que surgieron durante la entrevista (la zona horaria del usuario, los tokens `warning` e `info` y el
estado hover del botón primario) quedaron resueltas. Si aparece alguna al planificar, se marcará como
[NEEDS CLARIFICATION].

## Riesgos

- **Traducciones desactualizadas:** la copia de laravel-lang no se actualiza sola. El test de paridad cubre los cuatro
  archivos PHP, pero no los textos JSON de los correos y de Fortify.
- **Tests de navegador:** Playwright puede comportarse distinto en Windows y en CI. Hubo un cuelgue sin explicar en el
  spike (ver R13), y los +17 s del paso de checks (de unos 30 s más en total) se midieron una sola vez.
- **`boost:update` modifica `CLAUDE.md`** _(resuelto en el PR #20)_: al instalar el plugin, el `post-update-cmd` de
  Composer ejecuta `boost:update`, que añadía un bloque de reglas de Herd al `CLAUDE.md` versionado. Ya no lo añade:
  `config/boost.php` excluye la guía `herd`.
- **Fechas en UTC:** mientras ninguna spec decida de dónde sale `users.timezone`, "hoy" se calcula en UTC, y en
  República Dominicana cambia de día a las 20:00.
- **Figma y la spec se desincronizan:** `input-border`, `primary-foreground` y el ajuste de `text-subtle` no están
  todavía en Figma. Mandan los valores de esta spec.
- **Borrar archivos del starter kit rompe algo:** se mitiga con la búsqueda previa de cada archivo y con el build,
  `vp check` y Wayfinder después.
- **SSR:** `t()` y la cookie de apariencia tienen que dar el mismo resultado en el servidor y en el cliente; si no,
  habrá parpadeo o errores de hidratación.

## Resumen

### Decisiones y su efecto en la constitución

| Decisión | Cambio en la constitución                              |
| -------- | ------------------------------------------------------ |
| D-090    | Ninguno                                                |
| D-091    | §2 "Idioma" remite a ella                              |
| D-092    | Ninguno                                                |
| D-093    | Ninguno                                                |
| D-094    | §5 "Frontend y E2E" la resume; se quita de "Pendiente" |
| D-095    | Se añade a §5                                          |
| D-096    | Ninguno                                                |

### Lo que se añade a `specs/backlog.md`

- **Landing pública para el portafolio:** capturas reales y acceso de demo, a evaluar cerca del despliegue.
- **Zona horaria del usuario:** decidir de dónde sale y quién edita `users.timezone`, en la primera spec que muestre
  fechas de movimientos (D-020).
- **Entorno y `CLAUDE.md`** _(resuelto en el PR #20)_: sacar las reglas de Herd del `CLAUDE.md` versionado y llevar a
  `CLAUDE.local.md` esas reglas y dos más sobre Composer y PowerShell en Windows, porque solo valen en el entorno local.
