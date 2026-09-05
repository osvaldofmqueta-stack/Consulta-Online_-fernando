<?php
declare(strict_types=1);

final class Patient
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByRecordAndPhone(string $recordNumber, string $phone): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM patients WHERE UPPER(medical_record_number) = UPPER(?) AND phone = ?');
        $stmt->execute([trim($recordNumber), trim($phone)]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM patients ORDER BY created_at DESC')->fetchAll();
    }

    public static function forDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare('SELECT DISTINCT p.* FROM patients p JOIN appointments a ON a.patient_id = p.id WHERE a.doctor_id = ? ORDER BY p.created_at DESC');
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public static function updateContact(int $patientId, string $phone, string $neighborhood): void
    {
        $stmt = Database::connection()->prepare('UPDATE patients SET phone = ?, neighborhood = ? WHERE id = ?');
        $stmt->execute([$phone, $neighborhood !== '' ? $neighborhood : null, $patientId]);
    }
}