# Importación de Vehículos V3 — Vista previa, validación y actualización de existentes

## Objetivo de la versión

Agregar el comportamiento más parecido a lo que gustó de Snipe-IT:

- mapeo manual de columnas
- validación antes de importar
- opción para actualizar existentes
- si no existe, se crea
- si existe y la opción está marcada, se actualiza

---

## Función principal de V3

Agregar una casilla visible como:

- `Actualizar vehículos existentes si la placa ya existe`

### Comportamiento
- si la casilla está desmarcada:
  - la fila con placa existente se reporta como duplicada
  - no se actualiza
- si la casilla está marcada:
  - la fila intenta encontrar vehículo existente por `placa`
  - si existe, se actualiza
  - si no existe, se crea

La `placa` es la llave principal recomendada para lookup.

---

## Vista previa obligatoria

Antes de ejecutar la importación real, mostrar una vista previa con:

- total de filas detectadas
- filas válidas
- filas con error
- filas que crearán registro nuevo
- filas que actualizarán registro existente
- resumen de advertencias

### Vista por fila
Debe mostrar al menos:
- número de fila
- placa
- acción prevista: crear / actualizar / error
- mensaje de validación

---

## Política de actualización

Cuando una fila actualiza un vehículo existente:

- solo deben actualizarse los campos que el usuario haya mapeado
- no deben vaciarse campos que no fueron mapeados
- si una columna del archivo viene vacía y fue mapeada, debe definirse política clara:
  - opción A: ignorar vacío
  - opción B: sobrescribir con `NULL`

### Recomendación para V3
Usar por defecto:

- **si el campo viene vacío, no sobrescribir**

Eso es más seguro para producción.

---

## Reglas específicas para operador en actualización

Si se actualiza un vehículo existente y se mapeó operador:

- si el operador del archivo existe, usarlo
- si no existe, crearlo solo con nombre
- si el valor viene vacío, no borrar operador actual por defecto

---

## Validaciones mejoradas

En la vista previa, detectar:

- placa vacía
- marca vacía en creación
- modelo vacío en creación
- año inválido
- fecha inválida
- sucursal no encontrada
- estado no reconocido
- operador ambiguo
- columna mapeada dos veces
- archivo vacío
- encabezados duplicados

---

## Resultado de importación

Al terminar, mostrar resumen:

- total procesadas
- creadas
- actualizadas
- omitidas
- con error
- operadores creados automáticamente
- duración
- usuario ejecutor

---

## Persistencia recomendada

Agregar tablas como:

### `import_runs`
- id
- tipo
- filename
- update_existing
- total_rows
- created_count
- updated_count
- skipped_count
- error_count
- user_id
- created_at

### `import_run_rows`
- id
- run_id
- row_number
- lookup_value
- action
- status
- message
- payload_json
- created_at

---

## Resultado esperado de V3

Una importación ya realmente útil en operación diaria: el usuario puede subir archivo, mapear columnas, previsualizar el resultado y decidir si desea actualizar vehículos existentes o solo crear nuevos.
