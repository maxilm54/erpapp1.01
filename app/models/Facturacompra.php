<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Cuentacorrienteempresa.php';
require_once BASE_PATH . '/app/models/AsientoContable.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';

/**
 * Facturas de compra de proveedores.
 *
 * Registra la factura que llega del proveedor, normalmente contra una OC
 * ya aprobada (o sin OC para servicios). Al registrarse genera el asiento
 * de compra (Debe gasto + IVA / Haber Proveedores 2100) y queda pendiente
 * de pago a traves de ordenes de pago.
 *
 * La deuda de la factura = monto_total - suma de ordenes de pago
 * en estado APROBADA o PAGADA.
 */
class FacturaCompra extends Model
{
    public const ESTADOS = [
        'REGISTRADA' => 'Registrada',
        'ANULADA'    => 'Anulada',
    ];

    private AsientoAutomatico $asientoAuto;
    private AsientoContable $asientoModel;
    private CuentaCorrienteEmpresa $ccEmpresa;

    public function __construct()
    {
        parent::__construct();
        $this->asientoAuto = new AsientoAutomatico();
        $this->asientoModel = new AsientoContable();
        $this->ccEmpresa = new CuentaCorrienteEmpresa();
    }

    /**
     * Listado con filtros: estado, proveedor_id, texto (numero) y fechas.
     */
    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        $estado = strtoupper(trim((string)($filtros['estado'] ?? '')));
        if ($estado !== '' && isset(self::ESTADOS[$estado])) {
            $where[] = 'fc.estado = ?';
            $params[] = $estado;
        }

        $proveedorId = (int)($filtros['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $where[] = 'fc.proveedor_id = ?';
            $params[] = $proveedorId;
        }

        $texto = trim((string)($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%' . $texto . '%';
            $where[] = '(fc.numero LIKE ? OR p.razon_social LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'fc.fecha_factura >= ?';
            $params[] = $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'fc.fecha_factura <= ?';
            $params[] = $hasta;
        }

        $sql = "
            SELECT fc.*,
                   p.razon_social AS proveedor_nombre,
                   mon.simbolo AS moneda_simbolo,
                   COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0) AS pagado_comprometido,
                   (fc.monto_total - COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0)) AS saldo
            FROM facturas_compra fc
            LEFT JOIN proveedores p ON p.id = fc.proveedor_id
            LEFT JOIN monedas mon ON mon.id_moned = fc.moneda
            LEFT JOIN ordenes_pago op ON op.factura_compra_id = fc.id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY fc.id ORDER BY fc.fecha_factura DESC, fc.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT fc.*,
                   p.razon_social AS proveedor_nombre,
                   p.cuit AS proveedor_cuit,
                   oc.id AS oc_id,
                   mon.simbolo AS moneda_simbolo,
                   mon.nombre AS moneda_nombre,
                   u.nombre AS usuario_nombre,
                   COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0) AS pagado_comprometido,
                   (fc.monto_total - COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0)) AS saldo
            FROM facturas_compra fc
            LEFT JOIN proveedores p ON p.id = fc.proveedor_id
            LEFT JOIN ordenes_compra oc ON oc.id = fc.orden_compra_id
            LEFT JOIN monedas mon ON mon.id_moned = fc.moneda
            LEFT JOIN users u ON u.id = fc.usuario_id
            LEFT JOIN ordenes_pago op ON op.factura_compra_id = fc.id
            WHERE fc.id = ?
            GROUP BY fc.id
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Facturas activas de una orden de compra.
     */
    public function porOC(int $ocId): array
    {
        $stmt = $this->db->prepare("
            SELECT fc.*,
                   COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0) AS pagado_comprometido,
                   (fc.monto_total - COALESCE(SUM(CASE WHEN op.estado IN ('APROBADA','PAGADA') THEN op.monto ELSE 0 END), 0)) AS saldo
            FROM facturas_compra fc
            LEFT JOIN ordenes_pago op ON op.factura_compra_id = fc.id
            WHERE fc.orden_compra_id = ?
            GROUP BY fc.id
            ORDER BY fc.fecha_factura ASC, fc.id ASC
        ");
        $stmt->execute([$ocId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida que el numero de factura no este registrado para el proveedor
     * (entre facturas no anuladas).
     */
    public function numeroExistente(int $proveedorId, string $numero, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM facturas_compra
                WHERE proveedor_id = ? AND numero = ? AND estado = 'REGISTRADA'";
        $params = [$proveedorId, $numero];
        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Registra la factura y genera el asiento de compra dentro de una
     * transaccion. No toca caja: el pago se hace por orden de pago.
     */
    public function registrar(array $data, int $usuarioId): int
    {
        $proveedorId = (int)($data['proveedor_id'] ?? 0);
        $ocId = !empty($data['orden_compra_id']) ? (int)$data['orden_compra_id'] : null;
        $numero = trim((string)($data['numero'] ?? ''));
        $tipoFiscal = strtoupper(trim((string)($data['tipo_fiscal'] ?? '')));
        $fechaFactura = trim((string)($data['fecha_factura'] ?? ''));
        $fechaVencimiento = trim((string)($data['fecha_vencimiento'] ?? ''));
        $moneda = (int)($data['moneda'] ?? 1);
        $montoBase = (float)($data['monto_base'] ?? 0);
        $montoIva = (float)($data['monto_iva'] ?? 0);
        $montoTotal = (float)($data['monto_total'] ?? 0);

        if ($proveedorId <= 0) {
            throw new Exception('Seleccione el proveedor de la factura.');
        }
        if ($numero === '') {
            throw new Exception('Ingrese el número de la factura.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFactura)) {
            throw new Exception('Ingrese la fecha de la factura.');
        }
        [$anioF, $mesF, $diaF] = array_map('intval', explode('-', $fechaFactura));
        if (!checkdate($mesF, $diaF, $anioF)) {
            throw new Exception('La fecha de la factura no es válida.');
        }
        if ($fechaVencimiento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new Exception('La fecha de vencimiento no es válida.');
        }
        if ($montoBase <= 0 && $montoIva <= 0) {
            throw new Exception('El monto de la factura debe ser mayor a cero.');
        }
        if (abs(($montoBase + $montoIva) - $montoTotal) >= 0.02) {
            throw new Exception('El total no coincide con el neto más IVA.');
        }

        $stmt = $this->db->prepare("SELECT id FROM proveedores WHERE id = ? AND activo = 1");
        $stmt->execute([$proveedorId]);
        if (!$stmt->fetchColumn()) {
            throw new Exception('Proveedor no encontrado o inactivo.');
        }

        if ($ocId !== null) {
            $stmt = $this->db->prepare("
                SELECT proveedor_id, estado FROM ordenes_compra WHERE id = ?
            ");
            $stmt->execute([$ocId]);
            $oc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oc) {
                throw new Exception('La orden de compra no existe.');
            }
            if ((int)$oc['proveedor_id'] !== $proveedorId) {
                throw new Exception('La OC pertenece a otro proveedor.');
            }
            if (!in_array($oc['estado'], ['APROBADA', 'PARCIAL', 'RECIBIDA'], true)) {
                throw new Exception('Solo se pueden facturar órdenes aprobadas o recibidas.');
            }
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM monedas WHERE id_moned = ?");
        $stmt->execute([$moneda]);
        if (!(int)$stmt->fetchColumn()) {
            throw new Exception('La moneda seleccionada no existe.');
        }

        if ($this->numeroExistente($proveedorId, $numero)) {
            throw new Exception('Ya existe una factura con ese número para este proveedor.');
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare("
                INSERT INTO facturas_compra
                    (proveedor_id, orden_compra_id, tipo_fiscal, numero, fecha_factura,
                     fecha_vencimiento, moneda, monto_base, monto_iva, monto_total,
                     estado, observaciones, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'REGISTRADA', ?, ?)
            ");
            $stmt->execute([
                $proveedorId,
                $ocId,
                $tipoFiscal !== '' ? $tipoFiscal : null,
                $numero,
                $fechaFactura,
                $fechaVencimiento !== '' ? $fechaVencimiento : null,
                $moneda,
                $montoBase,
                $montoIva,
                $montoTotal,
                trim((string)($data['observaciones'] ?? '')) ?: null,
                $usuarioId,
            ]);
            $facturaId = (int)$this->db->lastInsertId();

            $asientoId = $this->asientoAuto->facturaCompraRegistrada([
                'id'           => $facturaId,
                'numero'       => $numero,
                'tipo_fiscal'  => $tipoFiscal,
                'monto_base'   => $montoBase,
                'monto_iva'    => $montoIva,
                'monto_total'  => $montoTotal,
                'fecha_factura' => $fechaFactura,
                'proveedor_id' => $proveedorId,
            ], $usuarioId);

            $this->db->prepare("UPDATE facturas_compra SET asiento_id = ? WHERE id = ?")
                ->execute([$asientoId, $facturaId]);

            if (!$inTransaction) {
                $this->db->commit();
            }
            return $facturaId;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula la factura: revierte el asiento y marca ANULADA.
     * Se bloquea si tiene ordenes de pago activas.
     */
    public function anular(int $id, int $usuarioId): void
    {
        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare("SELECT * FROM facturas_compra WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $factura = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$factura) {
                throw new Exception('Factura no encontrada.');
            }
            if ($factura['estado'] !== 'REGISTRADA') {
                throw new Exception('La factura ya está anulada.');
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM ordenes_pago
                WHERE factura_compra_id = ? AND estado IN ('BORRADOR','APROBADA','PAGADA')
            ");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('La factura tiene órdenes de pago activas. Anúlelas primero.');
            }

            if (!empty($factura['asiento_id'])) {
                $this->asientoModel->anular((int)$factura['asiento_id'], $usuarioId);
            }

            $this->db->prepare("UPDATE facturas_compra SET estado = 'ANULADA' WHERE id = ?")
                ->execute([$id]);

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
     * Saldo pendiente de una factura: total menos ordenes de pago
     * comprometidas (APROBADA o PAGADA).
     */
    public function saldo(int $facturaId): float
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
     * Ordenes de pago asociadas a una factura.
     */
    public function ordenesPago(int $facturaId): array
    {
        $stmt = $this->db->prepare("
            SELECT op.*, cb.nombre AS caja_nombre, u.nombre AS usuario_nombre
            FROM ordenes_pago op
            LEFT JOIN cajas_bancos cb ON cb.id = op.caja_banco_id
            LEFT JOIN users u ON u.id = op.usuario_id
            WHERE op.factura_compra_id = ?
            ORDER BY op.numero DESC
        ");
        $stmt->execute([$facturaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Proveedores activos para el formulario.
     */
    public function proveedoresActivos(): array
    {
        return $this->db->query("
            SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * OCs disponibles para facturar: aprobadas o recibidas, con proveedor
     * asignado, mostrando total, facturado y saldo.
     */
    public function ocsDisponibles(): array
    {
        return $this->db->query("
            SELECT oc.id,
                   oc.estado,
                   oc.proveedor_id,
                   p.razon_social AS proveedor_nombre,
                   COALESCE(ocd.total_oc, 0) AS total_oc,
                   COALESCE(fact.total_facturado, 0) AS total_facturado,
                   COALESCE(ocd.total_oc, 0) - COALESCE(fact.total_facturado, 0) AS saldo_oc
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id = oc.proveedor_id AND p.activo = 1
            LEFT JOIN (
                SELECT orden_compra_id, SUM(cantidad * precio_unitario) AS total_oc
                FROM ordenes_compra_detalle
                GROUP BY orden_compra_id
            ) ocd ON ocd.orden_compra_id = oc.id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(monto_total) AS total_facturado
                FROM facturas_compra
                WHERE estado = 'REGISTRADA' AND orden_compra_id IS NOT NULL
                GROUP BY orden_compra_id
            ) fact ON fact.orden_compra_id = oc.id
            WHERE oc.estado IN ('APROBADA','PARCIAL','RECIBIDA')
              AND oc.proveedor_id > 0
            ORDER BY oc.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Monedas activas para el selector.
     */
    public function monedas(): array
    {
        return $this->db->query("
            SELECT id_moned, nombre, simbolo FROM monedas ORDER BY id_moned
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Moneda por defecto para la factura: la que usa el simbolo $ (peso).
     * Los ids de moneda varian por tenant, asi que se resuelve por dato.
     */
    public function monedaPorDefecto(): int
    {
        $id = (int)($this->db->query("
            SELECT id_moned FROM monedas WHERE simbolo = '$' ORDER BY id_moned LIMIT 1
        ")->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        return (int)($this->db->query("SELECT id_moned FROM monedas ORDER BY id_moned LIMIT 1")->fetchColumn() ?: 0);
    }
}
