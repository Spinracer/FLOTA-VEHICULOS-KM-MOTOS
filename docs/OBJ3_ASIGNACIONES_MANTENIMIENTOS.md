# Objetivo 3 — Mejoras Asignaciones + Mantenimientos

## Resumen

Mejoras significativas en los módulos de Asignaciones y Mantenimientos, incluyendo calendario visual con FullCalendar.js, checklist dinámico basado en plantillas, aprobaciones multinivel para OTs de alto costo, y vinculación de componentes en partidas de mantenimiento.

---

## Asignaciones — Nuevas Funcionalidades

### 1. Calendario Visual (FullCalendar.js)

- **Toggle Tabla ↔ Calendario** en la barra de herramientas.
- Vista mensual, semanal y listado con FullCalendar 6.x (CDN).
- Filtrado por vehículo sincronizado con la vista de tabla.
- Eventos coloreados por estado: verde (Activa), gris (Cerrada).
- Click en evento activo abre modal de cierre directo.
- **API**: `GET /api/asignaciones.php?action=calendar&from=...&to=...&vehiculo_id=...`

### 2. Checklist Dinámico con Plantillas

- **Plantillas de checklist** configurables (tabla `checklist_plantillas`).
- Cada plantilla contiene N items ordenados con flag de obligatoriedad.
- Tipos: `entrega`, `retorno`, `ambos`.
- Plantilla por defecto "Estándar Flota" con 8 items pre-cargados.
- **Selector de plantilla** en modal de nueva asignación.
- Items dinámicos renderizados como checkboxes desde la API.
- **Retrocompatibilidad**: El checklist fijo original (gata, herramientas, etc.) se mantiene junto al dinámico.
- Las respuestas se guardan en `asignacion_checklist_respuestas` vinculadas a la asignación.

#### API Endpoints nuevos

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=calendar` | GET | Eventos para FullCalendar |
| `?action=checklist_plantillas` | GET | Listar plantillas activas |
| `?action=checklist_plantillas` | POST | Crear plantilla con items |
| `?action=checklist_plantillas` | DELETE | Desactivar plantilla |
| `?action=checklist_items` | GET | Items de una plantilla |
| `?action=checklist_respuestas` | GET | Respuestas de una asignación |
| `?action=checklist_respuestas` | POST | Guardar respuestas |

---

## Mantenimientos — Nuevas Funcionalidades

### 3. Aprobaciones Multinivel

- **Activación automática**: OTs con costo ≥ umbral N1 ($5,000 por defecto) requieren aprobación.
- **Dos niveles**: N1 para costos ≥ $5,000, N2 adicional para costos ≥ $15,000.
- **Umbrales configurables**: `system_settings` con claves `maintenance.umbral_aprobacion_n1` y `maintenance.umbral_aprobacion_n2`.
- **Bloqueo de transición**: OTs pendientes de aprobación no pueden pasar a "En proceso".
- **Roles autorizados**: Solo `coordinador_it` y `admin` pueden aprobar/rechazar.
- **Indicador visual**: Badge de estado de aprobación en tabla (aprobada/pendiente/rechazada/no requerida).
- **Botón de aprobación** directa en la fila de la tabla para OTs pendientes.
- **Contador de pendientes** en toolbar visible para coordinadores.

#### API Endpoints nuevos

| Endpoint | Método | Descripción |
|---|---|---|
| `?action=aprobaciones` | GET | Historial de aprobaciones de una OT |
| `?action=aprobaciones` | POST | Aprobar o rechazar una OT |
| `?action=pending_approvals` | GET | Listar OTs pendientes de aprobación |

### 4. Componentes en Partidas

- **Selector de componente** en modal de nueva/editar partida.
- Vinculación directa de componentes del vehículo con partidas de mantenimiento.
- Columna `component_id` en `mantenimiento_items`.
- Dropdown cargado dinámicamente con los componentes del vehículo de la OT.

---

## Migraciones de Base de Datos (install.php)

### §3.12 — Checklist con plantillas
```sql
CREATE TABLE checklist_plantillas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  tipo ENUM('entrega','retorno','ambos') DEFAULT 'ambos',
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE checklist_plantilla_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plantilla_id INT NOT NULL,
  label VARCHAR(200) NOT NULL,
  orden INT DEFAULT 0,
  requerido TINYINT(1) DEFAULT 0,
  FOREIGN KEY (plantilla_id) REFERENCES checklist_plantillas(id) ON DELETE CASCADE
);

CREATE TABLE asignacion_checklist_respuestas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  asignacion_id BIGINT NOT NULL,
  item_label VARCHAR(200) NOT NULL,
  momento ENUM('entrega','retorno') NOT NULL,
  checked TINYINT(1) DEFAULT 0,
  observacion TEXT NULL,
  FOREIGN KEY (asignacion_id) REFERENCES asignaciones(id) ON DELETE CASCADE
);
```

### §3.13 — Aprobaciones multinivel
```sql
CREATE TABLE mantenimiento_aprobaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mantenimiento_id INT NOT NULL,
  nivel INT DEFAULT 1,
  aprobador_id INT NOT NULL,
  estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  comentario TEXT NULL,
  fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id) ON DELETE CASCADE,
  FOREIGN KEY (aprobador_id) REFERENCES usuarios(id)
);
```
- Columnas `requiere_aprobacion` y `aprobacion_estado` en tabla `mantenimientos`.
- Settings: `maintenance.umbral_aprobacion_n1` ($5,000) y `maintenance.umbral_aprobacion_n2` ($15,000).

### §3.14 — Vincular componentes a partidas
- Columna `component_id INT NULL` en `mantenimiento_items`.
- Columna `plantilla_id INT NULL` en `asignaciones`.

---

## Archivos Modificados

| Archivo | Cambios |
|---|---|
| `install.php` | Secciones 3.12, 3.13, 3.14 |
| `modules/api/asignaciones.php` | +4 sub-endpoints (calendar, checklist_plantillas, checklist_items, checklist_respuestas) |
| `modules/api/mantenimientos.php` | +3 sub-endpoints (aprobaciones, pending_approvals), approval gate en PUT, auto-approval trigger en POST, component_id en items |
| `modules/web/asignaciones.php` | Toggle calendario (FullCalendar CDN), selector plantilla, checklist dinámico, guardado respuestas |
| `modules/web/mantenimientos.php` | Columna aprobación, badge estado, botón aprobar, contador pendientes, selector componente en items |

---

## Dependencias Añadidas (CDN)

- **FullCalendar 6.1.10**: `https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js`
