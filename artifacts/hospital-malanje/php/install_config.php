<?php
declare(strict_types=1);

$required = ['HM_DB_HOST', 'HM_DB_PORT', 'HM_DB_NAME', 'HM_DB_USER'];
foreach ($required as $key) {
    if (getenv($key) === false || getenv($key) === '') {
        fwrite(STDERR, "Variável $key não configurada.\n");
        exit(1);
    }
}

$config = [
    'host' => getenv('HM_DB_HOST'),
    'port' => getenv('HM_DB_PORT'),
    'database' => getenv('HM_DB_NAME'),
    'user' => getenv('HM_DB_USER'),
    'password' => getenv('HM_DB_PASSWORD') ?: '',
];

$target = __DIR__ . '/config.local.php';
$contents = "<?php\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($target, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Não foi possível gravar a configuração local.\n");
    exit(1);
}

echo "Configuração MySQL local criada.\n";