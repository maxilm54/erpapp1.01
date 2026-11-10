<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Numerador.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/models/AsientoContable.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';

/**
 * Ordenes de pago a proveedores.
 *
 * Documento formal de pago de una factura de compra:
 * BORRADOR -> APROBADA -> PAGADA (o ANULADA).
 *
 * Al PAGAR genera el asiento (Debe 2100 Proveedores / Haber Caja) y el
 * egreso en la caja o banco elegida. La anulacion de una orden pagada
 * revierte asiento y caja. El saldo de la factura se calcula como
 * total facturado menos ordenes en APROBADA o PAGADA.
 */
class OrdenPago extends Model
{
    public const ESTADOS = [
        'BORRADOR' => 'Borrador',
        'APROBADA' => 'Aprobada',
        'PAGADA'   => 'Pagada',
        'ANULADA'  => 'Anulada',
    ];

    public const TRANSICIONES = [
        'BORRADOR' => ['APROBADA', 'ANULADA'],
        'APROBADA' => ['PAGADA', 'ANULADA'],
        'PAGADA'   => ['ANULADA'],
        'ANULADA'  => [],
    ];

    private Numerador $numerador;
    private CajaBanco $cajaModel;
    private AsientoContable $asientoModel;
    private AsientoAutomatico $asientoAuto;

    public function __construct()
    {
        parent::__construct();
        $this->numerador = new Numerador();
        $this->cajaModel = new CajaBanco();
        $this->asientoModel = new AsientoContable();
        $this->asientoAuto = new AsientoAutomatico();
    }

    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        $estado = strtoupper(trim((string)($filtros['estado'] ?? '')));
        if ($estado !== '' && isset(self::ESTADOS[$estado])) {
            $where[] = 'op.estado = ?';
            $params[] = $estado;
        }

        $proveedorId = (int)($filtros['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $where[] = 'op.proveedor_id = ?';
            $params[] = $proveedorId;
        }

        $texto = trim((string)($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%' . $texto . '%';
            $where[] = '(p.razon_social LIKE ? OR fc.numero LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'op.fecha >= ?';
            $params[] = $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'op.fecha <= ?';
            $params[] = $hasta;
        }

        $sql = "
            SELECT op.*,
                   p.razon_social AS proveedor_nombre,
                   fc.numero AS factura_numero,
                   fc.tipo_fiscal AS factura_tipo,
                   fc.fecha_factura,
                   fc.monto_total AS factura_total,
                   fc.monto_base AS factura_base,
                   fc.monto_iva AS factura_iva,
                   cb.nombre AS caja_nombre,
                   u.nombre AS usuario_nombre
            FROM ordenes_pago op
            LEFT JOIN proveedores p ON p.id = op.proveedor_id
            LEFT JOIN facturas_compra fc ON fc.id = op.factura_compra_id
            LEFT JOIN cajas_bancos cb ON cb.id = op.caja_banco_id
            LEFT JOIN users u ON u.id = op.usuario_id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY op.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT op.*,
                   p.razon_social AS proveedor_nombre,
                   p.cuit AS proveedor_cuit,
                   fc.numero AS factura_numero,
                   fc.tipo_fiscal AS factura_tipo,
                   fc.fecha_factura,
                   fc.monto_total AS factura_total,
                   fc.monto_base AS factura_base,
                   fc.monto_iva AS factura_iva,
                   fc.orden_compra_id,
                   cb.nombre AS caja_nombre,
                   u.nombre AS usuario_nombre
            FROM ordenes_pago op
            LEFT JOIN proveedores p ON p.id = op.proveedor_id
            LEFT JOIN facturas_compra fc ON fc.id = op.factura_compra_id
            LEFT JOIN cajas_bancos cb ON cb.id = op.caja_banco_id
            LEFT JOIN users u ON u.id = op.usuario_id
            WHERE op.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Saldo disponible de una factura para nuevas ordenes de pago:
     * total factura menos ordenes en APROBADA o PAGADA.
     */
    public function saldoFactura(int $facturaId): float
    {
        $stmt = $this->db->prepare("
            SELECT fc.monto_total - COALESCE((
                SELECT SUM(op.monto) FROM ordenes_pago op
                WHERE op.factura_compra_id = fc.id AND op.estado IN ('APROBADA','PAGADA')
            ), 0) AS saldo
            FROM facturas_compra fc
            WHERE fc.id = ?
        ");
        $stmt->execute([$facturaId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Saldo de la factura sin contar una orden de pago propia. Se usa al
     * pagar: el monto de la orden ya esta comprometido dentro de ese saldo,
     * asi que no puede chocar contra si misma.
     */
    public function saldoFacturaExcluyendo(int $facturaId, int $excluirOrdenId): float
    {
        $stmt = $this->db->prepare("
            SELECT fc.monto_total - COALESCE((
                SELECT SUM(op.monto) FROM ordenes_pago op
                WHERE op.factura_compra_id = fc.id
                  AND op.estado IN ('APROBADA','PAGADA')
                  AND op.id != ?
            ), 0) AS saldo
            FROM facturas_compra fc
            WHERE fc.id = ?
        ");
        $stmt->execute([$excluirOrdenId, $facturaId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Facturas con saldo pendiente, para el formulario de nueva orden de pago.
     */
    public function facturasConSaldo(): array
    {
        return $this->db->query("
            SELECT fc.id,
                   fc.numero,
                   fc.tipo_fiscal,
                   fc.fecha_factura,
                   fc.monto_total,
                   fc.monto_base,
                   fc.monto_iva,
                   p.razon_social AS proveedor_nombre,
                   COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0) AS comprometido,
                   (fc.monto_total - COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0)) AS saldo
            FROM facturas_compra fc
            JOIN proveedores p ON p.id = fc.proveedor_id
            LEFT JOIN ordenes_pago op ON op.factura_compra_id = fc.id
            WHERE fc.estado = 'REGISTRADA'
            GROUP BY fc.id
            HAVING saldo > 0.009
            ORDER BY fc.fecha_factura ASC, fc.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea la orden de pago en BORRADOR con numerador ORDEN_PAGO.
     */
    public function crear(array $data, int $usuarioId): int
    {
        $facturaId = (int)($data['factura_compra_id'] ?? 0);
        $monto = (float)($data['monto'] ?? 0);
        $fecha = trim((string)($data['fecha'] ?? ''));
        $medioPago = strtoupper(trim((string)($data['medio_pago'] ?? 'TRANSFERENCIA')));

        if (!in_array($medioPago, ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'CHEQUE', 'OTRO'], true)) {
            $medioPago = 'TRANSFERENCIA';
        }

        if ($facturaId <= 0) {
            throw new Exception('Seleccione la factura a pagar.');
        }
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a cero.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception('Ingrese la fecha de la orden de pago.');
        }

        $stmt = $this->db->prepare("
            SELECT fc.proveedor_id, fc.estado, fc.monto_total
            FROM facturas_compra fc
            WHERE fc.id = ?
        ");
        $stmt->execute([$facturaId]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$factura) {
            throw new Exception('La factura no existe.');
        }
        if ($factura['estado'] !== 'REGISTRADA') {
            throw new Exception('La factura está anulada.');
        }

        $saldo = $this->saldoFactura($facturaId);
        if ($monto > $saldo + 0.009) {
            throw new Exception('El monto ($ ' . number_format($monto, 2, ',', '.') . ') excede el saldo de la factura ($ ' . number_format($saldo, 2, ',', '.') . ').');
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $numero = $this->numerador->siguiente('ORDEN_PAGO');

            $stmt = $this->db->prepare("
                INSERT INTO ordenes_pago
                    (numero, proveedor_id, factura_compra_id, monto, medio_pago, fecha,
                     estado, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, 'BORRADOR', ?, ?)
            ");
            $stmt->execute([
                $numero,
                (int)$factura['proveedor_id'],
                $facturaId,
                $monto,
                $medioPago,
                $fecha,
                trim((string)($data['observaciones'] ?? '')) ?: null,
                $usuarioId,
            ]);
            $ordenId = (int)$this->db->lastInsertId();

            if (!$inTransaction) {
                $this->db->commit();
            }
            return $ordenId;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambia el estado aplicando los efectos financieros.
     */
    public function cambiarEstado(int $id, string $estado, array $context = []): void
    {
        $estado = strtoupper(trim($estado));
        if (!isset(self::ESTADOS[$estado])) {
            throw new Exception('Estado de orden de pago inválido.');
        }

        $stmt = $this->db->prepare("SELECT * FROM ordenes_pago WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orden) {
            throw new Exception('Orden de pago no encontrada.');
        }

        $estadoAnterior = strtoupper((string)$orden['estado']);
        if ($estadoAnterior === $estado) {
            return;
        }

        $permitidos = self::TRANSICIONES[$estadoAnterior] ?? [];
        if (!in_array($estado, $permitidos, true)) {
            throw new Exception('No se puede pasar de "' . (self::ESTADOS[$estadoAnterior] ?? $estadoAnterior) . '" a "' . (self::ESTADOS[$estado] ?? $estado) . '".');
        }

        $usuarioId = (int)($context['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new Exception('No se pudo identificar al usuario.');
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($estado === 'PAGADA') {
                $this->pagar($orden, $context, $usuarioId);
            } elseif ($estado === 'APROBADA') {
                $this->db->prepare("
                    UPDATE ordenes_pago
                    SET estado = 'APROBADA', aprobada_at = ?, aprobado_por = ?
                    WHERE id = ?
                ")->execute([date('Y-m-d H:i:s'), $usuarioId, $id]);
            } elseif ($estado === 'ANULADA') {
                if ($estadoAnterior === 'PAGADA') {
                    $this->revertirPago($orden, $usuarioId);
                }
                $this->db->prepare("UPDATE ordenes_pago SET estado = 'ANULADA' WHERE id = ?")
                    ->execute([$id]);
            }

            if (!$inTransaction) {
                $this->db->commit();
            }
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ejecuta el pago: asiento (Debe Proveedores / Haber Caja) + egreso de caja.
     */
    private function pagar(array $orden, array $context, int $usuarioId): void
    {
        $id = (int)$orden['id'];
        $cajaBancoId = (int)($context['caja_banco_id'] ?? 0);
        if ($cajaBancoId <= 0) {
            throw new Exception('Seleccione la caja o banco desde la que se paga.');
        }

        $caja = $this->cajaModel->findById($cajaBancoId);
        if (!$caja || empty($caja['activa'])) {
            throw new Exception('La caja o banco seleccionada no es válida.');
        }

        $fecha = trim((string)($context['fecha_pago'] ?? '')) ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception('La fecha de pago no es válida.');
        }

        // El saldo puede haber cambiado desde la creacion: la factura pudo
        // recibir otras ordenes de pago. El monto propio no se cuenta porque
        // ya esta comprometido dentro del saldo.
        $saldo = $this->saldoFacturaExcluyendo((int)$orden['factura_compra_id'], $id);
        if ((float)$orden['monto'] > $saldo + 0.009) {
            throw new Exception('El saldo de la factura ya no cubre esta orden de pago ($ ' . number_format($saldo, 2, ',', '.') . ').');
        }

        $resultado = $this->asientoAuto->ordenPagoPagada($orden, $cajaBancoId, $usuarioId, $fecha);

        $this->db->prepare("
            UPDATE ordenes_pago
            SET estado = 'PAGADA',
                fecha_pago = ?,
                caja_banco_id = ?,
                asiento_id = ?,
                movimiento_caja_id = ?
            WHERE id = ?
        ")->execute([$fecha . ' 00:00:00', $cajaBancoId, $resultado['asiento_id'], $resultado['movimiento_id'], $id]);
    }

    /**
     * Revierte el pago de una orden PAGADA: asiento inverso + ingreso de caja.
     */
    private function revertirPago(array $orden, int $usuarioId): void
    {
        $id = (int)$orden['id'];

        $asientoId = !empty($orden['asiento_id']) ? (int)$orden['asiento_id'] : 0;
        if ($asientoId <= 0) {
            $asiento = $this->asientoModel->findByOrigen('ORDENES_PAGO', 'PAGO', $id);
            $asientoId = $asiento ? (int)$asiento['id'] : 0;
        }
        if ($asientoId > 0) {
            $this->asientoModel->anular($asientoId, $usuarioId);
        }

        $movimiento = null;
        if (!empty($orden['movimiento_caja_id'])) {
            $movimiento = $this->cajaModel->findMovimiento((int)$orden['movimiento_caja_id']);
        }
        if (!$movimiento && !empty($orden['caja_banco_id'])) {
            $movimiento = $this->cajaModel->getMovimientosByReferencia('ORDENES_PAGO', 'PAGO', $id);
        }

        if ($movimiento) {
            $this->cajaModel->registrarMovimiento([
                'caja_banco_id'       => $movimiento['caja_banco_id'],
                'fecha'               => date('Y-m-d'),
                'tipo'                => 'INGRESO',
                'monto'               => (float)$movimiento['monto'],
                'descripcion'         => "Reverso orden de pago #{$orden['numero']}",
                'asiento_contable_id' => null,
                'referencia_modulo'   => 'ORDENES_PAGO',
                'referencia_tipo'     => 'REVERSO',
                'referencia_id'       => $id,
                'usuario_id'          => $usuarioId,
            ]);
        }

        $this->db->prepare("
            UPDATE ordenes_pago
            SET fecha_pago = NULL,
                movimiento_caja_id = NULL,
                asiento_id = NULL
            WHERE id = ?
        ")->execute([$id]);
    }

    /**
     * Resumen para las tarjetas del listado.
     */
    public function resumen(): array
    {
        $row = $this->db->query("
            SELECT
                SUM(CASE WHEN estado = 'BORRADOR' THEN 1 ELSE 0 END) AS borrador_cant,
                COALESCE(SUM(CASE WHEN estado = 'BORRADOR' THEN monto ELSE 0 END), 0) AS borrador_monto,
                SUM(CASE WHEN estado = 'APROBADA' THEN 1 ELSE 0 END) AS aprobadas_cant,
                COALESCE(SUM(CASE WHEN estado = 'APROBADA' THEN monto ELSE 0 END), 0) AS aprobadas_monto,
                SUM(CASE WHEN estado = 'PAGADA' AND MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS pagadas_mes_cant,
                COALESCE(SUM(CASE WHEN estado = 'PAGADA' AND MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE()) THEN monto ELSE 0 END), 0) AS pagadas_mes_monto
            FROM ordenes_pago
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'borrador_cant'     => (int)($row['borrador_cant'] ?? 0),
            'borrador_monto'    => (float)($row['borrador_monto'] ?? 0),
            'aprobadas_cant'    => (int)($row['aprobadas_cant'] ?? 0),
            'aprobadas_monto'   => (float)($row['aprobadas_monto'] ?? 0),
            'pagadas_mes_cant'  => (int)($row['pagadas_mes_cant'] ?? 0),
            'pagadas_mes_monto' => (float)($row['pagadas_mes_monto'] ?? 0),
        ];
    }
}
