<?php
declare(strict_types=1);

final class HospitalDirectory
{
    public static function departments(): array
    {
        return Database::connection()->query('SELECT * FROM departamentos ORDER BY active DESC, name')->fetchAll();
    }

    public static function doctors(): array
    {
        return Database::connection()->query('SELECT doc.*, d.name AS department_name FROM medicos doc JOIN departamentos d ON d.id = doc.department_id ORDER BY doc.active DESC, doc.name')->fetchAll();
    }

    public static function createDepartment(string $name, string $color): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO departamentos (name, color, active) VALUES (?, ?, ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ')');
        $stmt->execute([$name, $color ?: '#2b7a78']);
    }

    public static function updateDepartment(int $id, string $name, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE departamentos SET name = ?, active = ? WHERE id = ?');
        $stmt->execute([$name, $active, $id]);
    }

    public static function createDoctor(string $name, string $specialty, int $departmentId): void
    {
        $initials = strtoupper(implode('', array_map(static fn (string $part): string => substr($part, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2))));
        $stmt = Database::connection()->prepare('INSERT INTO medicos (name, specialty, department_id, initials, active) VALUES (?, ?, ?, ?, ' . (Database::driver() === 'mysql' ? '1' : 'TRUE') . ')');
        $stmt->execute([$name, $specialty, $departmentId, $initials ?: 'DR']);
    }

    public static function updateDoctor(int $id, string $name, string $specialty, int $departmentId, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE medicos SET name = ?, specialty = ?, department_id = ?, active = ? WHERE id = ?');
        $stmt->execute([$name, $specialty, $departmentId, $active, $id]);
    }
}