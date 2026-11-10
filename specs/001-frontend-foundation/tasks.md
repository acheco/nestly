# Tasks 001 · Base del frontend

- **Estado:** aprobado (2026-11-10).
- **Fecha:** 2026-11-10.
- **Spec:** `spec.md` (aprobada). **Plan:** `plan.md` (aprobado). Decisiones D-090 a D-096.

## Reglas

- Una fase es una rama y un PR a `main`, en el orden del plan. Cada rama sale de `main` actualizado, después del merge
  de la fase anterior.
- Una tarea es un commit, que cita la spec: `feat(001): ...`, `test(001): ...` o `chore(001): ...`.
- Tests primero: cada tarea empieza por su test en rojo, y el rojo tiene que ser por el motivo que el test comprueba.
  Las excepciones se indican en la tarea, con su motivo.
- `composer ci:check` pasa antes de cada commit. Ningún commit deja el CI en rojo.
- Mutation check (romper a propósito el código protegido, confirmar que el test falla, revertir y mostrar el diff
  vacío):
    - en las tareas que protegen seguridad: 5.1 y 7.2;
    - en todo test que pasa desde el principio porque protege un comportamiento que ya existe: 3.4.
- Cada PR lista sus pasos manuales y adjunta la evidencia que pide el plan (greps, tablas, duración del CI).
- Las dependencias nuevas se escriben en `composer.json` y se aplican con `composer update <paquete>`. Después de cada
  `composer update` se comprueba que `CLAUDE.md` no cambió.

## Fase 1 · Herramientas de test (R13)

Rama: `chore/001-test-tooling`.

1. [ ] **Vitest.** Bloque `test` en `vite.config.ts` (`include: ['tests/js/**/*.test.ts']`, `environment: 'node'`,
       alias `@`). Script `"test": "vp test"` en `package.json`, que `composer ci:check` ejecuta después de
       `npm run types:check`. Un test trivial en `tests/js/smoke.test.ts`.
2. [ ] **Tests de navegador y CI.** En un solo commit, para que el CI no corra un test de navegador sin Chromium:
    - `pestphp/pest-plugin-browser` `^4.3` en `require-dev` y `playwright` en `devDependencies`;
    - suite `Browser` en `phpunit.xml` para `tests/Browser`, con un test trivial: visitar el login y ver el
      formulario;
    - en CI, la extensión `sockets` en `setup-php` y el paso
      `npx playwright install --with-deps --only-shell chromium` antes de `composer ci:check`.

**PR:**

- Duración del CI antes y después.
- Qué reglas de axe cubre `assertNoAccessibilityIssues` en cada nivel. No se da por hecho que cubra WCAG 2.2 AA.
- `CLAUDE.md` sin cambios después de `composer update`.

## Fase 2 · Idioma y traducciones (R1, R2, R3)

Rama: `feat/001-language`.

1. [ ] **Locale.** Test (Pest feature): una página trae `<html lang="es">`. Después, `APP_LOCALE=es` y
       `APP_FALLBACK_LOCALE=es` en `.env.example` y `'es'` por defecto en `config/app.php`.
2. [ ] **Textos de Laravel (copia única).** Primero el test de paridad (Pest): las claves de
       `lang/es/{auth,pagination,passwords,validation}.php` contra las de
       `vendor/laravel/framework/src/Illuminate/Translation/lang/en/`. Después, en el mismo commit: instalar
       `laravel-lang/lang` y `laravel-lang/starter-kits`, ejecutar `php artisan lang:add es`, revisar los archivos,
       desinstalar los paquetes y añadir `lang/README.md` con la procedencia (paquetes, versiones exactas y licencia
       MIT con su aviso de copyright).
3. [ ] **Helper `t()`.** Tests (Vitest) de los reemplazos `:name`, `:Name` y `:NAME`, de que las claves más largas se
       sustituyen primero y de que una clave sin traducción devuelve la clave. Después, `resources/js/lib/i18n.ts`, que
       importa `lang/es.json`.
4. [ ] **Test de claves.** Test (Vitest) que recorre `resources/js` (salvo `components/ui`, `actions`, `routes` y
       `wayfinder`), y falla si una clave de `t()` no está en `lang/es.json` o si no es un literal de texto. Se
       comprueba que falla con una clave inventada y con una clave en una variable, y se quitan esos casos antes del
       commit.
5. [ ] **Backend.** Las 34 marcas `TODO(i18n)` pasan a `__()`, con sus textos en `lang/es.json`. También la
       notificación `TeamInvitation` y el nombre del team personal (`CreateNewUser`). Los tests existentes que comparan
       esos textos siguen pasando.
6. [ ] **Pantallas de autenticación.** Todos los textos de `pages/auth` y `layouts/auth` pasan a `t()`.
7. [ ] **Resto de pantallas.** Ajustes, teams, Inicio, `TeamSwitcher`, `NavUser`, `UserMenuContent` y los modales
       pasan a `t()`.
8. [ ] **`CLAUDE.md`.** La línea de Conventions que describe `TODO(i18n)` pasa a describir `lang/es.json`, `__()` y
       `t()`.

**PR:**

- `git diff main -- composer.json composer.lock` vacío.
- `git grep "TODO(i18n)" -- . ':!specs'` vacío.
- Paso manual: `APP_LOCALE=es` en el `.env` local, sin el cual el test de la tarea 1 falla en local.
- Paso manual: con `MAIL_MAILER=log`, los correos de verificación, de reseteo de contraseña y de invitación están
  enteros en español.
- Paso manual (enmienda de R2): con `composer dev`, el HTML del servidor de una página trae el texto en español.

## Fase 3 · Identidad visual, contraste y apariencia (R4, R5, R7)

Rama: `feat/001-visual-identity`.

1. [ ] **Tokens y contraste.**
    - El test de contraste (Vitest) lee los tokens de R4 de `app.css` en los dos temas y comprueba cada par de R5 con
      su umbral, más `bg` sobre `danger`. Solo acepta valores `#RRGGBB`: si un token falta o tiene otro formato, falla
      con un mensaje que nombra el token, separado de las aserciones de contraste.
    - Para que el primer rojo sea de contraste, los tokens de R4 se añaden a `app.css` con los valores de la tabla,
      salvo `input-border`, que toma temporalmente el valor de `border`. El test debe fallar solo en los pares de
      `input-border`, con un mensaje que nombra el par, el tema, la razón obtenida (alrededor de 1.2–1.4:1, según R5)
      y el umbral de 3:1. Después, `input-border` toma su valor de R4 y el test pasa.
    - En el mismo commit: el mapeo a las variables de shadcn del plan, los tokens nuevos de Tailwind (`income` y
      `expense` incluidos), el borrado de los `--chart-*` y el estilo de compatibilidad de bordes con
      `var(--border)`.
2. [ ] **Radios y fuentes.** Revisar qué clase de radio usa cada componente de `components/ui` y fijar los radios del
       plan. Inter y JetBrains Mono con `bunny()` en lugar de Instrument Sans, y la utilidad `font-amount`. Sin test
       automático: revisión visual en el PR.
3. [ ] **Colores fuera de `app.css`.** `app.ts` pasa `var(--primary)` a la barra de progreso. En `app.blade.php` se
       quita solo el bloque `<style>` en línea; el `<script>` que pone la clase `dark` se mantiene.
4. [ ] **Apariencia.** Tests (Pest feature): con la cookie `appearance=dark`, `<html>` trae la clase `dark`; con
       `light`, no la trae. Excepción a "tests primero": pasan desde el principio, porque la lógica ya existe, y
       protegen R7 de cambios futuros. Mutation check: con `@class` de `app.blade.php` sin la condición de la cookie,
       los tests fallan.
5. [ ] **Nota en `docs/Design_System_Tokens_CSS.md`.** Una nota al principio que remite a D-090 en
       `specs/foundation/decisions.md` (D-071), con el formato de las notas que ya tienen los otros docs. El documento
       no se reescribe.

**PR:**

- La tabla de contraste que escribe el test de la tarea 1.
- El grep de colores directos fuera de `app.css`, que solo encuentra `pages/Welcome.vue` (se borra en la fase 6).
- Paso manual: sin el `<style>` en línea, el fondo sale bien en los dos temas, con y sin caché.
- Paso manual: `settings/Appearance.vue` se ve bien con los tokens nuevos en los dos temas.

## Fase 4 · Formato de dinero y fechas (R8)

Rama: `feat/001-formatting`.

1. [ ] **Dinero.** Tests (Vitest): `'999999999999.99'` en DOP y en USD con el resultado exacto, montos negativos y EUR
       con su código. Después, `formatMoney` en `resources/js/lib/money.ts`.
2. [ ] **Fechas.** Tests (Vitest): "Hoy", "Ayer", "Hace N días" de 2 a 6 días, la fecha absoluta desde 7, la
       mayúscula inicial y, con `America/Santo_Domingo`, la fecha de ayer a las 21:00 hora local como "Ayer". Después,
       `todayIn` y `formatRelativeDate` en `resources/js/lib/dates.ts`.
3. [ ] **`accounts/Index.vue`.** Deja su `Intl.NumberFormat('en-US')` y usa `formatMoney` (D-093).

**PR:** la salida de los tests de la tarea 1 en el CI (Node 22), que confirma que `Intl` acepta strings decimales.

## Fase 5 · Página de muestra (R6)

Rama: `feat/001-showcase`.

1. [ ] **Ruta solo en `local`.** Test (Pest feature): en `testing`, `GET /dev/showcase` responde 404. Después, la ruta
       registrada solo en `local` y `pages/dev/Showcase.vue` vacía. Mutation check: con la ruta registrada sin la
       condición de entorno, el test falla.
2. [ ] **Contenido.** Botones (todas las variantes y estados), tarjetas, montos positivos y negativos en DOP y USD con
       `formatMoney`, inputs con y sin error, los cuatro tipos de toast y un selector de tema.

**PR:** paso manual: comparación con las capturas de `design/` en los dos temas y en móvil.

## Fase 6 · Navegación, restos del starter kit e Inicio (R9, R11)

Rama: `feat/001-navigation`.

1. [ ] **Redirección de `/`.** Tests (Pest feature): un invitado va a `login`; un usuario con sesión va a `dashboard`
       de su team actual; un usuario sin team actual va a `dashboard` de su team personal. Después, la closure que
       sustituye a `Route::inertia('/', 'Welcome')`.
2. [ ] **Lista de entradas y sidebar.** Primero el test (navegador): a 768 px, el sidebar muestra Inicio y no tiene
       enlaces al repositorio ni a la documentación del starter kit. Falla hoy por esos enlaces. Después,
       `resources/js/lib/navigation.ts` con Inicio en la barra (D-096), y `AppSidebar` la usa y deja de usar
       `NavFooter`.
3. [ ] **Borrar los restos del starter kit.** Los siete archivos de R11 y el caso `Welcome` de `app.ts`. Antes de
       borrar cada archivo, el `git grep` que prueba que nada lo importa.
4. [ ] **Móvil.** Primero los tests de navegador: a 767 px se ve la barra y no el sidebar, y a 768 px al revés;
       `aria-current="page"`, Tab y Enter en cada entrada, "Más" se abre y se cierra con teclado, y el final del
       contenido queda visible. Después, la cabecera móvil, `components/BottomNav.vue` y el `Sheet` de "Más" (resto de
       entradas, Ajustes y cerrar sesión).
5. [ ] **Inicio.** `Dashboard.vue` deja `PlaceholderPattern`, muestra el nombre del team, mantiene
       `PendingInvitationsModal` y enlaza a `teams.edit`. Sin test automático: revisión visual en el PR.

**PR:**

- Los greps de la tarea 3.
- `npm run build`, `vp check` y `php artisan wayfinder:generate` sin errores después de borrar.

## Fase 7 · Toasts, páginas de error y accesibilidad (R10, R12)

Rama: `feat/001-errors-a11y`.

1. [ ] **Toaster único.** Test (navegador): un toast se ve en una pantalla de autenticación. Después, el `<Toaster>`
       sale de `AppSidebarLayout` y pasa al componente raíz, con los tokens de R4 en los cuatro tipos.
2. [ ] **Páginas de error.** Tests (Pest feature): 403, 404 y 500 conservan su código y renderizan `errors/Error`; el
       404 de un recurso inexistente y el de un recurso de otro team son idénticos (componente, props y texto), con
       owner como actor (D-073). Después, `handleExceptionsUsing()` en `AppServiceProvider` y `pages/errors/Error.vue`
       con su layout mínimo. Mutation check: si el 404 de otro team recibe una prop distinta, el test falla.
3. [ ] **Fallback del 500.** Test (Pest feature): si renderizar el 500 falla, la respuesta es
       `errors/500-static.blade.php` con código 500. Después, la vista estática con los colores de R4 en línea y el
       comentario que remite a la tabla.
4. [ ] **503.** Test (Pest feature): con la app en mantenimiento, la respuesta es la vista estática en español con
       código 503. Después, `resources/views/errors/503.blade.php`.
5. [ ] **419.** Test (Pest feature): un 419 redirige atrás con el flash del toast de sesión expirada. Después, el caso
       en el callback.
6. [ ] **Foco visible y movimiento reducido.** Tests (navegador): con Tab, cada elemento enfocado muestra el anillo;
       con `prefers-reduced-motion: reduce`, toasts, "Más" y diálogos sin animación. Después, `:focus-visible` en la
       capa base de `app.css` y las variantes `motion-reduce:`.
7. [ ] **Foco no tapado y tamaño de objetivos.** Tests (navegador): en ancho móvil, cada elemento enfocado queda por
       encima de la barra inferior (2.4.11); pestañas y botones táctiles de al menos 24×24 px (2.5.8);
       `assertNoAccessibilityIssues` en las páginas de la 001. Después, los ajustes que hagan falta.

**PR:**

- El grep de colores directos de todo el repositorio, que solo encuentra `app.css` y las dos vistas Blade estáticas
  (R4).
- Paso manual: la traza de error se sigue viendo en `local`.
