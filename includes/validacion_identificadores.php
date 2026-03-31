<?php
/**
 * Validación Cruzada de Identificadores de Vehículos
 * VIN, Chasis, Motor - detección de inconsistencias
 */

/**
 * Valida consistencia cruzada de identificadores
 * @param array $data Datos del vehículo (vin, numero_chasis, numero_motor)
 * @param PDO $db Conexión a base de datos
 * @return array ['valid' => bool, 'errors' => array]
 */
function validar_identificadores_vehiculo(array $data, $db): array {
    $errors = [];
    
    $vin = isset($data['vin']) ? strtoupper(trim($data['vin'])) : '';
    $chasis = isset($data['numero_chasis']) ? strtoupper(trim($data['numero_chasis'])) : '';
    $motor = isset($data['numero_motor']) ? strtoupper(trim($data['numero_motor'])) : '';
    
    // Si no hay identificadores, validación pasa
    if (!$vin && !$chasis && !$motor) {
        return ['valid' => true, 'errors' => []];
    }
    
    // Validar formatos
    if ($vin && strlen($vin) < 10) {
        $errors[] = "VIN debe tener al menos 10 caracteres";
    }
    if ($chasis && strlen($chasis) < 5) {
        $errors[] = "Chasis debe tener al menos 5 caracteres";
    }
    if ($motor && strlen($motor) < 3) {
        $errors[] = "Motor debe tener al menos 3 caracteres";
    }
    
    // Si hay errores de formato, retornar
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Búsqueda cruzada en BD
    if ($vin) {
        try {
            $stmt = $db->prepare("SELECT numero_chasis, numero_motor FROM vehiculos WHERE UPPER(vin) = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$vin]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($found) {
                if ($found['numero_chasis'] && $chasis && $found['numero_chasis'] !== $chasis) {
                    $errors[] = "VIN ya existe con Chasis: {$found['numero_chasis']}";
                }
                if ($found['numero_motor'] && $motor && $found['numero_motor'] !== $motor) {
                    $errors[] = "VIN ya existe con Motor: {$found['numero_motor']}";
                }
            }
        } catch (Exception $e) {
            // Ignorar errores de BD, permitir continuar
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
