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
        Database::connection()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGSERIAL PRIMARY KEY,
                account_id BIGINT NOT NULL REFERENCES php_accounts(id) ON DELETE CASCADE,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TIMESTAMPTZ NOT NULL,
                used_at TIMESTAMPTZ,
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

    public static function createPasswordReset(string $email): ?array
    {
        $account = self::findByEmail($email);
        if (!$account || !$account['active']) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare('INSERT INTO password_reset_tokens (account_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL \'60 minutes\')');
        $stmt->execute([(int) $account['id'], hash('sha256', $token)]);
        return ['account' => $account, 'token' => $token];
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT id, account_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();
        if (!$reset) {
            return false;
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE php_accounts SET password_hash = ? WHERE id = ? AND active = TRUE');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['account_id']]);
            $consume = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
            $consume->execute([(int) $reset['id']]);
            $db->commit();
            return $update->rowCount() === 1;
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function changePassword(int $accountId, string $currentPassword, string $newPassword): bool
    {
        $account = self::find($accountId);
        if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE php_accounts SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $accountId]);
        return $stmt->rowCount() === 1;
    }
}