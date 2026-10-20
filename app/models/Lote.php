<?php
require_once BASE_PATH . '/app/core/Model.php';

class Lote extends Model
{
    public function all(): array
    {
        return $this->db->query("
            SELECT l.*,
                   COALESCE(p.nombre, mp.nombre) AS producto_nombre,
                   CASE WHEN l.producto_id IS NOT NULL THEN 'PRODUCTO' ELSE 'MATERIA_PRIMA' END AS tipo
            FROM lotes l
            LEFT JOIN productos p ON p.id = l.producto_id
            LEFT JOIN materias_primas mp ON mp.id = l.materia_prima_id
            WHERE l.activo = 1
            ORDER BY l.fecha_vencimiento ASC, l.codigo_lote ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(p.nombre, mp.nombre) AS producto_nombre
            FROM lotes l
            LEFT JOIN productos p ON p.id = l.producto_id
            LEFT JOIN materias_primas mp ON mp.id = l.materia_prima_id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO lotes (codigo_lote, numero_serie, producto_id, materia_prima_id, fecha_vencimiento, fecha_produccion)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['codigo_lote'],
            $data['numero_serie'] ?? null,
            $data['producto_id'] ?? null,
            $data['materia_prima_id'] ?? null,
            $data['fecha_vencimiento'] ?? null,
            $data['fecha_produccion'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findOrCreate(array $data): int
    {
        $productoId = $data['producto_id'] ?? null;
        $materiaPrimaId = $data['materia_prima_id'] ?? null;
        $codigoLote = trim($data['codigo_lote'] ?? '');
        $numeroSerie = trim($data['numero_serie'] ?? '');

        if (empty($codigoLote)) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT id FROM lotes
            WHERE codigo_lote = ?
              AND ((producto_id = ? AND ? IS NOT NULL) OR (materia_prima_id = ? AND ? IS NOT NULL))
              AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$codigoLote, $productoId, $productoId, $materiaPrimaId, $materiaPrimaId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Actualizar serie si se proporcionó
            if (!empty($numeroSerie)) {
                $this->db->prepare("UPDATE lotes SET numero_serie = ? WHERE id = ?")
                    ->execute([$numeroSerie, $existing['id']]);
            }
            return (int)$existing['id'];
        }

        return $this->create($data);
    }

    public function searchByProducto(int $productoId, ?int $depositoId = null): array
    {
        $sql = "
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.producto_id = ?
            WHERE l.producto_id = ? AND l.activo = 1
        ";
        $params = [$productoId, $productoId];

        if ($depositoId) {
            $sql .= " AND (m.deposito_id = ? OR m.deposito_id IS NULL)";
            $params[] = $depositoId;
        }

        $sql .= " GROUP BY l.id ORDER BY l.fecha_vencimiento ASC, l.codigo_lote ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchByMateriaPrima(int $materiaPrimaId, ?int $depositoId = null): array
    {
        $sql = "
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.materia_prima_id = ?
            WHERE l.materia_prima_id = ? AND l.activo = 1
        ";
        $params = [$materiaPrimaId, $materiaPrimaId];

        if ($depositoId) {
            $sql .= " AND (m.deposito_id = ? OR m.deposito_id IS NULL)";
            $params[] = $depositoId;
        }

        $sql .= " GROUP BY l.id ORDER BY l.fecha_vencimiento ASC, l.codigo_lote ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(string $q): array
    {
        if (strlen($q) < 2) return [];

        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(p.nombre, mp.nombre) AS producto_nombre
            FROM lotes l
            LEFT JOIN productos p ON p.id = l.producto_id
            LEFT JOIN materias_primas mp ON mp.id = l.materia_prima_id
            WHERE l.codigo_lote LIKE ?
               OR p.nombre LIKE ?
               OR mp.nombre LIKE ?
            LIMIT 20
        ");
        $like = "%{$q}%";
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lotes SET codigo_lote = ?, numero_serie = ?, fecha_vencimiento = ?, fecha_produccion = ?
            WHERE id = ?");
        return $stmt->execute([
            $data['codigo_lote'],
            $data['numero_serie'] ?? null,
            $data['fecha_vencimiento'] ?? null,
            $data['fecha_produccion'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare(
            "UPDATE lotes SET activo = 0 WHERE id = ?"
        )->execute([$id]);
    }

    /** Lotes vencidos de un producto con stock > 0 */
    public function expiredByProducto(int $productoId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.producto_id = ?
            WHERE l.producto_id = ? AND l.activo = 1 AND l.fecha_vencimiento < CURDATE()
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        $stmt->execute([$productoId, $productoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lotes próximos a vencer (30 días) de un producto */
    public function expiringSoonByProducto(int $productoId, int $dias = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.producto_id = ?
            WHERE l.producto_id = ? AND l.activo = 1
              AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        $stmt->execute([$productoId, $productoId, $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

/** Lotes vencidos de una materia prima con stock > 0 */
    public function expiredByMateriaPrima(int $mpId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.materia_prima_id = ?
            WHERE l.materia_prima_id = ? AND l.activo = 1 AND l.fecha_vencimiento < CURDATE()
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        $stmt->execute([$mpId, $mpId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lotes próximos a vencer de una materia prima */
    public function expiringSoonByMateriaPrima(int $mpId, int $dias = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id AND m.materia_prima_id = ?
            WHERE l.materia_prima_id = ? AND l.activo = 1
              AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        $stmt->execute([$mpId, $mpId, $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Listado central: todos los lotes vencidos (productos + materias primas) con stock > 0 */
    public function allExpired(): array
    {
        $stmt = $this->db->query("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual,
                   p.nombre AS producto_nombre,
                   mp.nombre AS mp_nombre
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id
            LEFT JOIN productos p ON p.id = l.producto_id
            LEFT JOIN materias_primas mp ON mp.id = l.materia_prima_id
            WHERE l.activo = 1 AND l.fecha_vencimiento < CURDATE()
              AND (l.producto_id IS NOT NULL OR l.materia_prima_id IS NOT NULL)
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Listado central: todos los lotes próximos a vencer (productos + materias primas) */
    public function allExpiringSoon(int $dias = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   COALESCE(SUM(
                       CASE WHEN m.tipo IN ('ENTRADA','AJUSTE') THEN m.cantidad
                            WHEN m.tipo = 'SALIDA' THEN -m.cantidad END
                   ), 0) AS stock_actual,
                   p.nombre AS producto_nombre,
                   mp.nombre AS mp_nombre
            FROM lotes l
            LEFT JOIN movimientos_stock m ON m.lote_id = l.id
            LEFT JOIN productos p ON p.id = l.producto_id
            LEFT JOIN materias_primas mp ON mp.id = l.materia_prima_id
            WHERE l.activo = 1
              AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND (l.producto_id IS NOT NULL OR l.materia_prima_id IS NOT NULL)
            GROUP BY l.id
            HAVING stock_actual > 0
            ORDER BY l.fecha_vencimiento ASC
        ");
        $stmt->execute([$dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}