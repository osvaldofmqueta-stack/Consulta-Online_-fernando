<?php
/**
 * Dados demográficos, processo e classificação clínica dos pacientes.
 *
 * O campo patient_type é informação clínica administrativa e não é uma
 * permissão de acesso; as permissões continuam em Account::roleCan().
 */
declare(strict_types=1);

final class Patient
{
    private static bool $schemaReady = false;
    public const TYPES = ['general', 'child', 'pregnant', 'chronic'];

    /** Traduz o valor interno da classificação para texto da interface. */
    public static function typeLabel(string $type): string
    {
        return [
            'general' => 'Geral',
            'child' => 'Criança',
            'pregnant' => 'Gestante',
            'chronic' => 'Doença crónica',
        ][$type] ?? 'Geral';
    }

    /** Aplica as pequenas migrações necessárias a instalações antigas. */
    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        if (Database::driver() === 'mysql') {
            try {
                Database::connection()->exec('ALTER TABLE pacientes ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1');
            } catch (PDOException) {
                // A coluna já existe em instalações novas ou já migradas.
            }
            try {
                Database::connection()->exec("ALTER TABLE pacientes ADD COLUMN patient_type VARCHAR(30) NOT NULL DEFAULT 'general'");
            } catch (PDOException) {
                // A coluna já existe em instalações novas ou já migradas.
            }
            self::$schemaReady = true;
            return;
        }
        Database::connection()->exec('ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS active BOOLEAN NOT NULL DEFAULT TRUE');
        Database::connection()->exec("ALTER TABLE pacientes ADD COLUMN IF NOT EXISTS patient_type TEXT NOT NULL DEFAULT 'general'");
        self::$schemaReady = true;
    }

    /** Procura um paciente pelo identificador. */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pacientes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Encontra um paciente activo para ligação segura da conta pública. */
    public static function findByRecordAndPhone(string $recordNumber, string $phone): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pacientes WHERE UPPER(medical_record_number) = UPPER(?) AND phone = ? AND active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE'));
        $stmt->execute([trim($recordNumber), trim($phone)]);
        return $stmt->fetch() ?: null;
    }

    /** Mantém uma API curta para listar todos os pacientes autorizados. */
    public static function all(): array
    {
        return self::forStaff();
    }

    /** Lista pacientes que têm pelo menos uma consulta com o médico. */
    public static function forDoctor(int $doctorId): array
    {
        return self::forStaff([], $doctorId);
    }

    /** Lista pacientes com pesquisa, estado e isolamento opcional por médico. */
    public static function forStaff(array $filters = [], ?int $doctorScope = null): array
    {
        $where = [];
        $params = [];
        if ($doctorScope !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM consultas access_appointment WHERE access_appointment.patient_id = p.id AND access_appointment.doctor_id = ?)';
            $params[] = $doctorScope;
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(p.name LIKE ? OR p.medical_record_number LIKE ? OR p.phone LIKE ?)';
            array_push($params, $term, $term, $term);
        }
        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'p.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE');
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = 'p.active = ' . (Database::driver() === 'mysql' ? '0' : 'FALSE');
        }

        $sql = <<<'SQL'
            SELECT p.*,
                (SELECT a.date FROM consultas a WHERE a.patient_id = p.id AND a.status NOT IN ('completed', 'cancelled') AND a.date >= CURRENT_DATE ORDER BY a.date ASC, a.time ASC LIMIT 1) AS next_appointment_date,
                (SELECT a.time FROM consultas a WHERE a.patient_id = p.id AND a.status NOT IN ('completed', 'cancelled') AND a.date >= CURRENT_DATE ORDER BY a.date ASC, a.time ASC LIMIT 1) AS next_appointment_time,
                (SELECT doc.name FROM consultas a JOIN medicos doc ON doc.id = a.doctor_id WHERE a.patient_id = p.id AND a.status NOT IN ('completed', 'cancelled') AND a.date >= CURRENT_DATE ORDER BY a.date ASC, a.time ASC LIMIT 1) AS next_doctor_name,
                (SELECT a.date FROM consultas a WHERE a.patient_id = p.id ORDER BY a.date DESC, a.time DESC LIMIT 1) AS last_appointment_date
            FROM pacientes p
        SQL;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.active DESC, p.name ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Detecta duplicados pelos dados demográficos principais. */
    public static function findSimilar(string $name, string $birthDate, string $phone): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pacientes WHERE LOWER(name) = LOWER(?) AND birth_date = ? AND phone = ? LIMIT 1');
        $stmt->execute([trim($name), $birthDate, trim($phone)]);
        return $stmt->fetch() ?: null;
    }

    /** Cria o paciente e atribui um número de processo legível. */
    public static function create(string $name, string $sex, string $birthDate, string $phone, string $neighborhood, string $patientType = 'general'): int
    {
        $db = Database::connection();
        $temporaryRecord = 'PENDING-' . bin2hex(random_bytes(8));
        $stmt = $db->prepare('INSERT INTO pacientes (medical_record_number, name, sex, birth_date, phone, neighborhood, patient_type, active) VALUES (?, ?, ?, ?, ?, ?, ?, ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ')');
        $stmt->execute([$temporaryRecord, trim($name), trim($sex), $birthDate, trim($phone), $neighborhood !== '' ? trim($neighborhood) : null, in_array($patientType, self::TYPES, true) ? $patientType : 'general']);
        $id = Database::driver() === 'mysql' ? (int) $db->lastInsertId() : (int) $db->query('SELECT LASTVAL()')->fetchColumn();
        $record = 'HM-' . (new DateTimeImmutable())->format('Y') . '-' . str_pad((string) ($id + 1000), 5, '0', STR_PAD_LEFT);
        $update = $db->prepare('UPDATE pacientes SET medical_record_number = ? WHERE id = ?');
        $update->execute([$record, $id]);
        return $id;
    }

    /** Actualiza os dados demográficos e a classificação clínica permitida. */
    public static function update(int $patientId, string $name, string $sex, string $birthDate, string $phone, string $neighborhood, string $patientType = 'general'): bool
    {
        $stmt = Database::connection()->prepare('UPDATE pacientes SET name = ?, sex = ?, birth_date = ?, phone = ?, neighborhood = ?, patient_type = ? WHERE id = ?');
        $stmt->execute([trim($name), trim($sex), $birthDate, trim($phone), $neighborhood !== '' ? trim($neighborhood) : null, in_array($patientType, self::TYPES, true) ? $patientType : 'general', $patientId]);
        return $stmt->rowCount() > 0;
    }

    /** Desactiva sem apagar o histórico clínico ou as consultas. */
    public static function setActive(int $patientId, bool $active): bool
    {
        $stmt = Database::connection()->prepare('UPDATE pacientes SET active = ? WHERE id = ?');
        $stmt->execute([$active, $patientId]);
        return $stmt->rowCount() > 0;
    }

    /** Permite ao paciente actualizar apenas contactos do próprio perfil. */
    public static function updateContact(int $patientId, string $phone, string $neighborhood): void
    {
        $stmt = Database::connection()->prepare('UPDATE pacientes SET phone = ?, neighborhood = ? WHERE id = ?');
        $stmt->execute([$phone, $neighborhood !== '' ? $neighborhood : null, $patientId]);
    }
}