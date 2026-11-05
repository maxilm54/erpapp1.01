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
                (cliente_id, usuario_id, monto, medio_pago, observaciones)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['cliente_id'],
                $data['usuario_id'],
                $data['monto'],
                $data['medio_pago'],
                $data['observaciones'] // agregar en proxima vs el numero de remito
            ]);

            $pagoId = (int)$this->db->lastInsertId();

            // 2️⃣ Si el pago es con cheque, guardar todos sus datos
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

            // 3️⃣ Generar PDF
            $pdfService = new PdfService();
            $pdfPath = $pdfService->generarReciboPago($pagoId);// modelo pago con el id del pago levanto un comprobante. devulevo la ruta del pdf

            // 4️⃣ Guardar path actualizo en el pregistro de pago la ruta del pdf
            $this->db->prepare("
                UPDATE pagos SET pdf_path = ?
                WHERE id = ?
            ")->execute([$pdfPath, $pagoId]);

            // 5️⃣ Enviar mail voy a enviar ese mismo pdf por mail
            $mail = new MailService();
            $mail->enviarPago($pagoId);

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