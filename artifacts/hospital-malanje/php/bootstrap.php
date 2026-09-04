<?php
declare(strict_types=1);

session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $url = getenv('DATABASE_URL');
    if (!$url) {
        throw new RuntimeException('DATABASE_URL não está configurada.');
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || empty($parts['path'])) {
        throw new RuntimeException('DATABASE_URL inválida.');
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'], '/')
    );
    $pdo = new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS php_accounts (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'patient',
            patient_id INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    SQL);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'home', array $params = []): string
{
    return '?' . http_build_query(array_merge(['page' => $page], $params));
}

function redirect_to(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Pedido expirado. Volte atrás e tente novamente.');
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function current_user(): ?array
{
    if (empty($_SESSION['account_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM php_accounts WHERE id = ?');
    $stmt->execute([$_SESSION['account_id']]);
    return $stmt->fetch() ?: null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to(url('login'));
    }
    return $user;
}

function require_staff(): array
{
    $user = require_user();
    if ($user['role'] === 'patient') {
        redirect_to(url('portal'));
    }
    return $user;
}

function age_from_date(string $birthDate): int
{
    return (int) (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable())->y;
}