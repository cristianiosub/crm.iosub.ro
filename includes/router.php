<?php
class Router {
    public static function parse(): array {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = parse_url(APP_URL, PHP_URL_PATH) ?: '';
        if ($basePath && str_starts_with($uri, $basePath)) $uri = substr($uri, strlen($basePath));
        $uri = strtok(trim($uri, '/'), '?');
        $segments = $uri ? explode('/', $uri) : ['dashboard'];
        return array_map(fn($s) => preg_replace('/[^a-zA-Z0-9_-]/', '', $s), $segments);
    }

    public static function resolve(): array {
        $segments = self::parse();
        $page = $segments[0] ?? 'dashboard';
        $action = $segments[1] ?? 'list';
        $id = isset($segments[2]) ? (int)$segments[2] : 0;
        if (isset($segments[1]) && is_numeric($segments[1])) {
            $id = (int)$segments[1];
            $action = $segments[2] ?? 'view';
        }
        return ['page' => $page, 'action' => $action, 'id' => $id];
    }

    public static function url(string $path = '', array $query = []): string {
        $url = APP_URL . '/' . ltrim($path, '/');
        if (!empty($query)) $url .= '?' . http_build_query($query);
        return $url;
    }

    public static function redirect(string $path = '', array $query = []): void {
        header('Location: ' . self::url($path, $query)); exit;
    }

    public static function getValidPages(): array {
        return ['login','dashboard','clients','projects','offers','documents','campaigns','email-log','correspondence','profiles','ai-assistant','api'];
    }
}
