<?php
/**
 * Funções auxiliares partilhadas pelos controladores e templates.
 *
 * Este ficheiro concentra escaping, URLs, sessão, CSRF, mensagens flash
 * e regras pequenas que não pertencem a um modelo específico.
 */
declare(strict_types=1);

/** Escapa texto antes de o inserir em HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Constrói um caminho de asset compatível com a rota base do artefacto. */
function asset_url(string $asset): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim(dirname($script), '/.');
    return ($base === '' ? '' : $base) . '/' . ltrim($asset, '/');
}

/** Constrói URLs internas usando o router baseado no parâmetro page. */
function url(string $page = 'home', array $params = []): string
{
    return '?' . http_build_query(array_merge(['page' => $page], $params));
}

/** Redirecciona o pedido e interrompe imediatamente a execução actual. */
function redirect_to(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** Cria ou reutiliza o token CSRF guardado na sessão. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

/** Valida o token CSRF enviado por qualquer formulário POST. */
function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Pedido expirado. Volte atrás e tente novamente.');
    }
}

/**
 * Guarda uma mensagem para o próximo pedido ou lê-a uma única vez.
 *
 * O comportamento one-shot evita que a mesma mensagem apareça depois de
 * várias navegações do utilizador.
 */
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

/** Calcula a idade completa a partir da data de nascimento. */
function age_from_date(string $birthDate): int
{
    return (int) (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable())->y;
}

/** Traduz os identificadores internos dos papéis para a interface. */
function role_label(string $role): string
{
    return [
        'admin' => 'Administrador',
        'doctor' => 'Médico',
        'receptionist' => 'Recepção',
        'patient' => 'Paciente',
    ][$role] ?? ucfirst($role);
}

/** Consulta a matriz central de permissões da conta. */
function can_access(array $user, string $permission): bool
{
    return Account::roleCan((string) ($user['role'] ?? ''), $permission);
}