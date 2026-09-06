<?php
/**
 * Gera config.local.php a partir das variáveis fornecidas pelo instalador.
 *
 * O ficheiro resultante contém as credenciais locais e deve permanecer fora
 * do controlo de versões.
 */
declare(strict_types=1);

// Estas variáveis identificam a base MySQL/MariaDB usada pelo computador local.
$required = ['HM_DB_HOST', 'HM_DB_PORT', 'HM_DB_NAME', 'HM_DB_USER'];
foreach ($required as $key) {
    if (getenv($key) === false || getenv($key) === '') {
        fwrite(STDERR, "Variável $key não configurada.\n");
        exit(1);
    }
}

// Mantém a configuração num formato simples que Database::connection() entende.
$config = [
    'host' => getenv('HM_DB_HOST'),
    'port' => getenv('HM_DB_PORT'),
    'database' => getenv('HM_DB_NAME'),
    'user' => getenv('HM_DB_USER'),
    'password' => getenv('HM_DB_PASSWORD') ?: '',
];

// LOCK_EX evita que dois processos escrevam o ficheiro ao mesmo tempo.
$target = __DIR__ . '/config.local.php';
$contents = "<?php\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($target, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Não foi possível gravar a configuração local.\n");
    exit(1);
}

echo "Configuração MySQL local criada.\n";