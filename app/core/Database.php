<?php
class Database{
    private static ?PDO $masterInstance = null;
    private static ?PDO $tenantInstance = null;
    private static ?string $connectedDb = null;

    private static array $config = [];

    private static function loadConfig(): array{
        if(empty(self::$config)){
            self::$config = require BASE_PATH . '/app/config/database.php';
        }
        return self::$config;
    }

    private static function createConnection(string $host, string $dbname, string $user, string $pass, string $charset): PDO{
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Retorna la conexión a la BD master (users, tenants, user_tenant).
     */
    public static function getMaster(): PDO{
        if(self::$masterInstance === null){
            $config = self::loadConfig();
            $m = $config['master'];
            self::$masterInstance = self::createConnection(
                $m['host'], $m['dbname'], $m['user'], $m['pass'], $m['charset']
            );
        }
        return self::$masterInstance;
    }

    /**
     * Conecta a la BD de un tenant específico.
     */
    public static function connectTenant(string $dbname, string $host = 'localhost'): PDO{
        $config = self::loadConfig();
        self::$tenantInstance = self::createConnection(
            $host, $dbname, $config['user'], $config['pass'], $config['charset']
        );
        self::$connectedDb = $dbname;
        error_log("[Database] connectTenant() => {$dbname}@{$host}");
        return self::$tenantInstance;
    }

    /**
     * Retorna true si la conexión activa es la BD del tenant indicado.
     */
    public static function isTenantConnectedTo(string $dbname): bool{
        return self::$connectedDb === $dbname && self::$tenantInstance !== null;
    }

    /**
     * Retorna la conexión activa del tenant.
     * Si no hay tenant conectado, crea conexión a la BD default.
     */
    public static function getInstance(): PDO{
        if(self::$tenantInstance !== null){
            return self::$tenantInstance;
        }
        $config = self::loadConfig();
        error_log("[Database] getInstance() => FALLBACK a default: {$config['dbname']}");
        self::$tenantInstance = self::createConnection(
            $config['host'], $config['dbname'], $config['user'], $config['pass'], $config['charset']
        );
        self::$connectedDb = $config['dbname'];
        return self::$tenantInstance;
    }

    /**
     * Desconecta el tenant actual.
     */
    public static function disconnectTenant(): void{
        self::$tenantInstance = null;
        self::$connectedDb = null;
    }

    /**
     * Nombre de la BD actualmente conectada.
     */
    public static function getCurrentDbName(): ?string{
        return self::$connectedDb;
    }
}
