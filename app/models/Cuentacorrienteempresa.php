<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Cuenta corriente de la empresa.
 * INGRESO = entra dinero a la empresa. GASTO = sale dinero de la empresa.
 * Cuando se informa proveedor_id, el movimiento se computa en la deuda de ese proveedor.
 */
class CuentaCorrienteEmpresa extends Model
{
    public function create(array $data): int
    {
        $sql = "INSERT INTO cuentas_corrientes_empresa
                (tipo, descripcion, monto, fecha, categoria_id, proveedor_id, referencia_id, referencia_tipo, usuario_id)
                VALUES (:tipo, :descripcion, :monto, :fecha, :categoria_id, :proveedor_id, :referencia_id, :referencia_tipo, :usuario_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tipo'           => strtoupper($data['tipo']),
            ':descripcion'    => $data['descripcion'],
            ':monto'          => (float)$data['monto'],
            ':fecha'          => $data['fecha'] ?? date('Y-m-d H:i:s'),
            ':categoria_id'   => !empty($data['categoria_id']) ? (int)$data['categoria_id'] : null,
            ':proveedor_id'   => !empty($data['proveedor_id']) ? (int)$data['proveedor_id'] : null,
            ':referencia_id'  => !empty($data['referencia_id']) ? (int)$data['referencia_id'] : null,
            ':referencia_tipo' => $data['referencia_tipo'] ?? null,
            ':usuario_id'     => (int)$data['usuario_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Lista los movimientos con filtros opcionales.
     * Filtros: tipo, categoria_id, proveedor_id, fecha_desde, fecha_hasta, q
     */
    public function findAll(array $filters = []): array
    {
        $sql = "SELECT c.*, cat.nombre AS categoria_nombre, p.razon_social AS proveedor_nombre, u.nombre AS usuario_nombre
                FROM cuentas_corrientes_empresa c
                LEFT JOIN categorias_gastos_ingresos cat ON cat.id = c.categoria_id
                LEFT JOIN proveedores p ON p.id = c.proveedor_id
                LEFT JOIN users u ON u.id = c.usuario_id
                WHERE c.activo = 1";

        $params = [];
        $where = [];

        if (!empty($filters['tipo'])) {
            $where[] = "c.tipo = :tipo";
            $params[':tipo'] = strtoupper($filters['tipo']);
        }
        if (!empty($filters['categoria_id'])) {
            $where[] = "c.categoria_id = :categoria_id";
            $params[':categoria_id'] = (int)$filters['categoria_id'];
        }
        if (!empty($filters['proveedor_id'])) {
            $where[] = "c.proveedor_id = :proveedor_id";
            $params[':proveedor_id'] = (int)$filters['proveedor_id'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[] = "c.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "c.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['q'])) {
            $where[] = "c.descripcion LIKE :q";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if ($where) $sql .= " AND " . implode(' AND ', $where);
        $sql .= " ORDER BY c.fecha DESC, c.id DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cantidad de movimientos que cumplen los filtros (sin paginar).
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM cuentas_corrientes_empresa c WHERE c.activo = 1";
        $params = [];

        if (!empty($filters['tipo'])) {
            $sql .= " AND c.tipo = :tipo";
            $params[':tipo'] = strtoupper($filters['tipo']);
        }
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND c.categoria_id = :categoria_id";
            $params[':categoria_id'] = (int)$filters['categoria_id'];
        }
        if (!empty($filters['proveedor_id'])) {
            $sql .= " AND c.proveedor_id = :proveedor_id";
            $params[':proveedor_id'] = (int)$filters['proveedor_id'];
        }
        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND c.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND c.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND c.descripcion LIKE :q";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, cat.nombre AS categoria_nombre, p.razon_social AS proveedor_nombre
             FROM cuentas_corrientes_empresa c
             LEFT JOIN categorias_gastos_ingresos cat ON cat.id = c.categoria_id
             LEFT JOIN proveedores p ON p.id = c.proveedor_id
             WHERE c.id = :id AND c.activo = 1"
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE cuentas_corrientes_empresa SET
                    tipo = :tipo,
                    descripcion = :descripcion,
                    monto = :monto,
                    fecha = :fecha,
                    categoria_id = :categoria_id,
                    proveedor_id = :proveedor_id
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'           => $id,
            ':tipo'         => strtoupper($data['tipo']),
            ':descripcion'  => $data['descripcion'],
            ':monto'        => (float)$data['monto'],
            ':fecha'        => $data['fecha'],
            ':categoria_id' => !empty($data['categoria_id']) ? (int)$data['categoria_id'] : null,
            ':proveedor_id' => !empty($data['proveedor_id']) ? (int)$data['proveedor_id'] : null,
        ]);
    }

    /**
     * Baja logica. Los movimientos contables no se borran fisicamente.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE cuentas_corrientes_empresa SET activo = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Saldo global de la empresa: ingresos - gastos, sobre el conjunto filtrado.
     */
    public function getBalance(array $filters = []): array
    {
        $result = ['ingresos' => 0.0, 'gastos' => 0.0, 'balance' => 0.0];

        $sql = "SELECT tipo, COALESCE(SUM(monto), 0) AS total
                FROM cuentas_corrientes_empresa
                WHERE activo = 1";
        $params = [];

        if (!empty($filters['tipo'])) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = strtoupper($filters['tipo']);
        }
        if (!empty($filters['categoria_id'])) {
            $sql .= " AND categoria_id = :categoria_id";
            $params[':categoria_id'] = (int)$filters['categoria_id'];
        }
        if (!empty($filters['proveedor_id'])) {
            $sql .= " AND proveedor_id = :proveedor_id";
            $params[':proveedor_id'] = (int)$filters['proveedor_id'];
        }
        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND descripcion LIKE :q";
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        $sql .= " GROUP BY tipo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['tipo'] === 'INGRESO') {
                $result['ingresos'] = (float)$row['total'];
            } elseif ($row['tipo'] === 'GASTO') {
                $result['gastos'] = (float)$row['total'];
            }
        }
        $result['balance'] = $result['ingresos'] - $result['gastos'];
        return $result;
    }

    /**
     * Deuda por proveedor: lo que la empresa le debe a cada proveedor.
     * Positivo = debe. Negativo = la empresa esta a favor.
     */
    public function getSaldosPorProveedor(): array
    {
        $sql = "SELECT p.id, p.razon_social,
                       COALESCE(SUM(CASE WHEN c.tipo = 'GASTO' THEN c.monto
                                         WHEN c.tipo = 'INGRESO' THEN -c.monto END), 0) AS saldo
                FROM proveedores p
                LEFT JOIN cuentas_corrientes_empresa c ON c.proveedor_id = p.id AND c.activo = 1
                WHERE p.activo = 1
                GROUP BY p.id, p.razon_social
                HAVING saldo != 0
                ORDER BY saldo DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategorias(?string $tipo = null): array
    {
        $sql = "SELECT * FROM categorias_gastos_ingresos WHERE activo = 1";
        $params = [];
        if ($tipo) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = strtoupper($tipo);
        }
        $sql .= " ORDER BY tipo, nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProveedores(): array
    {
        $stmt = $this->db->query("SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
