<?php
/**
 * Abstrai a ligação à base de dados.
 *
 * A aplicação aceita uma configuração local para XAMPP/WAMP e também
 * DATABASE_URL para continuar compatível com o ambiente Replit.
 */
declare(strict_types=1);

final class Database
{
    // Mantém uma única ligação PDO reutilizável durante cada pedido HTTP.
    private static ?PDO $connection = null;
    // Guarda o driver activo para adaptar SQL entre MySQL/MariaDB e PostgreSQL.
    private static string $driver = '';

    /**
     * Cria ou devolve a ligação PDO partilhada.
     *
     * Primeiro tenta a configuração local; se ela não existir, interpreta
     * DATABASE_URL e escolhe automaticamente o driver correspondente.
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        // config.local.php é criado pelo instalador local e não deve ser versionado.
        $localConfigPath = dirname(__DIR__, 2) . '/config.local.php';
        $localConfig = is_file($localConfigPath) ? (require $localConfigPath) : [];
        $url = getenv('DATABASE_URL');

        // O caminho local usa MySQL/MariaDB com credenciais do XAMPP ou WAMP.
        if (is_array($localConfig) && !empty($localConfig['database'])) {
            $host = (string) ($localConfig['host'] ?? '127.0.0.1');
            $port = (string) ($localConfig['port'] ?? '3306');
            $database = (string) $localConfig['database'];
            $user = (string) ($localConfig['user'] ?? 'root');
            $password = (string) ($localConfig['password'] ?? '');
            self::$driver = 'mysql';
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
            self::$connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$connection;
        }

        // Em Replit, DATABASE_URL fornece host, credenciais, porta e base.
        if (!$url) {
            throw new RuntimeException('A base de dados não está configurada. Execute instalar.bat ou configure DATABASE_URL.');
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL inválida.');
        }
        self::$driver = str_starts_with($url, 'mysql://') ? 'mysql' : 'pgsql';
        $dsn = sprintf(
            self::$driver === 'mysql'
                ? 'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4'
                : 'pgsql:host=%s;port=%s;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/')
        );
        // Descodifica utilizador e palavra-passe porque podem conter caracteres escapados na URL.
        self::$connection = new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$connection;
    }

    /**
     * Devolve o driver actual, inicializando a ligação se necessário.
     */
    public static function driver(): string
    {
        if (self::$driver === '') {
            self::connection();
        }
        return self::$driver;
    }
}