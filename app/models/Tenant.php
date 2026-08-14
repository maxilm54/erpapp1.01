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
            INSERT INTO tenants (nombre, dbname, host, activo, cuit, email, telefono, direccion, logo)
            VALUES (:nombre, :dbname, :host, :activo, :cuit, :email, :telefono, :direccion, :logo)
        ");
        $stmt->execute([
            ':nombre'     => $data['nombre'],
            ':dbname'     => $data['dbname'],
            ':host'       => $data['host'] ?? 'localhost',
            ':activo'     => $data['activo'] ?? 1,
            ':cuit'       => $data['cuit'] ?? null,
            ':email'      => $data['email'] ?? null,
            ':telefono'   => $data['telefono'] ?? null,
            ':direccion'  => $data['direccion'] ?? null,
            ':logo'       => $data['logo'] ?? null,
        ]);
        $tenantId = (int)$this->db->lastInsertId();

        // Crear directorios privados de la empresa
        $this->crearDirectorios($tenantId);

        return $tenantId;
    }

    public function update(int $id, array $data): bool{
        $sql = "UPDATE tenants SET";
        $sets = [];
        $params = [':id' => $id];

        // Solo actualizar campos que vienen en $data
        $allFields = ['nombre', 'host', 'activo', 'dbname', 'cuit', 'email', 'telefono', 'direccion', 'logo'];
        foreach ($allFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field] ?? null;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql .= ' ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool{
        $stmt = $this->db->prepare("DELETE FROM tenants WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Retorna la configuración de empresa del tenant.
     */
    public function getEmpresaConfig(int $tenantId): array{
        $tenant = $this->findById($tenantId);
        if (!$tenant) {
            return $this->getEmpresaDefaults();
        }

        $logoUrl = null;
        if (!empty($tenant['logo'])) {
            $logoUrl = BASE_URL . '/uploads/img_config/empresa_' . $tenantId . '/' . $tenant['logo'];
        }

        return [
            'nombre'    => $tenant['nombre'] ?? 'Empresa',
            'cuit'      => $tenant['cuit'] ?? '',
            'email'     => $tenant['email'] ?? '',
            'telefono'  => $tenant['telefono'] ?? '',
            'direccion' => $tenant['direccion'] ?? '',
            'logo'      => $logoUrl,
            'logo_file' => $tenant['logo'] ?? null,
        ];
    }

    /**
     * Valores por defecto cuando no hay tenant seleccionado.
     */
    public function getEmpresaDefaults(): array{
        return [
            'nombre'    => 'Empresa',
            'cuit'      => '',
            'email'     => '',
            'telefono'  => '',
            'direccion' => '',
            'logo'      => null,
            'logo_file' => null,
        ];
    }

    /**
     * Crea la estructura de directorios privados para una empresa.
     */
    public function crearDirectorios(int $tenantId): void{
        $dirs = [
            BASE_PATH . "/public/uploads/img_config/empresa_{$tenantId}",
            BASE_PATH . "/public/uploads/productos/empresa_{$tenantId}",
            BASE_PATH . "/public/uploads/materiasprimas/empresa_{$tenantId}",
            BASE_PATH . "/storage/empresa_{$tenantId}/remitos",
            BASE_PATH . "/storage/empresa_{$tenantId}/pagos",
            BASE_PATH . "/storage/empresa_{$tenantId}/logs",
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    /**
     * Retorna la ruta de almacenamiento para una empresa.
     */
    public function getStoragePath(int $tenantId, string $tipo): string{
        $base = BASE_PATH . "/storage/empresa_{$tenantId}/{$tipo}";
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }
        return $base;
    }

    /**
     * Retorna la ruta de uploads para una empresa.
     */
    public function getUploadPath(int $tenantId, string $tipo): string{
        $base = BASE_PATH . "/public/uploads/{$tipo}/empresa_{$tenantId}";
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }
        return $base;
    }

    /**
     * Crea una nueva BD para un tenant clonando el esquema template.
     */
    public function createDatabase(string $dbname): bool{
        $host = 'localhost';
        $config = require BASE_PATH . '/app/config/database.php';

        $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $newDb = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);

        $schemaFile = BASE_PATH . '/app/helpers/squemadb/tenant_schema.sql';
        if (!file_exists($schemaFile)) {
            error_log("Archivo de esquema no encontrado: {$schemaFile}");
            return false;
        }

        $sql = file_get_contents($schemaFile);
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

    public function assignUser(int $tenantId, int $userId): bool{
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_tenant (user_id, tenant_id)
            VALUES (:user_id, :tenant_id)
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    public function removeUser(int $tenantId, int $userId): bool{
        $stmt = $this->db->prepare("
            DELETE FROM user_tenant
            WHERE user_id = :user_id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    private static function parseSqlStatements(string $sql): array{
        $statements = [];
        $current = '';
        $inBlock = 0;
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $upper = strtoupper(trim($line));

            if (preg_match('/\bBEGIN\b/', $upper)) {
                $inBlock++;
                $current .= $line . "\n";
                continue;
            }

            if (preg_match('/\bEND\b/', $upper) && $inBlock > 0) {
                $current .= $line . "\n";
                $inBlock--;
                if ($inBlock === 0) {
                    $statements[] = trim($current);
                    $current = '';
                }
                continue;
            }

            if ($inBlock > 0) {
                $current .= $line . "\n";
                continue;
            }

            $trimmedLine = ltrim($line);
            if (empty($trimmedLine) || $trimmedLine[0] === '-' || $trimmedLine === '') {
                continue;
            }

            $current .= $line . "\n";

            if (substr(trim($current), -1) === ';') {
                $statements[] = trim($current);
                $current = '';
            }
        }

        $current = trim($current);
        if (!empty($current)) {
            $statements[] = $current;
        }

        return $statements;
    }
}
