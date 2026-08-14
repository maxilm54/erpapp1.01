<?php
require_once BASE_PATH . '/app/core/Model.php';

class CreditoBancario extends Model {

    public function __construct(){
        $this->db = Database::getInstance();
    }

    public function getAll(): array{
        $stmt = $this->db->query("
            SELECT cb.*, caja.nombre AS caja_nombre, caja.tipo AS caja_tipo,
                   u.nombre AS usuario_nombre,
                   (SELECT COUNT(*) FROM creditos_pagos cp WHERE cp.credito_id = cb.id AND cp.estado = 'PENDIENTE') AS cuotas_pendientes,
                   (SELECT COUNT(*) FROM creditos_pagos cp WHERE cp.credito_id = cb.id AND cp.estado = 'PAGADO') AS cuotas_pagadas
            FROM creditos_bancarios cb
            LEFT JOIN cajas_bancos caja ON caja.id = cb.caja_banco_id
            LEFT JOIN users u ON u.id = cb.usuario_id
            ORDER BY cb.fecha_desembolso DESC, cb.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT cb.*, caja.nombre AS caja_nombre, caja.tipo AS caja_tipo,
                   u.nombre AS usuario_nombre
            FROM creditos_bancarios cb
            LEFT JOIN cajas_bancos caja ON caja.id = cb.caja_banco_id
            LEFT JOIN users u ON u.id = cb.usuario_id
            WHERE cb.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getCuotas(int $creditoId): array{
        $stmt = $this->db->prepare("
            SELECT cp.*, caja.nombre AS caja_nombre
            FROM creditos_pagos cp
            LEFT JOIN cajas_bancos caja ON caja.id = cp.caja_banco_id
            WHERE cp.credito_id = :credito_id
            ORDER BY cp.numero_cuota ASC
        ");
        $stmt->execute([':credito_id' => $creditoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCuota(int $cuotaId): ?array{
        $stmt = $this->db->prepare("SELECT * FROM creditos_pagos WHERE id = :id");
        $stmt->execute([':id' => $cuotaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO creditos_bancarios
            (caja_banco_id, entidad, monto_original, tasa_interes, cantidad_cuotas, monto_cuota,
             fecha_desembolso, fecha_vencimiento, tipo, moneda, saldo_actual, observaciones, usuario_id)
            VALUES (:caja_banco_id, :entidad, :monto_original, :tasa_interes, :cantidad_cuotas, :monto_cuota,
                    :fecha_desembolso, :fecha_vencimiento, :tipo, :moneda, :saldo_actual, :observaciones, :usuario_id)
        ");
        $stmt->execute([
            ':caja_banco_id'       => $data['caja_banco_id'],
            ':entidad'             => $data['entidad'],
            ':monto_original'      => $data['monto_original'],
            ':tasa_interes'        => $data['tasa_interes'] ?? 0,
            ':cantidad_cuotas'     => $data['cantidad_cuotas'],
            ':monto_cuota'         => $data['monto_cuota'],
            ':fecha_desembolso'    => $data['fecha_desembolso'],
            ':fecha_vencimiento'   => $data['fecha_vencimiento'] ?? null,
            ':tipo'                => $data['tipo'] ?? 'FIJO',
            ':moneda'              => $data['moneda'] ?? 'ARS',
            ':saldo_actual'        => $data['monto_original'],
            ':observaciones'       => $data['observaciones'] ?? null,
            ':usuario_id'          => $data['usuario_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool{
        $stmt = $this->db->prepare("
            UPDATE creditos_bancarios SET
                entidad = :entidad, tasa_interes = :tasa_interes,
                observaciones = :observaciones
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'             => $id,
            ':entidad'        => $data['entidad'],
            ':tasa_interes'   => $data['tasa_interes'] ?? 0,
            ':observaciones'  => $data['observaciones'] ?? null,
        ]);
    }

    public function generateCuotas(int $creditoId): void{
        $credito = $this->findById($creditoId);
        if (!$credito) throw new Exception("Credito no encontrado");

        $montoTotal = (float)$credito['monto_original'];
        $cantidadCuotas = (int)$credito['cantidad_cuotas'];
        $tasaAnual = (float)$credito['tasa_interes'];
        $tasaMensual = $tasaAnual > 0 ? ($tasaAnual / 12 / 100) : 0;

        // Calcular cuota fija (sistema frances)
        if ($tasaMensual > 0) {
            $montoCuota = $montoTotal * ($tasaMensual * pow(1 + $tasaMensual, $cantidadCuotas))
                        / (pow(1 + $tasaMensual, $cantidadCuotas) - 1);
        } else {
            $montoCuota = $montoTotal / $cantidadCuotas;
        }

        $saldo = $montoTotal;
        $fechaBase = new DateTime($credito['fecha_desembolso']);
        $ultimaFecha = null;

        for ($i = 1; $i <= $cantidadCuotas; $i++) {
            $interesCuota = round($saldo * $tasaMensual, 2);
            $capitalCuota = round($montoCuota - $interesCuota, 2);

            // Ajuste en la ultima cuota
            if ($i === $cantidadCuotas) {
                $capitalCuota = round($saldo, 2);
                $montoCuotaReal = round($capitalCuota + $interesCuota, 2);
            } else {
                $montoCuotaReal = round($montoCuota, 2);
            }

            $fechaVenc = clone $fechaBase;
            $fechaVenc->modify("+{$i} months");
            $ultimaFecha = $fechaVenc->format('Y-m-d');

            $stmt = $this->db->prepare("
                INSERT INTO creditos_pagos
                (credito_id, numero_cuota, monto, capital, interes, fecha_vencimiento, estado)
                VALUES (:credito_id, :numero_cuota, :monto, :capital, :interes, :fecha_vencimiento, 'PENDIENTE')
            ");
            $stmt->execute([
                ':credito_id'       => $creditoId,
                ':numero_cuota'     => $i,
                ':monto'            => $montoCuotaReal,
                ':capital'          => $capitalCuota,
                ':interes'          => $interesCuota,
                ':fecha_vencimiento' => $ultimaFecha,
            ]);

            $saldo -= $capitalCuota;
        }

        // Actualizar fecha de vencimiento del credito
        $stmt = $this->db->prepare("UPDATE creditos_bancarios SET monto_cuota = :monto, fecha_vencimiento = :fv WHERE id = :id");
        $stmt->execute([':monto' => round($montoCuota, 2), ':fv' => $ultimaFecha, ':id' => $creditoId]);
    }

    public function pagarCuota(int $cuotaId, int $cajaBancoId, int $usuarioId, ?string $observaciones = null): array{
        $cuota = $this->getCuota($cuotaId);
        if (!$cuota) throw new Exception("Cuota no encontrada");
        if ($cuota['estado'] === 'PAGADO') throw new Exception("Esta cuota ya esta pagada");

        $credito = $this->findById($cuota['credito_id']);
        if (!$credito) throw new Exception("Credito no encontrado");

        // Actualizar cuota
        $stmt = $this->db->prepare("
            UPDATE creditos_pagos SET
                estado = 'PAGADO', fecha_pago = CURDATE(),
                caja_banco_id = :caja_banco_id, observaciones = :observaciones
            WHERE id = :id
        ");
        $stmt->execute([
            ':caja_banco_id' => $cajaBancoId,
            ':observaciones' => $observaciones,
            ':id'            => $cuotaId,
        ]);

        // Actualizar saldo del credito
        $nuevoSaldo = (float)$credito['saldo_actual'] - (float)$cuota['capital'];
        $stmt = $this->db->prepare("UPDATE creditos_bancarios SET saldo_actual = :saldo WHERE id = :id");
        $stmt->execute([':saldo' => max(0, $nuevoSaldo), ':id' => $credito['id']]);

        // Verificar si quedan cuotas pendientes
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM creditos_pagos WHERE credito_id = :id AND estado = 'PENDIENTE'");
        $stmt->execute([':id' => $credito['id']]);
        $pendientes = (int)$stmt->fetchColumn();

        if ($pendientes === 0) {
            $stmt = $this->db->prepare("UPDATE creditos_bancarios SET estado = 'PAGADO', saldo_actual = 0 WHERE id = :id");
            $stmt->execute([':id' => $credito['id']]);
        }

        return $cuota;
    }

    public function getDashboard(): array{
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_creditos,
                SUM(CASE WHEN estado = 'ACTIVO' THEN 1 ELSE 0 END) AS creditos_activos,
                SUM(CASE WHEN estado = 'ACTIVO' THEN saldo_actual ELSE 0 END) AS saldo_total,
                SUM(CASE WHEN estado = 'ACTIVO' THEN monto_original ELSE 0 END) AS monto_total_original
            FROM creditos_bancarios
        ");
        $creditos = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $this->db->query("
            SELECT
                COUNT(*) AS total_cuotas,
                SUM(CASE WHEN estado = 'PENDIENTE' THEN monto ELSE 0 END) AS cuotas_pendientes_monto,
                SUM(CASE WHEN estado = 'PAGADO' THEN monto ELSE 0 END) AS cuotas_pagadas_monto
            FROM creditos_pagos
        ");
        $cuotas = $stmt2->fetch(PDO::FETCH_ASSOC);

        $stmt3 = $this->db->query("
            SELECT cp.*, cb.entidad
            FROM creditos_pagos cp
            INNER JOIN creditos_bancarios cb ON cb.id = cp.credito_id
            WHERE cp.estado = 'PENDIENTE'
            ORDER BY cp.fecha_vencimiento ASC
            LIMIT 10
        ");
        $proximas = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        return [
            'creditos'   => $creditos,
            'cuotas'     => $cuotas,
            'proximas'   => $proximas,
        ];
    }

    public function cancelarCredito(int $creditoId): bool{
        $stmt = $this->db->prepare("UPDATE creditos_bancarios SET estado = 'CANCELADO' WHERE id = :id AND estado = 'ACTIVO'");
        $stmt->execute([':id' => $creditoId]);

        $stmt2 = $this->db->prepare("UPDATE creditos_pagos SET estado = 'VENCIDO' WHERE credito_id = :id AND estado = 'PENDIENTE'");
        $stmt2->execute([':id' => $creditoId]);

        return true;
    }
}
