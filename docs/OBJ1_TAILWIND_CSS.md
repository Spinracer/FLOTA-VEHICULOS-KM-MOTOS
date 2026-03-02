# FlotaControl — Objetivo 1: Migración a Tailwind CSS

> Fecha: 2026-03-02  
> Versión: v3.1.0

---

## Resumen

Migración del frontend completo a **Tailwind CSS** como framework principal, con soporte para **tema oscuro/claro** y optimización **responsive hasta 4K**.

---

## Cambios Realizados

### 1. Integración de Tailwind CSS

- **Método**: Tailwind Play CDN (`cdn.tailwindcss.com`) integrado en `layout.php` y páginas standalone
- **Configuración personalizada**: Theme extendido con colores del sistema

```javascript
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        dark: '#0a0c10',    surface: '#111318',  surface2: '#181c24',
        border: '#222730',  accent: '#e8ff47',   accent2: '#47ffe8',
        danger: '#ff4757',  warning: '#ffa502',  success: '#2ed573',
        info: '#1e90ff',    muted: '#8892a4',
      },
      fontFamily: {
        heading: ['Syne', 'sans-serif'],
        body: ['DM Sans', 'sans-serif'],
      }
    }
  }
}
```

### 2. Sistema de Temas (Dark/Light)

- **Toggle** en topbar con iconos 🌙/☀️
- **Persistencia** en `localStorage` (`fc-theme`)
- **Clase CSS**: `.dark` y `.light` en `<html>`
- **Variables CSS**: Se alternan automáticamente para ambos temas
- **Tema oscuro por defecto**

#### Colores por tema

| Variable | Dark | Light |
|---|---|---|
| `--bg` | `#0a0c10` | `#f8fafc` |
| `--surface` | `#111318` | `#ffffff` |
| `--surface2` | `#181c24` | `#f1f5f9` |
| `--border` | `#222730` | `#e2e8f0` |
| `--accent` | `#e8ff47` (amarillo neón) | `#4f46e5` (indigo) |
| `--accent2` | `#47ffe8` (cyan) | `#0891b2` (teal) |
| `--text` | `#f0f2f5` | `#1e293b` |
| `--text2` | `#8892a4` | `#64748b` |

### 3. Layout Responsivo Mejorado

- **Sidebar**: Oculto en mobile (< 1024px) con botón hamburguesa
- **Click fuera** cierra sidebar en mobile
- **Topbar** adaptado con padding responsive
- **Dashboard** usa grids Tailwind: `grid-cols-2 sm:grid-cols-3 xl:grid-cols-6`

### 4. Soporte 4K

```css
@media(min-width:2560px) {
  .kpi-grid { grid-template-columns: repeat(6, 1fr); }
  .chart-grid { grid-template-columns: 1fr 1fr 1fr; }
  .page-content { max-width: 2200px; margin: 0 auto; }
}
```

---

## Archivos Modificados

| Archivo | Cambio |
|---|---|
| `includes/layout.php` | Reescrito con Tailwind CSS, sidebar responsivo, toggle de tema |
| `assets/style.css` | Convertido en capa de compatibilidad Tailwind con variables CSS duales (dark/light) |
| `index.php` | Login migrado a Tailwind con soporte tema |
| `modules/web/dashboard.php` | Grid Tailwind para KPIs y secciones |
| `firma.php` | Página standalone migrada a Tailwind |

---

## Compatibilidad

- **Sin breaking changes** en módulos web existentes
- Las clases CSS custom (`.btn`, `.badge`, `.modal-bg`, `.kpi-card`, etc.) se mantienen funcionales
- Tailwind complementa — no reemplaza — las clases que usan los 16 módulos
- `app.js` sin cambios (toast, api, modales, paginador siguen funcionando)

---

## Cómo Funciona el Toggle de Tema

```javascript
function toggleTheme() {
  const current = getTheme();                        // leer de localStorage
  const next = current === 'dark' ? 'light' : 'dark';
  localStorage.setItem('fc-theme', next);            // persistir
  applyTheme(next);                                  // aplicar
}
```

El tema se aplica:
1. Agregando/removiendo clase `dark`/`light` en `<html>`
2. Cambiando clases Tailwind en `<body>`
3. Las CSS variables en `:root`/`.dark`/`.light` hacen el resto

---

## Próximo Objetivo

**Objetivo 2**: Mejoras del módulo Vehículos (etiquetas, costo/km, gráfica km, telemetría).
