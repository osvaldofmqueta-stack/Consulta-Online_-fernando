<?php
declare(strict_types=1);

final class Audit
{
    public static function record(?int $accountId, string $eventType, ?string $entityType = null, ?string $entityId = null, ?string $detail = null): void
    {
        $stmt = Database::connection()->prepare('
            INSERT INTO audit_events (account_id, event_type, entity_type, entity_id, detail)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$accountId, $eventType, $entityType, $entityId, $detail]);
    }
}