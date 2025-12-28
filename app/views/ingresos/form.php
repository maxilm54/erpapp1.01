<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-8 mx-auto">

<div class="mb-3">
    <label class="form-label">Remito</label>
    <input type="text"
           name="remito"
           class="form-control"
           placeholder="00000-00000000"
           pattern="\d{5}-\d{8}"
           required>
</div>

<table class="table table-bordered">
<thead class="table-dark">
<tr>
    <th>Materia Prima</th>
    <th>Cantidad Pedida</th>
    <th>Cantidad Recibida</th>
</tr>
</thead>
<tbody>
<?php foreach ($detalle as $i => $d): ?>
<tr>
    <td><?= htmlspecialchars($d['nombre']) ?></td>
    <td><?= $d['cantidad'].' '.$d['unidad_medida'] ?></td>
    <td>
        <input type="hidden"
               name="items[<?= $i ?>][materia_prima_id]"
               value="<?= $d['materia_prima_id'] ?>">

        <input type="number" step="0.001" min="0"
               name="items[<?= $i ?>][cantidad]"
               class="form-control"
               required>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button class="btn btn-success w-100">
    Confirmar Ingreso
</button>

</form>