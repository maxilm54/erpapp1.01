<?php
require_once BASE_PATH . '/app/core/Model.php';

class User extends Model{

    public function __construct(){
        $this->db = Database::getMaster();
    }

    public function create(array $data):bool{
        $sql = "INSERT INTO users
                (nombre, email, password_hash, token_verificacion, rol)
                VALUES (:nombre, :email, :password, :token, :rol)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password'=> password_hash($data['password'], PASSWORD_DEFAULT),
            ':token' => $data['token'],
            ':rol' => $data['rol'] ?? 'USUARIO'
        ]);
    }

    public function findByEmail(string $email){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    public function verifyEmail(string $token): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET email_verificado = 1, token_verificacion = NULL
             WHERE token_verificacion = :token"
        );
        return $stmt->execute(['token' => $token]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET rol = :rol WHERE id = :id"
        );
        return $stmt->execute([':rol' => $role, ':id' => $id]);
    }

    public function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return $this->findById($_SESSION['user_id']);
    }

    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['rol'] === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['rol'], $roles);
    }

    public function getAllUsers():array{
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea un usuario nuevo (ya verificado, creado por admin).
     */
    public function createUser(array $data): int{
        $sql = "INSERT INTO users (nombre, email, password_hash, email_verificado, activo, rol)
                VALUES (:nombre, :email, :password, 1, 1, :rol)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'   => $data['nombre'],
            ':email'    => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':rol'      => $data['rol'] ?? 'USUARIO'
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza datos de un usuario.
     */
    public function updateUser(int $id, array $data): bool{
        $sql = "UPDATE users SET nombre = :nombre, email = :email, rol = :rol";
        $params = [
            ':nombre' => $data['nombre'],
            ':email'  => $data['email'],
            ':rol'    => $data['rol'] ?? 'USUARIO'
        ];

        if (!empty($data['password'])) {
            $sql .= ", password_hash = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id";
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool{
        $stmt = $this->db->prepare("DELETE FROM user_tenant WHERE user_id = :id");
        $stmt->execute([':id' => $id]);
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Activa o desactiva un usuario.
     */
    public function setActive(int $id, bool $active): bool{
        $stmt = $this->db->prepare("UPDATE users SET activo = :activo WHERE id = :id");
        return $stmt->execute([':activo' => $active ? 1 : 0, ':id' => $id]);
    }

    /**
     * Verifica si un email ya existe (excluyendo un ID opcional).
     */
    public function emailExists(string $email, int $excludeId = 0): bool{
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $params = [':email' => $email];
        if ($excludeId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Retorna los tenants asignados a un usuario.
     */
    public function getTenantsForUser(int $userId): array{
        $stmt = $this->db->prepare("
            SELECT t.id, t.nombre, t.dbname, t.host
            FROM tenants t
            INNER JOIN user_tenant ut ON ut.tenant_id = t.id
            WHERE ut.user_id = :user_id AND t.activo = 1
            ORDER BY t.nombre
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna un tenant a un usuario.
     */
    public function assignTenant(int $userId, int $tenantId): bool{
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_tenant (user_id, tenant_id)
            VALUES (:user_id, :tenant_id)
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    /**
     * Remueve un tenant de un usuario.
     */
    public function removeTenant(int $userId, int $tenantId): bool{
        $stmt = $this->db->prepare("
            DELETE FROM user_tenant
            WHERE user_id = :user_id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);
    }

    /**
     * Remueve todos los tenants de un usuario.
     */
    public function removeAllTenants(int $userId): bool{
        $stmt = $this->db->prepare("DELETE FROM user_tenant WHERE user_id = :user_id");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Sincroniza el usuario desde master DB a la tabla users del tenant.
     * Usado para que las foreign keys en tablas del tenant (productos, etc.) funcionen.
     */
    public function syncToTenant(int $userId, string $tenantDbname, string $host = 'localhost'): bool{
        $user = $this->findById($userId);
        if (!$user) return false;

        $config = require BASE_PATH . '/app/config/database.php';
        $dsn = "mysql:host={$host};dbname={$tenantDbname};charset=utf8mb4";
        $tenantDb = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = "INSERT INTO users (id, nombre, email, password_hash, email_verificado, rol, activo, created_at, updated_at)
                VALUES (:id, :nombre, :email, :password_hash, :email_verificado, :rol, :activo, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    email = VALUES(email),
                    password_hash = VALUES(password_hash),
                    email_verificado = VALUES(email_verificado),
                    rol = VALUES(rol),
                    activo = VALUES(activo),
                    updated_at = VALUES(updated_at)";

        $stmt = $tenantDb->prepare($sql);
        return $stmt->execute([
            ':id'              => $user['id'],
            ':nombre'          => $user['nombre'],
            ':email'           => $user['email'],
            ':password_hash'   => $user['password_hash'],
            ':email_verificado'=> $user['email_verificado'],
            ':rol'             => $user['rol'],
            ':activo'          => $user['activo'],
            ':created_at'      => $user['created_at'],
            ':updated_at'      => $user['updated_at'] ?? null,
        ]);
    }
}
