<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$nro = 'CP-' . str_pad((string)$pago['id'], 8, '0', STR_PAD_LEFT);
$anulado = (int)($pago['activo'] ?? 1) === 0;
$saldoDespues = $pago['saldo_pendiente_despues'];
$partesPago = explode('-', (string)$pago['fecha']);
$fechaPago = ($partesPago[2] ?? '') . '/' . ($partesPago[1] ?? '') . '/' . ($partesPago[0] ?? '');
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
        .aviso-anulado { background: #f8d7da; border-color: #dc3545; color: #721c24; border-left-color: #dc3545; font-size: 9px; font-weight: bold; }

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

        .monto-box { width: 55%; margin-left: 45%; border: 1px solid #1a3a5c; padding: 6px 8px; margin-bottom: 8px; }
        .monto-box .lbl { font-size: 8.5px; color: #555; text-transform: uppercase; }
        .monto-box .val { font-size: 16px; font-weight: bold; color: #1a3a5c; }
        .monto-box .saldo-lbl { font-size: 8.5px; color: #555; text-transform: uppercase; margin-top: 4px; }
        .monto-box .saldo-val { font-size: 13px; font-weight: bold; color: #a15c00; }

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
                <div class="doc-titulo">Comprobante de Pago</div>
                <div class="doc-sub">Documento no fiscal</div>
            </td>
            <td class="hdr-der">
                <div class="doc-numero">N° <?= htmlspecialchars($nro) ?></div>
                <div class="doc-fecha">Fecha del pago: <?= htmlspecialchars($fechaPago) ?></div>
                <div class="doc-fecha">Registrado: <?= date('d/m/Y H:i', strtotime($pago['created_at'])) ?></div>
            </td>
        </tr>
    </table>
</div>

<div class="aviso-legal">
    <strong>COMPROBANTE LETRA X</strong> — Documento <strong>no fiscal</strong>. No valido como factura.
    Constancia del pago registrado en la cuenta corriente.
</div>

<?php if ($anulado): ?>
<div class="aviso-legal aviso-anulado">
    PAGO ANULADO — El importe fue devuelto al saldo de los registros.
    <?php if (!empty($pago['anulado_at'])): ?>
        Anulado el <?= date('d/m/Y H:i', strtotime($pago['anulado_at'])) ?>
        por <?= htmlspecialchars($pago['anulado_por_nombre'] ?? '-') ?>.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="seccion-titulo">Datos del Cliente</div>
<div class="seccion-body">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:60%; vertical-align:top; padding:0;">
                <div><span class="dato-label">Empresa: </span><span class="dato-valor"><?= htmlspecialchars($pago['tenant_nombre']) ?></span></div>
                <div><span class="dato-label">Domicilio: </span><span class="dato-valor"><?= htmlspecialchars($pago['tenant_direccion'] ?? '-') ?></span></div>
            </td>
            <td style="width:40%; vertical-align:top; padding:0;">
                <div><span class="dato-label">CUIT: </span><span class="dato-valor"><?= htmlspecialchars($pago['tenant_cuit'] ?? '-') ?></span></div>
                <div><span class="dato-label">Email: </span><span class="dato-valor"><?= htmlspecialchars($pago['tenant_email'] ?? '-') ?></span></div>
                <div><span class="dato-label">Telefono: </span><span class="dato-valor"><?= htmlspecialchars($pago['tenant_telefono'] ?? '-') ?></span></div>
            </td>
        </tr>
    </table>
</div>

<div class="seccion-titulo">Datos del Pago</div>
<table class="tabla">
    <tbody>
        <tr>
            <td style="width:30%"><span class="dato-label">Fecha del pago</span></td>
            <td><?= htmlspecialchars($fechaPago) ?></td>
        </tr>
        <tr>
            <td><span class="dato-label">Registrado por</span></td>
            <td><?= htmlspecialchars($pago['usuario_nombre'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><span class="dato-label">Estado</span></td>
            <td><?= $anulado ? 'ANULADO' : 'ACTIVO' ?></td>
        </tr>
        <?php if (!empty($pago['obs'])): ?>
        <tr>
            <td><span class="dato-label">Observacion</span></td>
            <td><?= nl2br(htmlspecialchars($pago['obs'])) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($anulado && !empty($pago['motivo_anulacion'])): ?>
        <tr>
            <td><span class="dato-label">Motivo de anulacion</span></td>
            <td><?= nl2br(htmlspecialchars($pago['motivo_anulacion'])) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($aplicaciones)): ?>
<div class="seccion-titulo">Imputacion del Pago</div>
<table class="tabla">
    <thead>
        <tr>
            <th>Periodo</th>
            <th>Vencimiento</th>
            <th>Comprobante de deuda</th>
            <th class="num">Importe aplicado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($aplicaciones as $a):
            $partes = explode('-', (string)$a['periodo']);
            $periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
        ?>
        <tr>
            <td><?= htmlspecialchars($periodoTxt) ?></td>
            <td><?= date('d/m/Y', strtotime($a['fecha_vencimiento'])) ?></td>
            <td>CD-<?= str_pad((string)$a['registro_id'], 8, '0', STR_PAD_LEFT) ?></td>
            <td class="num">$ <?= $fmt($a['monto']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" style="text-align:right; font-weight:bold;">TOTAL APLICADO:</td>
            <td class="num" style="font-weight:bold;">$ <?= $fmt($pago['monto']) ?></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<div class="monto-box">
    <div class="lbl">Monto del pago</div>
    <div class="val">$ <?= $fmt($pago['monto']) ?></div>

    <div class="saldo-lbl">Deuda pendiente despues del pago</div>
    <?php if ($saldoDespues === null || $saldoDespues === ''): ?>
        <div class="saldo-val">—</div>
        <div style="font-size:8px;color:#777;">Sin dato historico (pago anterior a la version con comprobantes).</div>
    <?php elseif ((float)$saldoDespues <= 0): ?>
        <div class="saldo-val" style="color:#198754;">$ 0,00 — Sin deuda pendiente</div>
    <?php else: ?>
        <div class="saldo-val">$ <?= $fmt($saldoDespues) ?></div>
    <?php endif; ?>
</div>

<div class="footer-fixed">
    <strong><?= htmlspecialchars($emisor['nombre']) ?></strong> · <?= htmlspecialchars($emisor['email']) ?><br>
    <em>Comprobante Letra X — Documento no fiscal, generado automaticamente.</em>
</div>

</body>
</html>
