<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/models/MailLog.php';
require_once BASE_PATH . '/app/models/Pago.php';

class MailService
{
    private PHPMailer $mail;
    private MailLog $log;
    private Pago $pago;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->log  = new MailLog();
        $this->pago = new Pago();

        $this->mail->isSMTP();
        $this->mail->Host       = SMTP_HOST;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = SMTP_USER;
        $this->mail->Password   = SMTP_PASS;
        $this->mail->SMTPSecure = SMTP_SECURE;
        $this->mail->Port       = SMTP_PORT;

        $this->mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    public function enviarRemito(array $cliente, array $remito, int $usuarioId): void
    {
        try {
            $this->mail->addAddress($cliente['email']);
            $this->mail->Subject = "Remito de salida #{$remito['numero']}";
            $this->mail->Body = $this->renderTemplate('remito', compact('cliente','remito'));
            $this->mail->addAttachment($remito['pdf_path']);

            $this->mail->send();

            $this->log->registrar([
                'tipo' => 'REMITO',
                'referencia_id' => $remito['id'],
                'email' => $cliente['email'],
                'asunto' => $this->mail->Subject,
                'estado' => 'ENVIADO',
                'usuario_id' => $usuarioId
            ]);

        } catch (Exception $e) {

            $this->log->registrar([
                'tipo' => 'REMITO',
                'referencia_id' => $remito['id'],
                'email' => $cliente['email'],
                'asunto' => 'ERROR',
                'estado' => 'ERROR',
                'error' => $e->getMessage(),
                'usuario_id' => $usuarioId
            ]);

            throw $e;
        }
    }

   

    private function renderTemplate(string $template, array $data): string //recibe un string pago o remito para saber que archivo usar y un data de info
    {
        extract($data);

        // Variables globales de la empresa
        $empresa = config('empresa');

        ob_start();
        require BASE_PATH . "/app/views/mails/{$template}.php"; //usa la vista
        return ob_get_clean();
    }
    public function enviarPago(int $pagoId): void
    {
        $pago = $this->pago->obtenerPagoCompleto($pagoId);
        if (!$pago) {
            throw new Exception('Pago no encontrado');
        }
        $this->mail->addAddress($pago['email']);
        $this->mail->Subject = "Recibo de pago #{$pago['id']}";
        $this->mail->Body = $this->renderTemplate('pago', [
            'pago'   => $pago,
            'cliente'=> $pago, // mismo array, semántica distinta
        ]);
        if (!empty($pago['pdf_path'])) {
            $this->mail->addAttachment($pago['pdf_path']);
        }

        $this->mail->send();
    }
}