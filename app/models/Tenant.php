<?php
require_once BASE_PATH . '/app/core/Model.php';

class Tenant extends Model{

    public function __construct(){
        $this->db = Database::getMaster();
    }

    public function getAll(): array{
        $stmt = $this->db->prepare("SELECT * FROM tenants ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActives(): array{
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE activo = 1 ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("SELECT * FROM tenants WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO tenants (nombre, dbname, host, activo)
            VALUES (:nombre, :dbname, :host, :activo)
        ");
        $stmt->execute([
            ':nombre'  => $data['nombre'],
            ':dbname'  => $data['dbname'],
            ':host'    => $data['host'] ?? 'localhost',
            ':activo'  => $data['activo'] ?? 1
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $sql = "UPDATE tenants SET nombre = :nombre, host = :host, activo = :activo";
        $params = [
            ':nombre'  => $data['nombre'],
            ':host'    => $data['host'] ?? 'localhost',
            ':activo'  => $data['activo'] ?? 1,
            ':id'      => $id
        ];

        if (isset($data['dbname'])) {
            $sql .= ", dbname = :dbname";
            $params[':dbname'] = $data['dbname'];
        }

        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool{
        $stmt = $this->db->prepare("DELETE FROM tenants WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Crea una nueva BD para un tenant clonando el esquema template.
     */
    public function createDatabase(string $dbname): bool{
        $host = 'localhost';
        $config = require BASE_PATH . '/app/config/database.php';

        // Crear la BD, ojo en host la base se crea de forma manual y con un prefijo del proveedor
        $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Conectar a la nueva BD con multi_statement para soportar triggers
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $newDb = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);

        // Usar el schema limpio (sin DELIMITER, compatible con PDO)
        $schemaFile = BASE_PATH . '/app/helpers/squemadb/tenant_schema.sql';
        if (!file_exists($schemaFile)) {
            error_log("Archivo de esquema no encontrado: {$schemaFile}");
            return false;
        }

        $sql = file_get_contents($schemaFile);

        // Parsear SQL respetando BEGIN...END de triggers/procedimientos
        $statements = self::parseSqlStatements($sql);

        foreach ($statements as $statement) {
            $trimmed = ltrim($statement);
            if (empty($trimmed) || $trimmed[0] === '-' || $trimmed === '') {
                continue;
            }
            try {
                $newDb->exec($statement);
            } catch (PDOException $e) {
                error_log("Error ejecutando SQL en tenant {$dbname}: " . $e->getMessage() . " | SQL: " . substr($statement, 0, 200));
            }
        }

        return true;
    }

    /**
     * Retorna los usuarios asignados a un tenant.
     */
    public function getUsers(int $tenantId): array{
        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.email, u.rol, u.activo
            FROM users u
            INNER JOIN user_tenant ut ON ut.user_id = u.id
            WHERE ut.tenant_id = :tenant_id
            ORDER BY u.nombre
        ");
        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna los tenants de un usuario.
     */
    public function getTenantsForUser(int $userId): array{
        $stmt = $this->db->prepare("
            SELECT t.*
            FROM tenants t
            INNER JOIN user_tenant ut ON ut.tenant_id = t.id
            WHERE ut.user_id = :user_id
            ORDER BY t.nombre
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna un usuario a un tenant.
     */
    public function assignUser(int $tenantId, int $userId): bool{
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_tenant (user_id, tenant_id)
            VALUES (:user_id, :tenant_id)
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    /**
     * Remueve un usuario de un tenant.
     */
    public function removeUser(int $tenantId, int $userId): bool{
        $stmt = $this->db->prepare("
            DELETE FROM user_tenant
            WHERE user_id = :user_id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    /**
     * Parsea un archivo SQL dividiéndolo en statements individuales,
     * respetando bloques BEGIN...END de triggers/procedimientos.
     */
    private static function parseSqlStatements(string $sql): array{
        $statements = [];
        $current = '';
        $inBlock = 0;
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $upper = strtoupper(trim($line));

            // Detectar inicio de bloque
            if (preg_match('/\bBEGIN\b/', $upper)) {
                $inBlock++;
                $current .= $line . "\n";
                continue;
            }

            // Detectar fin de bloque
            if (preg_match('/\bEND\b/', $upper) && $inBlock > 0) {
                $current .= $line . "\n";
                $inBlock--;
                if ($inBlock === 0) {
                    $statements[] = trim($current);
                    $current = '';
                }
                continue;
            }

            // Dentro de un bloque: acumular sin dividir
            if ($inBlock > 0) {
                $current .= $line . "\n";
                continue;
            }

            // Fuera de bloques: saltar comentarios
            $trimmedLine = ltrim($line);
            if (empty($trimmedLine) || $trimmedLine[0] === '-' || $trimmedLine === '') {
                continue;
            }

            // Agregar línea al statement actual
            $current .= $line . "\n";

            // Si termina con ; es un statement completo
            if (substr(trim($current), -1) === ';') {
                $statements[] = trim($current);
                $current = '';
            }
        }

        // Último statement sin ;
        $current = trim($current);
        if (!empty($current)) {
            $statements[] = $current;
        }

        return $statements;
    }
}
