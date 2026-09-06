<?php
/**
 * Consultas, disponibilidade e regras de associação entre pacientes e médicos.
 *
 * Todas as consultas de equipa podem receber filtros; quando existe doctorScope
 * o SQL limita os resultados ao médico autenticado.
 */
declare(strict_types=1);

final class Appointment
{
    public const STATUSES = ['pending', 'scheduled', 'confirmed', 'waiting', 'in_progress', 'completed', 'cancelled'];

    /** Gera intervalos de 30 minutos dentro do horário configurado. */
    public static function availableTimes(): array
    {
        $hours = '08:00-17:00';
        $stmt = Database::connection()->query("SELECT setting_value FROM definicoes_hospital WHERE setting_key = 'working_hours' LIMIT 1");
        $configured = $stmt->fetchColumn();
        if (is_string($configured) && preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $configured, $match)) {
            $hours = $configured;
        }
        [$start, $end] = explode('-', $hours);
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));
        $startMinutes = $startHour * 60 + $startMinute;
        $endMinutes = $endHour * 60 + $endMinute;
        $times = [];
        for ($minutes = $startMinutes; $minutes < $endMinutes; $minutes += 30) {
            $times[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        return $times;
    }

    /** Lista departamentos activos que podem receber marcações. */
    public static function activeDepartments(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM departamentos WHERE active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' ORDER BY name')
            ->fetchAll();
    }

    /** Lista médicos activos pertencentes a departamentos activos. */
    public static function activeDoctors(): array
    {
        return Database::connection()
            ->query('SELECT doc.id, doc.name, doc.specialty, doc.department_id, d.name AS department_name FROM medicos doc JOIN departamentos d ON d.id = doc.department_id WHERE doc.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' AND d.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' ORDER BY d.name, doc.name')
            ->fetchAll();
    }

    /** Confirma que um médico e o respectivo departamento estão activos. */
    public static function activeDoctorExists(int $doctorId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM medicos doc JOIN departamentos d ON d.id = doc.department_id WHERE doc.id = ? AND doc.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' AND d.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE'));
        $stmt->execute([$doctorId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Impede a combinação de um médico com um departamento diferente. */
    public static function doctorBelongsToDepartment(int $doctorId, int $departmentId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM medicos doc JOIN departamentos d ON d.id = doc.department_id WHERE doc.id = ? AND doc.department_id = ? AND doc.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' AND d.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE'));
        $stmt->execute([$doctorId, $departmentId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Verifica conflitos simultâneos do médico ou do paciente no mesmo horário. */
    public static function slotIsAvailable(int $doctorId, string $date, string $time, int $patientId): bool
    {
        $stmt = Database::connection()->prepare('
            SELECT COUNT(*)
            FROM consultas
            WHERE date = ?
              AND time = ?
              AND status <> ?
              AND (doctor_id = ? OR patient_id = ?)
        ');
        $stmt->execute([$date, $time, 'cancelled', $doctorId, $patientId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    /** Cria uma consulta com estado pending ou scheduled conforme o fluxo. */
    public static function createForPatient(
        int $patientId,
        int $departmentId,
        int $doctorId,
        string $date,
        string $time,
        string $type,
        string $notes,
        string $status = 'pending'
    ): void {
        $stmt = Database::connection()->prepare('
            INSERT INTO consultas (patient_id, department_id, doctor_id, date, time, status, type, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$patientId, $departmentId, $doctorId, $date, $time, $status, $type, $notes !== '' ? $notes : null]);
    }

    /** Devolve o histórico completo de consultas de um paciente. */
    public static function forPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.date DESC, a.time DESC');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Atalho para a listagem geral usada pelo dashboard. */
    public static function all(): array
    {
        return self::forStaff();
    }

    /** Pesquisa consultas com filtros e isolamento opcional por médico. */
    public static function forStaff(array $filters = [], ?int $doctorScope = null): array
    {
        $where = [];
        $params = [];
        if ($doctorScope !== null) {
            $where[] = 'a.doctor_id = ?';
            $params[] = $doctorScope;
        }
        if (!empty($filters['date'])) {
            $where[] = 'a.date = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'a.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (!empty($filters['doctor_id'])) {
            $where[] = 'a.doctor_id = ?';
            $params[] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(p.name LIKE ? OR p.medical_record_number LIKE ? OR doc.name LIKE ?)';
            array_push($params, $term, $term, $term);
        }

        $sql = 'SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name
            FROM consultas a
            JOIN pacientes p ON p.id = a.patient_id
            JOIN departamentos d ON d.id = a.department_id
            JOIN medicos doc ON doc.id = a.doctor_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.date ASC, a.time ASC, a.id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Carrega uma consulta com os nomes relacionados para acções de equipa. */
    public static function findForStaff(int $appointmentId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN pacientes p ON p.id = a.patient_id JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id WHERE a.id = ?');
        $stmt->execute([$appointmentId]);
        return $stmt->fetch() ?: null;
    }

    /** Lista apenas consultas pertencentes a um médico. */
    public static function forDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN pacientes p ON p.id = a.patient_id JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id WHERE a.doctor_id = ? ORDER BY a.date DESC, a.time ASC');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    /** Confirma se já existe uma relação clínica paciente-médico. */
    public static function patientHasDoctor(int $patientId, int $doctorId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM consultas WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
        $stmt->execute([$patientId, $doctorId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Actualiza o estado, respeitando o isolamento opcional do médico. */
    public static function updateStatusForStaff(int $appointmentId, string $status, ?int $doctorId = null): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        if ($doctorId !== null) {
            $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ? AND doctor_id = ?');
            $stmt->execute([$status, $appointmentId, $doctorId]);
        } else {
            $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ?');
            $stmt->execute([$status, $appointmentId]);
        }
        return $stmt->rowCount() > 0;
    }

    /** Reagenda uma consulta activa depois de repetir a validação de conflitos. */
    public static function rescheduleForStaff(int $appointmentId, string $date, string $time, ?int $doctorId = null): bool
    {
        $appointment = self::findForStaff($appointmentId);
        if (!$appointment || in_array($appointment['status'], ['completed', 'cancelled'], true)) {
            return false;
        }
        if ($doctorId !== null && (int) $appointment['doctor_id'] !== $doctorId) {
            return false;
        }
        $conflict = Database::connection()->prepare('SELECT COUNT(*) FROM consultas WHERE id <> ? AND date = ? AND time = ? AND status <> ? AND (doctor_id = ? OR patient_id = ?)');
        $conflict->execute([$appointmentId, $date, $time, 'cancelled', (int) $appointment['doctor_id'], (int) $appointment['patient_id']]);
        if ((int) $conflict->fetchColumn() > 0) {
            return false;
        }
        $sql = 'UPDATE consultas SET date = ?, time = ?, status = ? WHERE id = ? AND status NOT IN (?, ?)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$date, $time, 'scheduled', $appointmentId, 'completed', 'cancelled']);
        return $stmt->rowCount() > 0;
    }

    /** Encontra o último médico para encaminhar mensagens do paciente. */
    public static function latestDoctorForPatient(int $patientId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT doctor_id FROM consultas WHERE patient_id = ? ORDER BY date DESC, time DESC LIMIT 1');
        $stmt->execute([$patientId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Mantém uma API simples de compatibilidade para actualizar estados. */
    public static function updateStatus(int $appointmentId, string $status): void
    {
        $allowed = ['pending', 'scheduled', 'waiting', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ?');
        $stmt->execute([$status, $appointmentId]);
    }

    /** Permite ao paciente cancelar apenas pedidos futuros ainda editáveis. */
    public static function cancelForPatient(int $appointmentId, int $patientId): bool
    {
        $stmt = Database::connection()->prepare("UPDATE consultas SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status IN ('pending', 'scheduled') AND date >= CURRENT_DATE");
        $stmt->execute([$appointmentId, $patientId]);
        return $stmt->rowCount() > 0;
    }
}