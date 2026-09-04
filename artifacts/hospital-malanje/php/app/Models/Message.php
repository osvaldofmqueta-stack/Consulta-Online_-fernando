<?php
declare(strict_types=1);

final class Message
{
    public static function forPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('SELECT pm.*, doc.name AS doctor_name FROM patient_messages pm LEFT JOIN doctors doc ON doc.id = pm.doctor_id WHERE pm.patient_id = ? ORDER BY pm.created_at ASC');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function createForPatient(int $patientId, ?int $doctorId, int $accountId, string $body): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO patient_messages (patient_id, doctor_id, sender_clerk_user_id, sender_role, body) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$patientId, $doctorId, 'php:' . $accountId, 'patient', $body]);
    }
}