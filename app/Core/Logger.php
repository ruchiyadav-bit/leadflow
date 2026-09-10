<?php
declare(strict_types=1);

namespace LeadFlow\Core;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

final class Logger
{
    private MonoLogger $log;

    public function __construct(Config $config)
    {
        $path = Config::env('LOG_PATH', 'storage/logs/app.log');
        $abs = Application::instance()->basePath($path);
        @mkdir(dirname($abs), 0777, true);
        $handler = new StreamHandler($abs, MonoLogger::INFO);
        $handler->setFormatter(new JsonFormatter());
        $this->log = new MonoLogger(Config::env('APP_NAME', 'leadflow'));
        $this->log->pushHandler($handler);
    }

    public function info(string $msg, array $ctx = []): void { $this->log->info($msg, $this->mask($ctx)); }
    public function error(string $msg, array $ctx = []): void { $this->log->error($msg, $this->mask($ctx)); }
    public function warning(string $msg, array $ctx = []): void { $this->log->warning($msg, $this->mask($ctx)); }
    public function debug(string $msg, array $ctx = []): void { $this->log->debug($msg, $this->mask($ctx)); }

    private function mask(array $ctx): array
    {
        $mask = ['ssn', 'password', 'api_key', 'authorization', 'email', 'phone', 'date_of_birth'];
        array_walk_recursive($ctx, function (&$v, $k) use ($mask) {
            if (in_array(strtolower((string)$k), $mask, true) && is_string($v) && $v !== '') {
                $v = substr($v, 0, 2) . '***';
            }
        });
        return $ctx;
    }
}
