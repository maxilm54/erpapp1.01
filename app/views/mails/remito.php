<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color:#333; }
        .box { border:1px solid #ddd; padding:20px; }
        .header { border-bottom:2px solid #000; margin-bottom:15px; }
        .footer { font-size:12px; color:#777; margin-top:20px; }
    </style>
</head>
<body>

<div class="box">
    <img src="<?= $empresa['logo'] ?>" width="120">
    <div class="header">
        <h2>Remito de Salida</h2>
    </div>

    <p>Estimado/a <strong><?= htmlspecialchars($cliente['razon_social']) ?></strong>,</p>

    <p>
        Le enviamos adjunto el <strong>Remito N° <?= $remito['numero'] ?></strong>,
        correspondiente a la entrega realizada el día
        <?= date('d/m/Y', strtotime($remito['fecha'])) ?>.
    </p>

    <p>
        Ante cualquier consulta, no dude en contactarnos.
    </p>

    <p>Saludos cordiales.</p>

    <div class="footer">
        <?= $empresa['nombre'] ?> · <?= $empresa['email'] ?>
    </div>
</div>

</body>
</html>