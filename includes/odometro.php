<?php

require_once __DIR__ . '/db.php';

function odometro_ultimo_km(PDO $db, int $vehiculoId): float {
    $stmtVeh = $db->prepare("SELECT COALESCE(km_actual,0) FROM vehiculos WHERE id=? LIMIT 1");
    $stmtVeh->execute([$vehiculoId]);
    $kmVeh = (float)$stmtVeh->fetchColumn();

    $stmtLog = $db->prepare("SELECT COALESCE(MAX(reading_km),0) FROM odometer_logs WHERE vehicle_id=?");
    $stmtLog->execute([$vehiculoId]);
    $kmLog = (float)$stmtLog->fetchColumn();

    return max($kmVeh, $kmLog);
}

function odometro_validar_km(PDO $db, int $vehiculoId, ?float $kmNuevo, bool $allowOverride, ?string $justificacion): void {
    if ($kmNuevo === null || $kmNuevo <= 0) {
        return;
    }

    $ultimo = odometro_ultimo_km($db, $vehiculoId);
    if ($kmNuevo < $ultimo) {
        if (!$allowOverride || !$justificacion) {
            throw new RuntimeException("El odómetro no puede disminuir. Último registrado: {$ultimo} km.");
        }
    }
}

function odometro_registrar(PDO $db, int $vehiculoId, float $km, string $source, ?int $userId = null): void {
    if ($km <= 0) {
        return;
    }

    $stmt = $db->prepare("INSERT INTO odometer_logs (vehicle_id, reading_km, source, recorded_at, user_id) VALUES (?,?,?,NOW(),?)");
    $stmt->execute([$vehiculoId, $km, $source, $userId]);

    $db->prepare("UPDATE vehiculos SET km_actual = GREATEST(km_actual, ?) WHERE id=?")->execute([$km, $vehiculoId]);
}
