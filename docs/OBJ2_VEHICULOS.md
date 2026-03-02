# Objetivo 2 — Mejoras del Módulo Vehículos (v3.2.0)

## Resumen

Mejoras integrales al módulo de Vehículos enfocadas en cuatro pilares:

1. **Clasificación por etiquetas** — Etiquetado libre para categorizar vehículos
2. **Cálculo automático de costo por kilómetro** — KPI financiero en el Perfil 360
3. **Historial visual de kilometraje** — Gráfica Chart.js en el Perfil 360
4. **Estructura para telemetría futura** — Tabla y placeholder UI listos

---

## 1. Clasificación por Etiquetas

### Base de datos

**Tabla `vehiculo_etiquetas`** (nueva):

| Columna       | Tipo          | Descripción                           |
| ------------- | ------------- | ------------------------------------- |
| `id`          | INT PK AUTO   | Identificador                         |
| `vehiculo_id` | INT FK        | Vehículo al que pertenece             |
| `etiqueta`    | VARCHAR(60)   | Texto de la etiqueta                  |
| `created_at`  | DATETIME      | Fecha de creación                     |
| —             | UNIQUE        | `(vehiculo_id, etiqueta)` sin duplicados |
| —             | INDEX         | `(etiqueta)` para filtrado rápido     |

### API Endpoints

| Método   | URL                                          | Descripción             |
| -------- | -------------------------------------------- | ----------------------- |
| `GET`    | `/api/vehiculos.php?action=tags&id={id}`     | Listar etiquetas        |
| `POST`   | `/api/vehiculos.php?action=add_tag`          | Agregar etiqueta        |
| `DELETE`  | `/api/vehiculos.php?action=remove_tag&id={tagId}` | Eliminar etiqueta |

**POST body:**
```json
{ "vehiculo_id": 5, "etiqueta": "Ruta norte" }
```

### UI

- **Tabla de listado**: Columna "Etiquetas" con badges de colores
- **Filtro por etiqueta**: `<select>` en toolbar que filtra la lista
- **Modal CRUD**: Sección dedicada con pills, input + botón para agregar, × para eliminar
- **Perfil 360**: Etiquetas visibles bajo el encabezado

### Color de etiquetas

Algoritmo hash simple sobre el string para asignar colores determinísticos del palette:
`['#e8ff47','#47ffe8','#ff6b6b','#a29bfe','#ffa502','#2ed573','#1e90ff','#fd79a8']`

---

## 2. Cálculo de Costo por Kilómetro

### Fórmula

$$
\text{Costo/km} = \frac{\text{Gasto mantenimiento} + \text{Gasto combustible}}{\text{KM actual}}
$$

Si `km_actual = 0`, el costo/km se muestra como `$0.00`.

### Implementación

- **API**: Calculado en el endpoint `?action=profile` dentro de `totales`
  - `costo_por_km`: Resultado del cálculo redondeado a 2 decimales
  - `gasto_total`: Suma de mantenimiento + combustible
- **UI**: KPI destacado con borde accent en la grilla de 5 columnas del Perfil 360

### Campos adicionales del vehículo

Se agregaron 3 columnas a `vehiculos`:

| Columna              | Tipo           | Descripción          |
| -------------------- | -------------- | -------------------- |
| `costo_adquisicion`  | DECIMAL(12,2)  | Precio de compra     |
| `aseguradora`        | VARCHAR(120)   | Nombre de aseguradora |
| `poliza_numero`      | VARCHAR(80)    | No. de póliza        |

Estas se incluyen en el modal CRUD y se muestran en el Perfil 360 cuando existen.

---

## 3. Historial Visual de Kilometraje

### Chart.js

- CDN: `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js`
- Cargado solo en la página de vehículos (no global)

### Datos

- **API**: `historial_odometro` en el perfil — últimos 30 registros de `odometer_logs`
- **Ordenamiento**: ASC por `recorded_at` para mostrar progresión temporal
- **Campos**: `reading_km`, `source`, `recorded_at`

### Gráfica

| Propiedad       | Valor                          |
| --------------- | ------------------------------ |
| Tipo            | Line chart con fill            |
| Color línea     | `#e8ff47` (accent)             |
| Fill            | `rgba(232,255,71,0.1)`         |
| Tension         | 0.3 (curva suave)              |
| Eje X           | Fechas (dd MMM)                |
| Eje Y           | km formateados con locale      |
| Fondo           | `var(--surface2)` con border-radius |

Se muestra solo si hay más de 1 registro de odómetro.

---

## 4. Estructura para Telemetría

### Base de datos

**Tabla `telemetria_logs`** (nueva):

| Columna       | Tipo           | Descripción                    |
| ------------- | -------------- | ------------------------------ |
| `id`          | BIGINT PK AUTO | Identificador                  |
| `vehiculo_id` | INT FK         | Vehículo asociado              |
| `tipo`        | VARCHAR(50)    | Tipo de lectura (velocidad, rpm, temp_motor, etc.) |
| `valor`       | VARCHAR(255)   | Valor de la lectura            |
| `unidad`      | VARCHAR(20)    | Unidad de medida               |
| `latitud`     | DECIMAL(10,7)  | Coordenada GPS                 |
| `longitud`    | DECIMAL(10,7)  | Coordenada GPS                 |
| `fuente`      | VARCHAR(50)    | Origen: manual, gps, obd, api  |
| `recorded_at` | DATETIME       | Timestamp del dato             |
| —             | INDEX          | `(vehiculo_id, tipo)`          |
| —             | INDEX          | `(recorded_at)`                |

### API

- Se incluyen los últimos 20 registros en el Perfil 360 bajo `telemetria`
- Query protegida con try/catch para compatibilidad si la tabla no existe aún

### UI

- Sección "📡 Telemetría" en el Perfil 360
- Si hay datos: tabla con tipo, valor, unidad, fecha
- Si no hay datos: placeholder informativo indicando futura integración GPS/OBD

---

## Archivos Modificados

| Archivo                        | Cambios                                           |
| ------------------------------ | ------------------------------------------------- |
| `install.php`                  | §3.9 vehiculo_etiquetas, §3.10 telemetria_logs, §3.11 cols extra |
| `modules/api/vehiculos.php`    | Tags CRUD, costo/km, historial_odometro, telemetria, cols extra |
| `modules/web/vehiculos.php`    | Filtro tags, columna tags, tag manager, Chart.js, costo/km KPI, telemetría placeholder, campos extra |
| `docs/OBJ2_VEHICULOS.md`      | Este archivo                                      |
| `docs/CHANGELOG.md`           | Entrada v3.2.0                                    |
| `README.md`                   | Actualización de features                         |

---

## Consideraciones Técnicas

- **Backwards compatible**: Las queries a `vehiculo_etiquetas` y `telemetria_logs` usan try/catch para no romper si las tablas no existen
- **Migración segura**: `install.php` usa `$existsColumn()` y `IF NOT EXISTS` para idempotencia
- **Performance**: Etiquetas se cargan en batch con `IN()` para el listado, no N+1
- **Chart.js**: Se destruye y recrea en cada apertura de perfil para evitar memory leaks
- **Etiquetas**: Restricción UNIQUE previene duplicados a nivel DB con `INSERT IGNORE`
