---
version: "2.2"
---

**Nota de versión 2.2:** alineado con el resto de la documentación del
remake de portafolio. Dos ajustes de fondo: (1) se confirma esta paleta
(azul/verde/ámbar sobre fondo oscuro) como la identidad visual vigente
del remake — **distinta y no relacionada** con la paleta brass/teal/sage/
rust del Aperture original, ya que este proyecto es una reconstrucción
independiente pensada como pieza de portafolio; (2) se resuelve el
problema de escalabilidad de la paleta categórica para subcategorías
(§6.2.1.1) y se agrega un chequeo de contraste para los colores
categóricos en modo claro (§6.6).

---

# Parte III — Diseño

## 6. Design System

**Versión:** 2.2
**Objetivo:** Sistema de diseño consistente, accesible y escalable para Nestly (web + PWA + móvil).

### 6.1 Principios de Diseño

1. **Claridad ante todo** — números, jerarquía visual evidente.
2. **Confianza** — look sobrio, no infantil; el dinero es serio.
3. **Rapidez percibida** — skeletons, feedback inmediato.
4. **Consistencia** — un patrón, una forma de hacerlo.
5. **Accesibilidad** — WCAG 2.1 AA mínimo.

### 6.2 Tokens

#### 6.2.1 Colores

```css
/* Base */
--color-bg:           #0B0F19;   /* dark mode default */
--color-surface:      #131826;
--color-surface-2:    #1C2233;
--color-border:       #262E42;

/* Texto */
--color-text:         #F5F7FA;
--color-text-muted:   #9AA4B8;
--color-text-subtle:  #6B7689;

/* Marca */
--color-primary:      #4F7CFF;
--color-primary-hover:#3D6BEE;
--color-primary-soft: #1E2A4D;

/* Semánticos */
--color-success:      #22C55E;   /* ingresos, positivo */
--color-danger:       #EF4444;   /* gastos, alertas */
--color-warning:      #F59E0B;   /* presupuesto 80% */
--color-info:         #38BDF8;

/* Categorías (paleta categórica accesible, base para categorías padre) */
--cat-1: #4F7CFF;  --cat-2: #22C55E;  --cat-3: #F59E0B;  --cat-4: #EF4444;
--cat-5: #A855F7;  --cat-6: #06B6D4;  --cat-7: #EC4899;  --cat-8: #84CC16;
```

**Light mode**: espejo con `--color-bg: #FFFFFF`, superficies grises suaves.

#### 6.2.1.1 Escalado de color para subcategorías

Los 8 `--cat-N` alcanzan para categorías padre, pero el modelo de datos
permite subcategorías (`CATEGORY.parent_id`, ver ERD §4.1) y pedirle al
usuario un color distinto por cada subcategoría no escala ni visualmente
ni en UX de formulario. En vez de agregar más colores fijos al token set,
las subcategorías **heredan el tono de la categoría padre y varían solo
en luminosidad**, generado en runtime, no como tokens nuevos:

```css
/* Utilidad: variante de una categoría por nivel de anidamiento.
   Se aplica sobre el valor ya resuelto de --cat-N en el elemento. */
.category-swatch {
  background-color: var(--cat-color);
}

.category-swatch[data-depth="1"] {
  background-color: color-mix(in oklch, var(--cat-color) 75%, white 25%);
}

.category-swatch[data-depth="2"] {
  background-color: color-mix(in oklch, var(--cat-color) 55%, white 45%);
}
```

```ts
// El componente CategoryPicker resuelve --cat-color a partir del
// category_id padre y aplica data-depth según la profundidad real,
// sin pedir un color nuevo al usuario en subcategorías.
```

Esto mantiene la paleta categórica cerrada en 8 tonos base (fácil de
recordar, fácil de mantener accesible) mientras sigue permitiendo
distinguir visualmente subcategorías dentro de un donut chart o una
lista, por variación de luminosidad en vez de matiz.

#### 6.2.2 Tipografía

```css
--font-sans: 'Inter', system-ui, sans-serif;
--font-mono: 'JetBrains Mono', monospace;  /* para cifras */

/* Escala (1.250 major third) */
--text-xs:   0.75rem;   /* 12px */
--text-sm:   0.875rem;  /* 14px */
--text-base: 1rem;      /* 16px */
--text-lg:   1.125rem;  /* 18px */
--text-xl:   1.25rem;   /* 20px */
--text-2xl:  1.5rem;    /* 24px */
--text-3xl:  1.875rem;  /* 30px */
--text-4xl:  2.25rem;   /* 36px */

--weight-regular: 400;
--weight-medium:  500;
--weight-semibold:600;
--weight-bold:    700;

--leading-tight: 1.2;
--leading-normal:1.5;
```

**Regla**: cifras monetarias siempre en `font-mono` con `font-variant-numeric: tabular-nums`.

#### 6.2.3 Espaciado (base 4px)

```css
--space-1: 4px;   --space-2: 8px;   --space-3: 12px;
--space-4: 16px;  --space-5: 20px;  --space-6: 24px;
--space-8: 32px;  --space-10: 40px; --space-12: 48px;
--space-16: 64px;
```

#### 6.2.4 Radios

```css
--radius-sm: 6px;   --radius-md: 10px;
--radius-lg: 16px;  --radius-full: 9999px;
```

#### 6.2.5 Sombras

```css
--shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
--shadow-md: 0 4px 12px rgba(0,0,0,0.25);
--shadow-lg: 0 12px 32px rgba(0,0,0,0.35);
```

#### 6.2.6 Motion

```css
--duration-fast: 120ms;
--duration-base: 200ms;
--duration-slow: 320ms;
--ease-out: cubic-bezier(0.16, 1, 0.3, 1);
```

### 6.3 Componentes

#### 6.3.1 Átomos

- **Button**: variantes `primary | secondary | ghost | danger`, tamaños `sm | md | lg`, estados `hover | active | disabled | loading`.
- **Input**: texto, número (con máscara de moneda), select, date picker.
- **Badge**: para estados (activo, pausado, vencido).
- **Avatar**.
- **Icon** (Lucide, 20/24px).
- **Skeleton**.

#### 6.3.2 Moléculas

- **FormField**: label + input + helper + error.
- **CurrencyInput**: con símbolo de moneda y separador de miles.
- **CategoryPicker**: icono + color + nombre. Para subcategorías, resuelve
  el color por herencia + luminosidad (§6.2.1.1), no por selector de color
  libre.
- **ProgressBar**: con estados (normal, warning 80%, danger 100%).
- **StatCard**: KPI con label, valor, delta y sparkline.
- **TransactionRow**: icono, descripción, categoría, monto (color según tipo), fecha.

#### 6.3.3 Organismos

- **AccountCard**: nombre, tipo, saldo, acciones.
- **BudgetCard**: categoría, límite, gastado, barra, % restante.
- **RecurringCard**: nombre, monto, próxima fecha, estado.
- **Charts**: LineChart (cashflow), DonutChart (categorías), BarChart (comparativas).
- **Sidebar / BottomNav**.
- **Topbar** con selector de periodo.
- **Modals**: crear/editar entidad, confirmación de borrado.

*(`PotCard` se agrega en v1.1 cuando se implemente Pots — ver PRD RF-06.)*

#### 6.3.4 Patrones

- **Empty states**: ilustración + copy + CTA.
- **Loading**: skeletons con forma del contenido final.
- **Errores**: banner inline + retry.
- **Onboarding**: 3 pasos (crear cuenta → categoría → primer presupuesto).

### 6.4 Layout

#### 6.4.1 Desktop

```
┌───────────────────────────────────────────────┐
│ Topbar: logo · periodo · user · notif         │
├──────┬────────────────────────────────────────┤
│ Side │  Main (grid 12 cols, gutter 24px)      │
│ nav  │                                        │
└──────┴────────────────────────────────────────┘
```

#### 6.4.2 Mobile (web responsive / PWA)

- Bottom nav con tabs: Dashboard, Cuentas, Presupuestos, Más.
- FAB para "+ Transacción".

*Nota: esto describe el layout responsive de la web/PWA. La futura app
nativa en React Native (v1.2) es una base de código de UI aparte — no
reutiliza estos componentes Vue, pero sí debe respetar los mismos tokens
de color, tipografía y espaciado documentados aquí para mantener
identidad visual consistente entre web y mobile.*

#### 6.4.3 Breakpoints

```
sm: 640px   md: 768px   lg: 1024px   xl: 1280px   2xl: 1536px
```

### 6.5 Iconografía

- **Librería**: Lucide.
- **Tamaños**: 16, 20, 24, 32px.
- **Trait**: outline, stroke 1.5–2px.

### 6.6 Accesibilidad

- Contraste mínimo 4.5:1 en texto.
- No depender solo del color (iconos + texto para estados).
- Focus visible con `outline: 2px solid var(--color-primary)`.
- Navegación completa por teclado.
- `aria-live` para KPIs que cambian.
- `prefers-reduced-motion` respetado.
- **Pendiente de verificar**: los 8 `--cat-N` y sus variantes por
  luminosidad (§6.2.1.1) solo están definidos para dark mode en este
  documento. Antes de usarlos como texto o ícono (no solo como fondo de
  swatch) en light mode, validar contraste contra `#FFFFFF` — algunos
  como `--cat-3` (#F59E0B, ámbar) y `--cat-6` (#06B6D4, cian) son
  candidatos a fallar 4.5:1 sobre blanco y podrían necesitar una variante
  "on-light" más oscura solo para uso como texto/ícono, no como swatch.

### 6.7 Contenido y tono

- Voz: cercana pero profesional, sin jerga financiera innecesaria.
- Formato de moneda: `Intl.NumberFormat(locale, { style: 'currency', currency })`.
- Fechas: relativas ("hace 2 días") en listas, absolutas en detalle.
- Ejemplos:
  - Correcto: "Aportaste $200 a Vacaciones".
  - Evitar: "Se ha realizado una transacción de aportación".

### 6.8 Estructura de archivos sugerida

```
/design-system
  /tokens
    colors.css
    typography.css
    spacing.css
  /components
    Button.tsx
    Input.tsx
    StatCard.tsx
    BudgetCard.tsx
    ...
  /charts
    CashflowChart.tsx
    CategoriesDonut.tsx
  /patterns
    EmptyState.tsx
    PageHeader.tsx
```

*(Ajustar extensiones a `.vue` — esta estructura es genérica de
componentes, no específica de React; ver TRD §3.4 para la estructura real
del proyecto Inertia.)*

### 6.9 Checklist antes de mergear UI

- [ ] Usa tokens (nada de colores hardcoded).
- [ ] Responsive probado en 3 breakpoints.
- [ ] Contraste validado (incluye colores categóricos usados como texto/ícono, ver §6.6).
- [ ] Estados: default, hover, focus, disabled, loading, error, empty.
- [ ] Textos con i18n.
- [ ] Sin regresiones visuales (Chromatic/Storybook).

---

## 7. Tokens CSS

Archivo `tokens.css` listo para importar en el proyecto:

```css
:root {
  /* ============================================
     COLORES
     ============================================ */
  --color-bg: #0B0F19;
  --color-surface: #131826;
  --color-surface-2: #1C2233;
  --color-border: #262E42;

  --color-text: #F5F7FA;
  --color-text-muted: #9AA4B8;
  --color-text-subtle: #6B7689;

  --color-primary: #4F7CFF;
  --color-primary-hover: #3D6BEE;
  --color-primary-soft: #1E2A4D;

  --color-success: #22C55E;
  --color-danger: #EF4444;
  --color-warning: #F59E0B;
  --color-info: #38BDF8;

  /* Categorías (base — subcategorías derivan por luminosidad, ver §6.2.1.1) */
  --cat-1: #4F7CFF; --cat-2: #22C55E; --cat-3: #F59E0B; --cat-4: #EF4444;
  --cat-5: #A855F7; --cat-6: #06B6D4; --cat-7: #EC4899; --cat-8: #84CC16;

  /* ============================================
     TIPOGRAFÍA
     ============================================ */
  --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

  --text-xs: 0.75rem;
  --text-sm: 0.875rem;
  --text-base: 1rem;
  --text-lg: 1.125rem;
  --text-xl: 1.25rem;
  --text-2xl: 1.5rem;
  --text-3xl: 1.875rem;
  --text-4xl: 2.25rem;

  --weight-regular: 400;
  --weight-medium: 500;
  --weight-semibold: 600;
  --weight-bold: 700;

  --leading-tight: 1.2;
  --leading-normal: 1.5;
  --leading-relaxed: 1.75;

  /* ============================================
     ESPACIADO (base 4px)
     ============================================ */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
  --space-10: 40px;
  --space-12: 48px;
  --space-16: 64px;

  /* ============================================
     RADIOS
     ============================================ */
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
  --radius-full: 9999px;

  /* ============================================
     SOMBRAS
     ============================================ */
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.25);
  --shadow-lg: 0 12px 32px rgba(0,0,0,0.35);

  /* ============================================
     MOTION
     ============================================ */
  --duration-fast: 120ms;
  --duration-base: 200ms;
  --duration-slow: 320ms;
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);

  /* ============================================
     Z-INDEX
     ============================================ */
  --z-dropdown: 1000;
  --z-sticky: 1020;
  --z-modal-backdrop: 1040;
  --z-modal: 1050;
  --z-popover: 1060;
  --z-tooltip: 1070;
  --z-toast: 1080;
}

/* ============================================
   LIGHT MODE
   ============================================ */
@media (prefers-color-scheme: light) {
  :root {
    --color-bg: #FFFFFF;
    --color-surface: #F7F8FA;
    --color-surface-2: #EEF0F4;
    --color-border: #E2E5EB;

    --color-text: #0B0F19;
    --color-text-muted: #5B6478;
    --color-text-subtle: #8B93A7;

    --color-primary-soft: #E8EEFF;

    /* Pendiente (§6.6): variantes "on-light" más oscuras de --cat-3 y
       --cat-6 si se usan como texto/ícono, no solo como swatch de fondo. */
  }
}

/* ============================================
   UTILIDADES
   ============================================ */
.tabular-nums {
  font-variant-numeric: tabular-nums;
}

.mono-currency {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
}

/* Escalado de color por profundidad de subcategoría (§6.2.1.1) */
.category-swatch[data-depth="1"] {
  background-color: color-mix(in oklch, var(--cat-color) 75%, white 25%);
}

.category-swatch[data-depth="2"] {
  background-color: color-mix(in oklch, var(--cat-color) 55%, white 45%);
}

/* ============================================
   ACCESIBILIDAD
   ============================================ */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}
```

---

**Fin de la Parte 4 de 5.**
