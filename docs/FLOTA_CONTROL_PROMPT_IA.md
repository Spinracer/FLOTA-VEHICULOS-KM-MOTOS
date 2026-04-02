# SISTEMA FLOTA CONTROL -- PROMPT MAESTRO PARA IA

Este documento contiene instrucciones DIRECTAS para la IA encargada de
evolucionar el sistema de gestión de flota.

La IA debe ejecutar estas tareas de manera estructurada, modular y
escalable. El humano responsable revisará y eliminará funcionalidades no
necesarias.

------------------------------------------------------------------------

# 1. OBJETIVO GENERAL

Modernizar, escalar y profesionalizar el sistema FLOTA CONTROL
implementando:

-   Arquitectura robusta basada en framework moderno
-   Frontend moderno con Tailwind CSS
-   Módulo de telemetría preparado (sin integrar proveedores aún)
-   Sistema de notificaciones centralizado
-   Mejoras en rendimiento, seguridad y escalabilidad
-   Diseño completamente responsive y profesional

------------------------------------------------------------------------

# 2. ARQUITECTURA OBLIGATORIA

La IA debe:

1.  Migrar gradualmente el backend a Laravel.
2.  Mantener compatibilidad temporal con la base de datos actual.
3.  Separar claramente:
    -   Controladores
    -   Servicios
    -   Repositorios
    -   DTOs
    -   Jobs (colas)
4.  Implementar estructura limpia tipo Clean Architecture.
5.  Preparar el sistema para API REST segura con JWT.

No romper módulos existentes sin migración progresiva.

------------------------------------------------------------------------

# 3. FRONTEND OBLIGATORIO

La IA debe:

1.  Implementar Tailwind CSS como framework principal.
2.  Configurar tema oscuro por defecto con posibilidad de tema claro.
3.  Crear sistema de componentes reutilizables:
    -   Botones
    -   Tablas
    -   Modales
    -   Badges
    -   Cards KPI
    -   Alertas
4.  Garantizar diseño responsive desde móvil hasta desktop 4K.
5.  Optimizar tablas grandes con paginación virtual si es necesario.

Evitar CSS personalizado innecesario.

------------------------------------------------------------------------

# 4. MÓDULOS A MEJORAR

La IA debe mejorar sin duplicar funcionalidades existentes.

## VEHÍCULOS

-   Agregar clasificación por etiquetas.
-   Implementar cálculo automático de costo por kilómetro.
-   Agregar historial visual de kilometraje (gráfica).
-   Preparar estructura para telemetría futura.

## ASIGNACIONES

-   Implementar calendario visual.
-   Evitar conflictos de reserva.
-   Mejorar checklist con plantillas dinámicas.

## MANTENIMIENTOS

-   Generar OT automática desde preventivos.
-   Sistema de aprobación multinivel.
-   Control de repuestos más detallado.

## COMBUSTIBLE

-   Mejorar algoritmo de detección de anomalías.
-   Implementar gráfico comparativo por período.
-   Crear indicador de eficiencia por vehículo.

## INCIDENTES

-   Adjuntar múltiples archivos.
-   Flujo de seguimiento por estados.
-   Dashboard de seguridad.

## RECORDATORIOS

-   Convertir en sistema de alertas centralizado.
-   Permitir reglas automáticas configurables.

## OPERADORES

-   Historial de capacitaciones.
-   Registro de infracciones.
-   KPI de desempeño.

## COMPONENTES

-   Convertir en inventario real con movimientos.
-   Alertas de vencimiento automático.

## PROVEEDORES

-   Sistema de evaluación de desempeño.
-   Registro de contratos.

## SUCURSALES

-   Dashboard comparativo entre sucursales.
-   Indicadores financieros por sede.

------------------------------------------------------------------------

# 5. NUEVOS MÓDULOS A IMPLEMENTAR

## CENTRO DE ALERTAS

Unificar: - Recordatorios - Mantenimientos - Incidentes - Combustible
anómalo

Debe permitir: - Prioridad - Estado - Responsable - Historial



## API EXTERNA

Crear endpoints REST seguros para: - Vehículos - Asignaciones -
Mantenimientos - Combustible - Incidentes

## DASHBOARD EJECUTIVO

Implementar panel con: - KPI globales - Gráficas comparativas - Filtros
dinámicos

------------------------------------------------------------------------

# 6. SEGURIDAD

La IA debe:

-   Implementar 2FA opcional.
-   Protección CSRF.
-   Rate limiting en API.
-   Logs extendidos en auditoría.
-   Encriptación de tokens.

------------------------------------------------------------------------

# 7. RENDIMIENTO

-   Indexar correctamente base de datos.
-   Implementar caché (Redis).
-   Utilizar colas para procesos pesados.
-   Optimizar consultas con eager loading.

------------------------------------------------------------------------

# 8. REGLAS PARA LA IA

1.  Leer el código antes de implementar.
2.  No duplicar funcionalidades existentes.
3.  Implementar por etapas modulares.
4.  Documentar cada módulo nuevo.
5.  Mantener coherencia visual con Tailwind.
6.  Priorizar escalabilidad y mantenibilidad.
7.  Generar código limpio y estructurado.
8.  No implementar todo de golpe; dividir en fases.

------------------------------------------------------------------------

# 9. FASES RECOMENDADAS

FASE 1: - Migración base a Laravel - Implementación Tailwind - Centro de
alertas

FASE 2: - Dashboard ejecutivo - Mejora módulos existentes

FASE 3: - API externa - 

FASE 4: - Optimización avanzada - Seguridad avanzada

------------------------------------------------------------------------

FIN DEL PROMPT MAESTRO.
