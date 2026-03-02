# Objetivo 5 — Operadores + Componentes + Proveedores + Sucursales

> Versión: **v3.5.0** — Fecha: 2026-03-02

---

## Resumen

Mejoras integrales en 4 módulos existentes:
- **Operadores**: Historial de capacitaciones, registro de infracciones, KPIs de desempeño
- **Componentes**: Inventario con movimientos, stock consolidado, alertas de vencimiento
- **Proveedores**: Evaluaciones de desempeño (1-5 estrellas), registro de contratos, ranking
- **Sucursales**: Dashboard comparativo con Chart.js (4 gráficos por sucursal)

---

## Migraciones — install.php §3.16

### Nuevas Tablas

| Tabla | Descripción | Relación |
|---|---|---|
| `operador_capacitaciones` | Historial de cursos/certificaciones del operador | FK → operadores |
| `operador_infracciones` | Multas, accidentes, violaciones con monto y estado | FK → operadores |
| `componente_movimientos` | Registro de entradas, salidas, transferencias, ajustes | FK → components, vehiculos, usuarios |
| `proveedor_evaluaciones` | Evaluación 4 dimensiones (calidad, puntualidad, precio, servicio) + promedio auto-calculado | FK → proveedores, usuarios |
| `proveedor_contratos` | Contratos con tipo, monto, fechas, estado | FK → proveedores |

### Nuevas Columnas

| Tabla | Columna | Tipo | Descripción |
|---|---|---|---|
| `components` | `stock` | INT DEFAULT 0 | Stock consolidado (se actualiza con movimientos) |
| `components` | `stock_minimo` | INT DEFAULT 0 | Umbral mínimo para alertas |

---

## API — Nuevos Endpoints

### Operadores (`modules/api/operadores.php`)

| Acción | Método | Parámetros | Descripción |
|---|---|---|---|
| `action=capacitaciones` | GET | `operador_id` | Lista capacitaciones del operador |
| `action=capacitaciones` | POST | body JSON | Registrar capacitación (titulo, tipo, horas, fecha, vencimiento) |
| `action=capacitaciones` | DELETE | `id` | Eliminar capacitación |
| `action=infracciones` | GET | `operador_id` | Lista infracciones del operador |
| `action=infracciones` | POST | body JSON | Registrar infracción (tipo, monto, referencia) |
| `action=infracciones` | PUT | body JSON | Cambiar estado (Pendiente→Pagada→Contestada) |
| `action=infracciones` | DELETE | `id` | Eliminar infracción |
| `action=kpis` | GET | `id` | KPIs completos: asignaciones, km, incidentes, infracciones, capacitaciones, eficiencia km/L, días activo |

### Componentes (`modules/api/componentes.php`)

| Acción | Método | Parámetros | Descripción |
|---|---|---|---|
| `section=movimientos` | GET | `component_id`, `vehiculo_id` (opcionales) | Lista movimientos de inventario |
| `section=movimientos` | POST | body JSON | Registrar movimiento (tipo, cantidad). Actualiza stock automáticamente |
| `section=alertas_vencimiento` | GET | `dias` (default 30) | Componentes por vencer en X días |

### Proveedores (`modules/api/proveedores.php`)

| Acción | Método | Parámetros | Descripción |
|---|---|---|---|
| `action=evaluaciones` | GET | `proveedor_id` | Lista evaluaciones del proveedor |
| `action=evaluaciones` | POST | body JSON | Evaluar (calidad, puntualidad, precio, servicio 1-5) |
| `action=evaluaciones` | DELETE | `id` | Eliminar evaluación |
| `action=contratos` | GET | `proveedor_id` | Lista contratos del proveedor |
| `action=contratos` | POST | body JSON | Registrar contrato (titulo, fechas, monto, tipo, estado) |
| `action=contratos` | PUT | body JSON | Actualizar contrato |
| `action=contratos` | DELETE | `id` | Eliminar contrato |
| `action=ranking` | GET | — | Ranking de proveedores por promedio de evaluación |

### Sucursales (`modules/api/sucursales.php`)

| Acción | Método | Descripción |
|---|---|---|
| `action=dashboard` | GET | Dashboard comparativo: vehículos, operadores, gasto mantenimiento, gasto combustible, incidentes por sucursal (últimos 12 meses) |

---

## Web — Funcionalidades Nuevas

### Operadores (`modules/web/operadores.php`)
- Botón **📊 KPIs** en cada fila → Modal con 10 métricas de desempeño
- Botón **📜 Capacitaciones** → Modal con tabla + formulario para agregar
- Botón **⚠️ Infracciones** → Modal con tabla + formulario, cambio de estado inline

### Componentes (`modules/web/componentes.php`)
- Columnas **Stock** y **Mín.** en tabla catálogo con badges color-coded
- Campo **Stock mínimo** en formulario de catálogo
- Botón **📦 Movimientos** → Modal con historial + formulario de registro
- Botón **⏰ Vencimientos** con badge rojo automático → Modal con alertas próximas

### Proveedores (`modules/web/proveedores.php`)
- Botón **⭐ Evaluaciones** por proveedor → Modal con historial de evaluaciones + formulario
- Botón **📋 Contratos** por proveedor → Modal con lista de contratos + formulario
- Botón **⭐ Ranking** global → Tabla ordenada por promedio de evaluación

### Sucursales (`modules/web/sucursales.php`)
- Botón **📊 Dashboard Comparativo** → Modal con 4 gráficos Chart.js:
  1. Vehículos y Operadores por sucursal
  2. Gasto en Mantenimiento por sucursal
  3. Gasto en Combustible por sucursal
  4. Incidentes (total + abiertos) por sucursal

---

## Archivos Modificados

| Archivo | Cambios |
|---|---|
| `install.php` | §3.16: 5 tablas nuevas + 2 columnas |
| `modules/api/operadores.php` | +3 endpoints (capacitaciones, infracciones, kpis) |
| `modules/web/operadores.php` | +3 botones acción, +2 modales, +JS funciones |
| `modules/api/componentes.php` | +2 secciones (movimientos, alertas_vencimiento), stock_minimo en PUT |
| `modules/web/componentes.php` | +Stock/Mín columnas, +Movimientos modal, +Alertas modal, +badge vencimientos |
| `modules/api/proveedores.php` | +4 endpoints (evaluaciones, contratos, ranking) |
| `modules/web/proveedores.php` | +Evaluaciones/Contratos/Ranking modales + formularios |
| `modules/api/sucursales.php` | +1 endpoint (dashboard) |
| `modules/web/sucursales.php` | +Dashboard comparativo con 4 Chart.js gráficos |
