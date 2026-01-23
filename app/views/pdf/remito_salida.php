<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { display:flex; justify-content:space-between; }
        .logo img { height:80px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #eee; }
        .header { margin-bottom: 15px; }
        .right { text-align: right; }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">
        <img src="<?= $logo ?>" width="80px">
    </div>
    <div>
        <h3>REMITO DE SALIDA</h3>
        <strong>N°:</strong> <?= $remito['numero'] ?><br>
        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($remito['created_at'])) ?>
    </div>
</div>

<hr>

<p>
<strong>Cliente:</strong> <?= htmlspecialchars($remito['cliente']) ?><br>
<strong>Dirección:</strong> <?= htmlspecialchars($remito['direccion'] ?? '-') ?><br>
<strong>CUIT:</strong> <?= htmlspecialchars($remito['cuit'] ?? '-') ?>
</p>

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th width="120">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($remito['detalle'] as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td class="right"><?= number_format($d['cantidad'], 3) ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>

<p style="margin-top:20px">
<strong>Observaciones:</strong><br>
<?= nl2br(htmlspecialchars($remito['observaciones'] ?? '')) ?>
</p>

</body>
</html>