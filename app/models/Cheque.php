<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH . '/app/models/Cuentacorrienteempresa.php';
require_once BASE_PATH . '/app/models/AsientoContable.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';

/**
 * Cheques de terceros recibidos en cobranzas.
 *
 * Un cheque se crea junto con el pago (Cobro::registrar / Pago::registrar)
 * cuando el medio de pago es "Cheque". Desde acá se controla la cartera,
 * los vencimientos, la acreditación bancaria y los endosos a proveedores.
 *
 * Regla financiera:
 * - EN_CARTERA / DEPOSITADO: es un activo, NO suma a la disponibilidad de caja/banco.
 * - COBRADO: recién acá se acredita en la caja/banco elegida.
 * - ENDOSADO: sale de la cartera y cancela deuda con un proveedor, sin pasar por banco.
 * - RECHAZADO: se revierte el crédito en la cuenta corriente del cliente.
 */
class Cheque extends Model
{
    public const ESTADOS = [
        'EN_CARTERA' => 'En cartera',
        'DEPOSITADO' => 'Depositado (en tránsito)',
        'COBRADO'    => 'Cobrado / Acreditado',
        'ENDOSADO'   => 'Endosado a proveedor',
        'RECHAZADO'  => 'Rechazado',
        'ANULADO'    => 'Anulado',
    ];

    public const TIPOS = [
        'FISICO' => 'Cheque físico',
        'ECHEQ'  => 'E-cheq',
    ];

    /**
     * Transiciones permitidas desde el detalle del cheque.
     * ANULADO no se permite manualmente: se llega anulando el cobro.
     */
    public const TRANSICIONES = [
        'EN_CARTERA' => ['DEPOSITADO', 'COBRADO', 'ENDOSADO', 'RECHAZADO'],
        'DEPOSITADO' => ['EN_CARTERA', 'COBRADO', 'ENDOSADO', 'RECHAZADO'],
        'COBRADO'    => ['EN_CARTERA', 'RECHAZADO'],
        'ENDOSADO'   => ['EN_CARTERA', 'RECHAZADO'],
        'RECHAZADO'  => ['EN_CARTERA'],
        'ANULADO'    => [],
    ];

    private AsientoAutomatico $asientoAuto;
    private AsientoContable $asientoModel;
    private CajaBanco $cajaModel;
    private CuentaCorrienteCliente $ccCliente;
    private CuentaCorrienteEmpresa $ccEmpresa;

    public function __construct()
    {
        parent::__construct();
        $this->asientoAuto  = new AsientoAutomatico();
        $this->asientoModel = new AsientoContable();
        $this->cajaModel    = new CajaBanco();
        $this->ccCliente    = new CuentaCorrienteCliente();
        $this->ccEmpresa    = new CuentaCorrienteEmpresa();
    }

    /**
     * Indica si el medio de pago elegido es cheque.
     */
    public static function esMedioCheque(?string $medioPago): bool
    {
        return strtoupper(trim((string)$medioPago)) === 'CHEQUE';
    }

    /**
     * Arma la estructura del cheque a partir del POST del cobro.
     * Devuelve null si el medio de pago no es cheque y lanza excepción si
     * falta algún dato obligatorio. Lo usan CobrosController y CtaCteController.
     */
    public static function datosDesdePost(array $post): ?array
    {
        if (!self::esMedioCheque($post['medio_pago'] ?? null)) {
            return null;
        }

        $numero = trim((string)($post['cheque_numero'] ?? ''));
        $banco = trim((string)($post['cheque_banco'] ?? ''));
        $fechaVencimiento = trim((string)($post['cheque_fecha_vencimiento'] ?? ''));
        $fechaEmision = trim((string)($post['cheque_fecha_emision'] ?? ''));

        if ($numero === '') {
            throw new Exception('Ingrese el número del cheque.');
        }
        if ($banco === '') {
            throw new Exception('Ingrese el banco del cheque.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new Exception('Ingrese la fecha de vencimiento del cheque.');
        }
        [$anio, $mes, $dia] = array_map('intval', explode('-', $fechaVencimiento));
        if (!checkdate($mes, $dia, $anio)) {
            throw new Exception('La fecha de vencimiento del cheque no es válida.');
        }
        if ($fechaEmision !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                throw new Exception('La fecha de emisión del cheque no es válida.');
            }
            [$anioE, $mesE, $diaE] = array_map('intval', explode('-', $fechaEmision));
            if (!checkdate($mesE, $diaE, $anioE)) {
                throw new Exception('La fecha de emisión del cheque no es válida.');
            }
        }

        $tipo = strtoupper((string)($post['cheque_tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        return [
            'tipo'              => $tipo,
            'numero'            => $numero,
            'banco'             => $banco,
            'sucursal'          => trim((string)($post['cheque_sucursal'] ?? '')),
            'titular'           => trim((string)($post['cheque_titular'] ?? '')),
            'cuit'              => trim((string)($post['cheque_cuit'] ?? '')),
            'fecha_emision'     => $fechaEmision !== '' ? $fechaEmision : null,
            'fecha_vencimiento' => $fechaVencimiento,
            'observaciones'     => trim((string)($post['cheque_observaciones'] ?? '')),
        ];
    }

    /**
     * Crea el cheque asociado a un pago y su asiento de cartera.
     * Se llama dentro de la transacción del cobro, así que si falla el cobro
     * completo hace rollback.
     */
    public function crearParaPago(int $pagoId, array $data): int
    {
        $tipo = strtoupper((string)($data['tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        $numero = trim((string)($data['numero'] ?? ''));
        $banco = trim((string)($data['banco'] ?? ''));
        $fechaVencimiento = trim((string)($data['fecha_vencimiento'] ?? ''));

        if ($numero === '' || $banco === '' || $fechaVencimiento === '') {
            throw new Exception('Faltan datos obligatorios del cheque (número, banco o vencimiento).');
        }
        if ((int)$data['cliente_id'] <= 0) {
            throw new Exception('El cheque no tiene un cliente válido.');
        }
        if ((float)$data['monto'] <= 0) {
            throw new Exception('El monto del cheque debe ser mayor a cero.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO cheques
                (pago_id, cliente_id, cliente_nombre, tipo, numero, banco, sucursal,
                 titular, cuit, fecha_emision, fecha_vencimiento, monto, estado,
                 observaciones, caja_banco_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'EN_CARTERA', ?, ?, ?)
        ");

        $stmt->execute([
            $pagoId,
            (int)$data['cliente_id'],
            !empty($data['cliente_nombre']) ? trim((string)$data['cliente_nombre']) : null,
            $tipo,
            $numero,
            $banco,
            !empty($data['sucursal']) ? trim((string)$data['sucursal']) : null,
            !empty($data['titular']) ? trim((string)$data['titular']) : null,
            !empty($data['cuit']) ? trim((string)$data['cuit']) : null,
            !empty($data['fecha_emision']) ? $data['fecha_emision'] : null,
            $fechaVencimiento,
            (float)$data['monto'],
            !empty($data['observaciones']) ? trim((string)$data['observaciones']) : null,
            !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null,
            (int)$data['usuario_id'],
        ]);

        $chequeId = (int)$this->db->lastInsertId();

        // El cheque entra como activo en cartera, no como caja/banco.
        $asientoId = $this->asientoAuto->chequeEnCartera(
            (int)$data['cliente_id'],
            (float)$data['monto'],
            $chequeId,
            (int)$data['usuario_id'],
            !empty($data['cliente_nombre']) ? (string)$data['cliente_nombre'] : null
        );

        $this->db->prepare("UPDATE cheques SET asiento_cartera_id = ? WHERE id = ?")
            ->execute([$asientoId, $chequeId]);

        return $chequeId;
    }

    /**
     * Cheque asociado a un pago (o null si el cobro no fue con cheque).
     */
    public function porPago(int $pagoId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ch.*, cb.nombre AS caja_nombre,
                   pv.razon_social AS proveedor_nombre,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
            LEFT JOIN proveedores pv ON pv.id = ch.proveedor_id
            WHERE ch.pago_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pagoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Buscar un cheque por ID con datos del cliente, la caja, el pago y el proveedor.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   c.cuit AS cliente_cuit,
                   c.email AS cliente_email,
                   cb.nombre AS caja_nombre,
                   pv.razon_social AS proveedor_nombre,
                   p.anulado AS pago_anulado,
                   p.monto AS pago_monto,
                   p.created_at AS pago_fecha,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
            LEFT JOIN proveedores pv ON pv.id = ch.proveedor_id
            LEFT JOIN pagos p ON p.id = ch.pago_id
            WHERE ch.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Listado con filtros: estado, vencimiento (vencidos|hoy|proximos|mes),
     * cliente_id, texto, desde y hasta (sobre fecha_vencimiento).
     */
    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];

        $estado = strtoupper(trim((string)($filtros['estado'] ?? '')));
        if ($estado !== '' && isset(self::ESTADOS[$estado])) {
            $where[] = 'ch.estado = ?';
            $params[] = $estado;
        }

        $vencimiento = trim((string)($filtros['vencimiento'] ?? ''));
        if ($vencimiento === 'vencidos') {
            $where[] = "ch.estado IN ('EN_CARTERA','DEPOSITADO') AND ch.fecha_vencimiento < CURDATE()";
        } elseif ($vencimiento === 'hoy') {
            $where[] = "ch.estado IN ('EN_CARTERA','DEPOSITADO') AND ch.fecha_vencimiento = CURDATE()";
        } elseif ($vencimiento === 'proximos') {
            $where[] = "ch.estado IN ('EN_CARTERA','DEPOSITADO') AND ch.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($vencimiento === 'mes') {
            $where[] = "DATE_FORMAT(ch.fecha_vencimiento, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }

        $clienteId = (int)($filtros['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $where[] = 'ch.cliente_id = ?';
            $params[] = $clienteId;
        }

        $texto = trim((string)($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%' . $texto . '%';
            $where[] = "(ch.numero LIKE ? OR ch.banco LIKE ? OR COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social) LIKE ? OR pv.razon_social LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'ch.fecha_vencimiento >= ?';
            $params[] = $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'ch.fecha_vencimiento <= ?';
            $params[] = $hasta;
        }

        $sql = "
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   cb.nombre AS caja_nombre,
                   pv.razon_social AS proveedor_nombre,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = ch.caja_banco_id
            LEFT JOIN proveedores pv ON pv.id = ch.proveedor_id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ch.fecha_vencimiento ASC, ch.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cheques pendientes de acreditación que vencen dentro de los próximos N días.
     * Con $incluirVencidos = true también trae los ya vencidos.
     */
    public function porVencer(int $dias = 30, bool $incluirVencidos = true): array
    {
        $dias = max(0, $dias);
        $condicion = $incluirVencidos
            ? "ch.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)"
            : "ch.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)";

        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado IN ('EN_CARTERA','DEPOSITADO') AND {$condicion}
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cheques pendientes de acreditación con fecha de vencimiento anterior a hoy.
     */
    public function vencidos(): array
    {
        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado IN ('EN_CARTERA','DEPOSITADO') AND ch.fecha_vencimiento < CURDATE()
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Totales para las tarjetas de resumen.
     */
    public function resumen(): array
    {
        $row = $this->db->query("
            SELECT
                SUM(CASE WHEN estado = 'EN_CARTERA' THEN 1 ELSE 0 END) AS en_cartera_cant,
                COALESCE(SUM(CASE WHEN estado = 'EN_CARTERA' THEN monto ELSE 0 END), 0) AS en_cartera_monto,
                SUM(CASE WHEN estado IN ('EN_CARTERA','DEPOSITADO') AND fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos_cant,
                COALESCE(SUM(CASE WHEN estado IN ('EN_CARTERA','DEPOSITADO') AND fecha_vencimiento < CURDATE() THEN monto ELSE 0 END), 0) AS vencidos_monto,
                SUM(CASE WHEN estado IN ('EN_CARTERA','DEPOSITADO') AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS por_vencer_cant,
                COALESCE(SUM(CASE WHEN estado IN ('EN_CARTERA','DEPOSITADO') AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN monto ELSE 0 END), 0) AS por_vencer_monto,
                SUM(CASE WHEN estado = 'DEPOSITADO' THEN 1 ELSE 0 END) AS depositados_cant,
                COALESCE(SUM(CASE WHEN estado = 'DEPOSITADO' THEN monto ELSE 0 END), 0) AS depositados_monto,
                SUM(CASE WHEN estado = 'ENDOSADO' THEN 1 ELSE 0 END) AS endosados_cant,
                COALESCE(SUM(CASE WHEN estado = 'ENDOSADO' THEN monto ELSE 0 END), 0) AS endosados_monto,
                SUM(CASE WHEN estado = 'RECHAZADO' THEN 1 ELSE 0 END) AS rechazados_cant,
                COALESCE(SUM(CASE WHEN estado = 'RECHAZADO' THEN monto ELSE 0 END), 0) AS rechazados_monto,
                SUM(CASE WHEN estado = 'COBRADO' AND DATE_FORMAT(COALESCE(fecha_acreditacion, DATE(updated_at)), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END) AS cobrados_mes_cant,
                COALESCE(SUM(CASE WHEN estado = 'COBRADO' AND DATE_FORMAT(COALESCE(fecha_acreditacion, DATE(updated_at)), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN monto ELSE 0 END), 0) AS cobrados_mes_monto
            FROM cheques
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'en_cartera_cant'    => (int)($row['en_cartera_cant'] ?? 0),
            'en_cartera_monto'   => (float)($row['en_cartera_monto'] ?? 0),
            'vencidos_cant'      => (int)($row['vencidos_cant'] ?? 0),
            'vencidos_monto'     => (float)($row['vencidos_monto'] ?? 0),
            'por_vencer_cant'    => (int)($row['por_vencer_cant'] ?? 0),
            'por_vencer_monto'   => (float)($row['por_vencer_monto'] ?? 0),
            'depositados_cant'   => (int)($row['depositados_cant'] ?? 0),
            'depositados_monto'  => (float)($row['depositados_monto'] ?? 0),
            'endosados_cant'     => (int)($row['endosados_cant'] ?? 0),
            'endosados_monto'    => (float)($row['endosados_monto'] ?? 0),
            'rechazados_cant'    => (int)($row['rechazados_cant'] ?? 0),
            'rechazados_monto'   => (float)($row['rechazados_monto'] ?? 0),
            'cobrados_mes_cant'  => (int)($row['cobrados_mes_cant'] ?? 0),
            'cobrados_mes_monto' => (float)($row['cobrados_mes_monto'] ?? 0),
        ];
    }

    /**
     * Cambia el estado del cheque aplicando los efectos financieros.
     *
     * $context puede traer:
     * - usuario_id
     * - caja_banco_id y fecha_acreditacion (para COBRADO)
     * - proveedor_id y fecha_endoso (para ENDOSADO)
     */
    public function cambiarEstado(int $id, string $estado, array $context = []): void
    {
        $estado = strtoupper(trim($estado));
        if (!isset(self::ESTADOS[$estado])) {
            throw new Exception('Estado de cheque inválido.');
        }
        if ($estado === 'ANULADO') {
            throw new Exception('Para anular el cheque, anule el cobro asociado.');
        }

        $cheque = $this->find($id);
        if (!$cheque) {
            throw new Exception('Cheque no encontrado.');
        }

        $estadoAnterior = strtoupper((string)$cheque['estado']);
        if ($estadoAnterior === $estado) {
            return;
        }

        if (!empty($cheque['pago_anulado'])) {
            throw new Exception('No se puede cambiar el estado: el cobro asociado está anulado.');
        }

        $permitidos = self::TRANSICIONES[$estadoAnterior] ?? [];
        if (!in_array($estado, $permitidos, true)) {
            throw new Exception('No se puede pasar de "' . (self::ESTADOS[$estadoAnterior] ?? $estadoAnterior) . '" a "' . (self::ESTADOS[$estado] ?? $estado) . '".');
        }

        $usuarioId = (int)($context['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new Exception('No se pudo identificar al usuario que cambia el cheque.');
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($estado === 'COBRADO') {
                $this->registrarCobro($cheque, $context, $usuarioId);
            } elseif ($estado === 'ENDOSADO') {
                $this->registrarEndoso($cheque, $context, $usuarioId);
            } elseif ($estado === 'RECHAZADO') {
                $this->registrarRechazo($cheque, $usuarioId);
            } elseif ($estado === 'EN_CARTERA') {
                if ($estadoAnterior === 'COBRADO') {
                    $this->revertirCobro($cheque, $usuarioId);
                    $this->setEstado($id, 'EN_CARTERA');
                } elseif ($estadoAnterior === 'ENDOSADO') {
                    $this->revertirEndoso($cheque, $usuarioId);
                    $this->setEstado($id, 'EN_CARTERA');
                } elseif ($estadoAnterior === 'RECHAZADO') {
                    $this->revertirRechazo($cheque, $usuarioId);
                } else {
                    $this->setEstado($id, 'EN_CARTERA');
                }
            } elseif ($estado === 'DEPOSITADO') {
                $this->setEstado($id, 'DEPOSITADO');
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
     * Acredita el cheque en la caja/banco elegida.
     */
    private function registrarCobro(array $cheque, array $context, int $usuarioId): void
    {
        $id = (int)$cheque['id'];
        $cajaBancoId = (int)($context['caja_banco_id'] ?? $cheque['caja_banco_id'] ?? 0);
        if ($cajaBancoId <= 0) {
            throw new Exception('Seleccione la caja o banco donde se acreditó el cheque.');
        }

        $caja = $this->cajaModel->findById($cajaBancoId);
        if (!$caja || empty($caja['activa'])) {
            throw new Exception('La caja/banco seleccionada no es válida.');
        }

        $fecha = trim((string)($context['fecha_acreditacion'] ?? '')) ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception('La fecha de acreditación no es válida.');
        }

        $resultado = $this->asientoAuto->chequeCobrado($cheque, $cajaBancoId, $usuarioId, $fecha);

        $this->db->prepare("
            UPDATE cheques
            SET estado = 'COBRADO',
                caja_banco_id = ?,
                fecha_acreditacion = ?,
                movimiento_caja_id = ?,
                asiento_cobro_id = ?
            WHERE id = ?
        ")->execute([$cajaBancoId, $fecha, $resultado['movimiento_id'], $resultado['asiento_id'], $id]);
    }

    /**
     * Endosa el cheque a un proveedor y cancela deuda en la cuenta corriente
     * de la empresa. No genera movimiento de caja/banco.
     */
    private function registrarEndoso(array $cheque, array $context, int $usuarioId): void
    {
        $id = (int)$cheque['id'];
        $proveedorId = (int)($context['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            throw new Exception('Seleccione el proveedor al que se endosa el cheque.');
        }

        $stmt = $this->db->prepare("SELECT id, razon_social FROM proveedores WHERE id = ? AND activo = 1");
        $stmt->execute([$proveedorId]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proveedor) {
            throw new Exception('Proveedor no encontrado o inactivo.');
        }

        $fecha = trim((string)($context['fecha_endoso'] ?? '')) ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Exception('La fecha del endoso no es válida.');
        }

        $descripcion = "Endoso cheque {$cheque['banco']} Nº {$cheque['numero']} - {$proveedor['razon_social']}";

        $cceId = $this->ccEmpresa->create([
            'tipo'            => 'ENDOSO',
            'descripcion'     => $descripcion,
            'monto'           => (float)$cheque['monto'],
            'fecha'           => $fecha . ' 00:00:00',
            'proveedor_id'    => $proveedorId,
            'referencia_id'   => $id,
            'referencia_tipo' => 'ENDOSO_CHEQUE',
            'usuario_id'      => $usuarioId,
        ]);

        $asientoId = $this->asientoAuto->chequeEndosado($cheque, $proveedorId, $usuarioId, $fecha);

        $this->db->prepare("
            UPDATE cheques
            SET estado = 'ENDOSADO',
                proveedor_id = ?,
                endosado_at = ?,
                cuenta_corriente_empresa_id = ?,
                asiento_endoso_id = ?
            WHERE id = ?
        ")->execute([$proveedorId, $fecha . ' 00:00:00', $cceId, $asientoId, $id]);
    }

    /**
     * Rechaza el cheque: revierte la acreditación si existía, saca el activo
     * de cartera y vuelve a debitar al cliente en su cuenta corriente.
     */
    private function registrarRechazo(array $cheque, int $usuarioId): void
    {
        $id = (int)$cheque['id'];
        $estadoAnterior = strtoupper((string)$cheque['estado']);

        if ($estadoAnterior === 'COBRADO') {
            $this->revertirCobro($cheque, $usuarioId);
        } elseif ($estadoAnterior === 'ENDOSADO') {
            $this->revertirEndoso($cheque, $usuarioId);
        }

        $this->revertirCartera($cheque, $usuarioId);

        $this->ccCliente->registrarDebito(
            (int)$cheque['cliente_id'],
            (float)$cheque['monto'],
            'CHEQUE_RECHAZADO',
            $id,
            $usuarioId,
            "Cheque rechazado {$cheque['banco']} Nº {$cheque['numero']}",
            !empty($cheque['cliente_nombre']) ? (string)$cheque['cliente_nombre'] : null
        );

        $this->setEstado($id, 'RECHAZADO');
    }

    /**
     * Vuelve de RECHAZADO a EN_CARTERA: rehace el crédito en ctacte y el
     * asiento de cartera que se habían revertido al rechazar.
     */
    private function revertirRechazo(array $cheque, int $usuarioId): void
    {
        $id = (int)$cheque['id'];

        $this->ccCliente->registrarCredito(
            (int)$cheque['cliente_id'],
            (float)$cheque['monto'],
            'CHEQUE_RECHAZO_REVERTIDO',
            $id,
            $usuarioId,
            "Reversión rechazo cheque {$cheque['banco']} Nº {$cheque['numero']}",
            !empty($cheque['cliente_nombre']) ? (string)$cheque['cliente_nombre'] : null
        );

        $asientoId = $this->asientoAuto->chequeEnCartera(
            (int)$cheque['cliente_id'],
            (float)$cheque['monto'],
            $id,
            $usuarioId,
            !empty($cheque['cliente_nombre']) ? (string)$cheque['cliente_nombre'] : null
        );

        $this->db->prepare("UPDATE cheques SET estado = 'EN_CARTERA', asiento_cartera_id = ? WHERE id = ?")
            ->execute([$asientoId, $id]);
    }

    /**
     * Revierte la acreditación en banco: asiento inverso + egreso de caja.
     */
    private function revertirCobro(array $cheque, int $usuarioId): void
    {
        $id = (int)$cheque['id'];

        $asientoId = !empty($cheque['asiento_cobro_id']) ? (int)$cheque['asiento_cobro_id'] : 0;
        if ($asientoId <= 0) {
            $asiento = $this->asientoModel->findByOrigen('CHEQUES', 'COBRADO', $id);
            $asientoId = $asiento ? (int)$asiento['id'] : 0;
        }

        $reversoAsientoId = null;
        if ($asientoId > 0) {
            $reversoAsientoId = $this->asientoModel->anular($asientoId, $usuarioId);
        }

        $movimiento = null;
        if (!empty($cheque['movimiento_caja_id'])) {
            $movimiento = $this->cajaModel->findMovimiento((int)$cheque['movimiento_caja_id']);
        }
        if (!$movimiento && !empty($cheque['caja_banco_id'])) {
            $movimiento = $this->cajaModel->getMovimientosByReferencia('CHEQUES', 'COBRADO', $id);
        }

        if ($movimiento) {
            $this->cajaModel->registrarMovimiento([
                'caja_banco_id'       => $movimiento['caja_banco_id'],
                'fecha'               => date('Y-m-d'),
                'tipo'                => 'EGRESO',
                'monto'               => (float)$movimiento['monto'],
                'descripcion'         => "Reverso cobro cheque #{$id}",
                'asiento_contable_id' => $reversoAsientoId,
                'referencia_modulo'   => 'CHEQUES',
                'referencia_tipo'     => 'REVERSO_COBRO',
                'referencia_id'       => $id,
                'usuario_id'          => $usuarioId,
            ]);
        }

        $this->db->prepare("
            UPDATE cheques
            SET fecha_acreditacion = NULL,
                movimiento_caja_id = NULL,
                asiento_cobro_id = NULL
            WHERE id = ?
        ")->execute([$id]);
    }

    /**
     * Revierte el endoso: asiento inverso y baja lógica del movimiento de
     * cuenta corriente de la empresa (la deuda con el proveedor vuelve).
     */
    private function revertirEndoso(array $cheque, int $usuarioId): void
    {
        $id = (int)$cheque['id'];

        $asientoId = !empty($cheque['asiento_endoso_id']) ? (int)$cheque['asiento_endoso_id'] : 0;
        if ($asientoId <= 0) {
            $asiento = $this->asientoModel->findByOrigen('CHEQUES', 'ENDOSADO', $id);
            $asientoId = $asiento ? (int)$asiento['id'] : 0;
        }
        if ($asientoId > 0) {
            $this->asientoModel->anular($asientoId, $usuarioId);
        }

        $cceId = !empty($cheque['cuenta_corriente_empresa_id']) ? (int)$cheque['cuenta_corriente_empresa_id'] : 0;
        if ($cceId <= 0) {
            $stmt = $this->db->prepare("
                SELECT id FROM cuentas_corrientes_empresa
                WHERE referencia_tipo = 'ENDOSO_CHEQUE' AND referencia_id = ? AND activo = 1
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$id]);
            $cceId = (int)$stmt->fetchColumn();
        }
        if ($cceId > 0) {
            $this->db->prepare("UPDATE cuentas_corrientes_empresa SET activo = 0 WHERE id = ?")
                ->execute([$cceId]);
        }

        $this->db->prepare("
            UPDATE cheques
            SET proveedor_id = NULL,
                endosado_at = NULL,
                cuenta_corriente_empresa_id = NULL,
                asiento_endoso_id = NULL
            WHERE id = ?
        ")->execute([$id]);
    }

    /**
     * Revierte el asiento de cartera del cheque.
     */
    private function revertirCartera(array $cheque, int $usuarioId): void
    {
        $id = (int)$cheque['id'];

        $asientoId = !empty($cheque['asiento_cartera_id']) ? (int)$cheque['asiento_cartera_id'] : 0;
        if ($asientoId <= 0) {
            $asiento = $this->asientoModel->findByOrigen('CHEQUES', 'EN_CARTERA', $id);
            $asientoId = $asiento ? (int)$asiento['id'] : 0;
        }
        if ($asientoId > 0) {
            $this->asientoModel->anular($asientoId, $usuarioId);
        }

        $this->db->prepare("UPDATE cheques SET asiento_cartera_id = NULL WHERE id = ?")
            ->execute([$id]);
    }

    private function setEstado(int $id, string $estado): void
    {
        $this->db->prepare("UPDATE cheques SET estado = ? WHERE id = ?")->execute([$estado, $id]);
    }

    /**
     * Anula el cheque porque se anuló el cobro. No toca la cuenta corriente:
     * eso lo hace Cobro::anular() con el débito de reversión.
     */
    public function anularCobro(int $pagoId, int $usuarioId): void
    {
        $cheque = $this->porPago($pagoId);
        if (!$cheque) {
            return;
        }
        if (strtoupper((string)$cheque['estado']) === 'ANULADO') {
            return;
        }

        $inTransaction = $this->db->inTransaction();
        if (!$inTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $estado = strtoupper((string)$cheque['estado']);
            if ($estado === 'COBRADO') {
                $this->revertirCobro($cheque, $usuarioId);
            } elseif ($estado === 'ENDOSADO') {
                $this->revertirEndoso($cheque, $usuarioId);
            }

            $this->revertirCartera($cheque, $usuarioId);

            $this->db->prepare("
                UPDATE cheques
                SET estado = 'ANULADO',
                    proveedor_id = NULL,
                    endosado_at = NULL,
                    fecha_acreditacion = NULL,
                    cuenta_corriente_empresa_id = NULL
                WHERE id = ?
            ")->execute([(int)$cheque['id']]);

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
     * Compatibilidad con el nombre anterior.
     */
    public function anularPorPago(int $pagoId): void
    {
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new Exception('No se pudo identificar al usuario para anular el cheque.');
        }
        $this->anularCobro($pagoId, $usuarioId);
    }

    /**
     * Edita los datos del cheque. Si cambia el vencimiento se resetea el
     * aviso para que el cron vuelva a notificarlo.
     */
    public function actualizar(int $id, array $data): void
    {
        $cheque = $this->find($id);
        if (!$cheque) {
            throw new Exception('Cheque no encontrado.');
        }
        if (strtoupper((string)($cheque['estado'] ?? '')) === 'ANULADO' || !empty($cheque['pago_anulado'])) {
            throw new Exception('No se puede editar un cheque anulado.');
        }

        $numero = trim((string)($data['numero'] ?? ''));
        $banco = trim((string)($data['banco'] ?? ''));
        $fechaVencimiento = trim((string)($data['fecha_vencimiento'] ?? ''));
        $monto = (float)($data['monto'] ?? 0);

        if ($numero === '') {
            throw new Exception('El número de cheque es obligatorio.');
        }
        if ($banco === '') {
            throw new Exception('El banco es obligatorio.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new Exception('La fecha de vencimiento es obligatoria.');
        }
        [$anioV, $mesV, $diaV] = array_map('intval', explode('-', $fechaVencimiento));
        if (!checkdate($mesV, $diaV, $anioV)) {
            throw new Exception('La fecha de vencimiento del cheque no es válida.');
        }
        if ($monto <= 0) {
            throw new Exception('El monto del cheque debe ser mayor a cero.');
        }
        if (abs($monto - (float)$cheque['monto']) >= 0.01) {
            throw new Exception('No se puede modificar el monto del cheque porque ya impactó en la cuenta corriente y en la contabilidad. Anulá el cobro y registrá uno nuevo.');
        }

        $nuevaCaja = !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null;
        if (($cheque['estado'] ?? '') === 'COBRADO' && (int)($cheque['caja_banco_id'] ?? 0) !== (int)($nuevaCaja ?? 0)) {
            throw new Exception('El cheque ya está acreditado en una caja/banco. Para cambiarlo, volvé a cartera primero.');
        }

        $tipo = strtoupper((string)($data['tipo'] ?? 'FISICO'));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'FISICO';
        }

        $fechaEmision = trim((string)($data['fecha_emision'] ?? ''));
        if ($fechaEmision !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                $fechaEmision = '';
            } else {
                [$anioE, $mesE, $diaE] = array_map('intval', explode('-', $fechaEmision));
                if (!checkdate($mesE, $diaE, $anioE)) {
                    $fechaEmision = '';
                }
            }
        }

        $resetAviso = ($cheque['fecha_vencimiento'] ?? null) !== $fechaVencimiento;

        $stmt = $this->db->prepare("
            UPDATE cheques SET
                tipo = ?,
                numero = ?,
                banco = ?,
                sucursal = ?,
                titular = ?,
                cuit = ?,
                fecha_emision = ?,
                fecha_vencimiento = ?,
                monto = ?,
                observaciones = ?,
                caja_banco_id = ?,
                ultimo_aviso_at = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $tipo,
            $numero,
            $banco,
            !empty($data['sucursal']) ? trim((string)$data['sucursal']) : null,
            !empty($data['titular']) ? trim((string)$data['titular']) : null,
            !empty($data['cuit']) ? trim((string)$data['cuit']) : null,
            $fechaEmision !== '' ? $fechaEmision : null,
            $fechaVencimiento,
            $monto,
            !empty($data['observaciones']) ? trim((string)$data['observaciones']) : null,
            !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null,
            $resetAviso ? null : ($cheque['ultimo_aviso_at'] ?? null),
            $id,
        ]);
    }

    /**
     * Cheques pendientes de acreditación que vencen dentro de N días (o ya
     * vencidos) y que todavía no fueron avisados hoy. Lo usa el cron.
     */
    public function pendientesAviso(int $dias = 7, bool $ignorarAviso = false): array
    {
        $dias = max(0, $dias);
        $filtroAviso = $ignorarAviso
            ? ''
            : 'AND (ch.ultimo_aviso_at IS NULL OR DATE(ch.ultimo_aviso_at) < CURDATE())';

        return $this->db->query("
            SELECT ch.*,
                   COALESCE(NULLIF(ch.cliente_nombre, ''), c.razon_social, CONCAT('Cliente #', ch.cliente_id)) AS nombre_cliente,
                   c.email AS cliente_email,
                   DATEDIFF(ch.fecha_vencimiento, CURDATE()) AS dias_restantes
            FROM cheques ch
            LEFT JOIN clientes c ON c.id = ch.cliente_id
            WHERE ch.estado IN ('EN_CARTERA','DEPOSITADO')
              AND ch.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL {$dias} DAY)
              {$filtroAviso}
            ORDER BY ch.fecha_vencimiento ASC, ch.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca los cheques avisados para no repetir el mail el mismo día.
     */
    public function marcarAvisados(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE cheques SET ultimo_aviso_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
    }

    /**
     * Proveedores activos para el formulario de endoso.
     */
    public function proveedoresActivos(): array
    {
        return $this->db->query("SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
