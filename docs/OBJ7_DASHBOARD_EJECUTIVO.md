# Objetivo 7 — Dashboard Ejecutivo Mejorado

> Versión: **v3.7.0** | Fecha: 2026-03-07

---

## Resumen

Rediseño completo del Dashboard principal, transformándolo de un módulo con KPIs estáticos server-side y 2 gráficos simples a un Dashboard Ejecutivo dinámico con:
- API dedicada con filtros dinámicos
- 6 KPIs con tendencias (vs período anterior)
- 5 gráficos Chart.js interactivos
- 3 paneles de listas en tiempo real
- Filtros por sucursal, vehículo y período

---

## API — modules/api/dashboard.php (Nuevo)

`GET /api/dashboard.php`

### Parámetros
| Parámetro | Tipo | Descripción |
|---|---|---|
| sucursal_id | INT | Filtrar por sucursal (0 = todas) |
| vehiculo_id | INT | Filtrar por vehículo (0 = todos) |
| periodo | STRING | `mes`, `trimestre`, `semestre`, `anio` (default) |
| from | DATE | Fecha inicio override |
| to | DATE | Fecha fin override |

### Respuesta
```json
{
  "ok": true,
  "periodo": { "from": "2026-01-01", "to": "2026-03-07" },
  "kpis": {
    "vehiculos": 45, "operadores": 30,
    "inc_abiertos": 3, "litros": 5200, "gasto_comb": 82000,
    "gasto_mant": 45000, "total_mant": 22,
    "km_recorridos": 125000, "alertas_activas": 8,
    "ots_pendientes": 4, "eficiencia_kml": 8.5,
    "trend_comb": 12.3, "trend_mant": -5.1
  },
  "charts": {
    "gasto_mensual": [...],
    "inc_mensual": [...],
    "top_vehiculos": [...],
    "dist_mant": [...],
    "top_eficiencia": [...]
  },
  "lists": {
    "recordatorios": [...],
    "ots": [...],
    "alertas": [...]
  },
  "filters": {
    "sucursales": [...],
    "vehiculos": [...]
  }
}
```

### KPIs con Tendencias
- `trend_comb`: % cambio gasto combustible vs período anterior
- `trend_mant`: % cambio gasto mantenimiento vs período anterior
- Se calcula automáticamente comparando el período seleccionado con el período inmediato anterior de igual duración

---

## Web — modules/web/dashboard.php (Reescrito)

### Filtros Dinámicos
Barra superior con 3 selectores:
- **Sucursal**: Filtra todo el dashboard por sucursal. Al cambiar, recarga la lista de vehículos disponibles.
- **Vehículo**: Filtra a un vehículo específico.
- **Período**: Este Mes, Trimestre, Semestre, Este Año (default).

### KPIs (6 tarjetas)
| KPI | Badge color | Contenido |
|---|---|---|
| 🚗 Vehículos | yellow | Total en flota |
| 👤 Operadores | orange | Activos |
| ⛽ Combustible | cyan | Gasto $ + Litros L + trend % |
| 🔧 Mantenimiento | blue | Gasto $ + Total OTs + trend % |
| ⚠️ Incidentes | red | Abiertos |
| 🚨 Alertas | yellow | Activas + OTs pendientes |

### Gráficos (5)
1. **Gasto Mensual (12 meses)**: Línea doble — Combustible (cyan) + Mantenimiento (accent). Eje Y formateado en $.
2. **Top 10 Vehículos por Costo**: Barras horizontales apiladas — Combustible + Mantenimiento.
3. **Distribución Mantenimiento**: Doughnut por tipo (Correctivo, Preventivo, etc.) con gasto $.
4. **Incidentes Mensuales**: Barras con conteo por mes (12 meses).
5. **Eficiencia Operadores (km/L)**: Barras horizontales con top operadores.

### Paneles de Listas (3 columnas)
- 🔔 Recordatorios Próximos (30 días)
- 🔧 OTs Activas (pendientes/en proceso)
- 🚨 Alertas Activas (link a Centro de Alertas)

---

## Mejoras vs Dashboard Anterior

| Aspecto | Antes (v3.6) | Ahora (v3.7) |
|---|---|---|
| KPIs | 6 estáticos (server-side) | 6 dinámicos + tendencias % |
| Gráficos | 2 barras simples (renderBarChart) | 5 Chart.js interactivos |
| Filtros | Ninguno | Sucursal + Vehículo + Período |
| Datos | Cargados en PHP | API JSON dedicada |
| Listas | 4 paneles estáticos | 3 paneles dinámicos |
| Alertas | Solo recordatorios | Integración con Centro de Alertas |
| Tendencias | No | Comparativa vs período anterior |

---

## Archivos modificados/creados

| Archivo | Acción |
|---|---|
| modules/api/dashboard.php | Nuevo — API completa |
| api/dashboard.php | Nuevo — Wrapper API |
| modules/web/dashboard.php | Reescrito completamente |
| docs/OBJ7_DASHBOARD_EJECUTIVO.md | Este documento |
| docs/CHANGELOG.md | v3.7.0 |
| README.md | Actualizado a v3.7.0 |
