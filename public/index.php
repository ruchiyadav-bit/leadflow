<?php
declare(strict_types=1);

use LeadFlow\Core\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application(dirname(__DIR__));
$app->boot();
$app->handleHttpRequest();
