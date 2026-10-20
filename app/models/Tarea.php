<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Registro de tareas del panel de administracion.
 * Vive en la BD MASTER (app_master) porque solo el superadmin lo administra.
 */
class Tarea extends Model
{
    public function __construct()
    {
        $this->db = Database::getMaster();
    }

    /**
     * Lista tareas con filtros opcionales: estado, tenant_id.
     */
    public function all(array $filters = []): array
    {
        $sql = "SELECT t.*, te.nombre AS tenant_nombre, u.nombre AS usuario_nombre, cu.nombre AS completada_por_nombre
                FROM tareas t
                LEFT JOIN tenants te ON te.id = t.tenant_id
                LEFT JOIN users u ON u.id = t.usuario_id
                LEFT JOIN users cu ON cu.id = t.completada_por";

        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = "t.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['tenant_id'])) {
            $where[] = "t.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = "t.texto LIKE :q";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY (t.estado = 'PENDIENTE') DESC, t.created_at DESC, t.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, te.nombre AS tenant_nombre, u.nombre AS usuario_nombre
             FROM tareas t
             LEFT JOIN tenants te ON te.id = t.tenant_id
             LEFT JOIN users u ON u.id = t.usuario_id
             WHERE t.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tareas (texto, estado, tenant_id, usuario_id)
             VALUES (:texto, 'PENDIENTE', :tenant_id, :usuario_id)"
        );
        $stmt->execute([
            ':texto'       => $data['texto'],
            ':tenant_id'   => !empty($data['tenant_id']) ? (int)$data['tenant_id'] : null,
            ':usuario_id'  => !empty($data['usuario_id']) ? (int)$data['usuario_id'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Confirma la tarea (la marca como HECHA).
     */
    public function confirmar(int $id, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tareas
             SET estado = 'HECHA', completada_at = NOW(), completada_por = :usuario_id
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    }

    /**
     * Reabre una tarea confirmada.
     */
    public function reabrir(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tareas
             SET estado = 'PENDIENTE', completada_at = NULL, completada_por = NULL
             WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tareas WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function countPendientes(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM tareas WHERE estado = 'PENDIENTE'")->fetchColumn();
    }

    /**
     * Tenants para poder asociar una tarea a una empresa.
     */
    public function getTenants(): array
    {
        $stmt = $this->db->query("SELECT id, nombre FROM tenants WHERE activo = 1 ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
