#!/usr/bin/env php
<?php
/**
 * CLI para Importación Batch de Vehículos
 * 
 * Uso:
 *   php cli/importacion_batch.php --archivo=ruta/archivo.csv [--usuario=ID] [--actualizar] [--campo-clave=vin]
 * 
 * Ejemplos:
 *   php cli/importacion_batch.php --archivo=vehiculos.csv
 *   php cli/importacion_batch.php --archivo=vehiculos.csv --usuario=1 --actualizar --campo-clave=vin
 *   php cli/importacion_batch.php --archivo=vehiculos.xlsx --usuario=1
 * 
 * Opciones:
 *   --archivo=RUTA          Ruta al archivo CSV o XLSX (obligatorio)
 *   --usuario=ID            ID del usuario ejecutando la importación (default: 1 - Admin)
 *   --actualizar            Actualizar vehículos existentes (flag, default: crear solo)
 *   --campo-clave=CAMPO     Campo para detectar duplicados: placa|vin|numero_chasis|numero_motor (default: placa)
 *   --verbose               Mostrar detalles del progreso
 *   --dry-run               Simular sin insertar (solo validación)
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/importacion_vehiculos.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

// Colores para output
class ConsoleColors {
    public const RESET = "\033[0m";
    public const RED = "\033[91m";
    public const GREEN = "\033[92m";
    public const YELLOW = "\033[93m";
    public const BLUE = "\033[94m";
    public const CYAN = "\033[96m";
}

function println($msg, $color = '') {
    echo $color . $msg . ConsoleColors::RESET . "\n";
}

function parse_options() {
    global $argv;
    $options = [];
    
    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            if (strpos($arg, '=') !== false) {
                [$key, $value] = explode('=', substr($arg, 2), 2);
                $options[$key] = $value;
            } else {
                $options[substr($arg, 2)] = true;
            }
        }
    }
    
    return $options;
}

function show_help() {
    global $argv;
    echo "
╔════════════════════════════════════════════════════════════════╗
║ FLOTA-VEHICULOS: CLI para Importación Batch                   ║
╚════════════════════════════════════════════════════════════════╝

USO:
  php {$argv[0]} --archivo=RUTA [OPCIONES]

OPCIONES:
  --archivo=RUTA              Ruta al archivo CSV o XLSX (obligatorio)
  --usuario=ID                ID del usuario (default: 1)
  --actualizar                Flag: actualizar existentes (default: crear)
  --campo-clave=CAMPO         placa|vin|numero_chasis|numero_motor (default: placa)
  --verbose                   Mostrar detalles del progreso
  --dry-run                   Solo validación, sin insertar
  --help                      Mostrar esta ayuda

EJEMPLOS:
  # Importar nuevos vehículos desde CSV
  php {$argv[0]} --archivo=vehiculos.csv

  # Actualizar vehículos existentes por VIN
  php {$argv[0]} --archivo=vehiculos.csv --actualizar --campo-clave=vin --usuario=1

  # Simular sin insertar
  php {$argv[0]} --archivo=vehiculos.xlsx --verbose --dry-run

\n";
}

// ─────────────────────────────────────────────────────────────
// MAIN
// ─────────────────────────────────────────────────────────────

$options = parse_options();

if (isset($options['help'])) {
    show_help();
    exit(0);
}

if (!isset($options['archivo'])) {
    println("ERROR: Parámetro --archivo es obligatorio", ConsoleColors::RED);
    show_help();
    exit(1);
}

$archivo = $options['archivo'];
$usuario_id = (int)($options['usuario'] ?? 1);
$actualizar = isset($options['actualizar']);
$campo_clave = $options['campo-clave'] ?? 'placa';
$verbose = isset($options['verbose']);
$dry_run = isset($options['dry-run']);

// Validar archivo
if (!file_exists($archivo)) {
    println("ERROR: Archivo no encontrado: $archivo", ConsoleColors::RED);
    exit(1);
}

$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'xlsx'])) {
    println("ERROR: Formato no soportado. Usa CSV o XLSX", ConsoleColors::RED);
    exit(1);
}

println("┌─────────────────────────────────────────────────────────────┐", ConsoleColors::BLUE);
println("│ Importación Batch de Vehículos v1.0                        │", ConsoleColors::BLUE);
println("└─────────────────────────────────────────────────────────────┘", ConsoleColors::BLUE);

// Información del proceso
println("\n📋 Configuración:", ConsoleColors::CYAN);
println("  • Archivo: $archivo");
println("  • Usuario ID: $usuario_id");
println("  • Modo: " . ($actualizar ? "ACTUALIZAR" : "CREAR"));
if ($actualizar) println("  • Campo clave: $campo_clave");
if ($dry_run) println("  • ⚠️  DRY RUN (no se insertarán datos)");
println("");

try {
    $db = getDB();
    
    // Verificar usuario
    $usuario_stmt = $db->prepare("SELECT nombre FROM usuarios WHERE id = ?");
    $usuario_stmt->execute([$usuario_id]);
    $usuario = $usuario_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        println("ERROR: Usuario ID $usuario_id no existe", ConsoleColors::RED);
        exit(1);
    }
    
    println("✓ Usuario: " . $usuario['nombre'], ConsoleColors::GREEN);
    
    // Leer archivo
    $verbose && println("\n⏳ Leyendo archivo...", ConsoleColors::YELLOW);
    
    if ($ext === 'csv') {
        $fh = fopen($archivo, 'r');
        $headers = fgetcsv($fh);
        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);
    } else {
        [$headers, $rows] = xlsx_read($archivo);
    }
    
    $verbose && println("✓ Archivo leído: " . count($rows) . " filas", ConsoleColors::GREEN);
    
    // Auto-detectar mapping
    $mapping = [];
    $campos_destino = array_keys(importacion_campos_destino());
    
    foreach ($headers as $idx => $header) {
        $header_lower = strtolower(trim($header));
        
        // Intentar matching exacto
        foreach ($campos_destino as $campo) {
            if (strtolower($campo) === $header_lower || 
                str_replace(['_', ' '], '', strtolower($campo)) === str_replace(['_', ' '], '', $header_lower)) {
                $mapping[$idx] = $campo;
                break;
            }
        }
        
        if (!isset($mapping[$idx])) {
            $mapping[$idx] = null; // Sin mapping
        }
    }
    
    $verbose && println("\n🔗 Mapping de columnas:", ConsoleColors::YELLOW);
    foreach ($headers as $idx => $header) {
        $mapped_to = $mapping[$idx] ?? '(ignorado)';
        $verbose && println("  [$idx] '$header' → $mapped_to");
    }
    
    // Ejecutar importación (con transacción si no es dry-run)
    $verbose && println("\n⏳ Ejecutando importación...", ConsoleColors::YELLOW);
    
    if ($dry_run) {
        $db->beginTransaction();
    }
    
    $resultado = importacion_ejecutar($rows, $headers, $mapping, $usuario_id, basename($archivo), $actualizar, $campo_clave);
    
    // Resultados
    println("\n" . str_repeat("─", 60), ConsoleColors::CYAN);
    println("📊 RESULTADOS:", ConsoleColors::CYAN);
    println(str_repeat("─", 60), ConsoleColors::CYAN);
    println("Total filas:          " . $resultado['total']);
    println("Creados:              " . $resultado['creados'] . " " . ConsoleColors::GREEN . "✓" . ConsoleColors::RESET);
    println("Actualizados:         " . $resultado['actualizados'] . " " . ($resultado['actualizados'] > 0 ? ConsoleColors::BLUE . "◆" : "") . ConsoleColors::RESET);
    println("Errores:              " . $resultado['errores'] . " " . ($resultado['errores'] > 0 ? ConsoleColors::RED . "✗" : ConsoleColors::GREEN . "✓") . ConsoleColors::RESET);
    println(str_repeat("─", 60), ConsoleColors::CYAN);
    
    // Detalles de errores
    if ($resultado['errores'] > 0 && $verbose) {
        println("\n⚠️  ERRORES ENCONTRADOS:", ConsoleColors::YELLOW);
        foreach ($resultado['detalle'] as $err) {
            if ($err['tipo'] === 'validacion' || $err['tipo'] === 'validacion_identificadores' || 
                $err['tipo'] === 'duplicado_bd' || $err['tipo'] === 'error_bd') {
                println("  Fila {$err['fila']} ({$err['placa']}): " . implode("; ", $err['errores']), ConsoleColors::RED);
            }
        }
    }
    
    // Confirmar o rollback
    if ($dry_run) {
        println("\n⚠️  Modo DRY RUN: Cambios NO guardados", ConsoleColors::YELLOW);
        $db->rollBack();
        println("Usa --dry-run para ver este resultado sin --dry-run para guardar", ConsoleColors::YELLOW);
    } else {
        println("\n✓ Importación completada exitosamente", ConsoleColors::GREEN);
    }
    
    // Resumen final
    $success_rate = $resultado['total'] > 0 ? round(($resultado['total'] - $resultado['errores']) / $resultado['total'] * 100, 1) : 0;
    println("\n📈 Tasa de éxito: $success_rate%", 
        $success_rate >= 90 ? ConsoleColors::GREEN : 
        ($success_rate >= 70 ? ConsoleColors::YELLOW : ConsoleColors::RED));
    
    println("\n✓ Proceso completado\n", ConsoleColors::GREEN);
    exit(0);

} catch (Exception $e) {
    println("\n❌ ERROR: " . $e->getMessage(), ConsoleColors::RED);
    println($e->getTraceAsString(), ConsoleColors::RED);
    exit(1);
}
