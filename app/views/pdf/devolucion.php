<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm 15mm 15mm 15mm; size: A4; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; line-height: 1.4; height: 100%; }

        .header { width: 100%; margin-bottom: 4px; padding-bottom: 5px; border-bottom: 2px solid #B03A2E; }
        .header-tabla { width: 100%; border-collapse: collapse; }
        .header-tabla td { vertical-align: top; padding: 0; }
        .hdr-izq { width: 30%; padding-left: 5mm; padding-right: 10px; }
        .hdr-izq img { height: 60px; display: block; margin-bottom: 3px; }
        .empresa-nombre { font-size: 12px; font-weight: bold; color: #B03A2E; margin-bottom: 1px; }
        .empresa-datos { font-size: 7.5px; color: #555; line-height: 1.4; }
        .hdr-centro { width: 40%; text-align: center; padding-top: 4px; }
        .hdr-centro .dev-badge { display: block; background: #B03A2E; color: #fff; font-size: 18px; font-weight: bold; width: 42px; height: 42px; line-height: 42px; text-align: center; border-radius: 5px; margin: 0 auto 3px auto; }
        .hdr-centro .dev-titulo { font-size: 12px; font-weight: bold; color: #B03A2E; text-transform: uppercase; letter-spacing: 0.8px; display: block; }
        .hdr-centro .dev-numero { font-size: 10px; color: #333; margin-top: 2px; }
        .hdr-centro .dev-fecha { font-size: 9px; color: #555; margin-top: 1px; }
        .hdr-der { width: 30%; text-align: center; padding-top: 8px; }

        .aviso-legal { background: #fdf2f2; border: 1px solid #d4c5c5; border-left: 3px solid #B03A2E; padding: 4px 8px; margin-bottom: 8px; font-size: 8px; color: #444; text-align: center; }
        .aviso-legal strong { color: #B03A2E; }

        .seccion-titulo { background: #B03A2E; color: #fff; padding: 3px 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .seccion-body { border: 1px solid #c0c0c0; border-top: none; padding: 6px 8px; margin-bottom: 8px; }
        .dato-fila { margin-bottom: 2px; }
        .dato-label { font-weight: bold; color: #B03A2E; font-size: 8.5px; text-transform: uppercase; }
        .dato-valor { font-size: 9.5px; color: #222; }

        .productos-tabla { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .productos-tabla thead th { background: #B03A2E; color: #fff; padding: 4px 6px; font-size: 8.5px; text-transform: uppercase; text-align: left; letter-spacing: 0.3px; }
        .productos-tabla thead th.num { text-align: center; width: 30px; }
        .productos-tabla thead th.col-cant { text-align: right; width: 70px; }
        .productos-tabla thead th.col-prec { text-align: right; width: 80px; }
        .productos-tabla thead th.col-sub { text-align: right; width: 90px; }
        .productos-tabla tbody td { padding: 4px 6px; border-bottom: 1px solid #ddd; font-size: 9.5px; }
        .productos-tabla tbody tr:nth-child(even) { background: #f5f5f5; }
        .productos-tabla tbody td.num { text-align: center; color: #666; }
        .productos-tabla tbody td.num-col { text-align: right; }
        .productos-tabla tbody tr.total-row td { border-bottom: 2px solid #B03A2E; border-top: 2px solid #B03A2E; font-weight: bold; font-size: 10.5px; background: #B03A2E; color: #fff; }

        .observaciones { margin-bottom: 6px; }
        .observaciones-texto { font-size: 9px; color: #333; white-space: pre-wrap; min-height: 20px; border: 1px solid #ddd; padding: 4px 6px; background: #fafbfc; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; padding: 6px 15mm; border-top: 2px solid #B03A2E; font-size: 7px; color: #777; text-align: center; line-height: 1.5; background: #fff; }
        .footer-fixed strong { color: #B03A2E; }
    </style>
</head>
<body>

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
                <div class="dev-badge">DR</div>
                <div class="dev-titulo">DEVOLUCIÓN</div>
            </td>
            <td class="hdr-der">
                <div class="dev-numero">N° DEV-<?= str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT) ?></div>
                <div class="dev-fecha">Fecha: <?= date('d/m/Y', strtotime($devolucion['created_at'])) ?></div>
                <?php if ($devolucion['remito_numero']): ?>
                <div class="dev-fecha">Remito: #<?= $devolucion['remito_numero'] ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<div class="aviso-legal">
    <strong>NOTA DE DEVOLUCIÓN</strong> — Documento que registra la devolución de mercadería por parte del cliente.
</div>

<div class="seccion-titulo">Datos del Cliente</div>
<div class="seccion-body">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:65%; vertical-align:top; padding:0;">
                <div class="dato-fila">
                    <span class="dato-label">Razón Social: </span>
                    <span class="dato-valor"><?= htmlspecialchars($devolucion['nombre_cliente']) ?></span>
                </div>
                <div class="dato-fila">
                    <span class="dato-label">Domicilio: </span>
                    <span class="dato-valor"><?= htmlspecialchars($devolucion['cliente_direccion'] ?? '-') ?></span>
                </div>
            </td>
            <td style="width:35%; vertical-align:top; padding:0;">
                <div class="dato-fila">
                    <span class="dato-label">CUIT: </span>
                    <span class="dato-valor"><?= htmlspecialchars($devolucion['cuit'] ?? '-') ?></span>
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="seccion-titulo">Motivo de Devolución</div>
<div class="seccion-body">
    <div class="dato-valor"><?= nl2br(htmlspecialchars($devolucion['motivo'])) ?></div>
</div>

<div class="seccion-titulo">Detalle de Productos Devueltos</div>
<?php
    $totalGeneral = 0;
    foreach ($devolucion['detalle'] as $d) {
        $precio = (float)$d['precio_unitario'];
        $cant = (float)$d['cantidad_total'];
        $subtotal = $precio * $cant;
        $totalGeneral += $subtotal;
    }
?>
<table class="productos-tabla">
    <thead>
        <tr>
            <th class="num">N°</th>
            <th>Producto</th>
            <th>Condición</th>
            <th class="col-cant">Devuelta</th>
            <th class="col-cant">Reingresa</th>
            <th class="col-cant">Descarta</th>
            <th class="col-prec">P. Unitario</th>
            <th class="col-sub">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $condicionesMap = [
            'NUEVO' => 'Nuevo',
            'BUEN_ESTADO' => 'Buen Estado',
            'ESTADO_REGULAR' => 'Estado Regular',
            'DANADO' => 'Dañado',
            'INSERVIBLE' => 'Inservible',
        ];
        $i = 1;
        foreach ($devolucion['detalle'] as $d):
            $precio = (float)$d['precio_unitario'];
            $cant = (float)$d['cantidad_total'];
            $sub = $precio * $cant;
            $nombre = $d['producto_nombre'] ?? 'Sin nombre';
            $cond = $condicionesMap[$d['condicion']] ?? $d['condicion'];
        ?>
        <tr>
            <td class="num"><?= $i++ ?></td>
            <td><?= htmlspecialchars($nombre) ?></td>
            <td><?= $cond ?></td>
            <td class="num-col"><?= number_format($cant, 2, ',', '.') ?></td>
            <td class="num-col"><?= number_format((float)$d['cantidad_reingresa'], 2, ',', '.') ?></td>
            <td class="num-col"><?= number_format((float)$d['cantidad_descarta'], 2, ',', '.') ?></td>
            <td class="num-col">$ <?= number_format($precio, 2, ',', '.') ?></td>
            <td class="num-col">$ <?= number_format($sub, 2, ',', '.') ?></td>
        </tr>
        <?php endforeach ?>
        <tr class="total-row">
            <td colspan="7" style="text-align:right; padding-right:10px;">TOTAL:</td>
            <td class="num-col">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
        </tr>
    </tbody>
</table>

<?php if (!empty($devolucion['reembolsos'])): ?>
<div class="seccion-titulo">Reembolsos</div>
<table class="productos-tabla">
    <thead>
        <tr>
            <th>Método</th>
            <th class="col-sub">Monto</th>
            <th>Caja / Banco</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $metodosMap = ['EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia', 'NOTA_CREDITO' => 'Nota de Crédito'];
    foreach ($devolucion['reembolsos'] as $r):
    ?>
        <tr>
            <td><?= $metodosMap[$r['metodo']] ?? $r['metodo'] ?></td>
            <td class="num-col">$ <?= number_format((float)$r['monto'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($r['caja_nombre'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty(trim($devolucion['motivo'] ?? ''))): ?>
<div class="observaciones">
    <div class="seccion-titulo">Observaciones</div>
    <div class="observaciones-texto"><?= nl2br(htmlspecialchars($devolucion['motivo'])) ?></div>
</div>
<?php endif; ?>

<div class="footer-fixed">
    <strong><?= htmlspecialchars($empresa['nombre']) ?></strong> · <?= htmlspecialchars($empresa['direccion']) ?> · CUIT <?= htmlspecialchars($empresa['cuit']) ?><br>
    <?= htmlspecialchars($empresa['email']) ?> · <?= htmlspecialchars($empresa['telefono']) ?><br>
    <em>Documento generado automáticamente. Nota de devolución.</em>
</div>

</body>
</html>
