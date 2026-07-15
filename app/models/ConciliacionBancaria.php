<?php
require_once BASE_PATH . '/app/core/Model.php';

class ConciliacionBancaria extends Model{

    public function getAll(int $cajaBancoId): array{
        $stmt = $this->db->prepare("
            SELECT cb.*, u.nombre AS usuario_nombre
            FROM conciliaciones_bancarias cb
            LEFT JOIN users u ON u.id = cb.usuario_id
            WHERE cb.caja_banco_id = :caja_banco_id
            ORDER BY cb.fecha_conciliacion DESC
        ");
        $stmt->execute([':caja_banco_id' => $cajaBancoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT cb.*, u.nombre AS usuario_nombre
            FROM conciliaciones_bancarias cb
            LEFT JOIN users u ON u.id = cb.usuario_id
            WHERE cb.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $conciliacion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conciliacion) return null;

        $stmt2 = $this->db->prepare("
            SELECT cd.*, mc.descripcion AS mov_descripcion, mc.fecha AS mov_fecha
            FROM conciliaciones_detalle cd
            LEFT JOIN movimientos_caja mc ON mc.id = cd.movimiento_caja_id
            WHERE cd.conciliacion_id = :conciliacion_id
            ORDER BY cd.fecha_movimiento
        ");
        $stmt2->execute([':conciliacion_id' => $id]);
        $conciliacion['detalle'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        return $conciliacion;
    }

    /**
     * Crear conciliación con sus movimientos.
     * $movimientos = [['movimiento_caja_id' => N, 'conciliado' => 1/0], ...]
     */
    public function create(array $data, array $movimientos): int{
        $this->db->beginTransaction();

        try {
            // Calcular saldo según sistema (movimientos no conciliados hasta la fecha)
            $saldoSistema = $this->calcularSaldoSegunSistema(
                $data['caja_banco_id'],
                $data['fecha_conciliacion']
            );

            $diferencia = (float)$data['saldo_segun_banco'] - $saldoSistema;

            // Crear cabecera
            $stmt = $this->db->prepare("
                INSERT INTO conciliaciones_bancarias
                (caja_banco_id, fecha_conciliacion, saldo_segun_banco, saldo_segun_sistema, diferencia, observaciones, estado, usuario_id)
                VALUES (:caja_banco_id, :fecha_conciliacion, :saldo_segun_banco, :saldo_segun_sistema, :diferencia, :observaciones, :estado, :usuario_id)
            ");
            $stmt->execute([
                ':caja_banco_id'       => $data['caja_banco_id'],
                ':fecha_conciliacion'  => $data['fecha_conciliacion'],
                ':saldo_segun_banco'   => $data['saldo_segun_banco'],
                ':saldo_segun_sistema' => $saldoSistema,
                ':diferencia'          => $diferencia,
                ':observaciones'       => $data['observaciones'] ?? null,
                ':estado'              => abs($diferencia) < 0.01 ? 'CONCILIADA' : 'PENDIENTE',
                ':usuario_id'          => $data['usuario_id'],
            ]);
            $conciliacionId = (int)$this->db->lastInsertId();

            // Insertar detalle
            $stmt2 = $this->db->prepare("
                INSERT INTO conciliaciones_detalle
                (conciliacion_id, movimiento_caja_id, fecha_movimiento, descripcion, monto, conciliado)
                VALUES (:conciliacion_id, :movimiento_caja_id, :fecha_movimiento, :descripcion, :monto, :conciliado)
            ");

            foreach ($movimientos as $mov) {
                $stmt2->execute([
                    ':conciliacion_id'      => $conciliacionId,
                    ':movimiento_caja_id'   => $mov['movimiento_caja_id'] ?? null,
                    ':fecha_movimiento'     => $mov['fecha_movimiento'],
                    ':descripcion'          => $mov['descripcion'],
                    ':monto'                => $mov['monto'],
                    ':conciliado'           => $mov['conciliado'] ?? 0,
                ]);
            }

            $this->db->commit();
            return $conciliacionId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtener movimientos no conciliados de una caja.
     */
    public function getMovimientosNoConciliados(int $cajaBancoId): array{
        $stmt = $this->db->prepare("
            SELECT mc.*
            FROM movimientos_caja mc
            WHERE mc.caja_banco_id = :caja_banco_id
              AND mc.id NOT IN (
                  SELECT cd.movimiento_caja_id
                  FROM conciliaciones_detalle cd
                  WHERE cd.movimiento_caja_id IS NOT NULL
                    AND cd.conciliado = 1
              )
            ORDER BY mc.fecha ASC
        ");
        $stmt->execute([':caja_banco_id' => $cajaBancoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular saldo del sistema (suma de movimientos hasta una fecha).
     */
    private function calcularSaldoSegunSistema(int $cajaBancoId, string $fecha): float{
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN tipo = 'EGRESO' THEN monto ELSE 0 END), 0) AS saldo
            FROM movimientos_caja
            WHERE caja_banco_id = :caja_banco_id AND fecha <= :fecha
        ");
        $stmt->execute([':caja_banco_id' => $cajaBancoId, ':fecha' => $fecha]);
        return (float)$stmt->fetchColumn();
    }
}
