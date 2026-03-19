# Importación de Vehículos V4 — Plantilla, experiencia tipo Snipe-IT y mejor UX

## Objetivo de la versión

Acercar la experiencia al estilo Snipe-IT de forma profesional, clara y repetible, sin romper la arquitectura actual del proyecto.

---

## Referencia funcional que se busca replicar

Tomar como inspiración estas ideas de Snipe-IT:

- el usuario sube archivo y el sistema detecta columnas
- luego mapea cada columna a un campo del sistema
- el orden de columnas no importa
- puede ignorar columnas que no quiere usar
- puede actualizar existentes usando una llave de búsqueda
- si una relación no existe, en ciertos casos se crea automáticamente
- el proceso devuelve resumen claro de lo ocurrido

---

## Funciones nuevas de V4

### 1. Plantilla descargable
Agregar botón:

- `Descargar plantilla`

Con una plantilla base que incluya encabezados recomendados, por ejemplo:

```text
placa,marca,modelo,anio,tipo,combustible,km_actual,color,vin,estado,operador,sucursal,venc_seguro,costo_adquisicion,aseguradora,poliza_numero,notas
```

---

### 2. Perfiles de mapeo guardables
Permitir guardar configuraciones de mapeo para reusarlas luego.

Ejemplo:
- `Plantilla KM Motos - vehículos`
- `Importación flota sucursales`
- `Importación motos usadas`

Tabla sugerida:
- `import_mapping_profiles`

Campos sugeridos:
- id
- nombre
- tipo_importacion
- mapping_json
- created_by
- created_at

---

### 3. Pantalla de resumen final más útil
Mostrar:

- creados
- actualizados
- omitidos
- errores
- operadores creados
- filas con advertencias
- tiempo total
- enlace a detalle del run

---

### 4. Historial de importaciones
Crear una pantalla para consultar importaciones pasadas.

Debe permitir ver:
- fecha
- archivo
- usuario
- modo crear/actualizar
- conteos
- detalle por fila

---

### 5. Archivo de errores descargable
Permitir descargar CSV con:

- fila
- placa
- error
- motivo

Así el usuario corrige solo las filas fallidas.

---

## Política de compatibilidad

Esta versión no debe reemplazar ni alterar el flujo manual de creación/edición de vehículos.

Debe ser un módulo adicional bien encapsulado.

---

## UX recomendada

## Paso 1
Subir archivo

## Paso 2
Elegir hoja si aplica

## Paso 3
Mapear columnas

## Paso 4
Configurar opciones
- actualizar existentes
- ignorar vacíos al actualizar
- crear operadores faltantes por nombre

## Paso 5
Vista previa

## Paso 6
Ejecutar importación

## Paso 7
Ver resumen y descargar errores

---

## Resultado esperado de V4

Un módulo ya cómodo para uso repetido en operación real, con experiencia profesional y muy cercana a la parte útil del importador de Snipe-IT.
