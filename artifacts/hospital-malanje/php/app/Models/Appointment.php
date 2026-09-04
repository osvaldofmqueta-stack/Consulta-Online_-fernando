<?php
declare(strict_types=1);

final class Appointment
{
    public static function forPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('SELECT a.*, d.name AS department_name, doc.name AS doctor_name FROM appointments a JOIN departments d ON d.id = a.department_id JOIN doctors doc ON doc.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.date DESC, a.time DESC');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN departments d ON d.id = a.department_id JOIN doctors doc ON doc.id = a.doctor_id ORDER BY a.date DESC, a.time ASC')->fetchAll();
    }

    public static function latestDoctorForPatient(int $patientId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT doctor_id FROM appointments WHERE patient_id = ? ORDER BY date DESC, time DESC LIMIT 1');
        $stmt->execute([$patientId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function updateStatus(int $appointmentId, string $status): void
    {
        $allowed = ['scheduled', 'waiting', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute([$status, $appointmentId]);
    }
}