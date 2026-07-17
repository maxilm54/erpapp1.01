<?php
require_once BASE_PATH . '/app/core/Model.php';

class CajaBanco extends Model{

    public function getAll(): array{
        $stmt = $this->db->query("
            SELECT cb.*, cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre
            FROM cajas_bancos cb
            LEFT JOIN cuentas_contables cc ON cc.id = cb.cuenta_contable_id
            ORDER BY cb.tipo, cb.nombre
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActivas(): array{
        $stmt = $this->db->query("
            SELECT cb.*, cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre
            FROM cajas_bancos cb
            LEFT JOIN cuentas_contables cc ON cc.id = cb.cuenta_contable_id
            WHERE cb.activa = 1
            ORDER BY cb.tipo, cb.nombre
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT cb.*, cc.codigo AS cuenta_codigo, cc.nombre AS cuenta_nombre
            FROM cajas_bancos cb
            LEFT JOIN cuentas_contables cc ON cc.id = cb.cuenta_contable_id
            WHERE cb.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO cajas_bancos (nombre, tipo, banco, numero_cuenta, cbu, saldo_inicial, saldo_actual, moneda, cuenta_contable_id)
            VALUES (:nombre, :tipo, :banco, :numero_cuenta, :cbu, :saldo_inicial, :saldo_actual, :moneda, :cuenta_contable_id)
        ");
        $stmt->execute([
            ':nombre'              => $data['nombre'],
            ':tipo'                => $data['tipo'],
            ':banco'               => $data['banco'] ?? null,
            ':numero_cuenta'       => $data['numero_cuenta'] ?? null,
            ':cbu'                 => $data['cbu'] ?? null,
            ':saldo_inicial'       => $data['saldo_inicial'] ?? 0,
            ':saldo_actual'        => $data['saldo_inicial'] ?? 0,
            ':moneda'              => $data['moneda'] ?? 'ARS',
            ':cuenta_contable_id'  => $data['cuenta_contable_id'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $stmt = $this->db->prepare("
            UPDATE cajas_bancos SET
                nombre = :nombre, tipo = :tipo, banco = :banco,
                numero_cuenta = :numero_cuenta, cbu = :cbu,
                cuenta_contable_id = :cuenta_contable_id, activa = :activa
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'                 => $id,
            ':nombre'             => $data['nombre'],
            ':tipo'               => $data['tipo'],
            ':banco'              => $data['banco'] ?? null,
            ':numero_cuenta'      => $data['numero_cuenta'] ?? null,
            ':cbu'                => $data['cbu'] ?? null,
            ':cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            ':activa'             => $data['activa'] ?? 1,
        ]);
    }

    /**
     * Registrar un movimiento en caja/banco y actualizar saldo.
     */
    public function registrarMovimiento(array $data): int{
        try {
            $caja = $this->findById($data['caja_banco_id']);
            if (!$caja) throw new Exception("Caja/Banco no encontrado");

            $saldoAnterior = (float)$caja['saldo_actual'];
            $monto = (float)$data['monto'];

            if ($data['tipo'] === 'INGRESO') {
                $saldoPosterior = $saldoAnterior + $monto;
            } else {
                $saldoPosterior = $saldoAnterior - $monto;
            }

            // Insertar movimiento
            $stmt = $this->db->prepare("
                INSERT INTO movimientos_caja
                (caja_banco_id, fecha, tipo, monto, saldo_anterior, saldo_posterior, descripcion,
                 asiento_contable_id, referencia_modulo, referencia_tipo, referencia_id, usuario_id)
                VALUES (:caja_banco_id, :fecha, :tipo, :monto, :saldo_anterior, :saldo_posterior, :descripcion,
                        :asiento_contable_id, :referencia_modulo, :referencia_tipo, :referencia_id, :usuario_id)
            ");
            $stmt->execute([
                ':caja_banco_id'       => $data['caja_banco_id'],
                ':fecha'               => $data['fecha'],
                ':tipo'                => $data['tipo'],
                ':monto'               => $monto,
                ':saldo_anterior'      => $saldoAnterior,
                ':saldo_posterior'     => $saldoPosterior,
                ':descripcion'         => $data['descripcion'] ?? null,
                ':asiento_contable_id' => $data['asiento_contable_id'] ?? null,
                ':referencia_modulo'   => $data['referencia_modulo'] ?? null,
                ':referencia_tipo'     => $data['referencia_tipo'] ?? null,
                ':referencia_id'       => $data['referencia_id'] ?? null,
                ':usuario_id'          => $data['usuario_id'],
            ]);
            $movimientoId = (int)$this->db->lastInsertId();

            // Actualizar saldo de la caja
            $stmt2 = $this->db->prepare("UPDATE cajas_bancos SET saldo_actual = :saldo WHERE id = :id");
            $stmt2->execute([':saldo' => $saldoPosterior, ':id' => $data['caja_banco_id']]);

            return $movimientoId;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Obtener movimientos de una caja/banco.
     */
    /**
     * Buscar un movimiento de caja por ID.
     */
    public function findMovimiento(int $id): ?array{
        $stmt = $this->db->prepare("SELECT * FROM movimientos_caja WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getMovimientos(int $cajaBancoId, array $filters = []): array{
        $sql = "
            SELECT mc.*, u.nombre AS usuario_nombre
            FROM movimientos_caja mc
            LEFT JOIN users u ON u.id = mc.usuario_id
            WHERE mc.caja_banco_id = :caja_banco_id
        ";
        $params = [':caja_banco_id' => $cajaBancoId];

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND mc.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND mc.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        $sql .= " ORDER BY mc.fecha DESC, mc.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen de saldos de todas las cajas/bancos.
     */
    public function getResumenSaldos(): array{
        $stmt = $this->db->query("
            SELECT tipo, SUM(saldo_actual) AS total
            FROM cajas_bancos
            WHERE activa = 1
            GROUP BY tipo
        ");
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['tipo']] = (float)$row['total'];
        }
        return $result;
    }

    /**
     * Busca el último movimiento por referencia (módulo, tipo, id).
     */
    public function getMovimientosByReferencia(string $modulo, string $tipo, int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT * FROM movimientos_caja
            WHERE referencia_modulo = :modulo AND referencia_tipo = :tipo AND referencia_id = :id
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':modulo' => $modulo, ':tipo' => $tipo, ':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
