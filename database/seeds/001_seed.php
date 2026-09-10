<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use LeadFlow\Core\Application;
use LeadFlow\Core\Database;
use LeadFlow\Services\AuthService;

$app = new Application(dirname(__DIR__, 2));
$app->boot();
$db = $app->container()->get(Database::class);
$auth = $app->container()->get(AuthService::class);
$now = date('Y-m-d H:i:s');

// Super admin
if (!$db->one('SELECT id FROM users WHERE email = :e', ['e' => 'admin@leadflow.local'])) {
    $db->insert('users', [
        'email' => 'admin@leadflow.local',
        'password_hash' => $auth->hash('admin1234'),
        'name' => 'Super Admin',
        'role' => 'super_admin',
        'active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "Created admin@leadflow.local / admin1234\n";
}

// Source + campaign
if (!$db->one('SELECT id FROM sources WHERE slug = :s', ['s' => 'facebook'])) {
    $db->insert('sources', [
        'name' => 'Facebook',
        'slug' => 'facebook',
        'api_key' => 'sk_test_' . bin2hex(random_bytes(12)),
        'active' => 1,
        'created_at' => $now,
    ]);
    echo "Created source Facebook (api_key printed above)\n";
    $row = $db->one('SELECT * FROM sources WHERE slug = :s', ['s' => 'facebook']);
    echo "  api_key = {$row['api_key']}\n";
    $db->insert('campaigns', [
        'source_id' => (int)$row['id'],
        'name' => 'Payday Campaign A',
        'campaign_key' => 'payday-a',
        'active' => 1,
        'created_at' => $now,
    ]);
}

echo "Seed complete.\n";
