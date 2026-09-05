<?php
declare(strict_types=1);

final class Account
{
    public static function ensureSchema(): void
    {
        if (Database::driver() === 'mysql') {
            Database::connection()->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS contas (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(190) NOT NULL,
                    email VARCHAR(190) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role VARCHAR(30) NOT NULL DEFAULT 'patient',
                    patient_id INT NULL,
                    doctor_id INT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    phone VARCHAR(40) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
            Database::connection()->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS tokens_recuperacao (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    account_id BIGINT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_tokens_contas FOREIGN KEY (account_id) REFERENCES contas(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
            return;
        }

        Database::connection()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS contas (
                id BIGSERIAL PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'patient',
                patient_id INTEGER,
                doctor_id INTEGER,
                active BOOLEAN NOT NULL DEFAULT TRUE,
                phone TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        Database::connection()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tokens_recuperacao (
                id BIGSERIAL PRIMARY KEY,
                account_id BIGINT NOT NULL REFERENCES contas(id) ON DELETE CASCADE,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TIMESTAMPTZ NOT NULL,
                used_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM contas WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM contas WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO contas (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT)]);
        return Database::driver() === 'mysql'
            ? (int) Database::connection()->lastInsertId()
            : (int) Database::connection()->query('SELECT LASTVAL()')->fetchColumn();
    }

    public static function createStaff(string $name, string $email, string $password, string $role, ?int $doctorId = null): int
    {
        if (!in_array($role, ['admin', 'doctor', 'receptionist'], true)) {
            throw new InvalidArgumentException('Papel inválido.');
        }
        $stmt = Database::connection()->prepare('INSERT INTO contas (name, email, password_hash, role, doctor_id, active) VALUES (?, ?, ?, ?, ?, ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ')');
        $stmt->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role, $role === 'doctor' ? ($doctorId ?: null) : null]);
        return Database::driver() === 'mysql'
            ? (int) Database::connection()->lastInsertId()
            : (int) Database::connection()->query('SELECT LASTVAL()')->fetchColumn();
    }

    public static function linkPatient(int $accountId, int $patientId): void
    {
        $stmt = Database::connection()->prepare('UPDATE contas SET patient_id = ? WHERE id = ?');
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
        return Database::connection()->query("SELECT id, name, email, role, doctor_id, active, created_at FROM contas WHERE role <> 'patient' ORDER BY name")->fetchAll();
    }

    public static function updateStaff(int $id, string $role, bool $active, ?int $doctorId = null): void
    {
        $allowed = ['admin', 'doctor', 'receptionist'];
        if (!in_array($role, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE contas SET role = ?, doctor_id = ?, active = ? WHERE id = ? AND role <> ?');
        $stmt->execute([$role, $role === 'doctor' ? ($doctorId ?: null) : null, $active, $id, 'patient']);
    }

    public static function createPasswordReset(string $email): ?array
    {
        $account = self::findByEmail($email);
        if (!$account || !$account['active']) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $expires = Database::driver() === 'mysql'
            ? 'DATE_ADD(NOW(), INTERVAL 60 MINUTE)'
            : "NOW() + INTERVAL '60 minutes'";
        $stmt = Database::connection()->prepare("INSERT INTO tokens_recuperacao (account_id, token_hash, expires_at) VALUES (?, ?, $expires)");
        $stmt->execute([(int) $account['id'], hash('sha256', $token)]);
        return ['account' => $account, 'token' => $token];
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT id, account_id FROM tokens_recuperacao WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();
        if (!$reset) {
            return false;
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE contas SET password_hash = ? WHERE id = ? AND active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE'));
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['account_id']]);
            $consume = $db->prepare('UPDATE tokens_recuperacao SET used_at = NOW() WHERE id = ?');
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
        $stmt = Database::connection()->prepare('UPDATE contas SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $accountId]);
        return $stmt->rowCount() === 1;
    }
}