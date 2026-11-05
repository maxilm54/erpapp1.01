<?php
/**
 * Avisos por email de cheques de terceros en cartera.
 *
 * Recorre todos los tenants activos y envía un mail con los cheques
 * EN_CARTERA que vencen dentro de los próximos N días o que ya están
 * vencidos. Cada cheque se avisa una vez por día (columna ultimo_aviso_at).
 *
 * Uso:
 *   php app/helpers/notificar_cheques.php
 *   php app/helpers/notificar_cheques.php --dias=7        (default 7)
 *   php app/helpers/notificar_cheques.php --tenant=2      (solo un tenant)
 *   php app/helpers/notificar_cheques.php --email=admin@empresa.com
 *   php app/helpers/notificar_cheques.php --force         (ignora ultimo_aviso_at)
 *   php app/helpers/notificar_cheques.php --dry-run       (no envía ni marca)
 *
 * Cron (Windows - Programador de tareas):
 *   php.exe C:\xampp\htdocs\app\app\helpers\notificar_cheques.php --dias=7
 *
 * Cron (Linux):
 *   0 8 * * * /usr/bin/php /home/usuario/app/app/helpers/notificar_cheques.php --dias=7
 *
 * El destinatario es el email de la empresa (tenants.email); si está vacío,
 * usa el primer usuario activo del tenant. --email= lo fuerza.
 */

$BASE_PATH = dirname(dirname(__DIR__));
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $BASE_PATH);
}

// config.php define la zona horaria y las constantes SMTP del .env.
require_once BASE_PATH . '/app/config/config.php';
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/core/Database.php';
require_once BASE_PATH . '/app/core/Model.php';
require_once BASE_PATH . '/app/models/Tenant.php';
require_once BASE_PATH . '/app/models/Cheque.php';
require_once BASE_PATH . '/app/models/EmailConfig.php';

use PHPMailer\PHPMailer\PHPMailer;

ini_set('display_errors', '1');

// ---------------------------------------------------------------- opciones
$dias = 7;
$tenantId = null;
$emailOverride = null;
$force = false;
$dryRun = false;

$uso = "Uso: php app/helpers/notificar_cheques.php [--dias=N] [--tenant=ID] [--email=EMAIL] [--force] [--dry-run]\n";

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo $uso;
        exit(0);
    } elseif (str_starts_with($arg, '--dias=')) {
        $dias = max(0, (int)substr($arg, 7));
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantId = (int)substr($arg, 9);
    } elseif (str_starts_with($arg, '--email=')) {
        $emailOverride = trim(substr($arg, 8));
    } elseif ($arg === '--force') {
        $force = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } else {
        echo "Opción desconocida: {$arg}\n";
        echo $uso;
        exit(1);
    }
}

/**
 * Genera el HTML del aviso.
 */
function htmlAvisoCheques(array $cheques, array $empresa, int $dias): string
{
    $total = 0.0;
    $filas = '';

    foreach ($cheques as $ch) {
        $total += (float)$ch['monto'];
        $diasRestantes = (int)($ch['dias_restantes'] ?? 0);

        if ($diasRestantes < 0) {
            $estado = '<span style="color:#b02a37;font-weight:bold;">Vencido hace ' . abs($diasRestantes) . ' días</span>';
        } elseif ($diasRestantes === 0) {
            $estado = '<span style="color:#b02a37;font-weight:bold;">Vence hoy</span>';
        } else {
            $estado = 'En ' . $diasRestantes . ' días';
        }

        $filas .= '<tr>'
            . '<td>' . htmlspecialchars($ch['banco'] ?? '') . '<br><small>Nº ' . htmlspecialchars($ch['numero'] ?? '') . '</small></td>'
            . '<td>' . htmlspecialchars($ch['nombre_cliente'] ?? '') . '</td>'
            . '<td>' . date('d/m/Y', strtotime($ch['fecha_vencimiento'])) . '</td>'
            . '<td>' . $estado . '</td>'
            . '<td style="text-align:right;">$ ' . number_format((float)$ch['monto'], 2, ',', '.') . '</td>'
            . '</tr>';
    }

    $nombreEmpresa = htmlspecialchars($empresa['nombre'] ?? 'ERP');
    $titulo = 'Cheques en cartera: vencimientos';
    $subtitulo = 'Cheques que vencen en los próximos ' . $dias . ' días o que ya están vencidos.';

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f5f5f5;">
<div style="border:1px solid #ddd;padding:20px;max-width:720px;margin:20px auto;background:#fff;">
  <div style="border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;">
    <h2 style="margin:0;color:#1a3a5c;">' . $titulo . '</h2>
    <p style="margin:5px 0 0;color:#666;">' . $subtitulo . '</p>
  </div>
  <table style="width:100%;border-collapse:collapse;margin:10px 0;">
    <thead>
      <tr>
        <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Banco / Nº</th>
        <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Cliente</th>
        <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Vencimiento</th>
        <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Estado</th>
        <th style="border:1px solid #ddd;padding:6px 8px;text-align:right;font-size:13px;background:#f0f0f0;">Monto</th>
      </tr>
    </thead>
    <tbody>' . $filas . '</tbody>
    <tfoot>
      <tr>
        <td colspan="4" style="border:1px solid #ddd;padding:8px;text-align:right;font-weight:bold;">Total</td>
        <td style="border:1px solid #ddd;padding:8px;text-align:right;font-weight:bold;">$ ' . number_format($total, 2, ',', '.') . '</td>
      </tr>
    </tfoot>
  </table>
  <p style="font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;">
    ' . $nombreEmpresa . ' &middot; Aviso automático de cheques. Ingresá al sistema para depositar, cobrar o rechazar cada cheque.
  </p>
</div>
</body>
</html>';
}

// ---------------------------------------------------------------- ejecutar
echo "=== Avisos de cheques por vencer (días: {$dias}) ===\n";

$tenantModel = new Tenant();
$tenants = $tenantModel->getActives();

if ($tenantId !== null) {
    $tenants = array_values(array_filter($tenants, fn($t) => (int)$t['id'] === $tenantId));
    if (empty($tenants)) {
        echo "No se encontró el tenant ID {$tenantId}.\n";
        exit(1);
    }
}

$totalEnviados = 0;
$totalCheques = 0;

foreach ($tenants as $t) {
    $tid = (int)$t['id'];
    echo "\n--- Tenant #{$tid} {$t['nombre']} ({$t['dbname']}) ---\n";

    try {
        Database::connectTenant($t['dbname'], $t['host'] ?? 'localhost');

        $chequeModel = new Cheque();
        $cheques = $chequeModel->pendientesAviso($dias, $force);

        if (empty($cheques)) {
            echo "Sin cheques pendientes de aviso.\n";
            continue;
        }

        echo count($cheques) . " cheque(s) para avisar.\n";

        if ($dryRun) {
            foreach ($cheques as $ch) {
                $d = (int)$ch['dias_restantes'];
                echo sprintf(
                    "  - %s %s | %s | vence %s (%s) | $ %s\n",
                    $ch['banco'],
                    $ch['numero'],
                    $ch['nombre_cliente'],
                    date('d/m/Y', strtotime($ch['fecha_vencimiento'])),
                    $d < 0 ? 'vencido hace ' . abs($d) . ' días' : ($d === 0 ? 'vence hoy' : 'en ' . $d . ' días'),
                    number_format((float)$ch['monto'], 2, ',', '.')
                );
            }
            $totalCheques += count($cheques);
            continue;
        }

        $empresa = $tenantModel->getEmpresaConfig($tid);
        $destino = $emailOverride ?: trim((string)($empresa['email'] ?? ''));

        if ($destino === '') {
            $destino = (string)(Database::getInstance()
                ->query("SELECT email FROM users WHERE activo = 1 AND email <> '' ORDER BY id ASC LIMIT 1")
                ->fetchColumn() ?: '');
        }

        if ($destino === '') {
            echo "No hay email de destino configurado. Se omite el envío.\n";
            continue;
        }

        $smtp = (new EmailConfig())->getResolved();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['smtp_user'];
        $mail->Password = $smtp['smtp_pass'];
        $mail->Port = (int)$smtp['smtp_port'];
        $mail->CharSet = 'UTF-8';

        $secure = strtolower((string)($smtp['smtp_secure'] ?? ''));
        if ($secure === 'ssl' || $secure === 'tls') {
            $mail->SMTPSecure = $secure;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = $smtp['smtp_from_email'] ?: $smtp['smtp_user'];
        $mail->setFrom($fromEmail, $smtp['smtp_from_name'] ?: ($empresa['nombre'] ?? 'ERP'));
        $mail->addAddress($destino);

        if (!empty($smtp['bcc_email']) && $smtp['bcc_email'] !== $destino) {
            $mail->addBCC($smtp['bcc_email']);
        }

        $mail->isHTML(true);
        $mail->Subject = 'Cheques por vencer / vencidos - ' . ($empresa['nombre'] ?? $t['nombre']);
        $mail->Body = htmlAvisoCheques($cheques, $empresa, $dias);
        $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>'], ["\n", "\n"], $mail->Body));

        $mail->send();

        $chequeModel->marcarAvisados(array_column($cheques, 'id'));
        $totalEnviados++;
        $totalCheques += count($cheques);

        echo "Mail enviado a {$destino} (" . count($cheques) . " cheque(s)).\n";

    } catch (Throwable $e) {
        echo "ERROR en tenant #{$tid}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Fin: {$totalEnviados} mail(s), {$totalCheques} cheque(s) procesado(s) ===\n";
exit(0);
