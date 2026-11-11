# Plan 001 · Base del frontend

- **Estado:** aprobado (2026-11-10).
- **Fecha:** 2026-11-10.
- **Spec:** `specs/001-frontend-foundation/spec.md` (aprobada el 2026-11-10). Decisiones D-090 a D-096.

Este plan dice cómo se implementa la spec: en qué orden, en qué archivos y con qué tests. No añade requisitos. Donde la
spec deja una elección técnica, el plan la toma y la justifica. Las cuatro preguntas abiertas del borrador se
resolvieron el 2026-11-10, y cada respuesta está en su fase.

## Estado de partida

Verificado en `main` (`e89f701`):

- **Idioma:** `APP_LOCALE=en` en `.env.example` y en `config/app.php`. No existe `lang/`. Hay 34 marcas
  `TODO(i18n)` en 12 archivos de `app/` y `resources/js` (la mayoría en las actions de transacciones), más la línea
  que las describe en `CLAUDE.md`.
- **Estilos:** `resources/css/app.css` tiene los tokens neutros del starter kit en `hsl()`, más los `--chart-*`.
  `app.blade.php` pinta el fondo de `<html>` con `oklch()` en línea. `app.ts` pasa `#4B5563` como color de la barra de
  progreso. `pages/Welcome.vue` está lleno de colores hex.
- **Fuentes:** Instrument Sans con `bunny()` en `vite.config.ts`.
- **Apariencia:** `HandleAppearance` comparte la cookie con Blade y `app.blade.php` ya pone `class="dark"` cuando vale
  `dark`. `useAppearance` y `settings/Appearance.vue` ya existen.
- **Toasts:** `vue-sonner` con `components/ui/sonner`. El `<Toaster>` solo está en `AppSidebarLayout`, así que en las
  pantallas de autenticación no aparece ninguno. `lib/flashToast.ts` ya muestra los toasts del flash.
- **Navegación:** `AppSidebar` tiene "Dashboard" y los enlaces del starter kit al repositorio y a la documentación
  (`NavFooter`). No hay barra inferior.
- **Rutas:** `/` renderiza `Welcome`. `accounts.index` existe y renderiza `accounts/Index.vue`, que es un esqueleto con
  el template vacío y un `Intl.NumberFormat('en-US')`.
- **Errores:** no hay manejo propio; Laravel renderiza sus páginas por defecto.
- **SSR:** `config/inertia.php` lo tiene activo. El plugin `@inertiajs/vite` usa `resources/js/app.ts` como entrada de
  SSR, porque no hay `ssr.ts`. Solo hay SSR con Vite en caliente (`composer dev`): en los tests no hay bundle ni
  servidor de SSR, y `HttpGateway` vuelve al render en el cliente sin avisar.
- **Tests:** Pest con las suites `Unit` y `Feature`. No hay tests de frontend. Vitest viene dentro de vite-plus
  (`vp test`), sin configurar. `users.timezone` existe y vale `UTC` por defecto.
- **CI:** un job con PostgreSQL 18, PHP 8.5 y Node 22, que ejecuta `composer setup` y `composer ci:check`.

## Fases

Siete fases, en este orden. Cada fase es una rama y un PR a `main`, con un commit por tarea (las tareas se escriben en
`tasks.md`). Cada PR pasa `composer ci:check`. Las fases dependen de las anteriores salvo donde se indica.

| Fase | Contenido                                   | Requisitos | Depende de |
| ---- | ------------------------------------------- | ---------- | ---------- |
| 1    | Herramientas de test                        | R13        | —          |
| 2    | Idioma y traducciones                       | R1, R2, R3 | 1          |
| 3    | Identidad visual, contraste y apariencia    | R4, R5, R7 | 1          |
| 4    | Formato de dinero y fechas                  | R8         | 1, 2       |
| 5    | Página de muestra                           | R6         | 3, 4       |
| 6    | Navegación, restos del starter kit e Inicio | R9, R11    | 2, 3       |
| 7    | Toasts, páginas de error y accesibilidad    | R10, R12   | 2, 3, 6    |

Las fases 2 y 3 son independientes entre sí.

### Fase 1 · Herramientas de test (R13)

Primero, porque las demás fases escriben sus tests con estas herramientas.

**Vitest**

- Los tests de funciones puras van en `tests/js/**/*.test.ts`, fuera de `resources/js`, para no mezclarlos con el
  código que Vite empaqueta.
- Se configuran en el bloque `test` de `vite.config.ts` (`include`, `environment: 'node'`), con el alias `@` que ya
  usa la app. Sin DOM simulado.
- Script nuevo `"test": "vp test"` en `package.json` (`vp test` ejecuta una vez por defecto). `composer ci:check` lo
  ejecuta después de `npm run types:check`.
- Un test trivial confirma que la configuración funciona en local y en CI.

**Tests de navegador**

- `pestphp/pest-plugin-browser` `^4.3` en `require-dev`, escrito en `composer.json` y aplicado con
  `composer update pestphp/pest-plugin-browser` (no con `composer require`, por el `^` en Windows). `playwright` en
  `devDependencies` de npm. El spike del PR #17 resolvió `^4.3` a la v4.3.1, con Playwright 1.65.0. La versión que se
  usa es la 1.61.1 exacta (D-097).
- Los tests van en `tests/Browser`, en una suite `Browser` nueva de `phpunit.xml`. `php artisan test` la ejecuta con
  las demás.
- CI: la extensión `sockets` en `setup-php` y un paso
  `npx playwright install --with-deps --only-shell chromium` antes de `composer ci:check`. Usa los assets del
  `npm run build` de `composer setup`.
- Un test trivial (visitar el login y ver el formulario) confirma que corre en Windows y en CI. El PR anota la
  duración del CI antes y después.
- `composer update` ejecuta `boost:update`: se comprueba que `CLAUDE.md` no cambia (riesgo resuelto en el PR #20).
- Se verifica qué reglas de axe cubre `assertNoAccessibilityIssues` en cada nivel y se anota en el PR (R13). No se da
  por hecho que cubra WCAG 2.2 AA.

### Fase 2 · Idioma y traducciones (R1, R2, R3)

**Configuración (R1)**

- `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es` en `.env.example`, y `'es'` como valor por defecto en `config/app.php`.
  `APP_FAKER_LOCALE` no cambia: solo afecta a los datos de prueba.
- Test (Pest feature): una página trae `<html lang="es">`.
- Paso manual en los pasos de prueba del PR: cambiar `APP_LOCALE=es` en el `.env` local. Sin ese cambio, el test de
  `<html lang>` falla en local.

**Textos de Laravel (R3)**, en un commit propio:

1. Instalar `laravel-lang/lang` y `laravel-lang/starter-kits`, ejecutar `php artisan lang:add es` y revisar los
   archivos generados.
2. Conservar `lang/es/{auth,pagination,passwords,validation}.php` y `lang/es.json`.
3. Desinstalar los paquetes. `composer.json` y `composer.lock` quedan idénticos a `main`, y el PR lo muestra con
   `git diff main -- composer.json composer.lock` vacío.
4. Añadir `lang/README.md` con la procedencia: paquetes, versiones exactas y licencia MIT con su aviso de copyright.

- Test de paridad (Pest): compara las claves de los cuatro archivos PHP con las de
  `vendor/laravel/framework/src/Illuminate/Translation/lang/en/`, sin publicar `lang/en`.
- Correos: verificación manual con `MAIL_MAILER=log` (verificación de email, reseteo de contraseña e invitación).

**Helper `t()` (R2)**

- `resources/js/lib/i18n.ts` exporta `t(key, replacements?)`. Importa `lang/es.json` con un `import` de Vite, así que
  el diccionario va dentro del bundle y el resultado es el mismo en el servidor y en el cliente, sin props de Inertia.
- Los reemplazos siguen a Laravel: `:name`, `:Name` y `:NAME`. Las claves más largas se sustituyen primero, como hace
  Laravel, para que `:name` no pise `:names`.
- Firma compatible con `trans()` de laravel-vue-i18n.
- Tests (Vitest): reemplazos en sus tres formas; una clave sin traducción devuelve la clave.
- Test de claves (Vitest): recorre `resources/js` (salvo `components/ui`, `actions`, `routes` y `wayfinder`) y busca
  las llamadas a `t(`. Falla si una clave no está en `lang/es.json` o si el primer argumento no es un literal de texto.
- SSR: verificación manual, no test (enmienda de R2 del 2026-11-10). Con `composer dev`, que hace SSR con Vite en
  caliente, el PR muestra el HTML del servidor de una página con el texto en español. En los tests no hay SSR, y el
  riesgo de que servidor y cliente difieran es bajo porque `t()` importa el mismo JSON en los dos lados.

**Traducción de lo existente (R1)**

- Pantallas de autenticación, ajustes, teams e Inicio, `TeamSwitcher` y los modales: cada texto pasa a `t('...')`
  con su entrada en `lang/es.json`.
- Backend: las 34 marcas `TODO(i18n)` pasan a `__('...')` con su entrada en `lang/es.json`. La notificación
  `TeamInvitation` y el nombre del team personal también usan `__()`.
- Los tests que hoy comparan textos en español escritos en el código siguen pasando, porque la traducción devuelve el
  mismo texto.
- `CLAUDE.md`: la línea de Conventions que describe `TODO(i18n)` pasa a describir `lang/es.json`, `__()` y `t()`.
- El PR muestra `git grep "TODO(i18n)" -- . ':!specs'` vacío.

### Fase 3 · Identidad visual, contraste y apariencia (R4, R5, R7)

**Tokens (R4)**

En `resources/css/app.css`, la tabla de R4 se define como variables propias (`--nestly-bg`, `--nestly-surface`, …) en
`:root` (claro) y `.dark` (oscuro). Las variables de shadcn apuntan a ellas:

| Variable de shadcn                              | Token de R4          | Nota                                   |
| ----------------------------------------------- | -------------------- | -------------------------------------- |
| `--background`                                  | `bg`                 |                                        |
| `--foreground`, `--card-foreground`, …          | `text`               | Todos los `*-foreground` de superficie |
| `--card`, `--popover`, `--secondary`, `--muted` | `surface`            |                                        |
| `--muted-foreground`                            | `text-muted`         |                                        |
| `--primary`                                     | `primary`            |                                        |
| `--primary-foreground`                          | `primary-foreground` |                                        |
| `--accent`                                      | `primary-soft`       | Hover de menús e ítem activo           |
| `--accent-foreground`                           | `primary-text`       | Par ya calculado en R5                 |
| `--destructive`                                 | `danger`             |                                        |
| `--destructive-foreground`                      | `bg`                 | En los dos temas; par ya calculado     |
| `--border`                                      | `border`             | Solo tarjetas y separadores (R5)       |
| `--input`                                       | `input-border`       |                                        |
| `--ring`                                        | `primary`            |                                        |
| `--sidebar`, `--sidebar-*`                      | Los mismos de arriba | `surface`, `text`, `primary-soft`, …   |

- Tokens nuevos para Tailwind: `text-subtle`, `primary-hover`, `primary-soft`, `primary-text`, `success`, `warning`,
  `info` (igual a `primary-text`), e `income` y `expense`, que apuntan a `success` y `danger`.
- **Texto sobre `danger`:** la spec no lo definía, y el blanco sobre `danger` oscuro (`#E5636B`) no llega a 4.5:1. Se
  usa `bg` en los dos temas (confirmado el 2026-11-10), porque ese par ya está en R5: 5.78 en oscuro y 5.01 en claro.
- Se borran los `--chart-*`: no hay gráficos (D-072).
- El estilo de compatibilidad de bordes pasa de `--color-gray-200` a `var(--border)`.
- **Radios:** `--radius-md: 6px` (botones e inputs), `--radius-lg` y `--radius-xl: 10px` (tarjetas) y
  `--radius-2xl: 16px` (contenedores). Antes se revisa qué clase de radio usa cada componente de `components/ui`.
- **Fuentes:** `bunny('Inter', { weights: [400, 500, 600] })` y `bunny('JetBrains Mono', { weights: [500] })` en
  `vite.config.ts`, en lugar de Instrument Sans. Una utilidad `font-amount` aplica JetBrains Mono con
  `tabular-nums`.
- **Colores fuera de `app.css`:** `app.ts` pasa `var(--primary)` a la barra de progreso. `Welcome.vue` se borra en la
  fase 6.
- **`app.blade.php`:** se quita solo el bloque `<style>` en línea que pinta el fondo de `<html>` con `oklch()`. El
  `<script>` en línea que pone la clase `dark` según la preferencia del sistema se mantiene. El `<link>` de `app.css`
  está en el `<head>` y bloquea el render, así que el fondo sale bien sin el estilo en línea. Se comprueba a mano en
  los dos temas, con y sin caché.
- El PR muestra el grep de colores directos fuera de `app.css`, que solo encuentra `pages/Welcome.vue` (se borra en la
  fase 6). El grep completo, que solo encuentra `app.css` y las vistas Blade del 503 y del fallback del 500, va en el
  PR de la fase 7.

**Contraste (R5)**

- Un test (Vitest) lee los tokens de `app.css` en los dos temas, calcula la luminancia relativa de WCAG y comprueba
  cada par de las tablas de R5 con su umbral (4.5:1 o 3:1). Así la tabla se recalcula con los valores finales, como
  pide R5, y un token que cambie sin pasar AA rompe el CI (D-095).
- El test escribe la tabla resultante en su salida, y el PR la adjunta.

**Apariencia (R7)**

- La lógica ya existe. Lo nuevo son los tests (Pest feature): con la cookie `appearance=dark`, `<html>` trae la clase
  `dark`; con `light`, no la trae.
- `settings/Appearance.vue` se revisa con los tokens nuevos en los dos temas.

### Fase 4 · Formato de dinero y fechas (R8)

- `resources/js/lib/money.ts` exporta `formatMoney(amount: string, currency: string)`. Usa
  `Intl.NumberFormat('es-DO', { style: 'currency', currency })` y le pasa el string tal cual a `format()`, que acepta
  strings decimales sin pasar por `Number` (verificado en Node 26; ver "Riesgos del plan"). Sin `parseFloat` ni
  `Number()`.
- `resources/js/lib/dates.ts` exporta `formatRelativeDate(date: string, today: string)`. Las dos fechas son
  `YYYY-MM-DD`, y la diferencia en días se calcula con `Date.UTC(y, m - 1, d)` de cada una, nunca con
  `new Date('YYYY-MM-DD')`. Las reglas son las de R8. "Hoy" lo calcula quien llama, con la zona horaria del usuario
  (`users.timezone`, hoy `UTC` para todos).
- Un segundo helper, `todayIn(timeZone: string, now: Date)`, devuelve la fecha de hoy en esa zona con
  `Intl.DateTimeFormat`. Recibe `now` como argumento para que los tests no dependan del reloj ni de `TZ`.
- Tests (Vitest):
    - `'999999999999.99'` en DOP y en USD, con el resultado exacto;
    - montos negativos, y EUR mostrado con su código;
    - "Hoy", "Ayer", "Hace N días" para 2 a 6 días, y la fecha absoluta desde 7;
    - con `America/Santo_Domingo`, la fecha de ayer a las 21:00 hora local se muestra "Ayer".
- `accounts/Index.vue`, el esqueleto de la spec 002, deja su `Intl.NumberFormat('en-US')` y usa `formatMoney`
  (D-093), aunque todavía no muestre nada.

### Fase 5 · Página de muestra (R6)

- Ruta `GET /dev/showcase`, registrada solo cuando `app()->environment('local')`, que renderiza
  `dev/Showcase.vue`. Fuera de `local` la ruta no existe y responde 404.
- La página muestra botones (todas las variantes y estados), tarjetas, montos positivos y negativos en DOP y USD con
  `formatMoney`, inputs con y sin error y los cuatro tipos de toast, con un selector de tema.
- Test (Pest feature): en `testing` la ruta responde 404.
- Aceptación manual: comparación con las capturas de `design/` en los dos temas y en móvil.

### Fase 6 · Navegación, restos del starter kit e Inicio (R9, R11)

**Limpieza (R11)**

- `/` pasa de `Route::inertia('/', 'Welcome')` a una closure que redirige: un invitado va a `login` y un usuario con
  sesión a `dashboard` de su team actual (o del personal, como hace hoy la ruta `/dashboard`). Tests (Pest feature):
  los dos casos.
- Se borran los siete archivos de R11. Antes de cada uno, el PR muestra el `git grep` que prueba que nada lo importa.
  Después pasan `npm run build`, `vp check` y `php artisan wayfinder:generate`.
- El caso `Welcome` de `app.ts` desaparece con la página.

**Navegación (R9)**

- Una sola lista de entradas en `resources/js/lib/navigation.ts`, con un campo que dice si la entrada va en la barra
  o en "Más" (D-096). Al cerrar la 001 solo tiene Inicio, en la barra.
- **Escritorio (`md` y más):** `AppSidebar` con la marca, el selector de team, las entradas y `NavUser`. Sin
  `NavFooter`.
- **Móvil (menos de `md`):** cabecera nueva con la marca y el selector de team, y `components/BottomNav.vue` con las
  entradas de la barra y "Más". "Más" abre un `Sheet` (ya está en `components/ui`) con el resto de entradas, Ajustes y
  cerrar sesión, sin selector de team.
- El sidebar se oculta por debajo de `md` y la barra desde `md`, con clases de Tailwind, sin JavaScript.
- La barra usa `padding-bottom: env(safe-area-inset-bottom)` y el contenido reserva su altura para que no tape el
  final.
- La entrada activa lleva `aria-current="page"`. Las pestañas miden al menos 24×24 px.
- Tests (navegador): sidebar y barra a 767 y 768 px; `aria-current`; Tab, Enter y apertura y cierre de "Más" con
  teclado; el final del contenido queda visible.

**Inicio (R11)**

- `Dashboard.vue` deja `PlaceholderPattern` y muestra el nombre del team, mantiene `PendingInvitationsModal` y enlaza
  a la gestión del team (`teams.edit`). No anuncia funciones futuras.

### Fase 7 · Toasts, páginas de error y accesibilidad (R10, R12)

**Toasts**

- El `<Toaster>` sale de `AppSidebarLayout` y pasa a un único punto montado en todos los layouts: un componente raíz
  que envuelve las páginas en `createInertiaApp`. Así también aparece en los layouts de autenticación.
- Los cuatro tipos usan los tokens de R4. Los errores de validación y los 422 siguen en su campo.
- Test (navegador): un toast se ve en una pantalla de autenticación.

**Páginas de error**

- Para 403, 404 y 500, `Inertia::handleExceptionsUsing()` en `AppServiceProvider` renderiza `errors/Error` con
  `status`. El componente usa un layout mínimo sin sidebar, con un enlace a Inicio o al login según haya sesión.
- 403 y 404 usan `withSharedData()`. El 500 se renderiza solo con `status`, sin `withSharedData()`, para no depender
  de `share()` ni de la base de datos. Si renderizarlo lanza una excepción, se devuelve `errors/500-static.blade.php`.
- 503: `resources/views/errors/503.blade.php`, estática y en español, con los colores de R4 en línea y un comentario
  que remite a la tabla. Lo mismo para la vista del fallback del 500.
- 419: en el callback, redirigir atrás con un flash `toast` de sesión expirada, que muestra `flashToast.ts`.
- En `local` y `testing` se mantiene la traza: el callback devuelve `null` en esos entornos, y los tests que lo
  necesitan fuerzan el entorno.
- Tests (Pest feature):
    - cada código conserva su estado HTTP;
    - 404 idéntico (componente, props y texto) para un recurso inexistente y uno de otro team, con owner como actor
      (D-073);
    - el 500 con un fallo simulado del render cae a la vista estática;
    - el 503 con la app en mantenimiento;
    - el 419 redirige con el toast.

**Accesibilidad (R12)**

- `:focus-visible` con el anillo `primary` en la capa base de `app.css`, para todo elemento interactivo.
- Con `prefers-reduced-motion: reduce`, los toasts, el panel "Más" y los diálogos se muestran sin animación
  (`motion-reduce:` en Tailwind y la opción de vue-sonner).
- Tests (navegador):
    - recorrer una página con Tab y comprobar que cada elemento enfocado muestra el anillo;
    - emular `prefers-reduced-motion` y comprobar que no hay animación;
    - en ancho móvil, cada elemento enfocado queda por encima de la barra inferior (2.4.11);
    - pestañas de la barra y botones táctiles de al menos 24×24 px (2.5.8);
    - `assertNoAccessibilityIssues` en las páginas de la 001, como complemento.

## Trazabilidad

| Requisito | Fase | Verificación                                                                 |
| --------- | ---- | ---------------------------------------------------------------------------- |
| R1        | 2    | Pest feature (`<html lang>`), grep de `TODO(i18n)`, recorrido manual         |
| R2        | 2    | Vitest (reemplazos, claves, literales); SSR a mano con `composer dev`        |
| R3        | 2    | Pest (paridad), diff de Composer, revisión de la procedencia, correos a mano |
| R4        | 3, 7 | grep de colores directos (completo en la fase 7), revisión visual            |
| R5        | 3    | Vitest (contraste desde `app.css`)                                           |
| R6        | 5    | Pest feature (404 fuera de `local`), comparación manual con `design/`        |
| R7        | 3    | Pest feature (cookie `dark` y `light`)                                       |
| R8        | 4    | Vitest                                                                       |
| R9        | 6    | Navegador                                                                    |
| R10       | 7    | Pest feature y navegador                                                     |
| R11       | 6    | Pest feature (redirección de `/`), greps antes de borrar, build              |
| R12       | 7    | Navegador                                                                    |
| R13       | 1    | Tests triviales en local y CI, duración del CI en el PR                      |

## Preguntas abiertas

Ninguna. Si aparece alguna al escribir las tareas, se marcará como [NEEDS CLARIFICATION].

## Riesgos del plan

- **Tests de navegador en `composer ci:check`:** en local, cada ejecución arranca Chromium. Si resulta lento o
  inestable en Windows (el cuelgue del spike), se valora excluir la suite `Browser` de la ejecución por defecto en
  local, sin quitarla del CI.
- **`Intl.NumberFormat` con strings:** en Node 26 (local) da `RD$999,999,999,999.99` sin pérdida. El CI usa Node 22;
  si allí no acepta strings decimales, el test del monto máximo falla, y antes de seguir hay que decidir qué hacer.
- **`.env` local:** sin el cambio manual de `APP_LOCALE`, los tests de idioma fallan en local aunque pasen en CI.
- **El resto de riesgos** son los de la spec y no cambian.
