<?php
declare(strict_types=1);

final class Clinical
{
    public static function documentsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT id, patient_id, title, category, file_name, mime_type, file_size, created_at
            FROM patient_documents
            WHERE patient_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function documentForPatient(int $documentId, int $patientId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM patient_documents WHERE id = ? AND patient_id = ?');
        $stmt->execute([$documentId, $patientId]);
        return $stmt->fetch() ?: null;
    }

    public static function documentForStaff(int $documentId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM patient_documents WHERE id = ?');
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    public static function saveDocument(
        int $patientId,
        int $accountId,
        string $title,
        string $category,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $content
    ): void {
        $stmt = Database::connection()->prepare('
            INSERT INTO patient_documents
                (patient_id, uploaded_by_account_id, title, category, file_name, mime_type, file_size, file_content)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bindValue(1, $patientId, PDO::PARAM_INT);
        $stmt->bindValue(2, $accountId, PDO::PARAM_INT);
        $stmt->bindValue(3, $title);
        $stmt->bindValue(4, $category);
        $stmt->bindValue(5, $fileName);
        $stmt->bindValue(6, $mimeType);
        $stmt->bindValue(7, $fileSize, PDO::PARAM_INT);
        $stmt->bindValue(8, $content, PDO::PARAM_LOB);
        $stmt->execute();
    }

    public static function prescriptionsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT pp.*, doc.name AS doctor_name
            FROM patient_prescriptions pp
            LEFT JOIN doctors doc ON doc.id = pp.doctor_id
            WHERE pp.patient_id = ?
            ORDER BY pp.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function createPrescription(
        int $patientId,
        ?int $doctorId,
        ?int $appointmentId,
        string $medication,
        string $dosage,
        string $frequency,
        string $duration,
        string $instructions
    ): void {
        $stmt = Database::connection()->prepare('
            INSERT INTO patient_prescriptions
                (patient_id, doctor_id, appointment_id, medication, dosage, frequency, duration, instructions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $patientId,
            $doctorId ?: null,
            $appointmentId ?: null,
            $medication,
            $dosage,
            $frequency,
            $duration,
            $instructions !== '' ? $instructions : null,
        ]);
    }

    public static function resultsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT pr.*, doc.name AS doctor_name
            FROM patient_results pr
            LEFT JOIN doctors doc ON doc.id = pr.doctor_id
            WHERE pr.patient_id = ?
            ORDER BY pr.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function createResult(
        int $patientId,
        ?int $doctorId,
        ?int $appointmentId,
        string $title,
        string $resultText
    ): void {
        $stmt = Database::connection()->prepare('
            INSERT INTO patient_results
                (patient_id, doctor_id, appointment_id, title, result_text)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $patientId,
            $doctorId ?: null,
            $appointmentId ?: null,
            $title,
            $resultText,
        ]);
    }

    public static function settings(): array
    {
        $rows = Database::connection()->query('SELECT setting_key, setting_value FROM hospital_settings ORDER BY setting_key')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public static function saveSetting(string $key, string $value): void
    {
        $stmt = Database::connection()->prepare('
            INSERT INTO hospital_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()
        ');
        $stmt->execute([$key, $value]);
    }
}