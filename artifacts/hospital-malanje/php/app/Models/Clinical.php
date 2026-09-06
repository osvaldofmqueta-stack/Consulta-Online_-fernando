<?php
/**
 * Repositório de documentos e informação clínica.
 *
 * Os documentos são guardados como bytes na base local e só são devolvidos
 * depois de o controlador confirmar a identidade e o papel do utilizador.
 */
declare(strict_types=1);

final class Clinical
{
    /** Lista metadados de documentos sem expor o conteúdo binário. */
    public static function documentsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT id, patient_id, title, category, file_name, mime_type, file_size, created_at
            FROM documentos_pacientes
            WHERE patient_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Carrega um documento apenas se pertencer ao paciente indicado. */
    public static function documentForPatient(int $documentId, int $patientId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM documentos_pacientes WHERE id = ? AND patient_id = ?');
        $stmt->execute([$documentId, $patientId]);
        return $stmt->fetch() ?: null;
    }

    /** Carrega um documento para uso depois da autorização da equipa. */
    public static function documentForStaff(int $documentId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM documentos_pacientes WHERE id = ?');
        $stmt->execute([$documentId]);
        return $stmt->fetch() ?: null;
    }

    /** Guarda metadados e conteúdo binário de um documento clínico. */
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
            INSERT INTO documentos_pacientes
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

    /** Lista receitas do paciente com o nome do médico quando disponível. */
    public static function prescriptionsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT pp.*, doc.name AS doctor_name
            FROM receitas_pacientes pp
            LEFT JOIN medicos doc ON doc.id = pp.doctor_id
            WHERE pp.patient_id = ?
            ORDER BY pp.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Regista uma receita ligada opcionalmente a médico e consulta. */
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
            INSERT INTO receitas_pacientes
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

    /** Lista resultados publicados para o paciente autenticado. */
    public static function resultsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('
            SELECT pr.*, doc.name AS doctor_name
            FROM resultados_pacientes pr
            LEFT JOIN medicos doc ON doc.id = pr.doctor_id
            WHERE pr.patient_id = ?
            ORDER BY pr.created_at DESC
        ');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Publica um resultado clínico ligado opcionalmente a uma consulta. */
    public static function createResult(
        int $patientId,
        ?int $doctorId,
        ?int $appointmentId,
        string $title,
        string $resultText
    ): void {
        $stmt = Database::connection()->prepare('
            INSERT INTO resultados_pacientes
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

    /** Converte as definições hospitalares numa matriz chave => valor. */
    public static function settings(): array
    {
        $rows = Database::connection()->query('SELECT setting_key, setting_value FROM definicoes_hospital ORDER BY setting_key')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /** Guarda uma definição usando UPSERT compatível com os dois drivers. */
    public static function saveSetting(string $key, string $value): void
    {
        $upsert = Database::driver() === 'mysql'
            ? 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            : 'ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()';
        $stmt = Database::connection()->prepare('
            INSERT INTO definicoes_hospital (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ' . $upsert . '
        ');
        $stmt->execute([$key, $value]);
    }
}