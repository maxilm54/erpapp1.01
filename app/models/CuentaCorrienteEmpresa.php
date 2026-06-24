<?php
require_once BASE_PATH . '/app/core/Model.php';

class CuentaCorrienteEmpresa extends Model
{
    public function create(array $data): bool
    {
        $sql = "INSERT INTO cuentas_corrientes_empresa 
                (tipo, descripcion, monto, fecha, categoria_id, referencia_id, referencia_tipo, usuario_id) 
                VALUES (:tipo, :descripcion, :monto, :fecha, :categoria_id, :referencia_id, :referencia_tipo, :usuario_id)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tipo' => $data['tipo'],
            ':descripcion' => $data['descripcion'],
            ':monto' => $data['monto'],
            ':fecha' => $data['fecha'] ?? date('Y-m-d H:i:s'),
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':referencia_id' => $data['referencia_id'] ?? null,
            ':referencia_tipo' => $data['referencia_tipo'] ?? null,
            ':usuario_id' => $data['usuario_id']
        ]);
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT c.*, u.nombre as usuarioNombre, cat.nombre as categoriaNombre 
                FROM cuentas_corrientes_empresa c 
                LEFT JOIN users u ON c.usuario_id = u.id 
                LEFT JOIN categorias_gastos_ingresos cat ON c.categoria_id = cat.id";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['tipo'])) {
            $where[] = "c.tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[] = "c.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "c.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY c.fecha DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cuentas_corrientes_empresa WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE cuentas_corrientes_empresa SET 
                    tipo = :tipo, 
                    descripcion = :descripcion, 
                    monto = :monto, 
                    fecha = :fecha,
                    categoria_id = :categoria_id
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':tipo' => $data['tipo'],
            ':descripcion' => $data['descripcion'],
            ':monto' => $data['monto'],
            ':fecha' => $data['fecha'],
            ':categoria_id' => $data['categoria_id'] ?? null
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cuentas_corrientes_empresa WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getBalance(): array
    {
        $sql = "SELECT tipo, SUM(monto) as total FROM cuentas_corrientes_empresa GROUP BY tipo";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $result = ['ingresos' => 0, 'gastos' => 0, 'balance' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['tipo'] === 'ingreso') {
                $result['ingresos'] = $row['total'];
            } elseif ($row['tipo'] === 'gasto') {
                $result['gastos'] = $row['total'];
            }
        }
        $result['balance'] = $result['ingresos'] - $result['gastos'];
        return $result;
    }
}