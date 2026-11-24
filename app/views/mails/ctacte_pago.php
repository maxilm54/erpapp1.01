<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$nro = 'CP-' . str_pad((string)$pago['id'], 8, '0', STR_PAD_LEFT);
$anulado = (int)($pago['activo'] ?? 1) === 0;
$saldoDespues = $pago['saldo_pendiente_despues'];
$fechaPago = date('d/m/Y', strtotime($pago['fecha']));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; font-size: 14px; }
        .box { border: 1px solid #ddd; padding: 20px; max-width: 680px; }
        .header { border-bottom: 2px solid #1a3a5c; margin-bottom: 15px; padding-bottom: 10px; }
        .header h2 { color: #1a3a5c; margin: 8px 0 0 0; font-size: 20px; }
        .badge { display: inline-block; background: #1a3a5c; color: #fff; font-weight: bold; padding: 4px 10px; border-radius: 4px; }
        .anulado { background: #f8d7da; color: #721c24; padding: 8px 10px; border-left: 3px solid #dc3545; margin: 10px 0; font-weight: bold; }
        .muted { color: #777; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border-bottom: 1px solid #e5e5e5; padding: 6px 4px; text-align: left; font-size: 13px; }
        th { background: #f2f5f9; color: #1a3a5c; }
        td.num, th.num { text-align: right; }
        .monto { font-size: 20px; font-weight: bold; color: #198754; }
        .saldo { font-size: 16px; font-weight: bold; color: #a15c00; }
        .footer { border-top: 1px solid #ddd; margin-top: 15px; padding-top: 10px; color: #777; font-size: 11px; }
    </style>
</head>
<body>
<div class="box">
    <div class="header">
        <?php if (!empty($logo)): ?><img src="<?= $logo ?>" width="120" alt=""><br><?php endif; ?>
        <strong><?= htmlspecialchars($emisor['nombre']) ?></strong>
        <div class="muted"><?= htmlspecialchars($emisor['email']) ?></div>
        <h2><span class="badge">X</span> Comprobante de pago <?= htmlspecialchars($nro) ?></h2>
        <div class="muted">Documento no fiscal</div>
    </div>

    <?php if ($anulado): ?>
    <div class="anulado">
        PAGO ANULADO<?= !empty($pago['anulado_at']) ? ' el ' . date('d/m/Y H:i', strtotime($pago['anulado_at'])) : '' ?>.
        El importe fue devuelto al saldo de los registros.
    </div>
    <?php endif; ?>

    <p>Estimado/a <strong><?= htmlspecialchars($pago['tenant_nombre']) ?></strong>,</p>
    <p>
        <?= $anulado ? 'Le informamos que el siguiente pago fue anulado' : 'Registramos el siguiente pago' ?>
        con fecha <strong><?= $fechaPago ?></strong>.
    </p>

    <p class="monto">Monto: $ <?= $fmt($pago['monto']) ?></p>

    <table>
        <tbody>
            <tr><td><strong>Comprobante</strong></td><td><?= htmlspecialchars($nro) ?></td></tr>
            <tr><td><strong>Fecha del pago</strong></td><td><?= $fechaPago ?></td></tr>
            <tr><td><strong>Registrado por</strong></td><td><?= htmlspecialchars($pago['usuario_nombre'] ?? '-') ?></td></tr>
            <tr><td><strong>Estado</strong></td><td><?= $anulado ? 'ANULADO' : 'ACTIVO' ?></td></tr>
            <?php if (!empty($pago['obs'])): ?>
            <tr><td><strong>Observacion</strong></td><td><?= nl2br(htmlspecialchars($pago['obs'])) ?></td></tr>
            <?php endif; ?>
            <?php if ($anulado && !empty($pago['motivo_anulacion'])): ?>
            <tr><td><strong>Motivo de anulacion</strong></td><td><?= nl2br(htmlspecialchars($pago['motivo_anulacion'])) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($aplicaciones)): ?>
    <h3 style="color:#1a3a5c;font-size:15px;">Imputacion del pago</h3>
    <table>
        <thead>
            <tr><th>Periodo</th><th>Vencimiento</th><th class="num">Importe aplicado</th></tr>
        </thead>
        <tbody>
            <?php foreach ($aplicaciones as $a):
                $partes = explode('-', (string)$a['periodo']);
                $periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
            ?>
            <tr>
                <td><?= htmlspecialchars($periodoTxt) ?></td>
                <td><?= date('d/m/Y', strtotime($a['fecha_vencimiento'])) ?></td>
                <td class="num">$ <?= $fmt($a['monto']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($saldoDespues === null || $saldoDespues === ''): ?>
        <p class="saldo">Deuda pendiente despues del pago: sin dato historico.</p>
    <?php elseif ((float)$saldoDespues <= 0): ?>
        <p class="saldo" style="color:#198754;">Deuda pendiente despues del pago: $ 0,00 (sin deuda).</p>
    <?php else: ?>
        <p class="saldo">Deuda pendiente despues del pago: $ <?= $fmt($saldoDespues) ?></p>
    <?php endif; ?>

    <p>El comprobante completo esta adjunto en formato PDF.</p>

    <div class="footer">
        <?= htmlspecialchars($emisor['nombre']) ?> · <?= htmlspecialchars($emisor['email']) ?><br>
        Comprobante Letra X — Documento no fiscal generado automaticamente.
    </div>
</div>
</body>
</html>
