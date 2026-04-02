#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# Script Rápido: Aplicar Migraciones de Importación
# ═══════════════════════════════════════════════════════════════
#
# Uso:
#   chmod +x apply-import-migration.sh
#   ./apply-import-migration.sh
#
# o via Docker:
#   docker exec flotacontrol-app bash /var/www/html/apply-import-migration.sh

set -e

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  🚀 MIGRACIÓN RÁPIDA: Importación de Vehículos/Operadores     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Encontrar el directorio raíz
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

# Verificar que existe el archivo de migración
if [ ! -f "$PROJECT_ROOT/tests/migrate_importacion_tables.php" ]; then
    echo "❌ ERROR: No se encontró script de migración"
    echo "   Esperado en: $PROJECT_ROOT/tests/migrate_importacion_tables.php"
    exit 1
fi

# Ejecutar migración
echo "⏳ Ejecutando migración de tablas de importación..."
echo ""

php "$PROJECT_ROOT/tests/migrate_importacion_tables.php"

RESULT=$?

echo ""
if [ $RESULT -eq 0 ]; then
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║ ✅ MIGRACIÓN COMPLETADA EXITOSAMENTE                          ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Ahora puedes:"
    echo "  1. Ir a: http://tu-servidor/importacion_vehiculos.php"
    echo "  2. Ir a: http://tu-servidor/importacion_operadores.php"
    echo "  3. Subir archivos y hacer importaciones"
    echo ""
    exit 0
else
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║ ❌ ERROR EN LA MIGRACIÓN                                       ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Revisa los errores arriba. Opciones:"
    echo "  • Verifica la conexión a BD"
    echo "  • Verifica permisos del usuario de BD"
    echo "  • Habilita APP_DEBUG=true en .env para más detalles"
    echo ""
    exit 1
fi
