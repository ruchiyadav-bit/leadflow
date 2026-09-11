<?php
declare(strict_types=1);

namespace LeadFlow\Core;

use Dotenv\Dotenv;

final class Application
{
    private static ?Application $instance = null;
    private Container $container;
    private Router $router;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        self::$instance = $this;
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/') : '');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function boot(): void
    {
        if (file_exists($this->basePath('.env'))) {
            Dotenv::createImmutable($this->basePath)->safeLoad();
        }
        date_default_timezone_set('UTC');

        $this->container = new Container();
        $this->registerBindings();

        $this->router = new Router($this->container);
        $this->loadRoutes();
    }

    private function registerBindings(): void
    {
        $c = $this->container;
        $c->singleton(Config::class, fn() => new Config($this->basePath('config')));
        $c->singleton(Database::class, fn(Container $c) => new Database($c->get(Config::class)));
        $c->singleton(RedisClient::class, fn(Container $c) => new RedisClient($c->get(Config::class)));
        $c->singleton(Logger::class, fn(Container $c) => new Logger($c->get(Config::class)));
        $c->singleton(\LeadFlow\Services\AuthService::class, fn(Container $c) => new \LeadFlow\Services\AuthService($c->get(Database::class), $c->get(Config::class)));
        $c->singleton(\LeadFlow\Repositories\LeadRepository::class, fn(Container $c) => new \LeadFlow\Repositories\LeadRepository($c->get(Database::class)));
        $c->singleton(\LeadFlow\Repositories\BuyerRepository::class, fn(Container $c) => new \LeadFlow\Repositories\BuyerRepository($c->get(Database::class)));
        $c->singleton(\LeadFlow\Repositories\PingRepository::class, fn(Container $c) => new \LeadFlow\Repositories\PingRepository($c->get(Database::class)));
        $c->singleton(\LeadFlow\Services\BuyerEligibilityService::class, fn(Container $c) => new \LeadFlow\Services\BuyerEligibilityService($c->get(\LeadFlow\Repositories\BuyerRepository::class)));
        $c->singleton(\LeadFlow\Services\FieldMappingService::class, fn() => new \LeadFlow\Services\FieldMappingService());
        $c->singleton(\LeadFlow\Services\ResponseParserService::class, fn() => new \LeadFlow\Services\ResponseParserService());
        $c->singleton(\LeadFlow\PingEngine\PingEngine::class, fn(Container $c) => new \LeadFlow\PingEngine\PingEngine(
            $c->get(\LeadFlow\Services\FieldMappingService::class),
            $c->get(\LeadFlow\Services\ResponseParserService::class),
            $c->get(\LeadFlow\Repositories\PingRepository::class),
            $c->get(Logger::class)
        ));
        $c->singleton(\LeadFlow\PingEngine\AuctionEngine::class, fn(Container $c) => new \LeadFlow\PingEngine\AuctionEngine($c->get(Config::class)));
        $c->singleton(\LeadFlow\PostEngine\PostEngine::class, fn(Container $c) => new \LeadFlow\PostEngine\PostEngine(
            $c->get(\LeadFlow\Services\FieldMappingService::class),
            $c->get(\LeadFlow\Services\ResponseParserService::class),
            $c->get(\LeadFlow\Repositories\PingRepository::class),
            $c->get(RedisClient::class),
            $c->get(Logger::class)
        ));
        $c->singleton(\LeadFlow\Services\LeadOrchestrator::class, fn(Container $c) => new \LeadFlow\Services\LeadOrchestrator(
            $c->get(\LeadFlow\Repositories\LeadRepository::class),
            $c->get(\LeadFlow\Services\BuyerEligibilityService::class),
            $c->get(\LeadFlow\PingEngine\PingEngine::class),
            $c->get(\LeadFlow\PingEngine\AuctionEngine::class),
            $c->get(\LeadFlow\PostEngine\PostEngine::class),
            $c->get(Logger::class)
        ));
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require $this->basePath('routes/api.php');
        require $this->basePath('routes/web.php');
    }

    public function handleHttpRequest(): void
    {
        // CORS headers — allow lander pages from any origin
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, x-api-key, Authorization, Origin, X-Requested-With, Accept');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight OPTIONS request
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            $response = $this->router->dispatch(
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
            );
            $response->send();
        } catch (\Throwable $e) {
            /** @var Logger $log */
            $log = $this->container->get(Logger::class);
            $log->error('Unhandled exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            http_response_code(500);
            header('Content-Type: application/json');
            $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            echo json_encode([
                'error' => 'server_error',
                'message' => $debug ? $e->getMessage() : 'Internal server error',
            ]);
        }
    }
}
