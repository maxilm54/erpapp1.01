<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$total = (float)$registro['sistema'] + (float)$registro['soporte'] + (float)$registro['desarrollo'];
$saldo = (float)$registro['saldo_pendiente'];
$partes = explode('-', (string)$registro['periodo']);
$periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
$nro = 'CD-' . str_pad((string)$registro['id'], 8, '0', STR_PAD_LEFT);
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
        .muted { color: #777; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border-bottom: 1px solid #e5e5e5; padding: 6px 4px; text-align: left; font-size: 13px; }
        th { background: #f2f5f9; color: #1a3a5c; }
        td.num, th.num { text-align: right; }
        .saldo { font-size: 18px; font-weight: bold; color: #a15c00; }
        .footer { border-top: 1px solid #ddd; margin-top: 15px; padding-top: 10px; color: #777; font-size: 11px; }
    </style>
</head>
<body>
<div class="box">
    <div class="header">
        <?php if (!empty($logo)): ?><img src="<?= $logo ?>" width="120" alt=""><br><?php endif; ?>
        <strong><?= htmlspecialchars($emisor['nombre']) ?></strong>
        <div class="muted"><?= htmlspecialchars($emisor['email']) ?></div>
        <h2><span class="badge">X</span> Comprobante de deuda <?= htmlspecialchars($nro) ?></h2>
        <div class="muted">Periodo <?= htmlspecialchars($periodoTxt) ?> · Documento no fiscal</div>
    </div>

    <p>Estimado/a <strong><?= htmlspecialchars($registro['tenant_nombre']) ?></strong>,</p>
    <p>Adjuntamos el comprobante de deuda del periodo <strong><?= htmlspecialchars($periodoTxt) ?></strong>.</p>

    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Sistema</td><td class="num">$ <?= $fmt($registro['sistema']) ?></td></tr>
            <tr><td>Soporte</td><td class="num">$ <?= $fmt($registro['soporte']) ?></td></tr>
            <tr><td>Desarrollo</td><td class="num">$ <?= $fmt($registro['desarrollo']) ?></td></tr>
            <tr><td><strong>Total del periodo</strong></td><td class="num"><strong>$ <?= $fmt($total) ?></strong></td></tr>
            <tr><td>Pagado</td><td class="num">$ <?= $fmt($registro['pago'] ?? 0) ?></td></tr>
        </tbody>
    </table>

    <p class="saldo">Saldo pendiente: $ <?= $fmt($saldo) ?></p>
    <p class="muted">Vencimiento: <?= date('d/m/Y', strtotime($registro['fecha_vencimiento'])) ?></p>

    <?php if (!empty($registro['obs'])): ?>
    <p><strong>Observaciones:</strong><br><?= nl2br(htmlspecialchars($registro['obs'])) ?></p>
    <?php endif; ?>

    <p>El comprobante completo esta adjunto en formato PDF.</p>

    <div class="footer">
        <?= htmlspecialchars($emisor['nombre']) ?> · <?= htmlspecialchars($emisor['email']) ?><br>
        Comprobante Letra X — Documento no fiscal generado automaticamente.
    </div>
</div>
</body>
</html>
