<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = 'home', array $params = []): string
{
    return '?' . http_build_query(array_merge(['page' => $page], $params));
}

function redirect_to(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Pedido expirado. Volte atrás e tente novamente.');
    }
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function age_from_date(string $birthDate): int
{
    return (int) (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable())->y;
}

function role_label(string $role): string
{
    return [
        'admin' => 'Administrador',
        'doctor' => 'Médico',
        'receptionist' => 'Recepção',
        'patient' => 'Paciente',
    ][$role] ?? ucfirst($role);
}

function can_access(array $user, string $permission): bool
{
    return Account::roleCan((string) ($user['role'] ?? ''), $permission);
}