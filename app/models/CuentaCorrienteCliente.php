<?php
require_once BASE_PATH . '/app/core/Model.php';
class CuentaCorrienteCliente extends Model
{
    protected string $table = 'cuentas_corriente_clientes';

    public function all(): array //trar todos los registros de asicentos, credito o debito de todos los clientes
    {
        return $this->db->query("
            SELECT ccc.*, c.razon_social
            FROM cuentas_corriente_clientes ccc
            JOIN clientes c ON c.id = ccc.cliente_id
            ORDER BY ccc.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ccc.*, c.razon_social
            FROM cuentas_corriente_clientes ccc
            JOIN clientes c ON c.id = ccc.cliente_id
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
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        }catch(Exception $e){
            error_log('Error fetching deudasActualCliente: ' . $e->getMessage());
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
            error_log('Error fetching datonp: ' . $e->getMessage());
            return null;
        }
    }

    public function registrarDebito(
        int $clienteId,
        float $monto,
        string $origen,
        int $referenciaId,
        int $usuarioId,
        ?string $obs = null
    ): void {
        // calcular saldo actual
        $saldoActual = $this->saldoCliente($clienteId);

        $stmt = $this->db->prepare("
            INSERT INTO cuentas_corriente_clientes
            (cliente_id, fecha, tipo, origen, referencia_id, monto, saldo, observaciones, usuario_id)
            VALUES (?, CURDATE(), 'DEBITO', ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $clienteId,
            $origen,
            $referenciaId,
            $monto,
            $saldoActual + $monto,
            $obs,
            $usuarioId
        ]);
    }

    public function registrarCredito(
        int $clienteId,
        float $monto,
        string $origen,
        int $referenciaId,
        int $usuarioId,
        ?string $obs = null
    ): void {
        $saldoActual = $this->saldoCliente($clienteId);

        $stmt = $this->db->prepare("
            INSERT INTO cuentas_corriente_clientes
            (cliente_id, fecha, tipo, origen, referencia_id, monto, saldo, observaciones, usuario_id)
            VALUES (?, CURDATE(), 'CREDITO', ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $clienteId,
            $origen,
            $referenciaId,
            $monto,
            $saldoActual - $monto,
            $obs,
            $usuarioId
        ]);
    }

    public function saldoCliente(int $clienteId): float
    {
        try{
            // calculo el gasto actual del cliente
            $stmt = $this->db->prepare("
                SELECT SUM(monto)
                FROM cuentas_corriente_clientes
                WHERE cliente_id = ? AND tipo = 'DEBITO'
                ORDER BY fecha DESC
                LIMIT 1
            ");
            $stmt->execute([$clienteId]);
            $saldodeuda = $stmt->fetchColumn();

            // calculo el pago actual del cliente
            $stmt = $this->db->prepare("
                SELECT SUM(monto)
                FROM cuentas_corriente_clientes
                WHERE cliente_id = ? AND tipo = 'CREDITO'
                ORDER BY fecha DESC
                LIMIT 1
            ");
            $stmt->execute([$clienteId]);
            $saldocredito = $stmt->fetchColumn();
            $saltocliente=$saldodeuda - $saldocredito;
            return (float)$saltocliente;
        }catch(Exception $e){
            error_log('Error calculating saldoCliente: ' . $e->getMessage());
            $_SESSION['error'] = 'Ocurrio un error al calcular el saldo del cliente (id:'. $clienteId .').'. $e->getMessage();
            header('Location: '.BASE_URL.'/ctacte');
            exit;
        }
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
            FROM cuentas_corriente_cliente
            WHERE cliente_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$clienteId]);
        return (float)($stmt->fetchColumn() ?? 0);
    }


}