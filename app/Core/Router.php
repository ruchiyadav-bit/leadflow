<?php
declare(strict_types=1);

namespace LeadFlow\Core;

final class Router
{
    /** @var array<int, array{method:string,pattern:string,regex:string,params:array,handler:mixed,middleware:array}> */
    private array $routes = [];
    private array $groupStack = [];

    public function __construct(private Container $container) {}

    public function get(string $pattern, mixed $handler, array $mw = []): void { $this->add('GET', $pattern, $handler, $mw); }
    public function post(string $pattern, mixed $handler, array $mw = []): void { $this->add('POST', $pattern, $handler, $mw); }
    public function put(string $pattern, mixed $handler, array $mw = []): void { $this->add('PUT', $pattern, $handler, $mw); }
    public function delete(string $pattern, mixed $handler, array $mw = []): void { $this->add('DELETE', $pattern, $handler, $mw); }

    public function group(array $attrs, callable $fn): void
    {
        $this->groupStack[] = $attrs;
        $fn($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $pattern, mixed $handler, array $mw): void
    {
        $prefix = '';
        $groupMw = [];
        foreach ($this->groupStack as $g) {
            if (!empty($g['prefix'])) $prefix .= '/' . trim($g['prefix'], '/');
            if (!empty($g['middleware'])) $groupMw = array_merge($groupMw, (array)$g['middleware']);
        }
        $full = rtrim($prefix, '/') . '/' . ltrim($pattern, '/');
        $full = '/' . trim($full, '/');
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $full);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $full,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => array_merge($groupMw, $mw),
        ];
    }

    public function dispatch(string $method, string $uri): Response
    {
        $uri = '/' . trim($uri, '/');
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            if (preg_match($r['regex'], $uri, $matches)) {
                array_shift($matches);
                $args = array_combine($r['params'], $matches) ?: [];
                $request = Request::capture()->withRouteParams($args);
                return $this->runWithMiddleware($request, $r['middleware'], function (Request $req) use ($r) {
                    return $this->callHandler($r['handler'], $req);
                });
            }
        }
        return Response::json(['error' => 'not_found'], 404);
    }

    private function runWithMiddleware(Request $req, array $middleware, callable $terminal): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function (callable $next, string $mw) {
                return function (Request $req) use ($mw, $next) {
                    $instance = $this->container->get($mw);
                    return $instance->handle($req, $next);
                };
            },
            $terminal
        );
        return $pipeline($req);
    }

    private function callHandler(mixed $handler, Request $req): Response
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $controller = $this->container->get($handler[0]);
            $method = $handler[1];
            return $controller->{$method}($req);
        }
        if (is_callable($handler)) {
            return $handler($req);
        }
        throw new \RuntimeException('Invalid handler');
    }
}
