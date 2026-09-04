<?php
declare(strict_types=1);

final class Account
{
    public static function ensureSchema(): void
    {
        Database::connection()->exec(<<<'SQL'
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
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM php_accounts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM php_accounts WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO php_accounts (name, email, password_hash) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $stmt->fetchColumn();
    }

    public static function linkPatient(int $accountId, int $patientId): void
    {
        $stmt = Database::connection()->prepare('UPDATE php_accounts SET patient_id = ? WHERE id = ?');
        $stmt->execute([$patientId, $accountId]);
    }
}