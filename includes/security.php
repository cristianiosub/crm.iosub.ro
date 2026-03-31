<?php
/**
 * CyberCRM — Security Module
 * ==========================
 * CSRF protection, rate limiting, security headers,
 * input sanitization, XSS/SQLi prevention.
 */

class Security {

    /**
     * Set all security HTTP headers
     */
    public static function headers(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'");
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header_remove('X-Powered-By');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    public static function generateCSRF(): string {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time']) || (time() - $_SESSION['csrf_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string {
        $token = self::generateCSRF();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
    }

    public static function validateCSRF(): bool {
        $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($token) || empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireCSRF(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::validateCSRF()) {
                http_response_code(403);
                die('<h1>403 Forbidden</h1><p>Invalid security token. Please go back and try again.</p>');
            }
        }
    }

    public static function rateLimit(string $key, int $maxRequests = 0, int $windowSeconds = 0): bool {
        $maxRequests = $maxRequests ?: RATE_LIMIT_MAX;
        $windowSeconds = $windowSeconds ?: RATE_LIMIT_WINDOW;
        $ratefile = LOG_PATH . '/ratelimit_' . md5($key) . '.json';
        $data = [];
        if (file_exists($ratefile)) {
            $data = json_decode(file_get_contents($ratefile), true) ?: [];
        }
        $now = time();
        $data = array_filter($data, fn($t) => ($now - $t) < $windowSeconds);
        if (count($data) >= $maxRequests) return false;
        $data[] = $now;
        file_put_contents($ratefile, json_encode($data), LOCK_EX);
        return true;
    }

    public static function sanitize(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function e(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function sanitizeEmail(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function validateUpload(array $file, array $allowedTypes = [], int $maxSizeMB = 20): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) return 'Upload error: ' . $file['error'];
        if ($file['size'] > ($maxSizeMB * 1024 * 1024)) return "File exceeds {$maxSizeMB}MB limit.";
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = !empty($allowedTypes) ? $allowedTypes : ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','csv','txt','zip'];
        if (!in_array($ext, $allowed)) return "File type .$ext is not allowed.";
        if (preg_match('/\.(php|phtml|pht|php[3-8]|shtml|cgi|pl|py|asp|aspx|jsp)/i', $file['name'])) return 'Potentially dangerous file type.';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (in_array($mimeType, ['application/x-httpd-php', 'text/x-php', 'application/x-php'])) return 'Dangerous MIME type detected.';
        return null;
    }

    public static function secureFilename(string $originalName): string {
        return bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    }

    public static function getIP(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function secureSession(): void {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        session_name(SESSION_NAME);
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
        $fingerprint = md5(self::getIP() . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
            session_destroy(); session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }
    }

    public static function logEvent(string $event, string $details = ''): void {
        $line = date('Y-m-d H:i:s') . ' | ' . self::getIP() . ' | ' . $event . ' | ' . $details . "\n";
        @file_put_contents(LOG_PATH . '/security.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function checkLoginRateLimit(string $identifier): bool {
        return self::rateLimit('login_' . md5($identifier), MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_MINUTES * 60);
    }

    public static function sanitizeHTML(string $html): string {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><td><th><a><span><div><blockquote><hr>');
        $html = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript:/i', 'href="#blocked:', $html);
        $html = preg_replace('/src\s*=\s*["\']?\s*javascript:/i', 'src="#blocked:', $html);
        return $html;
    }
}
