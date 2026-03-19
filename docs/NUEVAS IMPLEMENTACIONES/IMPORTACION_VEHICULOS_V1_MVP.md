# Importación de Vehículos V1 — MVP usable

## Objetivo de la versión

Construir la primera versión funcional de importación masiva de vehículos, manteniendo intacto el módulo manual actual.

Esta versión debe permitir:

- subir archivo `CSV`, `XLS` o `XLSX`
- seleccionar hoja si aplica
- mostrar columnas detectadas
- mapear manualmente columnas del archivo con campos del formulario de vehículos
- crear vehículos nuevos
- registrar errores por fila
- importar por lotes sin romper el sistema

Esta versión **no** debe intentar resolver toda la experiencia final de una sola vez.

---

## Alcance funcional de V1

### Entrada soportada
- `.csv` separado por comas
- `.xls`
- `.xlsx`

### Flujo base
1. El usuario sube archivo
2. El sistema lee encabezados
3. El usuario mapea columnas del archivo a campos del sistema
4. El sistema valida obligatorios
5. El sistema ejecuta importación
6. El sistema muestra resumen final

### Operación permitida
- solo **crear** nuevos vehículos

### Operación no incluida todavía
- actualizar existentes
- guardar perfiles de mapeo
- vista previa avanzada
- creación compleja de relaciones
- reportes descargables de errores

---

## Campos del sistema que deben mapearse en V1

Mapear contra los campos reales del formulario y API de vehículos actuales:

### Obligatorios
- `placa`
- `marca`
- `modelo`

### Opcionales
- `anio`
- `tipo`
- `combustible`
- `km_actual`
- `color`
- `vin`
- `estado`
- `venc_seguro`
- `notas`
- `sucursal_id`
- `costo_adquisicion`
- `aseguradora`
- `poliza_numero`

### V1 fuera de alcance
- checklist booleano
- detalles de checklist
- etiquetas
- adjuntos

---

## UI requerida

Agregar en módulo de vehículos un botón visible, por ejemplo:

- `Importar vehículos`

Ese botón debe abrir una pantalla o modal dedicado, no mezclar todo en el modal de crear/editar manual.

### Pantallas mínimas
- pantalla de carga de archivo
- pantalla de mapeo
- pantalla de ejecución / resumen

---

## Reglas de mapeo

El usuario debe poder decidir qué columna del archivo corresponde a qué campo.

### Reglas
- el orden de columnas del archivo no debe importar
- se deben poder ignorar columnas no usadas
- una columna del archivo no debe mapearse a más de un campo destino
- los campos obligatorios deben validarse antes de importar

---

## Validaciones mínimas

Por fila:

- `placa` requerida
- `marca` requerida
- `modelo` requerida
- `placa` normalizada a mayúsculas y sin espacios basura
- `anio` numérico si viene informado
- `km_actual` numérico si viene informado
- `costo_adquisicion` decimal si viene informado
- `venc_seguro` fecha válida si viene informado

### Duplicados
Si ya existe una placa, en V1 esa fila debe marcarse como error y no crear duplicado.

---

## Diseño técnico sugerido

## Archivos web
- `modules/web/importacion_vehiculos.php`
- wrapper opcional `importacion_vehiculos.php`

## API
- `modules/api/importacion_vehiculos.php`
- wrapper opcional `api/importacion_vehiculos.php`

## Servicio
- `includes/importacion_vehiculos.php`

## Migración segura
- `scripts/migrate_importacion_vehiculos.php`

---

## Tabla sugerida para auditoría básica

Crear una tabla como `import_runs` o similar para registrar:

- id
- tipo_importacion
- nombre_archivo
- usuario_id
- total_filas
- creados
- errores
- estado
- created_at

V1 puede dejar el detalle por fila en memoria si se quiere simplificar, pero es mejor dejar preparada una estructura mínima.

---

## Reglas de integración con el sistema actual

La importación debe reutilizar las mismas reglas base del módulo de vehículos:

- normalización de placa
- validaciones de datos
- auditoría
- invalidación de caché si corresponde
- registro de odómetro cuando venga `km_actual`

No duplicar reglas de negocio de forma inconsistente.

---

## Resultado esperado de V1

Una primera versión que ya permita cargar vehículos masivamente, aunque todavía no tenga actualización de existentes ni toda la experiencia completa estilo Snipe-IT.
