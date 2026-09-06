<?php
/**
 * Assistente de linha de comandos para criar a primeira conta administrativa.
 *
 * A validação CLI impede que este script seja usado como endpoint web.
 */
declare(strict_types=1);

// O instalador só pode ser executado no terminal local.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/app/Core/Database.php';
require __DIR__ . '/app/Core/Support.php';
require __DIR__ . '/app/Models/Account.php';

// Garante que a tabela de contas existe antes de pedir os dados.
Account::ensureSchema();

// Lê um valor do terminal sem expor detalhes adicionais da instalação.
$read = static function (string $label): string {
    echo $label;
    $value = fgets(STDIN);
    return trim($value === false ? '' : $value);
};

$name = $read("Nome do administrador: ");
$email = strtolower($read("Email do administrador: "));
$password = $read("Palavra-passe do administrador (mínimo 8 caracteres): ");

// Exige dados suficientes para evitar uma conta administrativa incompleta.
if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
    fwrite(STDERR, "Dados inválidos. O nome, email e palavra-passe são obrigatórios.\n");
    exit(1);
}

// A instalação é idempotente: não duplica uma conta já existente.
if (Account::findByEmail($email)) {
    echo "A conta indicada já existe; a instalação pode continuar.\n";
    exit(0);
}

$id = Account::createStaff($name, $email, $password, 'admin');
echo "Administrador criado com o ID $id.\n";