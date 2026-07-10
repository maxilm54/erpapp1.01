<?php
require_once BASE_PATH . '/app/core/Model.php';

class Gasto extends Model{

    public function getAll(array $filters = []): array{
        $sql = "
            SELECT g.*, u.nombre AS usuario_nombre, oc.id AS oc_numero
            FROM gastos g
            LEFT JOIN users u ON u.id = g.usuario_id
            LEFT JOIN ordenes_compra oc ON oc.id = g.orden_compra_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['categoria'])) {
            $sql .= " AND g.categoria = :categoria";
            $params[':categoria'] = $filters['categoria'];
        }
        if (!empty($filters['estado'])) {
            $sql .= " AND g.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND g.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND g.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['buscar'])) {
            $sql .= " AND (g.descripcion LIKE :buscar OR g.comprobante LIKE :buscar2)";
            $params[':buscar'] = '%' . $filters['buscar'] . '%';
            $params[':buscar2'] = '%' . $filters['buscar'] . '%';
        }

        $sql .= " ORDER BY g.fecha DESC, g.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT g.*, u.nombre AS usuario_nombre,
                   oc.id AS oc_numero, oc.estado AS oc_estado,
                   p.razon_social AS proveedor_nombre,
                   ocd.total_oc, gpag.total_pagado,
                   COALESCE(ocd.total_oc, 0) - COALESCE(gpag.total_pagado, 0) AS oc_saldo_pendiente
            FROM gastos g
            LEFT JOIN users u ON u.id = g.usuario_id
            LEFT JOIN ordenes_compra oc ON oc.id = g.orden_compra_id
            LEFT JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(cantidad * precio_unitario) AS total_oc
                FROM ordenes_compra_detalle
                GROUP BY orden_compra_id
            ) ocd ON ocd.orden_compra_id = oc.id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(monto_total) AS total_pagado
                FROM gastos
                WHERE estado != 'ANULADO' AND orden_compra_id IS NOT NULL
                GROUP BY orden_compra_id
            ) gpag ON gpag.orden_compra_id = oc.id
            WHERE g.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int{
        $sql = "INSERT INTO gastos
                (fecha, categoria, descripcion, orden_compra_id, monto_total, medio_pago, comprobante, estado, usuario_id, observaciones)
                VALUES (:fecha, :categoria, :descripcion, :orden_compra_id, :monto_total, :medio_pago, :comprobante, :estado, :usuario_id, :observaciones)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fecha'           => $data['fecha'],
            ':categoria'       => $data['categoria'],
            ':descripcion'     => $data['descripcion'],
            ':orden_compra_id' => $data['orden_compra_id'] ?: null,
            ':monto_total'     => $data['monto_total'],
            ':medio_pago'      => $data['medio_pago'],
            ':comprobante'     => $data['comprobante'] ?: null,
            ':estado'          => $data['estado'] ?? 'BORRADOR',
            ':usuario_id'      => $data['usuario_id'],
            ':observaciones'   => $data['observaciones'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $sql = "UPDATE gastos SET
                    fecha = :fecha,
                    categoria = :categoria,
                    descripcion = :descripcion,
                    orden_compra_id = :orden_compra_id,
                    monto_total = :monto_total,
                    medio_pago = :medio_pago,
                    comprobante = :comprobante,
                    observaciones = :observaciones
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'              => $id,
            ':fecha'           => $data['fecha'],
            ':categoria'       => $data['categoria'],
            ':descripcion'     => $data['descripcion'],
            ':orden_compra_id' => $data['orden_compra_id'] ?: null,
            ':monto_total'     => $data['monto_total'],
            ':medio_pago'      => $data['medio_pago'],
            ':comprobante'     => $data['comprobante'] ?: null,
            ':observaciones'   => $data['observaciones'] ?: null,
        ]);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool{
        $estadosValidos = ['BORRADOR', 'APROBADO', 'PAGADO', 'ANULADO'];
        if (!in_array($nuevoEstado, $estadosValidos)) return false;

        $stmt = $this->db->prepare("UPDATE gastos SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);
    }

    /**
     * Resumen mensual: total general + totales por categoría.
     */
    public function getResumenMensual(int $mes, int $año): array{
        // Total general (excluyendo anulados)
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(monto_total), 0) AS total
            FROM gastos
            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
              AND estado != 'ANULADO'
        ");
        $stmt->execute([':mes' => $mes, ':anio' => $año]);
        $totalGeneral = (float)$stmt->fetchColumn();

        // Totales por categoría
        $stmt = $this->db->prepare("
            SELECT categoria, COALESCE(SUM(monto_total), 0) AS total, COUNT(*) AS cantidad
            FROM gastos
            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
              AND estado != 'ANULADO'
            GROUP BY categoria
            ORDER BY total DESC
        ");
        $stmt->execute([':mes' => $mes, ':anio' => $año]);
        $porCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totales por estado
        $stmt = $this->db->prepare("
            SELECT estado, COUNT(*) AS cantidad
            FROM gastos
            WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio
            GROUP BY estado
        ");
        $stmt->execute([':mes' => $mes, ':anio' => $año]);
        $porEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_general' => $totalGeneral,
            'por_categoria' => $porCategoria,
            'por_estado'    => $porEstado,
        ];
    }

    /**
     * Gastos agrupados por proveedor (solo categoría PROVEEDORES).
     */
    public function getPorProveedor(int $mes, int $año): array{
        $stmt = $this->db->prepare("
            SELECT p.razon_social AS proveedor, COALESCE(SUM(g.monto_total), 0) AS total
            FROM gastos g
            LEFT JOIN ordenes_compra oc ON oc.id = g.orden_compra_id
            LEFT JOIN proveedores p ON p.id = oc.proveedor_id
            WHERE MONTH(g.fecha) = :mes AND YEAR(g.fecha) = :anio
              AND g.categoria = 'PROVEEDORES' AND g.estado != 'ANULADO'
            GROUP BY p.razon_social
            ORDER BY total DESC
        ");
        $stmt->execute([':mes' => $mes, ':anio' => $año]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cantidad de gastos por estado (para badges del dashboard).
     */
    public function countByEstado(): array{
        $stmt = $this->db->query("
            SELECT estado, COUNT(*) AS cantidad
            FROM gastos
            GROUP BY estado
        ");
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['estado']] = (int)$row['cantidad'];
        }
        return $result;
    }

    /**
     * Últimos N gastos registrados.
     */
    public function getRecientes(int $limit = 5): array{
        $stmt = $this->db->prepare("
            SELECT g.*, u.nombre AS usuario_nombre
            FROM gastos g
            LEFT JOIN users u ON u.id = g.usuario_id
            ORDER BY g.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Trae órdenes de compra con monto total, pagado y faltante.
     * Solo OC con saldo pendiente (> 0).
     */
    public function getOCPendientes(): array{
        $stmt = $this->db->query("
            SELECT
                oc.id,
                oc.estado,
                p.razon_social AS proveedor_nombre,
                COALESCE(ocd.total_oc, 0) AS total_oc,
                COALESCE(gpagado.total_pagado, 0) AS total_pagado,
                COALESCE(ocd.total_oc, 0) - COALESCE(gpagado.total_pagado, 0) AS saldo_pendiente
            FROM ordenes_compra oc
            LEFT JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(cantidad * precio_unitario) AS total_oc
                FROM ordenes_compra_detalle
                GROUP BY orden_compra_id
            ) ocd ON ocd.orden_compra_id = oc.id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(monto_total) AS total_pagado
                FROM gastos
                WHERE estado != 'ANULADO' AND orden_compra_id IS NOT NULL
                GROUP BY orden_compra_id
            ) gpagado ON gpagado.orden_compra_id = oc.id
            WHERE oc.estado IN ('PENDIENTE','APROBADA','RECIBIDA','PARCIAL')
              AND (COALESCE(ocd.total_oc, 0) - COALESCE(gpagado.total_pagado, 0)) > 0
            ORDER BY oc.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el detalle de saldo de una OC específica.
     */
    public function getOCSaldo(int $ocId): ?array{
        $stmt = $this->db->prepare("
            SELECT
                oc.id,
                COALESCE(ocd.total_oc, 0) AS total_oc,
                COALESCE(gpagado.total_pagado, 0) AS total_pagado,
                COALESCE(ocd.total_oc, 0) - COALESCE(gpagado.total_pagado, 0) AS saldo_pendiente
            FROM ordenes_compra oc
            LEFT JOIN (
                SELECT orden_compra_id, SUM(cantidad * precio_unitario) AS total_oc
                FROM ordenes_compra_detalle
                WHERE orden_compra_id = :oc_id
                GROUP BY orden_compra_id
            ) ocd ON ocd.orden_compra_id = oc.id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(monto_total) AS total_pagado
                FROM gastos
                WHERE estado != 'ANULADO' AND orden_compra_id = :oc_id2
                GROUP BY orden_compra_id
            ) gpagado ON gpagado.orden_compra_id = oc.id
            WHERE oc.id = :oc_id3
        ");
        $stmt->execute([':oc_id' => $ocId, ':oc_id2' => $ocId, ':oc_id3' => $ocId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
