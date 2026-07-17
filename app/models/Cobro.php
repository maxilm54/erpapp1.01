<?php
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/CajaBanco.php';
require_once BASE_PATH . '/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH . '/app/helpers/AsientoAutomatico.php';
require_once BASE_PATH . '/app/services/PdfService.php';
require_once BASE_PATH . '/app/services/MailService.php';

class Cobro extends Model
{
    /**
     * Listar todos los cobros con datos del cliente y caja/banco.
     */
    public function all(): array
    {
        return $this->db->query("
            SELECT p.*, 
                   COALESCE(p.cliente_nombre, c.razon_social) AS nombre_cliente,
                   cb.nombre AS caja_nombre
            FROM pagos p
            LEFT JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = p.caja_banco_id
            ORDER BY p.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar un cobro por ID con datos completos.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   COALESCE(p.cliente_nombre, c.razon_social) AS nombre_cliente,
                   c.cuit, c.email, cb.nombre AS caja_nombre
            FROM pagos p
            LEFT JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN cajas_bancos cb ON cb.id = p.caja_banco_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Registrar un cobro completo: pago + ctacte + caja + asiento + PDF + mail.
     */
    public function registrar(array $data): int
    {
        $this->db->beginTransaction();

        try {
            $clienteId = (int)$data['cliente_id'];
            $monto = (float)$data['monto'];
            $usuarioId = (int)$data['usuario_id'];
            $medioPago = $data['medio_pago'] ?? null;
            $observaciones = $data['observaciones'] ?? null;
            $cajaBancoId = !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null;
            $clienteNombre = $data['cliente_nombre'] ?? null;
            $remitoId = !empty($data['remito_id']) ? (int)$data['remito_id'] : null;

            // Validar que no se pague de más
            $ccModel = new CuentaCorrienteCliente();
            if ($clienteId == 9999 && !empty($clienteNombre)) {
                $saldoData = $ccModel->saldoPorNombreOcasional($clienteNombre);
                $saldoActual = (float)($saldoData['saldo'] ?? 0);
            } else {
                $saldoData = $ccModel->deudasActualCliente($clienteId);
                $saldoActual = (float)($saldoData[0]['saldo'] ?? 0);
            }
            if ($saldoActual <= 0) {
                throw new Exception('Este cliente no tiene deuda pendiente.');
            }
            if ($monto > $saldoActual) {
                throw new Exception('El monto ($' . number_format($monto, 2, ',', '.') . ') excede la deuda ($' . number_format($saldoActual, 2, ',', '.') . ').');
            }

            // 1️⃣ Insertar pago
            $stmt = $this->db->prepare("
                INSERT INTO pagos (cliente_id, cliente_nombre, usuario_id, monto, medio_pago, observaciones, caja_banco_id, remito_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$clienteId, $clienteNombre, $usuarioId, $monto, $medioPago, $observaciones, $cajaBancoId, $remitoId]);
            $pagoId = (int)$this->db->lastInsertId();

            // 2️⃣ Registrar crédito en cuenta corriente
            $ccModel->registrarCredito(
                $clienteId,
                $monto,
                'PAGO',
                $pagoId,
                $usuarioId,
                $observaciones ?: "Cobro #{$pagoId}",
                $clienteNombre
            );

            // 3️⃣ Registrar movimiento en caja/banco
            $asientoId = null;
            if ($cajaBancoId) {
                $asientoAuto = new AsientoAutomatico();
                $asientoId = $asientoAuto->ventaCredito($clienteId, $monto, 'PAGO', $pagoId, $usuarioId, $cajaBancoId, $clienteNombre);
            }

            // 4️⃣ Generar PDF del recibo
            $pdfService = new PdfService();
            $pdfPath = $pdfService->generarReciboPago($pagoId);
            $this->db->prepare("UPDATE pagos SET pdf_path = ? WHERE id = ?")->execute([$pdfPath, $pagoId]);

            // 5️⃣ Enviar mail
            try {
                $mail = new MailService();
                $mail->enviarPago($pagoId);
            } catch (Exception $e) {
                error_log("Error enviando mail de cobro #{$pagoId}: " . $e->getMessage());
            }

            $this->db->commit();
            return $pagoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al registrar cobro: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al registrar cobro: ' . $e->getMessage());
        }
    }

    /**
     * Anular un cobro: revertir ctacte + caja + marcar pago como anulado.
     */
    public function anular(int $pagoId, int $usuarioId): void
    {
        $this->db->beginTransaction();

        try {
            $pago = $this->find($pagoId);
            if (!$pago) throw new Exception('Cobro no encontrado');
            if (!empty($pago['anulado'])) throw new Exception('El cobro ya está anulado');

            // 1️⃣ Revertir crédito en ctacte (registrar un DEBITO por el mismo monto)
            $ccModel = new CuentaCorrienteCliente();
            $ccModel->registrarDebito(
                $pago['cliente_id'],
                (float)$pago['monto'],
                'ANULACION_PAGO',
                $pagoId,
                $usuarioId,
                'Anulación de cobro #' . $pagoId
            );

            // 2️⃣ Revertir movimiento en caja/banco si tiene
            if (!empty($pago['caja_banco_id'])) {
                $cajaModel = new CajaBanco();
                $cajaModel->registrarMovimiento([
                    'caja_banco_id' => $pago['caja_banco_id'],
                    'fecha' => date('Y-m-d'),
                    'tipo' => 'EGRESO',
                    'monto' => (float)$pago['monto'],
                    'descripcion' => 'Anulación de cobro #' . $pagoId,
                    'referencia_modulo' => 'COBROS',
                    'referencia_tipo' => 'ANULACION',
                    'referencia_id' => $pagoId,
                    'usuario_id' => $usuarioId,
                ]);
            }

            // 3️⃣ Marcar pago como anulado
            $this->db->prepare("UPDATE pagos SET anulado = 1 WHERE id = ?")->execute([$pagoId]);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al anular cobro: ' . $e->getMessage() . ' - ' . __FILE__ . ':' . __LINE__);
            throw new Exception('Error al anular cobro: ' . $e->getMessage());
        }
    }
}
