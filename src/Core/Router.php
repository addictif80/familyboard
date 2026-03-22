<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove base URL prefix
        $base = BASE_URL;
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') $uri = '/';

        $routes = $this->routes[$method] ?? [];

        // Exact match
        if (isset($routes[$uri])) {
            $this->call($routes[$uri], []);
            return;
        }

        // Pattern match
        foreach ($routes as $pattern => $handler) {
            $regex = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $pattern);
            $regex = '@^' . $regex . '$@';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($handler, $params);
                return;
            }
        }

        http_response_code(404);
        require BASE_PATH . '/templates/404.php';
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method($params);
        } else {
            $handler($params);
        }
    }
}
