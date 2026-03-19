# Importación de Vehículos V2 — Operadores y relaciones mínimas

## Objetivo de la versión

Extender la V1 para resolver relaciones importantes sin volver frágil la importación.

Esta versión se enfoca en:

- operador
- sucursal
- estado del vehículo
- tipo
- combustible

La prioridad es que el archivo pueda traer información más útil, pero con reglas seguras y predecibles.

---

## Cambio clave de esta versión

## Operadores por nombre con creación automática mínima

Si el archivo trae una columna mapeada a operador y el operador no existe, el sistema debe crearlo automáticamente **solo con el nombre**.

### Regla obligatoria
Crear nuevo operador con:

- `nombre` = valor del archivo
- `estado` = valor por defecto del sistema o `Activo` si aplica
- todos los demás campos vacíos o `NULL`

### Regla obligatoria adicional
No inventar ni autocompletar:

- licencia
- categoría de licencia
- teléfono
- email
- notas personales
- sucursal
- documento de identidad

### Auditoría recomendada
Registrar en notas o auditoría que fue:

- `creado automáticamente por importación de vehículos`

---

## Lógica de resolución de operador

### Si viene operador vacío
- dejar `operador_id = NULL`

### Si viene operador con texto
1. normalizar nombre
2. buscar coincidencia exacta o normalizada
3. si existe uno, usarlo
4. si no existe, crearlo solo con nombre
5. usar el `id` resultante

### Regla de ambigüedad
Si hay múltiples coincidencias razonables, preferir coincidencia exacta normalizada.
Si no puede resolverse de forma segura, marcar error por fila y no asignar operador arbitrario.

---

## Sucursales

### Regla sugerida
Permitir mapear por:
- nombre de sucursal
- o ID, si el archivo ya lo trae así

### Comportamiento
- si se encuentra coincidencia, usar `sucursal_id`
- si no se encuentra:
  - en V2 no crear sucursales automáticamente
  - marcar advertencia o error según la estrategia elegida

**Recomendación:** en V2 no autocrear sucursales para evitar sucursales mal escritas.

---

## Estados del vehículo

Aceptar texto del archivo y resolver contra el catálogo actual.

### Ejemplos aceptables
- Activo
- En mantenimiento
- Fuera de servicio

### Comportamiento
- comparar normalizado
- si no coincide, usar error por fila o valor por defecto configurable

**Recomendación:** si viene columna mapeada y el valor no existe, marcar error.  
No adivinar estados.

---

## Tipo y combustible

Resolver de forma similar con listas conocidas del sistema actual.

### Tipo
- Automóvil
- Camioneta
- Camión
- Motocicleta
- Furgoneta
- Maquinaria
- Otro

### Combustible
- Gasolina
- Diésel
- Gas LP
- Eléctrico
- Híbrido

Permitir normalización básica, por ejemplo:
- diesel -> Diésel
- electrico -> Eléctrico

---

## Mejoras técnicas de V2

Agregar helpers reutilizables:

- `resolver_operador_por_nombre()`
- `resolver_sucursal()`
- `resolver_estado_vehiculo()`
- `resolver_tipo_vehiculo()`
- `resolver_combustible_vehiculo()`

---

## Reglas de seguridad

- no crear operadores duplicados por diferencias triviales de mayúsculas o espacios
- no crear sucursales automáticamente
- no inventar valores faltantes
- seguir permitiendo importar aunque algunas columnas de relación no se usen

---

## Resultado esperado de V2

La importación ya no solo crea vehículos, sino que también puede asociarlos correctamente a relaciones importantes, con una política segura para operadores: si no existe, se crea solo con nombre.
