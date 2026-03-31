<?php
/**
 * CyberCRM — Authentication
 * Rate-limited login, secure session handling, role checking.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {
    public static function init(): void { Security::secureSession(); }

    public static function login(string $email, string $password): array {
        if (!Security::checkLoginRateLimit(Security::getIP())) {
            Security::logEvent('LOGIN_RATE_LIMITED', $email);
            return ['success' => false, 'error' => 'Prea multe încercări. Încearcă din nou în ' . LOGIN_LOCKOUT_MINUTES . ' minute.'];
        }
        if (!Security::checkLoginRateLimit($email)) {
            Security::logEvent('LOGIN_RATE_LIMITED_EMAIL', $email);
            return ['success' => false, 'error' => 'Cont blocat temporar. Încearcă din nou mai târziu.'];
        }
        $user = DB::fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Security::logEvent('LOGIN_FAILED', $email);
            if (!$user) password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            return ['success' => false, 'error' => 'Email sau parolă incorectă.'];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])], 'id = ?', [$user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['_login_time'] = time();
        $profile = DB::fetchOne("SELECT id FROM profiles ORDER BY id ASC LIMIT 1");
        if ($profile) $_SESSION['active_profile_id'] = $profile['id'];
        DB::update('users', ['last_login' => date('Y-m-d H:i:s'), 'last_ip' => Security::getIP()], 'id = ?', [$user['id']]);
        Security::logEvent('LOGIN_SUCCESS', $email);
        return ['success' => true];
    }

    public static function logout(): void {
        Security::logEvent('LOGOUT', $_SESSION['user_email'] ?? '');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool { return isset($_SESSION['user_id']); }

    public static function requireAuth(): void {
        if (!self::check()) { header('Location: ' . APP_URL . '/login'); exit; }
        if (isset($_SESSION['_login_time']) && (time() - $_SESSION['_login_time']) > SESSION_LIFETIME) {
            self::logout(); session_start();
            $_SESSION['flash'][] = ['type' => 'warning', 'message' => 'Sesiunea a expirat.'];
            header('Location: ' . APP_URL . '/login'); exit;
        }
    }

    public static function userId(): ?int { return $_SESSION['user_id'] ?? null; }
    public static function profileId(): ?int { return $_SESSION['active_profile_id'] ?? null; }
    public static function userName(): string { return $_SESSION['user_name'] ?? ''; }
    public static function userEmail(): string { return $_SESSION['user_email'] ?? ''; }

    public static function setProfile(int $id): void {
        $p = DB::fetchOne("SELECT id FROM profiles WHERE id = ?", [$id]);
        if ($p) $_SESSION['active_profile_id'] = $id;
    }

    public static function getProfile(): ?array {
        $id = self::profileId();
        return $id ? DB::fetchOne("SELECT * FROM profiles WHERE id = ?", [$id]) : null;
    }

    public static function getAllProfiles(): array {
        return DB::fetchAll("SELECT id, name, logo_path FROM profiles ORDER BY name");
    }
}
