# Objetivo 4 — Mejoras Combustible + Incidentes

## Resumen

Mejoras significativas en los módulos de Combustible e Incidentes: gráficos comparativos por período, indicador de eficiencia por vehículo con ranking, sistema de adjuntos en incidentes, flujo de seguimiento por estados con log de actividad, y dashboard de seguridad con Chart.js.

---

## Combustible — Nuevas Funcionalidades

### 1. Gráfico Comparativo por Período

- **Toggle Gráficos** en toolbar: muestra/oculta sección de charts.
- **Gráfico de Gasto ($)**: Bar chart mensual/semanal/diario con línea de precio promedio por litro (eje Y secundario).
- **Gráfico de Litros**: Bar chart de consumo por período.
- **Comparativa**: Diferencia porcentual vs período anterior (misma duración) con indicadores ↑↓ coloreados.
- **Filtros**: Respeta los filtros de vehículo y rango de fechas del toolbar principal.

#### API Endpoint

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=chart_data` | GET | Series temporales agrupadas (month/week/day) + totales período anterior |

Parámetros: `vehiculo_id`, `from`, `to`, `group` (month|week|day)

### 2. Indicador de Eficiencia por Vehículo

- **Modal Eficiencia** con tabla ranking de todos los vehículos.
- Métricas por vehículo: cargas, litros totales, gasto, km recorridos, **km/L**, **$/km**.
- **Ranking** por mejor rendimiento km/L (colores: verde ≥10, naranja ≥6, rojo <6).
- Filtros por rango de fechas.
- Requiere mínimo 2 cargas por vehículo para calcular.

#### API Endpoint

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=eficiencia` | GET | Ranking de eficiencia por vehículo |

Parámetros: `from`, `to`

---

## Incidentes — Nuevas Funcionalidades

### 3. Sistema de Adjuntos

- **AttachmentWidget** integrado en modal de crear/editar incidente.
- Soporte multi-archivo con drag & drop.
- También disponible en modal de detalle del incidente.

### 4. Flujo de Seguimiento por Estados

- **Máquina de estados formal**:
  - `Abierto → En proceso → Cerrado`
  - `En proceso → Abierto` (reabrir)
  - `Cerrado → Abierto` (reabrir)
- **Validación server-side** de transiciones en PUT.
- **Log de seguimientos** (`incidente_seguimientos`): registro automático de cambios de estado y notas manuales.
- **Vista de seguimientos** en modal de detalle: timeline con usuario, acción, estado anterior/nuevo, comentario.
- **Input de nota rápida** para agregar seguimientos directamente desde el detalle.
- **Campo Prioridad**: `Baja`, `Normal`, `Alta`, `Urgente` — disponible en formulario de edición.
- **Campos de resolución**: `resolved_at` y `resolved_by` se llenan automáticamente al cerrar.

#### API Endpoints nuevos

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=seguimientos` | GET | Historial de seguimientos de un incidente |
| `?action=seguimientos` | POST | Agregar nota de seguimiento |

### 5. Dashboard de Seguridad

- **Button "📊 Dashboard Seguridad"** en toolbar.
- **4 KPIs**: Total incidentes, Abiertos, Críticos, Días promedio resolución.
- **Gráfico de barras**: Incidentes por mes con línea de costo estimado.
- **Gráfico doughnut**: Distribución por severidad (Crítica/Alta/Media/Baja).
- **Top 10 vehículos** con más incidentes y su costo acumulado.
- **Filtro por año** (últimos 4 años).

#### API Endpoint

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=dashboard` | GET | Datos completos del dashboard de seguridad |

Datos: by_severity, by_type, by_month, by_status, top_vehicles, reclamos, avg_resolve_days.

---

## Migraciones de Base de Datos (install.php)

### §3.15 — Seguimiento de incidentes
```sql
CREATE TABLE incidente_seguimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incidente_id INT NOT NULL,
  usuario_id INT NULL,
  accion VARCHAR(60) NOT NULL,
  estado_anterior VARCHAR(30) NULL,
  estado_nuevo VARCHAR(30) NULL,
  comentario TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (incidente_id) REFERENCES incidentes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);
```

Columnas nuevas en `incidentes`:
- `resolved_at DATETIME NULL`
- `resolved_by INT NULL`
- `prioridad ENUM('Baja','Normal','Alta','Urgente') DEFAULT 'Normal'`

---

## Archivos Modificados

| Archivo | Cambios |
|---|---|
| `install.php` | Sección 3.15: tabla incidente_seguimientos + 3 columnas en incidentes |
| `modules/api/combustible.php` | +2 endpoints (chart_data, eficiencia) |
| `modules/web/combustible.php` | Gráficos Chart.js toggle, modal eficiencia, comparativa período anterior |
| `modules/api/incidentes.php` | +2 endpoints (seguimientos, dashboard), máquina de estados, resolved_at, prioridad |
| `modules/web/incidentes.php` | AttachmentWidget, seguimientos en detalle, campo prioridad, dashboard de seguridad con Chart.js |

---

## Dependencias

- **Chart.js** (ya integrado via CDN desde Obj 2) — usado para gráficos de combustible y dashboard de seguridad.
