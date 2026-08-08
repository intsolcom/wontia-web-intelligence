<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previous = $this->prefix;
        $previousMw = $this->groupMiddleware;
        $this->prefix = $previous . $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->prefix = $previous;
        $this->groupMiddleware = $previousMw;
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable|array $handler): void
    {
        $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, callable|array $handler): void
    {
        $pattern = $this->prefix . $pattern;
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
            'regex' => $this->patternToRegex($pattern),
            'paramNames' => $this->extractParamNames($pattern),
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim($uri, '/') ?: '/';
        $uri = $uri === '' ? '/' : $uri;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches);
                $params = [];
                foreach ($route['paramNames'] as $i => $name) {
                    $params[$name] = $matches[$i] ?? null;
                }
                $request = Request::capture();
                foreach ($route['middleware'] as $mw) {
                    if (is_string($mw) && class_exists($mw)) {
                        (new $mw())->handle($request);
                    } elseif (is_callable($mw)) {
                        $mw($request);
                    }
                }
                $handler = $route['handler'];
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    if (is_string($class) && class_exists($class)) {
                        (new $class())->{$action}($request, ...$params);
                    }
                } elseif (is_callable($handler)) {
                    $handler($request, ...$params);
                }
                return;
            }
        }
        http_response_code(404);
        Response::error('Not Found', 404);
    }

    private function patternToRegex(string $pattern): string
    {
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function extractParamNames(string $pattern): array
    {
        preg_match_all('/\{([a-zA-Z_]+)\}/', $pattern, $m);
        return $m[1] ?? [];
    }
}
