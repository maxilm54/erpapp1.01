<h1>Deudas de <?= $cliente['razon_social'] ?></h1>
<hr>
<table class="table table-bordered">
<thead>
<tr>
    <th>Debito</th>
    <th>Credito</th>
    <th>Saldo</th>
</tr>
</thead>
<tbody>
<?php foreach ($cliente_mov as $d): ?>
<tr>
    <td><?= $d['Debito'] ?></td>
    <td><?= $d['Credito'] ?></td>
    <td><span class="badge bg-<?= $d['saldo'] > 0 ? 'danger' : 'success' ?>"><?= $d['saldo'] ?></span></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<hr>
<div class="table-scroll mt-3">
    <table class="table table-bordered">
    <thead>
    <tr>
        <th>Fecha</th>
        <th>Origen</th>
        <th>Referencia</th>
        <th>Monto</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($deudas as $d): ?>
    <tr>
        <td><?= $d['fecha'] ?></td>
        <td><?= $d['origen'] ?></td>
        <td>#<?= $d['referencia_id'] ?></td>
        <td><?= number_format($d['monto'],2) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
</div>
<hr>

<form method="POST"> <!-- /ctacte/registrarpago -->
    <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label fw-bold">Monto a pagar *</label>
            <input type="number" step="0.01" name="monto" class="form-control" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label fw-bold">Medio de Pago *</label>
            <select name="medio_pago" class="form-select" required>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Cheque">Cheque</option>
                <option value="Tarjeta">Tarjeta</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label fw-bold">Caja/Banco</label>
            <select name="caja_banco_id" class="form-select">
                <option value="">Sin especificar</option>
                <?php foreach (($cajasBancos ?? []) as $cb): ?>
                    <option value="<?= $cb['id'] ?>">
                        <?= htmlspecialchars($cb['nombre']) ?>
                        (Saldo: $ <?= number_format($cb['saldo_actual'], 2, ',', '.') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Para registrar el ingreso en caja/banco</div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" class="form-control" rows="2"></textarea>
    </div>

    <button class="btn btn-success" onclick="return confirm('¿Confirmar pago? Se generará el asiento contable.')">
        <i class="bi bi-check-circle"></i> Confirmar Pago
    </button>
</form>