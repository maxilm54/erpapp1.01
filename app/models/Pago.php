<?php

require_once BASE_PATH.'/app/models/Cuentacorrientecliente.php';
require_once BASE_PATH.'/app/models/Cheque.php';
require_once BASE_PATH.'/app/services/MailService.php';
require_once BASE_PATH.'/app/services/PdfService.php';


class Pago extends Model
{
    public function registrar(array $data): int
    {
        $this->db->beginTransaction();

        try {
            // 1️⃣ Insertar pago
            $stmt = $this->db->prepare("
                INSERT INTO pagos
                (cliente_id, usuario_id, monto, medio_pago, observaciones, caja_banco_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['cliente_id'],
                $data['usuario_id'],
                $data['monto'],
                $data['medio_pago'],
                $data['observaciones'], // agregar en proxima vs el numero de remito
                !empty($data['caja_banco_id']) ? (int)$data['caja_banco_id'] : null,
            ]);

            $pagoId = (int)$this->db->lastInsertId();

            // 2️⃣ Registrar el crédito en la cuenta corriente del cliente.
            // Se hace dentro de la misma transacción para que pago, ctacte y
            // cheque queden consistentes.
            $ccModel = new CuentaCorrienteCliente();
            $ccModel->registrarCredito(
                (int)$data['cliente_id'],
                (float)$data['monto'],
                'PAGO',
                $pagoId,
                (int)$data['usuario_id'],
                $data['observaciones'] ?? null,
                $data['cliente_nombre'] ?? null
            );

            // 3️⃣ Si el pago es con cheque, guardar todos sus datos
            $chequeData = $data['cheque'] ?? null;
            if (!empty($chequeData)) {
                $chequeData['cliente_id'] = $data['cliente_id'];
                $chequeData['cliente_nombre'] = $data['cliente_nombre'] ?? null;
                $chequeData['monto'] = $data['monto'];
                $chequeData['usuario_id'] = $data['usuario_id'];
                $chequeData['caja_banco_id'] = $data['caja_banco_id'] ?? null;
                $chequeModel = new Cheque();
                $chequeModel->crearParaPago($pagoId, $chequeData);
            }

            // 4️⃣ Generar PDF
            $pdfService = new PdfService();
            $pdfPath = $pdfService->generarReciboPago($pagoId);// modelo pago con el id del pago levanto un comprobante. devulevo la ruta del pdf

            // 5️⃣ Guardar path actualizo en el pregistro de pago la ruta del pdf
            $this->db->prepare("
                UPDATE pagos SET pdf_path = ?
                WHERE id = ?
            ")->execute([$pdfPath, $pagoId]);

            // 6️⃣ Enviar mail voy a enviar ese mismo pdf por mail.
            // Un fallo de SMTP no debe revertir el pago ni el cheque.
            try {
                $mail = new MailService();
                $mail->enviarPago($pagoId);
            } catch (Exception $e) {
                empresaLog("Error enviando mail de pago #{$pagoId}: " . $e->getMessage(), 'ERROR');
            }

            $this->db->commit();
            return $pagoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerPagoCompleto(int $id_pago) {
        try{
            $stmt=$this->db->prepare("
            SELECT p.*,cli.*,p.created_at as fecha,
                   ch.id AS cheque_id,
                   ch.tipo AS cheque_tipo,
                   ch.numero AS cheque_numero,
                   ch.banco AS cheque_banco,
                   ch.sucursal AS cheque_sucursal,
                   ch.titular AS cheque_titular,
                   ch.cuit AS cheque_cuit,
                   ch.fecha_emision AS cheque_fecha_emision,
                   ch.fecha_vencimiento AS cheque_fecha_vencimiento,
                   ch.estado AS cheque_estado
            FROM pagos p
            LEFT JOIN clientes cli ON cli.id=p.cliente_id
            LEFT JOIN cheques ch ON ch.pago_id=p.id
            WHERE p.id=?");
            $stmt->execute([$id_pago]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }catch(Exception $e){
            error_log('error al leer un pago'.$e->getMessage());
        }
    }
}