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

    public static function createStaff(string $name, string $email, string $password, string $role, ?int $doctorId = null): int
    {
        if (!in_array($role, ['admin', 'doctor', 'receptionist'], true)) {
            throw new InvalidArgumentException('Papel inválido.');
        }
        $stmt = Database::connection()->prepare('INSERT INTO php_accounts (name, email, password_hash, role, doctor_id, active) VALUES (?, ?, ?, ?, ?, TRUE) RETURNING id');
        $stmt->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role, $role === 'doctor' ? ($doctorId ?: null) : null]);
        return (int) $stmt->fetchColumn();
    }

    public static function linkPatient(int $accountId, int $patientId): void
    {
        $stmt = Database::connection()->prepare('UPDATE php_accounts SET patient_id = ? WHERE id = ?');
        $stmt->execute([$patientId, $accountId]);
    }

    public static function roleCan(string $role, string $permission): bool
    {
        $permissions = [
            'patient' => ['portal'],
            'receptionist' => ['dashboard', 'appointments', 'patients', 'messages'],
            'doctor' => ['dashboard', 'appointments', 'patients', 'messages', 'clinical'],
            'admin' => ['dashboard', 'appointments', 'patients', 'messages', 'clinical', 'reports', 'settings', 'users'],
        ];
        return in_array($permission, $permissions[$role] ?? [], true);
    }

    public static function allStaff(): array
    {
        return Database::connection()->query("SELECT id, name, email, role, doctor_id, active, created_at FROM php_accounts WHERE role <> 'patient' ORDER BY name")->fetchAll();
    }

    public static function updateStaff(int $id, string $role, bool $active, ?int $doctorId = null): void
    {
        $allowed = ['admin', 'doctor', 'receptionist'];
        if (!in_array($role, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE php_accounts SET role = ?, doctor_id = ?, active = ? WHERE id = ? AND role <> ?');
        $stmt->execute([$role, $role === 'doctor' ? ($doctorId ?: null) : null, $active, $id, 'patient']);
    }
}