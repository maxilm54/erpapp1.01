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

<hr>

<form method="POST"> <!-- /ctacte/registrarpago -->
    <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">

    <label>Monto a pagar</label>
    <input type="number" step="0.01" name="monto" class="form-control" required>
    <label>Tipo de Pago</label>
    <select name="medio_pago" class="form-control" required>
        <option value="Efectivo">Efectivo</option>
        <option value="Cheque">Cheque</option>
        <option value="Tarjeta">Tarjeta</option>
    </select>

    <label>Observaciones</label>
    <textarea name="observaciones" class="form-control"></textarea>

    <br>
    <button class="btn btn-success">Confirmar Pago</button>
</form>