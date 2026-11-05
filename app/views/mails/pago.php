<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color:#333; }
        .box { border:1px solid #ddd; padding:20px; }
        .header { border-bottom:2px solid #000; margin-bottom:15px; }
        .total { font-size:18px; font-weight:bold; }
    </style>
</head>
<body>
<img src="<?= $empresa['logo'] ?>" width="120">
<div class="box">
    <div class="header">
        <h2>Comprobante de Pago</h2>
    </div>

    <p>Estimado/a <strong><?= htmlspecialchars($pago['razon_social']) ?></strong>,</p>

    <p>
        Registramos un pago realizado el día
        <?= date('d/m/Y H:i', strtotime($pago['fecha'])) ?>.
    </p>

    <p class="total">
        Monto: $<?= number_format($pago['monto'], 2, ',', '.') ?>
    </p>
    <p>
    Medio de pago:
    <?= htmlspecialchars($pago['medio_pago'] ?? 'No especificado') ?>
</p>
<?php if (!empty($pago['cheque_id'])): ?>
<p>
    <strong>Datos del cheque:</strong><br>
    <?= ($pago['cheque_tipo'] ?? 'FISICO') === 'ECHEQ' ? 'E-cheq' : 'Cheque físico' ?><br>
    Banco: <?= htmlspecialchars($pago['cheque_banco'] ?? '') ?>
    <?php if (!empty($pago['cheque_sucursal'])): ?> (<?= htmlspecialchars($pago['cheque_sucursal']) ?>)<?php endif; ?><br>
    Número: <?= htmlspecialchars($pago['cheque_numero'] ?? '') ?><br>
    <?php if (!empty($pago['cheque_titular'])): ?>Titular: <?= htmlspecialchars($pago['cheque_titular']) ?><br><?php endif; ?>
    <?php if (!empty($pago['cheque_cuit'])): ?>CUIT: <?= htmlspecialchars($pago['cheque_cuit']) ?><br><?php endif; ?>
    <?php if (!empty($pago['cheque_fecha_emision'])): ?>Emisión: <?= date('d/m/Y', strtotime($pago['cheque_fecha_emision'])) ?><br><?php endif; ?>
    <?php if (!empty($pago['cheque_fecha_vencimiento'])): ?>Vencimiento: <?= date('d/m/Y', strtotime($pago['cheque_fecha_vencimiento'])) ?><?php endif; ?>
</p>
<?php endif; ?>
<?php if (!empty($pago['observaciones'])): ?>
<p>
    <strong>Observaciones:</strong><br>
    <?= nl2br(htmlspecialchars($pago['observaciones'])) ?>
</p>
<?php endif; ?>
    <p>
        Muchas gracias por su pago.
    </p>

    <p>Atentamente.</p>
<hr>
    <small>
        <p style="font-size:12px;color:#666;">
    <?= htmlspecialchars($empresa['nombre']) ?><br>
    <?= htmlspecialchars($empresa['direccion']) ?><br>
    <?= htmlspecialchars($empresa['email']) ?>
</p>
    </small>
</div>

</body>
</html>