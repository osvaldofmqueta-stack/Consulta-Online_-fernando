<?php
/**
 * Mensagens entre pacientes e equipa.
 *
 * O filtro por médico impede que cada médico veja conversas fora da sua
 * relação clínica, enquanto recepção e administração podem ver a caixa geral.
 */
declare(strict_types=1);

final class Message
{
    /** Lista a conversa completa do próprio paciente. */
    public static function forPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare('SELECT pm.*, doc.name AS doctor_name FROM mensagens_pacientes pm LEFT JOIN medicos doc ON doc.id = pm.doctor_id WHERE pm.patient_id = ? ORDER BY pm.created_at ASC');
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Regista uma mensagem enviada pelo portal do paciente. */
    public static function createForPatient(int $patientId, ?int $doctorId, int $accountId, string $body): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO mensagens_pacientes (patient_id, doctor_id, sender_clerk_user_id, sender_role, body) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$patientId, $doctorId, 'php:' . $accountId, 'patient', $body]);
    }

    /** Lista a caixa geral de mensagens para equipa autorizada. */
    public static function inbox(): array
    {
        return Database::connection()->query('
            SELECT pm.*, p.name AS patient_name, p.medical_record_number, doc.name AS doctor_name
            FROM mensagens_pacientes pm
            JOIN pacientes p ON p.id = pm.patient_id
            LEFT JOIN medicos doc ON doc.id = pm.doctor_id
            ORDER BY pm.created_at DESC
        ')->fetchAll();
    }

    /** Lista mensagens dirigidas ou relacionadas com consultas do médico. */
    public static function inboxForDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare('SELECT pm.*, p.name AS patient_name, p.medical_record_number, doc.name AS doctor_name FROM mensagens_pacientes pm JOIN pacientes p ON p.id = pm.patient_id LEFT JOIN medicos doc ON doc.id = pm.doctor_id WHERE pm.doctor_id = ? OR EXISTS (SELECT 1 FROM consultas a WHERE a.patient_id = pm.patient_id AND a.doctor_id = ?) ORDER BY pm.created_at DESC');
        $stmt->execute([$doctorId, $doctorId]);
        return $stmt->fetchAll();
    }

    /** Regista uma resposta enviada por médico, recepção ou administração. */
    public static function createForStaff(int $patientId, ?int $doctorId, int $accountId, string $role, string $body): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO mensagens_pacientes (patient_id, doctor_id, sender_clerk_user_id, sender_role, body) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$patientId, $doctorId, 'php:' . $accountId, $role, $body]);
    }

    /** Marca uma mensagem como lida sem apagar o conteúdo. */
    public static function markRead(int $messageId): void
    {
        $stmt = Database::connection()->prepare('UPDATE mensagens_pacientes SET read_at = NOW() WHERE id = ?');
        $stmt->execute([$messageId]);
    }

    /** Conta mensagens novas de pacientes para o indicador da equipa. */
    public static function unreadCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM mensagens_pacientes WHERE sender_role = 'patient' AND read_at IS NULL")->fetchColumn();
    }
}