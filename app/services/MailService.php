<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/models/Maillog.php';
require_once BASE_PATH . '/app/models/EmailConfig.php';
require_once BASE_PATH . '/app/models/EmailTemplate.php';
require_once BASE_PATH . '/app/models/Pago.php';

class MailService
{
    private PHPMailer $mail;
    private MailLog $log;
    private array $smtpConfig;

    public function __construct()
    {
        $this->log = new MailLog();

        // Cargar configuración SMTP del tenant (o fallback a .env)
        $emailConfigModel = new EmailConfig();
        $this->smtpConfig = $emailConfigModel->getResolved();

        $this->initMailer();
    }

    private function initMailer(): void
    {
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host       = $this->smtpConfig['smtp_host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $this->smtpConfig['smtp_user'];
        $this->mail->Password   = $this->smtpConfig['smtp_pass'];
        $this->mail->SMTPSecure = $this->smtpConfig['smtp_secure'];
        $this->mail->Port       = $this->smtpConfig['smtp_port'];
        $this->mail->setFrom(
            $this->smtpConfig['smtp_from_email'],
            $this->smtpConfig['smtp_from_name']
        );
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    /**
     * Método genérico de envío de emails.
     *
     * @param string $tipo Tipo de email (REMITO, PAGO, PRESUPUESTO, etc.)
     * @param int $referenciaId ID del registro referenciado
     * @param string $email Email del destinatario
     * @param int $usuarioId ID del usuario que envía
     * @param array $data Datos para renderizar el template
     * @param array $options Opciones adicionales (attachments, bcc, etc.)
     */
    public function enviar(string $tipo, int $referenciaId, string $email, int $usuarioId, array $data = [], array $options = []): void
    {
        try {
            $templateModel = new EmailTemplate();
            $template = $templateModel->getActiveByType($tipo);

            if (!$template) {
                // Fallback: usar template de archivo (compatibilidad con migración no ejecutada)
                $this->enviarLegacy($tipo, $referenciaId, $email, $usuarioId, $data, $options);
                return;
            }

            // Cargar configuración de empresa
            $empresa = config('empresa');
            $logoPath = empresaLogoPath();
            $empresa['logo'] = ($logoPath && file_exists($logoPath))
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
                : '';

            // Agregar datos globales al array de renderizado
            $renderData = array_merge([
                'empresa_nombre'    => $empresa['nombre'] ?? '',
                'empresa_email'     => $empresa['email'] ?? '',
                'empresa_cuit'      => $empresa['cuit'] ?? '',
                'empresa_direccion' => $empresa['direccion'] ?? '',
                'logo'              => $empresa['logo'],
            ], $data);

            // Renderizar template
            $body = $templateModel->render($template['cuerpo_html'], $renderData);
            $subject = $templateModel->render($template['asunto'], $renderData);

            // Configurar destinatario
            $this->mail->clearAddresses();
            $this->mail->addAddress($email);

            // BCC opcional
            if (!empty($options['bcc'])) {
                $this->mail->addBCC($options['bcc']);
            } elseif (!empty($this->smtpConfig['bcc_email'])) {
                $this->mail->addBCC($this->smtpConfig['bcc_email']);
            }

            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            // Adjuntos
            if (!empty($options['attachments'])) {
                foreach ((array)$options['attachments'] as $attachment) {
                    if (!empty($attachment) && file_exists($attachment)) {
                        $this->mail->addAttachment($attachment);
                    }
                }
            }

            $this->mail->send();

            // Registrar éxito
            $this->log->registrar([
                'tipo'          => $tipo,
                'referencia_id' => $referenciaId,
                'email'         => $email,
                'asunto'        => $subject,
                'estado'        => 'ENVIADO',
                'usuario_id'    => $usuarioId,
            ]);

        } catch (Exception $e) {
            error_log("Error enviando email [{$tipo}] #{$referenciaId}: " . $e->getMessage());

            // Registrar error
            $this->log->registrar([
                'tipo'          => $tipo,
                'referencia_id' => $referenciaId,
                'email'         => $email,
                'asunto'        => 'ERROR',
                'estado'        => 'ERROR',
                'error'         => $e->getMessage(),
                'usuario_id'    => $usuarioId,
            ]);

            throw $e;
        }
    }

    // =====================================================
    // MÉTODOS ESPECÍFICOS (envuelven el método genérico)
    // =====================================================

    /**
     * Enviar remito por email (método legacy, compatible con código existente).
     */
    public function enviarRemito(array $cliente, array $remito, int $usuarioId): void
    {
        $email = $cliente['email'] ?? '';
        if (empty($email)) {
            throw new Exception('El cliente no tiene email configurado');
        }

        // Construir tabla de detalle
        $detalleHtml = '';
        $total = 0;
        if (!empty($remito['detalle'])) {
            foreach ($remito['detalle'] as $d) {
                $precio = (float)($d['precio_unitario'] ?? $d['precioUnitario'] ?? 0);
                $cant = (float)($d['cantidad'] ?? $d['CantRem'] ?? 0);
                $nombre = $d['ProdRem'] ?? $d['nombre'] ?? $d['descripcion'] ?? 'Item';
                $sub = $precio * $cant;
                $total += $sub;
                $detalleHtml .= "<tr><td>" . htmlspecialchars($nombre) . "</td><td>" . number_format($cant, 2) . "</td><td>$ " . number_format($precio, 2, ',', '.') . "</td><td>$ " . number_format($sub, 2, ',', '.') . "</td></tr>";
            }
        }

        $this->enviar('REMITO', $remito['id'], $email, $usuarioId, [
            'cliente_nombre' => $cliente['razon_social'] ?? $cliente['nombre'] ?? '',
            'numero'         => $remito['numero'] ?? $remito['id'],
            'fecha'          => date('d/m/Y', strtotime($remito['fecha'] ?? $remito['created_at'] ?? 'now')),
            'detalle_tabla'  => $detalleHtml,
            'total'          => number_format($total, 2, ',', '.'),
        ], [
            'attachments' => $remito['pdf_path'] ?? null,
        ]);
    }

    /**
     * Enviar comprobante de pago por email (método legacy, compatible con código existente).
     */
    public function enviarPago(int $pagoId): void
    {
        $pagoModel = new Pago();
        $pago = $pagoModel->obtenerPagoCompleto($pagoId);

        if (!$pago) {
            throw new Exception('Pago no encontrado');
        }

        $email = $pago['email'] ?? '';
        if (empty($email)) {
            throw new Exception('El cliente no tiene email configurado');
        }

        $this->enviar('PAGO', $pagoId, $email, $_SESSION['user_id'] ?? 0, [
            'cliente_nombre' => $pago['razon_social'] ?? '',
            'pago_id'        => $pagoId,
            'fecha'          => date('d/m/Y H:i', strtotime($pago['fecha'])),
            'monto'          => number_format($pago['monto'], 2, ',', '.'),
            'medio_pago'     => $pago['medio_pago'] ?? 'No especificado',
            'observaciones'  => $pago['observaciones'] ?? '',
        ], [
            'attachments' => $pago['pdf_path'] ?? null,
        ]);
    }

    /**
     * Fallback legacy: usa templates de archivos cuando no hay BD templates.
     */
    private function enviarLegacy(string $tipo, int $referenciaId, string $email, int $usuarioId, array $data, array $options): void
    {
        $templateMap = [
            'REMITO' => 'remito',
            'PAGO'   => 'pago',
        ];

        $templateFile = $templateMap[$tipo] ?? null;
        if (!$templateFile) {
            throw new Exception("No hay template para el tipo: {$tipo}");
        }

        $templatePath = BASE_PATH . "/app/views/mails/{$templateFile}.php";
        if (!file_exists($templatePath)) {
            throw new Exception("Template no encontrado: {$templateFile}");
        }

        $empresa = config('empresa');
        $logoPath = empresaLogoPath();
        $empresa['logo'] = ($logoPath && file_exists($logoPath))
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '';

        extract($data);
        ob_start();
        require $templatePath;
        $body = ob_get_clean();

        $subject = $data['asunto'] ?? "Email - {$tipo} #{$referenciaId}";

        $this->mail->clearAddresses();
        $this->mail->addAddress($email);

        if (!empty($options['bcc'])) {
            $this->mail->addBCC($options['bcc']);
        } elseif (!empty($this->smtpConfig['bcc_email'])) {
            $this->mail->addBCC($this->smtpConfig['bcc_email']);
        }

        $this->mail->Subject = $subject;
        $this->mail->Body = $body;

        if (!empty($options['attachments'])) {
            foreach ((array)$options['attachments'] as $attachment) {
                if (!empty($attachment) && file_exists($attachment)) {
                    $this->mail->addAttachment($attachment);
                }
            }
        }

        $this->mail->send();

        $this->log->registrar([
            'tipo'          => $tipo,
            'referencia_id' => $referenciaId,
            'email'         => $email,
            'asunto'        => $subject,
            'estado'        => 'ENVIADO',
            'usuario_id'    => $usuarioId,
        ]);
    }
}
