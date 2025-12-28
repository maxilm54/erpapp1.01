<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4">

<div class="mb-3">
    <label class="form-label">Proveedor</label>
    <select name="proveedor_id" class="form-select" required>
        <option value="">Seleccione proveedor</option>
        <?php foreach ($proveedores as $p): ?>
            <option value="<?= $p['id'] ?>"
                <?= isset($orden) && $orden['proveedor_id']==$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['razon_social']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<h5>Detalle</h5>

<table class="table">
<thead>
<tr>
    <th>Materia Prima</th>
    <th>Cantidad</th>
</tr>
</thead>
<tbody>
<?php 
    $items = $detalle ?? $materias_primas;
    foreach ($items as $i => $mp): 
?>
<tr>
    <td>
        <?= htmlspecialchars($mp['nombre']) ?>
        <input type="hidden"
               name="items[<?= $i ?>][materia_prima_id]"
               value="<?= $mp['materia_prima_id'] ?? $mp['id'] ?>">
    </td>
    <td>
        <input type="number" step="0.001" min="0"
               name="items[<?= $i ?>][cantidad]"
               class="form-control" value="<?= $mp['cantidad'] ?? '' ?>">
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button class="btn btn-primary w-100">
    Guardar Orden de Compra
</button>

</form>