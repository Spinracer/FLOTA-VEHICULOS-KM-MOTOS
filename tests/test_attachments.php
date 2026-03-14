<?php
/**
 * Test attachment upload system - simulates file upload directly
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uploadBase = __DIR__ . '/../uploads';
echo "=== UPLOAD SYSTEM DIAGNOSTICS ===\n";
echo "Upload dir: {$uploadBase}\n";
echo "Exists: " . (file_exists($uploadBase) ? 'YES' : 'NO') . "\n";
echo "Writable: " . (is_writable($uploadBase) ? 'YES' : 'NO') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";

// Create a temp test image
$tmpFile = tempnam(sys_get_temp_dir(), 'att_test_');
$img = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($img, 255, 0, 0);
imagefill($img, 0, 0, $red);
imagejpeg($img, $tmpFile, 90);
imagedestroy($img);
echo "\nTest file: {$tmpFile} (" . filesize($tmpFile) . " bytes)\n";

// Include the attachment upload function
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/attachments.php';

// Simulate $_FILES
$_FILES['archivo'] = [
    'name'     => ['test_photo.jpg'],
    'type'     => ['image/jpeg'],
    'tmp_name' => [$tmpFile],
    'error'    => [UPLOAD_ERR_OK],
    'size'     => [filesize($tmpFile)],
];

echo "\n=== TESTING attachment_upload('vehiculos', 999) ===\n";
try {
    $result = attachment_upload('vehiculos', 999);
    echo "SUCCESS! Result:\n";
    print_r($result);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Check DB
$db = getDB();
echo "\n=== DB CHECK ===\n";
$stmt = $db->query("SELECT * FROM attachments ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total attachments: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} entidad={$r['entidad']} file={$r['filename']}\n";
    $path = $uploadBase . '/' . $r['filename'];
    echo "    disk: " . (file_exists($path) ? 'EXISTS ('.filesize($path).'b)' : 'MISSING') . "\n";
}

// Check upload subdirs
echo "\n=== UPLOADS DIR TREE ===\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadBase, RecursiveDirectoryIterator::SKIP_DOTS));
$found = 0;
foreach ($it as $file) {
    echo "  " . $file->getPathname() . " (" . $file->getSize() . " bytes)\n";
    $found++;
}
if ($found === 0) echo "  (empty)\n";

// Cleanup test record
$db->exec("DELETE FROM attachments WHERE entidad='vehiculos' AND entidad_id=999");
echo "\nCleaned up test records.\n";

unlink($tmpFile);
echo "DONE\n";
