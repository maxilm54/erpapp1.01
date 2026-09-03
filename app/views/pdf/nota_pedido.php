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
        .hdr-centro .doc-badge { display: block; background: #1a3a5c; color: #fff; font-size: 20px; font-weight: bold; width: 42px; height: 42px; line-height: 42px; text-align: center; border-radius: 5px; margin: 0 auto 3px auto; }
        .hdr-centro .doc-titulo { font-size: 12px; font-weight: bold; color: #1a3a5c; text-transform: uppercase; letter-spacing: 0.8px; display: block; }
        .hdr-centro .doc-estado { font-size: 9px; margin-top: 2px; }
        .hdr-centro .doc-estado span { display: inline-block; padding: 1px 8px; border-radius: 3px; font-weight: bold; font-size: 8px; color: #fff; }
        .estado-borrador { background: #6c757d; }
        .estado-aprobada { background: #198754; }
        .estado-anulada { background: #dc3545; }
        .hdr-der { width: 30%; text-align: center; padding-top: 8px; }
        .hdr-der .doc-numero { font-size: 10px; color: #333; }
        .hdr-der .doc-fecha { font-size: 9px; color: #555; margin-top: 1px; }

        .aviso-legal { background: #f0f4f8; border: 1px solid #c0c8d0; border-left: 3px solid #1a3a5c; padding: 4px 8px; margin-bottom: 8px; font-size: 8px; color: #444; text-align: center; }
        .aviso-legal strong { color: #1a3a5c; }

        .seccion-titulo { background: #1a3a5c; color: #fff; padding: 3px 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .seccion-body { border: 1px solid #c0c8d0; border-top: none; padding: 6px 8px; margin-bottom: 8px; }
        .dato-fila { margin-bottom: 2px; }
        .dato-label { font-weight: bold; color: #1a3a5c; font-size: 8.5px; text-transform: uppercase; }
        .dato-valor { font-size: 9.5px; color: #222; }

        .productos-tabla { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .productos-tabla thead th { background: #1a3a5c; color: #fff; padding: 4px 6px; font-size: 8.5px; text-transform: uppercase; text-align: left; letter-spacing: 0.3px; }
        .productos-tabla thead th.num { text-align: center; width: 30px; }
        .productos-tabla thead th.col-cant { text-align: right; width: 70px; }
        .productos-tabla thead th.col-prec { text-align: right; width: 80px; }
        .productos-tabla thead th.col-sub { text-align: right; width: 90px; }
        .productos-tabla tbody td { padding: 4px 6px; border-bottom: 1px solid #ddd; font-size: 9.5px; }
        .productos-tabla tbody tr:nth-child(even) { background: #f5f7fa; }
        .productos-tabla tbody td.num { text-align: center; color: #666; }
        .productos-tabla tbody td.num-col { text-align: right; }
        .productos-tabla tbody tr.total-row td { border-bottom: 2px solid #1a3a5c; border-top: 2px solid #1a3a5c; font-weight: bold; font-size: 10.5px; background: #1a3a5c; color: #fff; }

        .observaciones { margin-bottom: 6px; }
        .observaciones-texto { font-size: 9px; color: #333; white-space: pre-wrap; min-height: 20px; border: 1px solid #ddd; padding: 4px 6px; background: #fafbfc; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; padding: 6px 15mm; border-top: 2px solid #1a3a5c; font-size: 7px; color: #777; text-align: center; line-height: 1.5; background: #fff; }
        .footer-fixed strong { color: #1a3a5c; }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <table class="header-tabla">
        <tr>
            <td class="hdr-izq">
                <img src="<?= $logo ?>">
                <div class="empresa-nombre"><?= htmlspecialchars($empresa['nombre']) ?></div>
                <div class="empresa-datos">
                    <?= htmlspecialchars($empresa['direccion']) ?><br>
                    CUIT: <?= htmlspecialchars($empresa['cuit']) ?><br>
                    <?= htmlspecialchars($empresa['telefono']) ?> · <?= htmlspecialchars($empresa['email']) ?>
                </div>
            </td>
            <td class="hdr-centro">
                <div class="doc-badge">NP</div>
                <div class="doc-titulo">NOTA DE PEDIDO</div>
                <div class="doc-estado">
                    <?php if ($np['estado'] === 'APROBADA'): ?>
                        <span class="estado-aprobada">APROBADA</span>
                    <?php elseif ($np['estado'] === 'ANULADA'): ?>
                        <span class="estado-anulada">ANULADA</span>
                    <?php else: ?>
                        <span class="estado-borrador">BORRADOR</span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="hdr-der">
                <div class="doc-numero">N° <?= $np['id'] ?></div>
                <div class="doc-fecha">Fecha: <?= date('d/m/Y', strtotime($np['created_at'])) ?></div>
            </td>
        </tr>
    </table>
</div>

<!-- AVISO LEGAL -->
<div class="aviso-legal">
    <strong>NOTA DE PEDIDO</strong> — Este documento <strong>no constituye comprobante fiscal</strong>.
    Documento interno de pedido de mercadería.
</div>

<!-- DATOS DEL CLIENTE -->
<div class="seccion-titulo">Datos del Cliente</div>
<div class="seccion-body">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:65%; vertical-align:top; padding:0;">
                <div class="dato-fila">
                    <span class="dato-label">Razón Social: </span>
                    <span class="dato-valor"><?= htmlspecialchars($np['cliente']) ?></span>
                </div>
                <div class="dato-fila">
                    <span class="dato-label">Domicilio: </span>
                    <span class="dato-valor"><?= htmlspecialchars($np['direccion'] ?? '-') ?></span>
                </div>
            </td>
            <td style="width:35%; vertical-align:top; padding:0;">
                <div class="dato-fila">
                    <span class="dato-label">CUIT: </span>
                    <span class="dato-valor"><?= htmlspecialchars($np['cuit'] ?? '-') ?></span>
                </div>
            </td>
        </tr>
    </table>
</div>

<!-- DETALLE -->
<div class="seccion-titulo">Detalle del Pedido</div>
<?php
    $totalGeneral = 0;
    foreach ($np['detalle'] as $d) {
        $precio = (float)$d['precio'];
        $cant = (float)$d['cantidad'];
        $totalGeneral += $precio * $cant;
    }
?>
<table class="productos-tabla">
    <thead>
        <tr>
            <th class="num">N°</th>
            <th>Producto</th>
            <th class="col-cant">Cantidad</th>
            <th class="col-prec">P. Unitario</th>
            <th class="col-sub">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($np['detalle'] as $d):
            $precio = (float)$d['precio'];
            $cant = (float)$d['cantidad'];
            $sub = $precio * $cant;
        ?>
        <tr>
            <td class="num"><?= $i++ ?></td>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td class="num-col"><?= number_format($cant, 2, ',', '.') ?></td>
            <td class="num-col">$ <?= number_format($precio, 2, ',', '.') ?></td>
            <td class="num-col">$ <?= number_format($sub, 2, ',', '.') ?></td>
        </tr>
        <?php endforeach ?>
        <tr class="total-row">
            <td colspan="4" style="text-align:right; padding-right:10px;">TOTAL:</td>
            <td class="num-col">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
        </tr>
    </tbody>
</table>

<!-- OBSERVACIONES -->
<?php if (!empty(trim($np['observaciones'] ?? ''))): ?>
<div class="observaciones">
    <div class="seccion-titulo">Observaciones</div>
    <div class="observaciones-texto"><?= nl2br(htmlspecialchars($np['observaciones'])) ?></div>
</div>
<?php endif; ?>

<!-- FOOTER -->
<div class="footer-fixed">
    <strong><?= htmlspecialchars($empresa['nombre']) ?></strong> · <?= htmlspecialchars($empresa['direccion']) ?> · CUIT <?= htmlspecialchars($empresa['cuit']) ?><br>
    <?= htmlspecialchars($empresa['email']) ?> · <?= htmlspecialchars($empresa['telefono']) ?><br>
    <em>Documento generado automáticamente. Nota de Pedido — No válido como factura.</em>
</div>

</body>
</html>
