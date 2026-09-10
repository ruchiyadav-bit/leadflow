<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeadFlow\Core\Application;
use LeadFlow\Core\Config;

$app = new Application(dirname(__DIR__));
$app->boot();

$host = (string)Config::env('DB_HOST', '(unset)');
$port = (int)Config::env('DB_PORT', 3306);
$db   = (string)Config::env('DB_DATABASE', '(unset)');
$user = (string)Config::env('DB_USERNAME', '(unset)');
$pass = (string)Config::env('DB_PASSWORD', '');
$ssl  = Config::env('DB_SSL', '(unset)');

echo "===== DB DIAGNOSTIC =====\n";
echo "host       : {$host}\n";
echo "port       : {$port}\n";
echo "database   : {$db}\n";
echo "username   : {$user} (length=" . strlen($user) . ")\n";
echo "password   : length=" . strlen($pass)
   . " first=" . (strlen($pass) ? $pass[0] : '-')
   . " last=" . (strlen($pass) ? $pass[strlen($pass) - 1] : '-') . "\n";
echo "whitespace : " . ($pass !== trim($pass) ? "YES -- password has leading/trailing whitespace!" : "no") . "\n";
echo "DB_SSL     : " . var_export($ssl, true) . "\n";

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);

$attempt = function (string $label, array $extra) use ($dsn, $user, $pass): void {
    $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 8] + $extra;
    try {
        $pdo = new \PDO($dsn, $user, $pass, $opts);
        $row = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(\PDO::FETCH_ASSOC);
        echo "{$label} : SUCCESS  (cipher=" . (($row['Value'] ?? '') ?: 'none') . ")\n";
    } catch (\Throwable $e) {
        echo "{$label} : FAILED   " . $e->getMessage() . "\n";
    }
};

$attempt('plain, no TLS ', []);
$attempt('TLS, no verify', [
    \PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
]);
echo "===== END DIAGNOSTIC =====\n";
