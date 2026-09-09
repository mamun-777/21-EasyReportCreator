<?php
declare(strict_types=1);

/**
 * Simple company accounts for multi-tenant logo / header / template standards.
 */
final class ErcAuth
{
    private static ?PDO $pdo = null;

    public static function db(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        erc_ensure_dirs();
        $path = ERC_DATA . '/auth.sqlite';
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                display_name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
            )'
        );
        self::$pdo = $pdo;
        return $pdo;
    }

    /** @return array{id:int,email:string,display_name:string,company_id:int,company_name:string}|null */
    public static function currentUser(): ?array
    {
        $userId = (int) ($_SESSION['erc_user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }
        $stmt = self::db()->prepare(
            'SELECT u.id, u.email, u.display_name, u.company_id, c.name AS company_name
             FROM users u JOIN companies c ON c.id = u.company_id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            unset($_SESSION['erc_user_id'], $_SESSION['erc_company_id']);
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
            'company_id' => (int) $row['company_id'],
            'company_name' => (string) $row['company_name'],
        ];
    }

    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) {
            erc_error('Please sign in to continue.', 401);
        }
        return $user;
    }

    /**
     * @return array{user: array{id:int,email:string,display_name:string,company_id:int,company_name:string}}
     */
    public static function register(string $companyName, string $email, string $password, string $displayName): array
    {
        $companyName = trim($companyName);
        $email = strtolower(trim($email));
        $displayName = trim($displayName);
        if ($companyName === '' || strlen($companyName) < 2) {
            throw new InvalidArgumentException('Company name is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        if ($displayName === '') {
            $displayName = explode('@', $email)[0];
        }

        $pdo = self::db();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }

        $now = gmdate('c');
        $pdo->beginTransaction();
        try {
            $insCo = $pdo->prepare('INSERT INTO companies (name, created_at) VALUES (?, ?)');
            $insCo->execute([$companyName, $now]);
            $companyId = (int) $pdo->lastInsertId();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insUser = $pdo->prepare(
                'INSERT INTO users (company_id, email, password_hash, display_name, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            $insUser->execute([$companyId, $email, $hash, $displayName, $now]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::loginSession($userId, $companyId);
        erc_company_ensure_dirs($companyId);
        return ['user' => self::currentUser()];
    }

    /**
     * @return array{user: array{id:int,email:string,display_name:string,company_id:int,company_name:string}}
     */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $stmt = self::db()->prepare(
            'SELECT u.id, u.password_hash, u.company_id FROM users u WHERE u.email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            throw new InvalidArgumentException('Invalid email or password.');
        }
        self::loginSession((int) $row['id'], (int) $row['company_id']);
        erc_company_ensure_dirs((int) $row['company_id']);
        return ['user' => self::currentUser()];
    }

    public static function logout(): void
    {
        if (function_exists('erc_purge_session_uploads')) {
            erc_purge_session_uploads();
        }
        unset(
            $_SESSION['erc_user_id'],
            $_SESSION['erc_company_id'],
            $_SESSION['erc_working'],
            $_SESSION['erc_dcf'],
            $_SESSION['erc_uploaded_name']
        );
    }

    private static function loginSession(int $userId, int $companyId): void
    {
        session_regenerate_id(true);
        $_SESSION['erc_user_id'] = $userId;
        $_SESSION['erc_company_id'] = $companyId;
        unset($_SESSION['erc_working']);
    }
}
