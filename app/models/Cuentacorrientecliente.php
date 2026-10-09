<?php

use function Safe\error_log;

require_once BASE_PATH . '/app/core/Model.php';
class CuentaCorrienteCliente extends Model
{
    protected string $table = 'cuentas_corriente_clientes';

    /**
     * Construye WHERE dinámico con prepared statements a partir de los filtros.
     * Filtros soportados: cliente_id, tipo, origen, fecha_desde, fecha_hasta, buscar.
     * Requiere el JOIN a clientes (alias c) en la query que lo use.
     * @return array [$whereSql, $params]  ($whereSql vacío si no hay filtros)
     */
    private function buildWhere(array $filtros, string $alias = 'ccc'): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtros['cliente_id'])) {
            $where[]  = "$alias.cliente_id = ?";
            $params[] = (int)$filtros['cliente_id'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]  = "$alias.tipo = ?";
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['origen'])) {
            $where[]  = "$alias.origen = ?";
            $params[] = $filtros['origen'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]  = "$alias.fecha >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]  = "$alias.fecha <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['buscar'])) {
            $q = '%' . $filtros['buscar'] . '%';
            $where[] = "(COALESCE(c.razon_social, '') LIKE ?
                        OR COALESCE($alias.cliente_nombre, '') LIKE ?
                        OR COALESCE($alias.origen, '') LIKE ?
                        OR CAST($alias.referencia_id AS CHAR) LIKE ?)";
            array_push($params, $q, $q, $q, $q);
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Libro general de movimientos (todos los clientes) con filtros y paginación.
     */
    public function all(array $filtros = [], int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = $this->buildWhere($filtros);

        $sql = "
            SELECT ccc.*,
                   COALESCE(c.razon_social, ccc.cliente_nombre) AS nombre_cliente
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id"
            . ($where ? " WHERE $where" : '')
            . " ORDER BY ccc.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)(($page - 1) * $perPage);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de movimientos que cumplen los filtros (para paginación).
     */
    public function countAll(array $filtros = []): int
    {
        [$where, $params] = $this->buildWhere($filtros);

        $sql = "
            SELECT COUNT(*)
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id"
            . ($where ? " WHERE $where" : '');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Resumen (débitos, créditos, saldo) del conjunto filtrado.
     */
    public function resumenFiltros(array $filtros): array
    {
        [$where, $params] = $this->buildWhere($filtros);

        $sql = "
            SELECT COALESCE(SUM(CASE WHEN ccc.tipo = 'DEBITO'  THEN ccc.monto ELSE 0 END), 0) AS total_debito,
                   COALESCE(SUM(CASE WHEN ccc.tipo = 'CREDITO' THEN ccc.monto ELSE 0 END), 0) AS total_credito,
                   COALESCE(SUM(CASE WHEN ccc.tipo = 'DEBITO'  THEN ccc.monto ELSE 0 END), 0)
                 - COALESCE(SUM(CASE WHEN ccc.tipo = 'CREDITO' THEN ccc.monto ELSE 0 END), 0) AS saldo
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id"
            . ($where ? " WHERE $where" : '');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['total_debito' => 0, 'total_credito' => 0, 'saldo' => 0];
    }

    /**
     * Orígenes distintos presentes en la tabla (para el combo de filtros).
     */
    public function origenes(): array
    {
        return $this->db->query("
            SELECT DISTINCT origen
            FROM cuentas_corriente_clientes
            ORDER BY origen
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Desglose por cliente: débitos, créditos, saldo y última actividad.
     * Clientes ocasionales (id 9999) se agrupan por cliente_nombre (cada uno por su nombre).
     */
    public function resumenPorClientes(): array
    {
        return $this->db->query("
            SELECT ccc.cliente_id,
                   CASE WHEN ccc.cliente_id = 9999
                        THEN MAX(ccc.cliente_nombre)
                        ELSE COALESCE(MAX(c.razon_social), CONCAT('Cliente #', ccc.cliente_id))
                   END AS nombre_cliente,
                   CASE WHEN ccc.cliente_id = 9999
                        THEN ''
                        ELSE COALESCE(MAX(c.cuit), '')
                   END AS cuit,
                   CASE WHEN ccc.cliente_id = 9999
                        THEN MAX(ccc.cliente_nombre)
                        ELSE NULL
                   END AS cliente_nombre,
                   SUM(CASE WHEN ccc.tipo = 'DEBITO'  THEN ccc.monto ELSE 0 END) AS total_debito,
                   SUM(CASE WHEN ccc.tipo = 'CREDITO' THEN ccc.monto ELSE 0 END) AS total_credito,
                   SUM(CASE WHEN ccc.tipo = 'DEBITO'  THEN ccc.monto ELSE 0 END)
                 - SUM(CASE WHEN ccc.tipo = 'CREDITO' THEN ccc.monto ELSE 0 END) AS saldo,
                   MAX(ccc.fecha) AS ultima_actividad
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id
            GROUP BY ccc.cliente_id, IF(ccc.cliente_id = 9999, ccc.cliente_nombre, NULL)
            ORDER BY saldo DESC, nombre_cliente ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen (débitos, créditos, saldo actual) de un solo cliente.
     * Para ocasionales (id 9999) se puede acotar por cliente_nombre.
     */
    public function resumenCliente(int $clienteId, ?string $clienteNombre = null): array
    {
        $sql = "
            SELECT COALESCE(SUM(CASE WHEN tipo = 'DEBITO'  THEN monto ELSE 0 END), 0) AS total_debito,
                   COALESCE(SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END), 0) AS total_credito,
                   COALESCE(SUM(CASE WHEN tipo = 'DEBITO'  THEN monto ELSE 0 END), 0)
                 - COALESCE(SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END), 0) AS saldo
            FROM cuentas_corriente_clientes
            WHERE cliente_id = ?";
        $params = [$clienteId];

        if ($clienteId === 9999 && $clienteNombre !== null && $clienteNombre !== '') {
            $sql .= " AND cliente_nombre = ?";
            $params[] = $clienteNombre;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['total_debito' => 0, 'total_credito' => 0, 'saldo' => 0];
    }

    /**
     * Extracto de movimientos de un cliente (orden cronológico), con filtros y paginación.
     * Para ocasionales (id 9999) se puede acotar por cliente_nombre.
     */
    public function movimientosCliente(int $clienteId, array $filtros = [], int $page = 1, int $perPage = 50): array
    {
        $filtros['cliente_id'] = $clienteId;
        [$where, $params] = $this->buildWhere($filtros);

        if ($clienteId === 9999 && !empty($filtros['cliente_nombre'])) {
            $where       = $where ? "$where AND ccc.cliente_nombre = ?" : "ccc.cliente_nombre = ?";
            $params[]    = $filtros['cliente_nombre'];
        }

        $sql = "
            SELECT ccc.*,
                   COALESCE(c.razon_social, ccc.cliente_nombre) AS nombre_cliente
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id"
            . ($where ? " WHERE $where" : '')
            . " ORDER BY ccc.fecha ASC, ccc.id ASC
            LIMIT " . (int)$perPage . " OFFSET " . (int)(($page - 1) * $perPage);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total de movimientos de un cliente que cumplen los filtros (para paginación).
     * Para ocasionales (id 9999) se puede acotar por cliente_nombre.
     */
    public function countMovimientosCliente(int $clienteId, array $filtros = []): int
    {
        $filtros['cliente_id'] = $clienteId;
        [$where, $params] = $this->buildWhere($filtros);

        if ($clienteId === 9999 && !empty($filtros['cliente_nombre'])) {
            $where       = $where ? "$where AND ccc.cliente_nombre = ?" : "ccc.cliente_nombre = ?";
            $params[]    = $filtros['cliente_nombre'];
        }

        $sql = "
            SELECT COUNT(*)
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id"
            . ($where ? " WHERE $where" : '');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ccc.*, 
                   c.razon_social AS nombre_cliente
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id
            WHERE ccc.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 📌 Deudas pendientes (solo débitos no compensados)
     */
    public function deudasPorCliente(int $clienteId): array
    {
        return $this->db->query("
            SELECT *
            FROM cuentas_corriente_clientes
            WHERE cliente_id = $clienteId
              AND tipo = 'DEBITO'
            ORDER BY fecha
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * 📌 devolver debito y redito por cliente para tener el valor de deuda claro.
     */
    public function deudasActualCliente(int $clienteId): array
    {
        try{
            $stmt = $this->db->prepare("
                SELECT
                    (SELECT SUM(monto) FROM cuentas_corriente_clientes WHERE cliente_id=? AND tipo='DEBITO') AS Debito, 
                    (SELECT SUM(monto) FROM cuentas_corriente_clientes WHERE cliente_id=? AND tipo='CREDITO') AS Credito,
                    saldo
                FROM cuentas_corriente_clientes
                WHERE cliente_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$clienteId, $clienteId, $clienteId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log('deudasActualCliente result: ' . print_r($result, true).' - '.__FILE__ . ':' . __LINE__);
            return $result ? [$result] : [];
        }catch(Exception $e){
            error_log('Error fetching deudasActualCliente: ' . $e->getMessage().' - '.__FILE__ . ':' . __LINE__);
            $_SESSION['error'] = 'Ocurrio un error al obtener las deudas del cliente (id:'. $clienteId .').'. $e->getMessage();
            return [];
        }
    }

    public function datonp($id,$pord_id){
        try {
            $stmt = $this->db->prepare("
                SELECT npd.*,np.*, SUM(npd.cantidad*npd.precio) AS subtotal
                FROM notas_pedido_detalle npd
                LEFT JOIN notas_pedido np ON np.id=npd.nota_pedido_id
                WHERE npd.nota_pedido_id = ? AND npd.producto_id=?
            ");
            $stmt->execute([$id, $pord_id]);
            $dato = $stmt->fetch(PDO::FETCH_ASSOC);
            return $dato ?: null;
        } catch (Exception $e) {
            error_log('Error fetching datonp: ' . $e->getMessage().' - '.__FILE__ . ':' . __LINE__);
            return null;
        }
    }

    public function registrarDebito(int $clienteId,float $monto,string $origen,int $referenciaId,int $usuarioId,?string $obs = null, ?string $clienteNombre = null): void {
        try {
            // calcular saldo actual
            $saldoActual = $this->saldoCliente($clienteId);
            $stmt = $this->db->prepare("
                INSERT INTO cuentas_corriente_clientes
                (cliente_id, cliente_nombre, fecha, tipo, origen, referencia_id, monto, saldo, observaciones, usuario_id)
                VALUES (?, ?, CURDATE(), 'DEBITO', ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([$clienteId, $clienteNombre, $origen,$referenciaId,$monto,$saldoActual + $monto,$obs,$usuarioId]);
            empresaLog("Debito registrado: cliente_id=$clienteId, nombre=$clienteNombre, monto=$monto, nuevo_saldo=" . ($saldoActual + $monto));
        } catch (Exception $e) {
            empresaLog("Error registrando debito: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function registrarCredito(int $clienteId,float $monto,string $origen,int $referenciaId,int $usuarioId,?string $obs = null, ?string $clienteNombre = null): void {
        try {
            $saldoActual = $this->saldoCliente($clienteId);
            $stmt = $this->db->prepare("
                INSERT INTO cuentas_corriente_clientes
                (cliente_id, cliente_nombre, fecha, tipo, origen, referencia_id, monto, saldo, observaciones, usuario_id)
                VALUES (?, ?, CURDATE(), 'CREDITO', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$clienteId, $clienteNombre, $origen,$referenciaId,$monto,$saldoActual - $monto,$obs,$usuarioId]);
            empresaLog("Credito registrado: cliente_id=$clienteId, nombre=$clienteNombre, monto=$monto, nuevo_saldo=" . ($saldoActual - $monto));
        } catch (Exception $e) {
            empresaLog("Error registrando credito: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function saldoCliente(int $clienteId): float
    {
        try{
            // calculo el gasto actual del cliente
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(monto), 0)
                FROM cuentas_corriente_clientes
                WHERE cliente_id = ? AND tipo = 'DEBITO'
            ");
            $stmt->execute([$clienteId]);
            $saldodeuda = $stmt->fetchColumn();

            // calculo el pago actual del cliente
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(monto), 0)
                FROM cuentas_corriente_clientes
                WHERE cliente_id = ? AND tipo = 'CREDITO'
            ");
            $stmt->execute([$clienteId]);
            $saldocredito = $stmt->fetchColumn();
            $saltocliente=$saldodeuda - $saldocredito;
            return (float)$saltocliente;
        }catch(Exception $e){
            error_log('Error calculating saldoCliente: ' . $e->getMessage().' - '.__FILE__ . ':' . __LINE__);
            $_SESSION['error'] = 'Ocurrio un error al calcular el saldo del cliente (id:'. $clienteId .').'. $e->getMessage();
            header('Location: '.BASE_URL.'/ctacte');
            exit;
        }
    }

    /**
     * Obtener nombres únicos de clientes ocasionales (id 9999) con saldo deudor.
     */
    public function clientesOcasionalesConDeuda(): array
    {
        $stmt = $this->db->prepare("
            SELECT cliente_nombre,
                   SUM(CASE WHEN tipo = 'DEBITO' THEN monto ELSE 0 END) AS total_debito,
                   SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END) AS total_credito,
                   SUM(CASE WHEN tipo = 'DEBITO' THEN monto ELSE 0 END) - SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END) AS saldo
            FROM cuentas_corriente_clientes
            WHERE cliente_id = 9999 AND cliente_nombre IS NOT NULL AND cliente_nombre != ''
            GROUP BY cliente_nombre
            HAVING saldo > 0
            ORDER BY cliente_nombre
        ");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deudas de un cliente ocasional por nombre (no por ID).
     */
    public function deudasPorNombreOcasional(string $clienteNombre): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM cuentas_corriente_clientes
            WHERE cliente_id = 9999 AND cliente_nombre = ?
            ORDER BY fecha ASC, id ASC
        ");
        $stmt->execute([$clienteNombre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo de un cliente ocasional por nombre.
     */
    public function saldoPorNombreOcasional(string $clienteNombre): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN tipo = 'DEBITO' THEN monto ELSE 0 END) AS Debito,
                SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END) AS Credito,
                SUM(CASE WHEN tipo = 'DEBITO' THEN monto ELSE 0 END) - SUM(CASE WHEN tipo = 'CREDITO' THEN monto ELSE 0 END) AS saldo
            FROM cuentas_corriente_clientes
            WHERE cliente_id = 9999 AND cliente_nombre = ?
        ");
        $stmt->execute([$clienteNombre]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['Debito' => 0, 'Credito' => 0, 'saldo' => 0];
    }

    public function ultimoSaldo(int $clienteId): float
    {
        $stmt = $this->db->prepare("
            SELECT saldo
            FROM cuentas_corriente_clientes
            WHERE cliente_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$clienteId]);
        return (float)($stmt->fetchColumn() ?? 0);
    }

    /**
     * Ventas no cobradas: remitos con saldo pendiente de pago.
     * Distribuye devoluciones FIFO: si una devolución excede el saldo de un remito,
     * el exceso reduce el saldo de otros remitos del mismo cliente (más antiguo primero).
     */
    public function ventasNoCobradas(): array
    {
        // 1. Obtener remitos con deuda bruta (monto - pagos)
        $remitosRaw = $this->db->query("
            SELECT 
                d.referencia_id AS remito_id,
                COALESCE(d.cliente_nombre, c.razon_social) AS cliente,
                d.cliente_id,
                d.cliente_nombre,
                MIN(d.fecha) AS fecha,
                SUM(d.monto) AS monto_total,
                COALESCE((
                    SELECT SUM(p.monto) 
                    FROM pagos p 
                    WHERE p.remito_id = d.referencia_id 
                      AND p.cliente_id = d.cliente_id
                      AND (p.anulado IS NULL OR p.anulado = 0)
                ), 0) AS pagado
            FROM cuentas_corriente_clientes d
            LEFT JOIN clientes c ON c.id = d.cliente_id
            WHERE d.origen = 'REMITO' AND d.tipo = 'DEBITO'
            GROUP BY d.referencia_id, d.cliente_id, d.cliente_nombre
            HAVING monto_total - pagado > 0.01
            ORDER BY d.cliente_id, MIN(d.fecha) ASC, d.referencia_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($remitosRaw)) return [];

        // 2. Obtener devoluciones por cliente (todas las no anuladas)
        $clienteIds = array_values(array_unique(array_column($remitosRaw, 'cliente_id')));
        $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
        $stmtDev = $this->db->prepare("
            SELECT dv.cliente_id, dv.remito_id,
                   COALESCE(SUM(dd.cantidad_total * dd.precio_unitario), 0) AS monto_devolucion
            FROM devoluciones dv
            JOIN devoluciones_detalle dd ON dd.devolucion_id = dv.id
            WHERE dv.cliente_id IN ($placeholders)
              AND dv.estado != 'ANULADA'
            GROUP BY dv.cliente_id, dv.remito_id
            ORDER BY dv.cliente_id, dv.created_at ASC
        ");
        $stmtDev->execute($clienteIds);
        $devolucionesRaw = $stmtDev->fetchAll(PDO::FETCH_ASSOC);

        // 3. Indexar devoluciones por cliente
        $devolucionesPorCliente = [];
        foreach ($devolucionesRaw as $dev) {
            $cid = (int)$dev['cliente_id'];
            if (!isset($devolucionesPorCliente[$cid])) {
                $devolucionesPorCliente[$cid] = 0.0;
            }
            $devolucionesPorCliente[$cid] += (float)$dev['monto_devolucion'];
        }

        // 4. Distribuir devoluciones FIFO por cliente
        $resultados = [];
        $remitosPorCliente = [];
        foreach ($remitosRaw as $r) {
            $remitosPorCliente[(int)$r['cliente_id']][] = $r;
        }

        foreach ($remitosPorCliente as $clienteId => $remitos) {
            $devolucionPendiente = $devolucionesPorCliente[$clienteId] ?? 0.0;

            foreach ($remitos as $r) {
                $deudaBruta = (float)$r['monto_total'] - (float)$r['pagado'];

                if ($devolucionPendiente > 0) {
                    $devAplicada = min($devolucionPendiente, $deudaBruta);
                    $deudaBruta -= $devAplicada;
                    $devolucionPendiente -= $devAplicada;
                }

                if ($deudaBruta > 0.01) {
                    $resultados[] = [
                        'remito_id'       => (int)$r['remito_id'],
                        'cliente'         => $r['cliente'],
                        'cliente_id'      => (int)$r['cliente_id'],
                        'cliente_nombre'  => $r['cliente_nombre'],
                        'fecha'           => $r['fecha'],
                        'monto_total'     => (float)$r['monto_total'],
                        'pagado'          => (float)$r['pagado'],
                        'devoluciones'    => (float)$r['monto_total'] - (float)$r['pagado'] - $deudaBruta,
                        'saldo_pendiente' => $deudaBruta,
                    ];
                }
            }
        }

        // Ordenar por fecha descendente para mostrar los más recientes primero
        usort($resultados, function ($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        return $resultados;
    }
}
