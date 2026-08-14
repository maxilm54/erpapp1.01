<?php
require_once BASE_PATH . '/app/core/Model.php';
class MailLog extends Model
{
    protected string $table = 'mails_log';

    public function registrar(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO mails_log
            (tipo, referencia_id, email_destino, asunto, estado, error, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['tipo'],
            $data['referencia_id'],
            $data['email'],
            $data['asunto'],
            $data['estado'],
            $data['error'] ?? null,
            $data['usuario_id'] ?? null
        ]);
    }

    /**
     * Listar emails enviados con filtros.
     */
    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = 'l.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'l.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(l.email_destino LIKE ? OR l.asunto LIKE ?)';
            $busqueda = '%' . $filtros['buscar'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'l.enviado_at >= ?';
            $params[] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'l.enviado_at <= ?';
            $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $sql = "
            SELECT l.*, u.nombre AS usuario_nombre
            FROM mails_log l
            LEFT JOIN users u ON u.id = l.usuario_id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY l.enviado_at DESC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener estadísticas de emails.
     */
    public function stats(): array
    {
        $stmt = $this->db->query("
            SELECT
                tipo,
                estado,
                COUNT(*) AS cantidad
            FROM mails_log
            WHERE enviado_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY tipo, estado
            ORDER BY tipo, estado
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
