<?php
require_once BASE_PATH . '/app/core/Model.php';

/**
 * Cuenta corriente de las empresas (panel superadmin).
 *
 * Vive en la BD MASTER (app_master) porque la administra el superadmin.
 * Cada fila de ctacte_tenant es el registro mensual de UNA empresa:
 * sistema + soporte + desarrollo es el total facturado del mes, pago es lo
 * cobrado acumulado y saldo_pendiente es la diferencia.
 *
 * Los pagos se guardan en ctacte_tenant_pagos y su reparto entre registros
 * (siempre del vencimiento mas viejo al mas nuevo) en
 * ctacte_tenant_pago_aplicaciones.
 *
 * Los montos se calculan en centavos para no arrastrar errores de redondeo
 * de float. En la base quedan como DECIMAL(12,2).
 */
class Ctactetenant extends Model
{
    /** Importe maximo permitido por concepto/pago (DECIMAL(12,2)) */
    public const MONTO_MAXIMO = 9999999999.99;

    public function __construct()
    {
        $this->db = Database::getMaster();
    }

    // =====================================================
    // Consultas
    // =====================================================

    /**
     * Registros de cuenta corriente con filtros: tenant_id, estado, periodo,
     * vencidos (1 = solo con saldo y fecha de vencimiento pasada) y q (texto).
     * Los pendientes van primero, del vencimiento mas viejo al mas nuevo.
     */
    public function all(array $filters = []): array
    {
        $sql = "SELECT c.*, t.nombre AS tenant_nombre, t.dbname AS tenant_dbname,
                       t.activo AS tenant_activo, DATE(t.created_at) AS tenant_alta,
                       u.nombre AS actualizado_por_nombre
                FROM ctacte_tenant c
                INNER JOIN tenants t ON t.id = c.tenant_id
                LEFT JOIN users u ON u.id = c.updated_by
                WHERE 1 = 1";

        $params = [];

        if (!empty($filters['tenant_id'])) {
            $sql .= " AND c.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }
        if (!empty($filters['estado'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = strtoupper($filters['estado']);
        }
        if (!empty($filters['periodo'])) {
            $sql .= " AND c.periodo = :periodo";
            $params[':periodo'] = $filters['periodo'];
        }
        if (!empty($filters['vencidos'])) {
            $sql .= " AND c.saldo_pendiente > 0 AND c.fecha_vencimiento < CURDATE()";
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (t.nombre LIKE :q OR t.dbname LIKE :q2 OR c.obs LIKE :q3)";
            $like = '%' . $filters['q'] . '%';
            $params[':q'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sql .= " ORDER BY (c.saldo_pendiente > 0) DESC, c.fecha_vencimiento ASC, c.periodo ASC, c.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, t.nombre AS tenant_nombre, t.dbname AS tenant_dbname,
                    t.activo AS tenant_activo, DATE(t.created_at) AS tenant_alta
             FROM ctacte_tenant c
             INNER JOIN tenants t ON t.id = c.tenant_id
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Registros con saldo pendiente de una empresa, del mas viejo al mas nuevo.
     */
    public function pendientes(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ctacte_tenant
             WHERE tenant_id = :tenant_id AND saldo_pendiente > 0
             ORDER BY fecha_vencimiento ASC, periodo ASC, id ASC"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historial de pagos (con el nombre de la empresa, del usuario que lo
     * registro y del usuario que lo anulo). Incluye los anulados.
     */
    public function pagos(array $filters = []): array
    {
        $sql = "SELECT p.*, t.nombre AS tenant_nombre,
                       u.nombre AS usuario_nombre,
                       a.nombre AS anulado_por_nombre
                FROM ctacte_tenant_pagos p
                INNER JOIN tenants t ON t.id = p.tenant_id
                LEFT JOIN users u ON u.id = p.usuario_id
                LEFT JOIN users a ON a.id = p.anulado_por
                WHERE 1 = 1";
        $params = [];

        if (!empty($filters['tenant_id'])) {
            $sql .= " AND p.tenant_id = :tenant_id";
            $params[':tenant_id'] = (int)$filters['tenant_id'];
        }
        if (array_key_exists('activo', $filters) && $filters['activo'] !== '' && $filters['activo'] !== null) {
            $sql .= " AND p.activo = :activo";
            $params[':activo'] = (int)$filters['activo'];
        }

        $sql .= " ORDER BY p.fecha DESC, p.id DESC LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Totales generales (o de una empresa): facturado, cobrado, saldo y vencido.
     */
    public function resumen(?int $tenantId = null): array
    {
        $sql = "SELECT
                    COALESCE(SUM(c.sistema + c.soporte + c.desarrollo), 0) AS facturado,
                    COALESCE(SUM(COALESCE(c.pago, 0)), 0) AS cobrado,
                    COALESCE(SUM(c.saldo_pendiente), 0) AS saldo,
                    COALESCE(SUM(CASE WHEN c.saldo_pendiente > 0
                                       AND c.fecha_vencimiento < CURDATE()
                                      THEN c.saldo_pendiente ELSE 0 END), 0) AS vencido,
                    COALESCE(SUM(CASE WHEN c.saldo_pendiente > 0 THEN 1 ELSE 0 END), 0) AS pendientes
                FROM ctacte_tenant c";
        $params = [];

        if ($tenantId !== null) {
            $sql .= " WHERE c.tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'facturado'   => (float)($row['facturado'] ?? 0),
            'cobrado'     => (float)($row['cobrado'] ?? 0),
            'saldo'       => (float)($row['saldo'] ?? 0),
            'vencido'     => (float)($row['vencido'] ?? 0),
            'pendientes'  => (int)($row['pendientes'] ?? 0),
        ];
    }

    /**
     * Empresas para los combos. Con $soloConSaldo trae unicamente las que
     * tienen registros pendientes (para el formulario de pago).
     */
    public function getTenants(bool $soloConSaldo = false): array
    {
        if ($soloConSaldo) {
            $sql = "SELECT DISTINCT t.id, t.nombre, t.dbname
                    FROM tenants t
                    INNER JOIN ctacte_tenant c ON c.tenant_id = t.id
                    WHERE c.saldo_pendiente > 0
                    ORDER BY t.nombre";
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return $this->db->query(
            "SELECT id, nombre, dbname FROM tenants ORDER BY nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // Generacion mensual
    // =====================================================

    /**
     * Genera el registro del periodo para cada empresa (idempotente gracias a
     * la clave unica tenant_id + periodo).
     *
     * @param string|null $periodo      YYYY-MM (por defecto el mes actual)
     * @param int|null    $tenantId     limitar a una empresa
     * @param bool        $soloActivos  omitir empresas inactivas
     * @return int cantidad de registros creados
     */
    public function generarMes(?string $periodo = null, ?int $tenantId = null, bool $soloActivos = true): int
    {
        $periodo = $periodo !== null && $periodo !== '' ? $periodo : date('Y-m');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            throw new InvalidArgumentException('El periodo debe tener el formato AAAA-MM.');
        }

        $sql = "SELECT id FROM tenants";
        $where = [];
        $params = [];

        if ($tenantId !== null) {
            $where[] = "id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }
        if ($soloActivos) {
            $where[] = "activo = 1";
        }
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tenantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $creados = 0;
        foreach ($tenantIds as $id) {
            if ($this->asegurarRegistro((int)$id, $periodo)) {
                $creados++;
            }
        }

        return $creados;
    }

    /**
     * Crea el registro de un periodo puntual si no existe.
     * fecha_alta = primer dia del periodo, vencimiento = dia 10.
     */
    public function asegurarRegistro(int $tenantId, ?string $periodo = null): bool
    {
        $periodo = $periodo !== null && $periodo !== '' ? $periodo : date('Y-m');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            throw new InvalidArgumentException('El periodo debe tener el formato AAAA-MM.');
        }

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO ctacte_tenant
                (tenant_id, periodo, fecha_alta, fecha_vencimiento,
                 sistema, soporte, desarrollo, pago, saldo_pendiente, estado)
             VALUES
                (:tenant_id, :periodo, :fecha_alta, :fecha_vencimiento,
                 0, 0, 0, NULL, 0, 'PENDIENTE')"
        );
        $stmt->execute([
            ':tenant_id'         => $tenantId,
            ':periodo'           => $periodo,
            ':fecha_alta'        => $periodo . '-01',
            ':fecha_vencimiento' => $periodo . '-10',
        ]);

        return $stmt->rowCount() > 0;
    }

    // =====================================================
    // ABM del registro mensual
    // =====================================================

    /**
     * Alta de un registro mensual.
     * $data: tenant_id, periodo, fecha_alta, fecha_vencimiento, obs,
     *        sistema, soporte, desarrollo
     */
    public function create(array $data): int
    {
        $sistema    = $this->normalizarMonto($data['sistema'] ?? 0);
        $soporte    = $this->normalizarMonto($data['soporte'] ?? 0);
        $desarrollo = $this->normalizarMonto($data['desarrollo'] ?? 0);
        $total      = round($sistema + $soporte + $desarrollo, 2);

        $stmt = $this->db->prepare(
            "INSERT INTO ctacte_tenant
                (tenant_id, periodo, fecha_alta, fecha_vencimiento, obs,
                 sistema, soporte, desarrollo, pago, saldo_pendiente, estado)
             VALUES
                (:tenant_id, :periodo, :fecha_alta, :fecha_vencimiento, :obs,
                 :sistema, :soporte, :desarrollo, NULL, :saldo, 'PENDIENTE')"
        );
        $stmt->execute([
            ':tenant_id'         => (int)$data['tenant_id'],
            ':periodo'           => $data['periodo'],
            ':fecha_alta'        => $data['fecha_alta'],
            ':fecha_vencimiento' => $data['fecha_vencimiento'],
            ':obs'               => ($data['obs'] ?? '') !== '' ? $data['obs'] : null,
            ':sistema'           => $sistema,
            ':soporte'           => $soporte,
            ':desarrollo'        => $desarrollo,
            ':saldo'             => $total,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza conceptos, fechas y observacion. El pago acumulado no se toca:
     * si el nuevo total queda por debajo de lo ya cobrado, se rechaza.
     */
    public function update(int $id, array $data): bool
    {
        $actual = $this->findById($id);
        if (!$actual) {
            throw new RuntimeException('El registro de cuenta corriente no existe.');
        }

        $sistema    = $this->normalizarMonto($data['sistema'] ?? 0);
        $soporte    = $this->normalizarMonto($data['soporte'] ?? 0);
        $desarrollo = $this->normalizarMonto($data['desarrollo'] ?? 0);
        $total      = round($sistema + $soporte + $desarrollo, 2);
        $pago       = (float)($actual['pago'] ?? 0);

        if ($total + 0.005 < $pago) {
            throw new RuntimeException(
                'El total de los conceptos ($' . number_format($total, 2) . ') no puede ser menor '
                . 'a lo ya pagado ($' . number_format($pago, 2) . ').'
            );
        }

        $saldo  = max(0, round($total - $pago, 2));
        $estado = $this->estadoPara($pago, $saldo);

        $stmt = $this->db->prepare(
            "UPDATE ctacte_tenant
             SET fecha_alta = :fecha_alta,
                 fecha_vencimiento = :fecha_vencimiento,
                 obs = :obs,
                 sistema = :sistema,
                 soporte = :soporte,
                 desarrollo = :desarrollo,
                 saldo_pendiente = :saldo,
                 estado = :estado,
                 updated_by = :updated_by
             WHERE id = :id"
        );

        return $stmt->execute([
            ':fecha_alta'        => $data['fecha_alta'],
            ':fecha_vencimiento' => $data['fecha_vencimiento'],
            ':obs'               => ($data['obs'] ?? '') !== '' ? $data['obs'] : null,
            ':sistema'           => $sistema,
            ':soporte'           => $soporte,
            ':desarrollo'        => $desarrollo,
            ':saldo'             => $saldo,
            ':estado'            => $estado,
            ':updated_by'        => $data['updated_by'] ?? null,
            ':id'                => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM ctacte_tenant WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // =====================================================
    // Pagos
    // =====================================================

    /**
     * Registra un pago y lo reparte entre los registros elegidos, siempre del
     * vencimiento mas viejo al mas nuevo. El importe no puede superar la suma
     * de saldos seleccionados.
     *
     * @param int[] $registroIds
     * @return array{pago_id:int, aplicado:float, aplicaciones:int}
     */
    public function registrarPago(
        int $tenantId,
        float $monto,
        string $fecha,
        ?string $obs,
        array $registroIds,
        ?int $usuarioId = null
    ): array {
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }
        if ($monto > self::MONTO_MAXIMO) {
            throw new InvalidArgumentException('El monto del pago es demasiado grande.');
        }

        $registroIds = array_values(array_unique(array_filter(
            array_map('intval', $registroIds),
            fn($id) => $id > 0
        )));

        if (empty($registroIds)) {
            throw new InvalidArgumentException('Selecciona al menos un registro a saldar.');
        }

        $montoC = self::aCentavos($monto);

        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($registroIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT * FROM ctacte_tenant
                 WHERE tenant_id = ? AND id IN ($placeholders)
                 ORDER BY fecha_vencimiento ASC, periodo ASC, id ASC
                 FOR UPDATE"
            );
            $stmt->execute(array_merge([$tenantId], $registroIds));
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($registros) !== count($registroIds)) {
                throw new RuntimeException('Alguno de los registros seleccionados no pertenece a la empresa.');
            }

            $disponibleC = 0;
            foreach ($registros as $r) {
                $disponibleC += max(0, self::aCentavos($r['saldo_pendiente']));
            }

            if ($disponibleC <= 0) {
                throw new RuntimeException('Los registros seleccionados no tienen saldo pendiente.');
            }
            if ($montoC > $disponibleC) {
                throw new RuntimeException(
                    'El pago ($' . self::formatear($montoC) . ') supera el saldo pendiente de los '
                    . 'registros seleccionados ($' . self::formatear($disponibleC) . ').'
                );
            }

            $insPago = $this->db->prepare(
                "INSERT INTO ctacte_tenant_pagos (tenant_id, fecha, monto, obs, usuario_id)
                 VALUES (:tenant_id, :fecha, :monto, :obs, :usuario_id)"
            );
            $insPago->execute([
                ':tenant_id'  => $tenantId,
                ':fecha'      => $fecha,
                ':monto'      => self::aPesos($montoC),
                ':obs'        => ($obs ?? '') !== '' ? $obs : null,
                ':usuario_id' => $usuarioId && $usuarioId > 0 ? $usuarioId : null,
            ]);
            $pagoId = (int)$this->db->lastInsertId();

            $updRegistro = $this->db->prepare(
                "UPDATE ctacte_tenant
                 SET pago = :pago, saldo_pendiente = :saldo, estado = :estado
                 WHERE id = :id"
            );
            $insAplicacion = $this->db->prepare(
                "INSERT INTO ctacte_tenant_pago_aplicaciones (pago_id, registro_id, monto)
                 VALUES (:pago_id, :registro_id, :monto)"
            );

            $restanteC = $montoC;
            $aplicaciones = 0;

            foreach ($registros as $r) {
                if ($restanteC <= 0) {
                    break;
                }

                $saldoC = max(0, self::aCentavos($r['saldo_pendiente']));
                if ($saldoC <= 0) {
                    continue;
                }

                $aplicaC   = min($restanteC, $saldoC);
                $nuevoSaldoC = $saldoC - $aplicaC;
                $nuevoPagoC  = self::aCentavos($r['pago']) + $aplicaC;

                $updRegistro->execute([
                    ':pago'   => self::aPesos($nuevoPagoC),
                    ':saldo'  => self::aPesos($nuevoSaldoC),
                    ':estado' => $this->estadoPara(self::aPesos($nuevoPagoC), self::aPesos($nuevoSaldoC)),
                    ':id'     => (int)$r['id'],
                ]);

                $insAplicacion->execute([
                    ':pago_id'     => $pagoId,
                    ':registro_id' => (int)$r['id'],
                    ':monto'       => self::aPesos($aplicaC),
                ]);

                $restanteC -= $aplicaC;
                $aplicaciones++;
            }

            if ($restanteC > 0) {
                throw new RuntimeException('No se pudo aplicar todo el pago a los registros seleccionados.');
            }

            $this->db->commit();

            return [
                'pago_id'      => $pagoId,
                'aplicado'     => self::aPesos($montoC - $restanteC),
                'aplicaciones' => $aplicaciones,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula un pago con baja logica: revierte los importes en cada registro,
     * pero conserva el pago (activo = 0), su reparto y los datos de la
     * anulacion (fecha/hora, usuario y motivo).
     */
    public function anularPago(int $pagoId, string $motivo, ?int $usuarioId = null): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo de la anulacion es obligatorio.');
        }
        if (mb_strlen($motivo) > 500) {
            throw new InvalidArgumentException('El motivo de la anulacion no puede superar los 500 caracteres.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM ctacte_tenant_pagos WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $pagoId]);
            $pago = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pago) {
                throw new RuntimeException('El pago no existe.');
            }
            if ((int)($pago['activo'] ?? 1) === 0) {
                throw new RuntimeException('El pago ya esta anulado.');
            }

            $stmt = $this->db->prepare(
                "SELECT registro_id, monto FROM ctacte_tenant_pago_aplicaciones WHERE pago_id = :pago_id"
            );
            $stmt->execute([':pago_id' => $pagoId]);
            $aplicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $selRegistro = $this->db->prepare("SELECT * FROM ctacte_tenant WHERE id = :id FOR UPDATE");
            $updRegistro = $this->db->prepare(
                "UPDATE ctacte_tenant
                 SET pago = :pago, saldo_pendiente = :saldo, estado = :estado
                 WHERE id = :id"
            );

            foreach ($aplicaciones as $a) {
                $selRegistro->execute([':id' => (int)$a['registro_id']]);
                $registro = $selRegistro->fetch(PDO::FETCH_ASSOC);
                if (!$registro) {
                    continue;
                }

                $aplicaC = self::aCentavos($a['monto']);
                $pagoC   = max(0, self::aCentavos($registro['pago']) - $aplicaC);
                $saldoC  = self::aCentavos($registro['saldo_pendiente']) + $aplicaC;

                $updRegistro->execute([
                    ':pago'   => self::aPesos($pagoC),
                    ':saldo'  => self::aPesos($saldoC),
                    ':estado' => $this->estadoPara(self::aPesos($pagoC), self::aPesos($saldoC)),
                    ':id'     => (int)$registro['id'],
                ]);
            }

            // Baja logica: el pago y sus aplicaciones quedan como historial.
            $updPago = $this->db->prepare(
                "UPDATE ctacte_tenant_pagos
                 SET activo = 0,
                     anulado_at = NOW(),
                     anulado_por = :usuario_id,
                     motivo_anulacion = :motivo
                 WHERE id = :id"
            );
            $updPago->execute([
                ':usuario_id' => $usuarioId && $usuarioId > 0 ? $usuarioId : null,
                ':motivo'     => $motivo,
                ':id'         => $pagoId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // =====================================================
    // Helpers
    // =====================================================

    /**
     * Estado segun lo pagado y el saldo. Un registro sin pagos queda
     * PENDIENTE aunque su total sea 0 (todavia no se le cargaron conceptos).
     */
    private function estadoPara(float $pago, float $saldo): string
    {
        if (self::aCentavos($pago) > 0 && self::aCentavos($saldo) <= 0) {
            return 'SALDADO';
        }
        if (self::aCentavos($pago) > 0) {
            return 'PARCIAL';
        }
        return 'PENDIENTE';
    }

    private function normalizarMonto($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace(',', '.', trim($valor));
        }
        $valor = (float)$valor;
        if ($valor < 0) {
            throw new InvalidArgumentException('Los montos no pueden ser negativos.');
        }
        if ($valor > self::MONTO_MAXIMO) {
            throw new InvalidArgumentException('El monto es demasiado grande.');
        }
        return round($valor, 2);
    }

    private static function aCentavos($valor): int
    {
        return (int)round(((float)$valor) * 100);
    }

    private static function aPesos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    private static function formatear(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }
}
