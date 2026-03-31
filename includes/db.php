<?php
/**
 * CyberCRM — Database (PDO Singleton)
 * All queries use prepared statements — no raw interpolation ever.
 */
require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::MYSQL_ATTR_FOUND_ROWS => true, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"]
                );
                self::$instance->exec("SET NAMES utf8mb4");
                self::$instance->exec("SET CHARACTER SET utf8mb4");
            } catch (PDOException $e) {
                error_log('DB Connection failed: ' . $e->getMessage());
                die('Database connection error.');
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(); }
    public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(); return $r ?: null; }
    public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }

    public static function insert(string $table, array $data): int {
        $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return (int)self::get()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        return self::query("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public static function count(string $table, string $where = '1=1', array $params = []): int {
        return (int)self::fetchColumn("SELECT COUNT(*) FROM `$table` WHERE $where", $params);
    }
}
