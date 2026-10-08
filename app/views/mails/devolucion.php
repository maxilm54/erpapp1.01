<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color:#333; }
        .box { border:1px solid #ddd; padding:20px; }
        .header { border-bottom:2px solid #8B0000; margin-bottom:15px; }
        .header h2 { color: #8B0000; }
        .footer { font-size:12px; color:#777; margin-top:20px; }
    </style>
</head>
<body>

<div class="box">
    <?php if (!empty($empresa['logo'])): ?>
        <img src="<?= $empresa['logo'] ?>" width="120">
    <?php endif; ?>
    <div class="header">
        <h2>Nota de Devolución</h2>
    </div>

    <p>Estimado/a <strong><?= htmlspecialchars($cliente_nombre) ?></strong>,</p>

    <p>
        Le informamos que se ha procesado la <strong>Devolución N° DEV-<?= str_pad($numero, 6, '0', STR_PAD_LEFT) ?></strong>,
        correspondiente al día <?= $fecha ?>.
    </p>

    <?php if (!empty($motivo)): ?>
    <p><strong>Motivo:</strong> <?= htmlspecialchars($motivo) ?></p>
    <?php endif; ?>

    <p>
        El detalle de los productos devueltos y el método de reembolso se encuentran en el documento adjunto.
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
