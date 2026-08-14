<?php

use function Safe\error_log;

require_once BASE_PATH . '/app/core/Model.php';
class CuentaCorrienteCliente extends Model
{
    protected string $table = 'cuentas_corriente_clientes';

    public function all(): array //trar todos los registros de asicentos, credito o debito de todos los clientes
    {
        return $this->db->query("
            SELECT ccc.*, 
                   c.razon_social AS nombre_cliente
            FROM cuentas_corriente_clientes ccc
            LEFT JOIN clientes c ON c.id = ccc.cliente_id
            ORDER BY ccc.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
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

    public function movimientosPorCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM cuentas_corriente_cliente
            WHERE cliente_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Usa la tabla pagos (que tiene remito_id) para calcular lo cobrado.
     */
    public function ventasNoCobradas(): array
    {
        return $this->db->query("
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
                ), 0) AS pagado,
                SUM(d.monto) - COALESCE((
                    SELECT SUM(p.monto) 
                    FROM pagos p 
                    WHERE p.remito_id = d.referencia_id 
                      AND p.cliente_id = d.cliente_id
                      AND (p.anulado IS NULL OR p.anulado = 0)
                ), 0) AS saldo_pendiente
            FROM cuentas_corriente_clientes d
            LEFT JOIN clientes c ON c.id = d.cliente_id
            WHERE d.origen = 'REMITO' AND d.tipo = 'DEBITO'
            GROUP BY d.referencia_id, d.cliente_id, d.cliente_nombre
            HAVING saldo_pendiente > 0.01
            ORDER BY MIN(d.fecha) DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
