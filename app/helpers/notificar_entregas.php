<?php
/**
 * Avisos por email de fechas de entrega comprometidas.
 *
 * Recorre todos los tenants activos y envia un mail con:
 *  - Presupuestos y Notas de Pedido con fecha de entrega que vence en los
 *    proximos N dias (o ya vencida), mientras el documento siga abierto
 *    (compromisos con clientes).
 *  - Ordenes de Compra con fecha de entrega que vence en los proximos N dias
 *    (o ya vencida), mientras la orden siga pendiente/aprobada/parcial
 *    (recepciones a proveedores).
 *
 * Cada documento se avisa una vez por dia (columna aviso_entrega_at de cada tabla).
 *
 * Uso:
 *   php app/helpers/notificar_entregas.php
 *   php app/helpers/notificar_entregas.php --dias=7        (default 7)
 *   php app/helpers/notificar_entregas.php --tenant=2      (solo un tenant)
 *   php app/helpers/notificar_entregas.php --email=admin@empresa.com
 *   php app/helpers/notificar_entregas.php --force         (ignora aviso_entrega_at)
 *   php app/helpers/notificar_entregas.php --dry-run       (no envia ni marca)
 *
 * Cron (Windows - Programador de tareas):
 *   php.exe C:\xampp\htdocs\app\app\helpers\notificar_entregas.php --dias=7
 *
 * Cron (Linux):
 *   0 8 * * * /usr/bin/php /home/usuario/app/app/helpers/notificar_entregas.php --dias=7
 *
 * El destinatario es el email de la empresa (tenants.email); si esta vacio,
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
require_once BASE_PATH . '/app/models/Presupuesto.php';
require_once BASE_PATH . '/app/models/Notapedido.php';
require_once BASE_PATH . '/app/models/Ordencompra.php';
require_once BASE_PATH . '/app/models/EmailConfig.php';

use PHPMailer\PHPMailer\PHPMailer;

ini_set('display_errors', '1');

// ---------------------------------------------------------------- opciones
$dias = 7;
$tenantId = null;
$emailOverride = null;
$force = false;
$dryRun = false;

$uso = "Uso: php app/helpers/notificar_entregas.php [--dias=N] [--tenant=ID] [--email=EMAIL] [--force] [--dry-run]\n";

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
 * Estado del vencimiento para mostrar en el mail y en la consola.
 */
function estadoEntrega(int $diasRestantes): string
{
    if ($diasRestantes < 0) {
        return 'Vencido hace ' . abs($diasRestantes) . ' día(s)';
    }
    if ($diasRestantes === 0) {
        return 'Vence HOY';
    }
    return 'En ' . $diasRestantes . ' día(s)';
}

/**
 * Filas de una tabla del aviso. $tipoLabel es "Presupuesto", "Nota de Pedido" u
 * "Orden de Compra"; $urlBase permite armar el link "Ver" en el mail.
 */
function filasEntregas(array $documentos, string $tipoLabel): string
{
    $filas = '';

    foreach ($documentos as $doc) {
        $diasRestantes = (int)($doc['dias_restantes'] ?? 0);
        $estado = estadoEntrega($diasRestantes);
        $color = $diasRestantes <= 0 ? '#b02a37' : '#1a3a5c';

        $filas .= '<tr>'
            . '<td style="border:1px solid #ddd;padding:6px 8px;font-size:13px;">'
            . $tipoLabel . ' #' . (int)$doc['id']
            . '<br><small style="color:#666;">' . htmlspecialchars((string)($doc['estado'] ?? '')) . '</small></td>'
            . '<td style="border:1px solid #ddd;padding:6px 8px;font-size:13px;">' . htmlspecialchars((string)($doc['razon_social'] ?? '')) . '</td>'
            . '<td style="border:1px solid #ddd;padding:6px 8px;font-size:13px;">' . date('d/m/Y', strtotime($doc['fecha_entrega'])) . '</td>'
            . '<td style="border:1px solid #ddd;padding:6px 8px;font-size:13px;color:' . $color . ';font-weight:bold;">' . $estado . '</td>'
            . '</tr>';
    }

    return $filas;
}

/**
 * Seccion del aviso (tabla) con su titulo. Vacia si no hay documentos.
 */
function seccionEntregas(string $titulo, string $subtitulo, array $documentos, string $tipoLabel): string
{
    if (empty($documentos)) {
        return '';
    }

    return '<div style="margin-bottom:20px;">
    <h3 style="margin:0 0 2px;color:#1a3a5c;font-size:15px;">' . $titulo . '</h3>
    <p style="margin:0 0 8px;color:#666;font-size:12px;">' . $subtitulo . '</p>
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Documento</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Cliente / Proveedor</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Entrega comprometida</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Estado</th>
        </tr>
      </thead>
      <tbody>' . filasEntregas($documentos, $tipoLabel) . '</tbody>
    </table>
  </div>';
}

/**
 * Genera el HTML del aviso completo.
 */
function htmlAvisoEntregas(array $presupuestos, array $notas, array $ordenes, array $empresa, int $dias): string
{
    $nombreEmpresa = htmlspecialchars($empresa['nombre'] ?? 'ERP');
    $titulo = 'Entregas comprometidas: próximos ' . $dias . ' días y vencidas';

    // Seccion de ventas: presupuestos y notas de pedido mezclados, cada uno con su label
    $seccionVentas = '';
    if (!empty($presupuestos) || !empty($notas)) {
        $filasVentas = '';
        foreach ($presupuestos as $pr) {
            $filasVentas .= filasEntregas([$pr], 'Presupuesto');
        }
        foreach ($notas as $np) {
            $filasVentas .= filasEntregas([$np], 'Nota de Pedido');
        }

        $seccionVentas = '<div style="margin-bottom:20px;">
    <h3 style="margin:0 0 2px;color:#1a3a5c;font-size:15px;">Compromisos con clientes</h3>
    <p style="margin:0 0 8px;color:#666;font-size:12px;">Presupuestos y Notas de Pedido con fecha de entrega que vence pronto o ya venció.</p>
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Documento</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Cliente</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Entrega comprometida</th>
          <th style="border:1px solid #ddd;padding:6px 8px;text-align:left;font-size:13px;background:#f0f0f0;">Estado</th>
        </tr>
      </thead>
      <tbody>' . $filasVentas . '</tbody>
    </table>
  </div>';
    }

    $seccionCompras = seccionEntregas(
        'Recepciones a proveedores',
        'Órdenes de compra con fecha de entrega que vence pronto o ya venció.',
        $ordenes,
        'Orden de Compra'
    );

    if ($seccionVentas === '' && $seccionCompras === '') {
        return '';
    }

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;margin:0;padding:0;background:#f5f5f5;">
<div style="border:1px solid #ddd;padding:20px;max-width:760px;margin:20px auto;background:#fff;">
  <div style="border-bottom:2px solid #1a3a5c;margin-bottom:15px;padding-bottom:10px;">
    <h2 style="margin:0;color:#1a3a5c;">' . $titulo . '</h2>
  </div>
  ' . $seccionVentas . $seccionCompras . '
  <p style="font-size:12px;color:#777;margin-top:20px;border-top:1px solid #ddd;padding-top:10px;">
    ' . $nombreEmpresa . ' &middot; Aviso automático de entregas. Ingresá al sistema para revisar cada documento.
  </p>
</div>
</body>
</html>';
}

// ---------------------------------------------------------------- ejecutar
echo "=== Avisos de entregas comprometidas (días: {$dias}) ===\n";

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
$totalDocumentos = 0;

foreach ($tenants as $t) {
    $tid = (int)$t['id'];
    echo "\n--- Tenant #{$tid} {$t['nombre']} ({$t['dbname']}) ---\n";

    try {
        Database::connectTenant($t['dbname'], $t['host'] ?? 'localhost');

        $presupuestoModel = new Presupuesto();
        $notaPedidoModel = new NotaPedido();
        $ordenCompraModel = new OrdenCompra();

        $presupuestos = $presupuestoModel->pendientesAvisoEntrega($dias, $force);
        $notas = $notaPedidoModel->pendientesAvisoEntrega($dias, $force);
        $ordenes = $ordenCompraModel->pendientesAvisoEntrega($dias, $force);

        if (empty($presupuestos) && empty($notas) && empty($ordenes)) {
            echo "Sin documentos pendientes de aviso.\n";
            continue;
        }

        echo count($presupuestos) . " presupuesto(s), " . count($notas) . " nota(s) de pedido y "
            . count($ordenes) . " orden(es) de compra para avisar.\n";

        if ($dryRun) {
            foreach ($presupuestos as $pr) {
                echo sprintf("  - Presupuesto #%d | %s | entrega %s | %s\n",
                    $pr['id'], $pr['razon_social'],
                    date('d/m/Y', strtotime($pr['fecha_entrega'])),
                    estadoEntrega((int)$pr['dias_restantes']));
            }
            foreach ($notas as $np) {
                echo sprintf("  - Nota de Pedido #%d | %s | entrega %s | %s\n",
                    $np['id'], $np['razon_social'],
                    date('d/m/Y', strtotime($np['fecha_entrega'])),
                    estadoEntrega((int)$np['dias_restantes']));
            }
            foreach ($ordenes as $oc) {
                echo sprintf("  - Orden de Compra #%d | %s | entrega %s | %s\n",
                    $oc['id'], $oc['razon_social'],
                    date('d/m/Y', strtotime($oc['fecha_entrega'])),
                    estadoEntrega((int)$oc['dias_restantes']));
            }
            $totalDocumentos += count($presupuestos) + count($notas) + count($ordenes);
            continue;
        }

        $empresa = $tenantModel->getEmpresaConfig($tid);
        $html = htmlAvisoEntregas($presupuestos, $notas, $ordenes, $empresa, $dias);

        if ($html === '') {
            continue;
        }

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
        $mail->Subject = 'Entregas comprometidas por vencer / vencidas - ' . ($empresa['nombre'] ?? $t['nombre']);
        $mail->Body = $html;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>'], ["\n", "\n"], $mail->Body));

        $mail->send();

        // Solo se marcan como avisados si el mail salio bien
        $presupuestoModel->marcarAvisadosEntrega(array_column($presupuestos, 'id'));
        $notaPedidoModel->marcarAvisadosEntrega(array_column($notas, 'id'));
        $ordenCompraModel->marcarAvisadosEntrega(array_column($ordenes, 'id'));

        $cant = count($presupuestos) + count($notas) + count($ordenes);
        $totalEnviados++;
        $totalDocumentos += $cant;

        echo "Mail enviado a {$destino} ({$cant} documento(s)).\n";

    } catch (Throwable $e) {
        echo "ERROR en tenant #{$tid}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Fin: {$totalEnviados} mail(s), {$totalDocumentos} documento(s) procesado(s) ===\n";
exit(0);
