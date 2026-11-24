<?php
$total = (float)$registro['sistema'] + (float)$registro['soporte'] + (float)$registro['desarrollo'];
$pagado = (float)($registro['pago'] ?? 0);
$saldo = (float)$registro['saldo_pendiente'];
$partes = explode('-', (string)$registro['periodo']);
$periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
$nro = 'CD-' . str_pad((string)$registro['id'], 8, '0', STR_PAD_LEFT);
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm 15mm 15mm 15mm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; line-height: 1.4; }

        .header { width: 100%; margin-bottom: 4px; padding-bottom: 5px; border-bottom: 2px solid #1a3a5c; }
        .header-tabla { width: 100%; border-collapse: collapse; }
        .header-tabla td { vertical-align: top; padding: 0; }
        .hdr-izq { width: 30%; padding-left: 5mm; padding-right: 10px; }
        .hdr-izq img { height: 60px; display: block; margin-bottom: 3px; }
        .empresa-nombre { font-size: 12px; font-weight: bold; color: #1a3a5c; margin-bottom: 1px; }
        .empresa-datos { font-size: 7.5px; color: #555; line-height: 1.4; }
        .hdr-centro { width: 40%; text-align: center; padding-top: 4px; }
        .doc-badge { display: block; background: #1a3a5c; color: #fff; font-size: 20px; font-weight: bold; width: 42px; height: 42px; line-height: 42px; text-align: center; border-radius: 5px; margin: 0 auto 3px auto; }
        .doc-titulo { font-size: 12px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; letter-spacing: 0.8px; display: block; }
        .doc-sub { font-size: 8px; color: #555; margin-top: 2px; }
        .hdr-der { width: 30%; text-align: center; padding-top: 8px; }
        .doc-numero { font-size: 10px; color: #333; font-weight: bold; }
        .doc-fecha { font-size: 9px; color: #555; margin-top: 1px; }

        .aviso-legal { background: #f0f4f8; border: 1px solid #c0c8d0; border-left: 3px solid #1a3a5c; padding: 4px 8px; margin-bottom: 8px; font-size: 8px; color: #444; text-align: center; }
        .aviso-legal strong { color: #1a3a5c; }

        .seccion-titulo { background: #1a3a5c; color: #fff; padding: 3px 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .seccion-body { border: 1px solid #c0c8d0; border-top: none; padding: 6px 8px; margin-bottom: 8px; }
        .dato-label { font-weight: bold; color: #1a3a5c; font-size: 8.5px; text-transform: uppercase; }
        .dato-valor { font-size: 9.5px; color: #222; }

        .tabla { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .tabla thead th { background: #1a3a5c; color: #fff; padding: 4px 6px; font-size: 8.5px; text-transform: uppercase; text-align: left; }
        .tabla thead th.num { text-align: right; }
        .tabla tbody td { padding: 4px 6px; border-bottom: 1px solid #ddd; font-size: 9.5px; }
        .tabla tbody tr:nth-child(even) { background: #f5f7fa; }
        .tabla tbody td.num { text-align: right; }
        .tabla tbody tr.total-row td { border-bottom: 2px solid #1a3a5c; border-top: 2px solid #1a3a5c; font-weight: bold; font-size: 10.5px; background: #1a3a5c; color: #fff; }

        .resumen { width: 55%; margin-left: 45%; border-collapse: collapse; margin-bottom: 8px; }
        .resumen td { padding: 3px 6px; font-size: 9.5px; border-bottom: 1px solid #e0e0e0; }
        .resumen .lbl { font-weight: bold; color: #1a3a5c; }
        .resumen .val { text-align: right; }
        .resumen .saldo td { font-size: 11px; font-weight: bold; border-top: 2px solid #1a3a5c; color: #a15c00; }

        .estado { display: inline-block; padding: 1px 8px; border-radius: 3px; font-size: 8px; font-weight: bold; color: #fff; }
        .estado-PENDIENTE { background: #b8860b; }
        .estado-PARCIAL { background: #0d6efd; }
        .estado-SALDADO { background: #198754; }

        .observaciones-texto { font-size: 9px; color: #333; white-space: pre-wrap; min-height: 18px; border: 1px solid #ddd; padding: 4px 6px; background: #fafbfc; margin-bottom: 8px; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; padding: 6px 15mm; border-top: 2px solid #1a3a5c; font-size: 7px; color: #777; text-align: center; line-height: 1.5; background: #fff; }
        .footer-fixed strong { color: #1a3a5c; }
    </style>
</head>
<body>

<div class="header">
    <table class="header-tabla">
        <tr>
            <td class="hdr-izq">
                <?php if (!empty($logo)): ?><img src="<?= $logo ?>"><?php endif; ?>
                <div class="empresa-nombre"><?= htmlspecialchars($emisor['nombre']) ?></div>
                <div class="empresa-datos">
                    <?= htmlspecialchars($emisor['email']) ?><br>
                    Administracion del sistema
                </div>
            </td>
            <td class="hdr-centro">
                <div class="doc-badge">X</div>
                <div class="doc-titulo">Comprobante de Deuda</div>
                <div class="doc-sub">Documento no fiscal</div>
            </td>
            <td class="hdr-der">
                <div class="doc-numero">N° <?= htmlspecialchars($nro) ?></div>
                <div class="doc-fecha">Emision: <?= date('d/m/Y') ?></div>
                <div class="doc-fecha">Periodo: <?= htmlspecialchars($periodoTxt) ?></div>
            </td>
        </tr>
    </table>
</div>

<div class="aviso-legal">
    <strong>COMPROBANTE LETRA X</strong> — Documento <strong>no fiscal</strong>. No valido como factura.
    Detalle de los conceptos facturados y del saldo pendiente de la cuenta corriente.
</div>

<div class="seccion-titulo">Datos del Cliente</div>
<div class="seccion-body">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:60%; vertical-align:top; padding:0;">
                <div><span class="dato-label">Empresa: </span><span class="dato-valor"><?= htmlspecialchars($registro['tenant_nombre']) ?></span></div>
                <div><span class="dato-label">Domicilio: </span><span class="dato-valor"><?= htmlspecialchars($registro['tenant_direccion'] ?? '-') ?></span></div>
                <div><span class="dato-label">Alta: </span><span class="dato-valor"><?= !empty($registro['tenant_created_at']) ? date('d/m/Y', strtotime($registro['tenant_created_at'])) : '-' ?></span></div>
            </td>
            <td style="width:40%; vertical-align:top; padding:0;">
                <div><span class="dato-label">CUIT: </span><span class="dato-valor"><?= htmlspecialchars($registro['tenant_cuit'] ?? '-') ?></span></div>
                <div><span class="dato-label">Email: </span><span class="dato-valor"><?= htmlspecialchars($registro['tenant_email'] ?? '-') ?></span></div>
                <div><span class="dato-label">Telefono: </span><span class="dato-valor"><?= htmlspecialchars($registro['tenant_telefono'] ?? '-') ?></span></div>
            </td>
        </tr>
    </table>
</div>

<div class="seccion-titulo">Detalle del Periodo <?= htmlspecialchars($periodoTxt) ?></div>
<table class="tabla">
    <thead>
        <tr>
            <th>Concepto</th>
            <th>Periodo</th>
            <th>Fecha de alta</th>
            <th>Vencimiento</th>
            <th class="num">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Sistema</td>
            <td><?= htmlspecialchars($periodoTxt) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_alta'])) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_vencimiento'])) ?></td>
            <td class="num">$ <?= $fmt($registro['sistema']) ?></td>
        </tr>
        <tr>
            <td>Soporte</td>
            <td><?= htmlspecialchars($periodoTxt) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_alta'])) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_vencimiento'])) ?></td>
            <td class="num">$ <?= $fmt($registro['soporte']) ?></td>
        </tr>
        <tr>
            <td>Desarrollo</td>
            <td><?= htmlspecialchars($periodoTxt) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_alta'])) ?></td>
            <td><?= date('d/m/Y', strtotime($registro['fecha_vencimiento'])) ?></td>
            <td class="num">$ <?= $fmt($registro['desarrollo']) ?></td>
        </tr>
        <tr class="total-row">
            <td colspan="4" style="text-align:right; padding-right:10px;">TOTAL DEL PERIODO:</td>
            <td class="num">$ <?= $fmt($total) ?></td>
        </tr>
    </tbody>
</table>

<table class="resumen">
    <tr>
        <td class="lbl">Total facturado</td>
        <td class="val">$ <?= $fmt($total) ?></td>
    </tr>
    <tr>
        <td class="lbl">Pagado</td>
        <td class="val">$ <?= $fmt($pagado) ?></td>
    </tr>
    <tr class="saldo">
        <td class="lbl">Saldo pendiente</td>
        <td class="val">$ <?= $fmt($saldo) ?></td>
    </tr>
    <tr>
        <td class="lbl">Estado</td>
        <td class="val">
            <span class="estado estado-<?= htmlspecialchars($registro['estado']) ?>"><?= htmlspecialchars($registro['estado']) ?></span>
        </td>
    </tr>
</table>

<?php if (!empty($pagos)): ?>
<div class="seccion-titulo">Pagos aplicados a este periodo</div>
<table class="tabla">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Comprobante de pago</th>
            <th>Observacion</th>
            <th class="num">Importe aplicado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pagos as $p): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
            <td>CP-<?= str_pad((string)$p['id'], 8, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($p['obs'] ?? '-') ?></td>
            <td class="num">$ <?= $fmt($p['monto_aplicado']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty(trim((string)($registro['obs'] ?? '')))): ?>
<div class="seccion-titulo">Observaciones</div>
<div class="observaciones-texto"><?= nl2br(htmlspecialchars($registro['obs'])) ?></div>
<?php endif; ?>

<div class="footer-fixed">
    <strong><?= htmlspecialchars($emisor['nombre']) ?></strong> · <?= htmlspecialchars($emisor['email']) ?><br>
    <em>Comprobante Letra X — Documento no fiscal, generado automaticamente.</em>
</div>

</body>
</html>
