<?php
declare(strict_types=1);

final class Appointment
{
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

    public static function activeDepartments(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM departamentos WHERE active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' ORDER BY name')
            ->fetchAll();
    }

    public static function activeDoctors(): array
    {
        return Database::connection()
            ->query('SELECT doc.id, doc.name, doc.specialty, doc.department_id, d.name AS department_name FROM medicos doc JOIN departamentos d ON d.id = doc.department_id WHERE doc.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' AND d.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' ORDER BY d.name, doc.name')
            ->fetchAll();
    }

    public static function doctorBelongsToDepartment(int $doctorId, int $departmentId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM medicos doc JOIN departamentos d ON d.id = doc.department_id WHERE doc.id = ? AND doc.department_id = ? AND doc.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ' AND d.active = ' . (Database::driver() === 'mysql' ? '1' : 'TRUE'));
        $stmt->execute([$doctorId, $departmentId]);
        return (bool) $stmt->fetchColumn();
    }

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

    public static function forPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.date DESC, a.time DESC');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN pacientes p ON p.id = a.patient_id JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id ORDER BY a.date DESC, a.time ASC')->fetchAll();
    }

    public static function forDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM consultas a JOIN pacientes p ON p.id = a.patient_id JOIN departamentos d ON d.id = a.department_id JOIN medicos doc ON doc.id = a.doctor_id WHERE a.doctor_id = ? ORDER BY a.date DESC, a.time ASC');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public static function patientHasDoctor(int $patientId, int $doctorId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM consultas WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
        $stmt->execute([$patientId, $doctorId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function updateStatusForStaff(int $appointmentId, string $status, ?int $doctorId = null): bool
    {
        if ($doctorId !== null) {
            $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ? AND doctor_id = ?');
            $stmt->execute([$status, $appointmentId, $doctorId]);
        } else {
            $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ?');
            $stmt->execute([$status, $appointmentId]);
        }
        return $stmt->rowCount() > 0;
    }

    public static function latestDoctorForPatient(int $patientId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT doctor_id FROM consultas WHERE patient_id = ? ORDER BY date DESC, time DESC LIMIT 1');
        $stmt->execute([$patientId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function updateStatus(int $appointmentId, string $status): void
    {
        $allowed = ['pending', 'scheduled', 'waiting', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE consultas SET status = ? WHERE id = ?');
        $stmt->execute([$status, $appointmentId]);
    }

    public static function cancelForPatient(int $appointmentId, int $patientId): bool
    {
        $stmt = Database::connection()->prepare("UPDATE consultas SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status IN ('pending', 'scheduled') AND date >= CURRENT_DATE");
        $stmt->execute([$appointmentId, $patientId]);
        return $stmt->rowCount() > 0;
    }
}