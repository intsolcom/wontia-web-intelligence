<?php
namespace App\Core;

class Request
{
    private array $query;
    private array $body;
    private ?array $json = null;
    private string $method;
    private string $uri;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public static function capture(): self
    {
        return new self();
    }

    public function get(string $key, $default = null): mixed
    {
        return isset($this->query[$key]) ? $this->sanitize($this->query[$key]) : $default;
    }

    public function post(string $key, $default = null): mixed
    {
        return isset($this->body[$key]) ? $this->sanitize($this->body[$key]) : $default;
    }

    public function input(string $key, $default = null): mixed
    {
        $json = $this->json();
        if (isset($json[$key])) return $json[$key];
        return $this->post($key, $default) ?: $this->get($key, $default);
    }

    public function json(): array
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input');
            $this->json = json_decode($raw, true) ?? [];
        }
        return $this->json;
    }

    public function method(): string
    {
        if ($this->method === 'POST') {
            $override = $this->post('_method') ?? $this->header('X-HTTP-Method-Override');
            if ($override) return strtoupper($override);
        }
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body, $this->json());
    }

    private function sanitize($value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        return is_string($value) ? trim($value) : $value;
    }
}
