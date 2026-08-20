<?php

require_once BASE_PATH.'/app/models/Cuentacorrientecliente.php';
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
            SELECT p.*,cli.*,p.created_at as fecha FROM pagos p
            LEFT JOIN clientes cli ON cli.id=p.cliente_id
            WHERE p.id=?");
            $stmt->execute([$id_pago]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }catch(Exception $e){
            error_log('error al leer un pago'.$e->getMessage());
        }
    }
}