<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeadFlow\Core\Application;
use LeadFlow\Core\Database;

$app = new Application(dirname(__DIR__));
$app->boot();
$db = $app->container()->get(Database::class);

$dir = __DIR__ . '/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);
foreach ($files as $f) {
    echo "Running " . basename($f) . " ... ";
    $sql = file_get_contents($f);
    // Split on semicolons at end of line (naive but works for these files)
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) continue;
        $db->pdo()->exec($stmt);
    }
    echo "OK\n";
}
echo "Migrations complete.\n";
