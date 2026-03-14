<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

// Clear corrupted transparent firma data for all assignments
$s = $db->prepare("UPDATE asignaciones SET firma_data = NULL, firma_tipo = 'ninguna', firma_fecha = NULL, firma_ip = NULL WHERE firma_data IS NOT NULL");
$s->execute();
echo "Cleared " . $s->rowCount() . " corrupted firma(s)\n";

// Verify
$s = $db->prepare("SELECT id, firma_tipo, firma_data IS NOT NULL as has_data, firma_token FROM asignaciones ORDER BY id DESC LIMIT 5");
$s->execute();
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID={$r['id']} tipo={$r['firma_tipo']} has_data={$r['has_data']} token=" . ($r['firma_token'] ? 'yes' : 'no') . "\n";
}
echo "DONE - Users can now re-sign via their existing links\n";
