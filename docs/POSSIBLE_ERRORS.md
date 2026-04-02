# Posibles errores / asuntos a revisar

Este archivo lista los puntos detectados en los cambios antes de subir todo a GitHub.

## 1. Reporte de importaciones no accesible en el frontend
- Archivo: `modules/web/reportes.php`
- Descripción: el `select` de tipos de reporte no incluye la opción `importaciones`, por lo que el nuevo reporte puede no estar visible para el usuario.
- Recomendación: agregar `Importaciones` al selector y validar que `loadReport()` pueda cargarlo.

## 2. Nueva lógica de validación cruzada en importación
- Archivo: `includes/importacion_vehiculos.php`
- Descripción: se agregó la función `importacion_validar_identificadores_cruzados()` y su uso en `importacion_ejecutar()`.
- Riesgo: si existen datos inconsistentes en la base de datos, la importación puede marcar filas como error y detenerse.
- Recomendación: revisar los casos de datos heredados y asegurar que la validación no rechace entradas válidas por fallo de normalización.

## 3. Posible incompatibilidad de encabezados CSV
- Archivo: `assets/plantilla_importacion_vehiculos.csv`
- Descripción: se agregaron columnas nuevas (`No. Chasis`, `No. Motor`, `RTN`).
- Riesgo: el importador debe mapear correctamente estos nuevos campos con los nombres usados en el código.
- Recomendación: verificar que los mapeos de columna soporten los nuevos títulos y el `campo_clave` actual.

## 4. Sync OC↔OT aún presente y puede no ser necesario
- Archivos: `modules/web/sincronizacion_dashboard.php`, `sincronizacion_dashboard.php`
- Descripción: el módulo de sincronización todavía existe en el árbol de cambios.
- Riesgo: confusión, rutas innecesarias o despliegue de funcionalidad no deseada.
- Recomendación: eliminarlo si no se va a usar y dejar sólo el reporte unificado.

## 5. Reporte de exportación importaciones
- Archivo: `modules/api/reportes.php`
- Descripción: se agregó soporte para `export=importaciones`.
- Riesgo: el frontend actual podría no exponer correctamente la opción de exportación si no se selecciona el tipo de reporte correcto.
- Recomendación: comprobar la URL de exportación y la generación de archivos CSV/XLSX/PDF para importaciones.

## 6. Posible necesidad de ajustes de permiso/URL
- Archivos: `includes/layout.php`, `modules/web/reportes.php`, `importacion_reportes.php`
- Descripción: se han creado rutas y wrappers nuevos.
- Riesgo: si la configuración de nginx o la estructura de carpetas no coincide, puede generar 404/500.
- Recomendación: verificar la ruta real que usa la aplicación (no solo el código PHP).

## 7. Documentación de cambios sin pruebas finales
- Archivos: `BATCH_2_RESUMEN_IMPLEMENTACION.md`, `TESTING_FEATURES_BATCH_2.md`, `docs/FEATURES_AVANZADAS_BATCH_2.md`
- Descripción: se generaron documentos de resumen y pruebas.
- Riesgo: pueden contener información desactualizada si se hacen más cambios antes de la validación final.
- Recomendación: actualizar estos documentos después de validar el estado final del reporte y la importación.

---

## Estado de sintaxis al momento del commit
- `php -l` pasó en los archivos clave modificados.

## Siguientes pasos sugeridos
1. Probar el selector de reportes y agregar la opción `Importaciones`.
2. Ejecutar una importación de prueba para validar la nueva lógica de VIN/Chasis/Motor.
3. Eliminar el módulo `sync` si no se necesita.
4. Validar la exportación `export=importaciones` y su integración en frontend.
