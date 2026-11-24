<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/models/Ctactetenant.php';
require_once BASE_PATH . '/app/services/CtactePdfService.php';

/**
 * Envio por email de los comprobantes de CtaCte.
 *
 * El modulo vive en el panel del superadmin, donde NO hay tenant conectado.
 * Para no depender de un unico SMTP global, intenta usar la configuracion
 * de email (email_config) de la MISMA empresa destinataria, que es la que
 * ya funciona para los mails del tenant. Si esa empresa no tiene
 * configuracion, cae al SMTP global del .env (SMTP_*).
 *
 * El remitente visible es siempre la empresa que administra el sistema
 * (SMTP_FROM_NAME), aunque el envio se haga con la cuenta autenticada del
 * tenant.
 */
class CtacteMailService
{
    private Ctactetenant $model;
    private CtactePdfService $pdf;

    public function __construct()
    {
        $this->model = new Ctactetenant();
        $this->pdf = new CtactePdfService();
    }

    public function enviarComprobanteDeuda(int $registroId, string $email, int $usuarioId = 0): void
    {
        $this->validarEmail($email);

        $registro = $this->model->findById($registroId);
        if (!$registro) {
            throw new RuntimeException('El registro de cuenta corriente no existe.');
        }

        $path = $this->pdf->generarComprobanteDeuda($registroId);
        $pagos = $this->model->pagosAplicados($registroId);
        $emisor = $this->pdf->datosEmisor();

        $partes = explode('-', (string)$registro['periodo']);
        $periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');

        $html = $this->render('ctacte_deuda', [
            'registro' => $registro,
            'pagos'    => $pagos,
            'emisor'   => $emisor,
            'logo'     => $this->pdf->logoEmisor(),
        ]);

        $this->enviar(
            'Comprobante de deuda X - Periodo ' . $periodoTxt . ' - ' . $registro['tenant_nombre'],
            $html,
            $path,
            $email,
            $this->configSmtp($registro)
        );
    }

    public function enviarComprobantePago(int $pagoId, string $email, int $usuarioId = 0): void
    {
        $this->validarEmail($email);

        $pago = $this->model->findPago($pagoId);
        if (!$pago) {
            throw new RuntimeException('El pago no existe.');
        }

        $path = $this->pdf->generarComprobantePago($pagoId);
        $aplicaciones = $this->model->aplicacionesDePago($pagoId);
        $emisor = $this->pdf->datosEmisor();

        $html = $this->render('ctacte_pago', [
            'pago'         => $pago,
            'aplicaciones' => $aplicaciones,
            'emisor'       => $emisor,
            'logo'         => $this->pdf->logoEmisor(),
        ]);

        $this->enviar(
            'Comprobante de pago X - ' . $pago['tenant_nombre'] . ' - $' . number_format((float)$pago['monto'], 2, ',', '.'),
            $html,
            $path,
            $email,
            $this->configSmtp($pago)
        );
    }

    private function validarEmail(string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            throw new InvalidArgumentException('La empresa no tiene email configurado. Cargalo en Empresas antes de enviar.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El email de la empresa no es valido: ' . $email);
        }
    }

    /**
     * Configuracion SMTP a usar: primero la email_config de la empresa
     * destinataria (misma cuenta que ya usan sus mails), despues la de
     * cualquier empresa activa que tenga una configuracion valida (util para
     * tenants nuevos sin email_config) y por ultimo el SMTP global del .env.
     */
    private function configSmtp(array $contexto): array
    {
        $fallback = [
            'host'       => defined('SMTP_HOST') ? SMTP_HOST : '',
            'port'       => defined('SMTP_PORT') ? (int)SMTP_PORT : 465,
            'secure'     => defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl',
            'user'       => defined('SMTP_USER') ? SMTP_USER : '',
            'pass'       => defined('SMTP_PASS') ? SMTP_PASS : '',
            'from_email' => defined('SMTP_FROM') ? SMTP_FROM : '',
            'from_name'  => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : '',
            'source'     => 'env',
        ];

        $dbname = trim((string)($contexto['tenant_dbname'] ?? ''));
        $host = trim((string)($contexto['tenant_host'] ?? 'localhost')) ?: 'localhost';

        // 1) Configuracion de la empresa destinataria.
        if ($dbname !== '') {
            $row = $this->leerEmailConfig($dbname, $host);
            if ($row) {
                return $this->smtpDesdeFila($row, $dbname, $fallback);
            }
        }

        // 2) Configuracion de cualquier otra empresa activa (misma cuenta
        //    proveedora que ya funciona para los mails de los tenants).
        try {
            $tenants = Database::getMaster()
                ->query("SELECT dbname, host FROM tenants WHERE activo = 1 ORDER BY id")
                ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tenants as $t) {
                $otroDb = trim((string)($t['dbname'] ?? ''));
                if ($otroDb === '' || $otroDb === $dbname) {
                    continue;
                }
                $row = $this->leerEmailConfig($otroDb, trim((string)($t['host'] ?? 'localhost')) ?: 'localhost');
                if ($row) {
                    return $this->smtpDesdeFila($row, $otroDb, $fallback);
                }
            }
        } catch (Throwable $e) {
            error_log('[CtaCteMail] No se pudo buscar una email_config alternativa: ' . $e->getMessage());
        }

        // 3) SMTP global del .env.
        return $fallback;
    }

    /**
     * Lee la configuracion SMTP activa de una base de tenant.
     */
    private function leerEmailConfig(string $dbname, string $host): ?array
    {
        try {
            $dbConfig = require BASE_PATH . '/app/config/database.php';
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $stmt = $pdo->query("SELECT * FROM email_config WHERE activo = 1 ORDER BY id ASC LIMIT 1");
            $row = $stmt->fetch();

            if (!$row || empty($row['smtp_host']) || empty($row['smtp_user'])) {
                return null;
            }

            return $row;
        } catch (Throwable $e) {
            error_log('[CtaCteMail] No se pudo leer email_config de ' . $dbname . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Arma la configuracion final. El remitente visible es el sistema
     * (SMTP_FROM_NAME), no el tenant.
     */
    private function smtpDesdeFila(array $row, string $dbname, array $fallback): array
    {
        return [
            'host'       => $row['smtp_host'],
            'port'       => (int)($row['smtp_port'] ?: 465),
            'secure'     => $row['smtp_secure'] ?: 'ssl',
            'user'       => $row['smtp_user'],
            'pass'       => (string)($row['smtp_pass'] ?? ''),
            'from_email' => !empty($row['smtp_from_email']) ? $row['smtp_from_email'] : $fallback['from_email'],
            'from_name'  => !empty($fallback['from_name']) ? $fallback['from_name'] : ($row['smtp_from_name'] ?? ''),
            'source'     => 'tenant:' . $dbname,
        ];
    }

    private function enviar(string $asunto, string $html, string $pdfPath, string $email, array $smtp): void
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException('No se pudo generar el PDF del comprobante.');
        }

        if (empty($smtp['host']) || empty($smtp['user'])) {
            throw new RuntimeException('No hay configuracion SMTP disponible para enviar el comprobante.');
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = (int)$smtp['port'];
            $mail->setFrom(
                !empty($smtp['from_email']) ? $smtp['from_email'] : $smtp['user'],
                $smtp['from_name']
            );
            $mail->addAddress(trim($email));
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';

            $mail->Subject = $asunto;
            $mail->Body = $html;
            $mail->addAttachment($pdfPath);

            $mail->send();
        } catch (Throwable $e) {
            error_log('[CtaCteMail] Error enviando a ' . $email . ' usando ' . ($smtp['source'] ?? 'smtp') . ': ' . $e->getMessage());
            throw new RuntimeException('No se pudo enviar el email: ' . $e->getMessage());
        }
    }

    private function render(string $template, array $data): string
    {
        extract($data);
        ob_start();
        require BASE_PATH . "/app/views/mails/{$template}.php";
        return ob_get_clean();
    }
}
