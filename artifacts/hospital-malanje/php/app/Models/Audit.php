<?php
/** Regista eventos importantes para rastreabilidade e auditoria clínica. */
declare(strict_types=1);

final class Audit
{
    /**
     * Guarda quem executou uma acção, sobre que entidade e com que detalhe.
     *
     * accountId pode ser nulo para permitir eventos técnicos sem utilizador.
     */
    public static function record(?int $accountId, string $eventType, ?string $entityType = null, ?string $entityId = null, ?string $detail = null): void
    {
        $stmt = Database::connection()->prepare('
            INSERT INTO eventos_auditoria (account_id, event_type, entity_type, entity_id, detail)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$accountId, $eventType, $entityType, $entityId, $detail]);
    }
}