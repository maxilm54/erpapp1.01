<h1>Editar Receta - <?= $receta['id'] ?></h1>
<div class="container">
<form method="post" class="gap-2" id="formReceta">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
    <input type="hidden" name="receta_id" class="form-control" value="<?= $receta['id'] ?>">
    <input type="hidden" name="producto_id" class="form-control" value="<?= $receta['producto_id'] ?>">
    <label>Producto Final - <?= htmlspecialchars($receta['producto']) ?></label>
    <label>Nombre:</label>
    <input type="text" class="form-control mb-3" required name="nombre" value="<?= htmlspecialchars($receta['nombre']) ?>">
    <hr>

    <h5>Insumos</h5>
   
    <?php
        foreach($receta['detalle'] as $det){
            echo '<div class="row mb-2">
                    <div class="col-md-3">'.htmlspecialchars($det['nombre']).'</div>
                    <div class="col-md-3"><input step="0.001" class="form-control w-50" type="number" name="items['.(int)$det['materia_prima_id'].']" value="'.htmlspecialchars((string)$det['cantidad']).'"></div>
                </div>';
        }
    ?>

    <hr>
    <h5>Procesos de fabricación</h5>
    <p class="text-muted small">Mínimo 1 proceso. Las horas son estimadas por pieza en ese proceso.</p>
    <?php
        $procesos = $receta['procesos'] ?? [];
        if (empty($procesos)) {
            $procesos = [['nombre' => 'Proceso 1', 'horas_por_unidad' => 0]];
        }
    ?>
    <div class="table-scroll">
        <table class="table" id="tablaProcesos">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Nombre del proceso</th>
                    <th width="180">Horas por pieza</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($procesos as $i => $proceso): ?>
                <tr>
                    <td class="num-proceso"><?= $i + 1 ?></td>
                    <td><input type="text" name="procesos[<?= $i ?>][nombre]" class="form-control" maxlength="150" required value="<?= htmlspecialchars($proceso['nombre'] ?? '') ?>"></td>
                    <td><input type="number" name="procesos[<?= $i ?>][horas_por_unidad]" class="form-control" step="0.0001" min="0" required value="<?= htmlspecialchars((string)($proceso['horas_por_unidad'] ?? 0)) ?>"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProceso(this)">×</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-secondary mb-3" onclick="agregarProceso()">
        + Agregar Proceso
    </button>

    <label>Indique un procedimiento de fabricacion u observacion, en caso de ser necesario:</label>
    <textarea name="procedimiento" class="form-control" rows="4"><?= htmlspecialchars($receta['proceso_fabrica'] ?? '') ?></textarea>
    <br>
    <button class="btn btn-success">Guardar Receta</button>
    <a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">Cancelar</a>
</form>
</div>

<script>
function agregarProceso() {
    const tbody = document.querySelector('#tablaProcesos tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="num-proceso"></td>
        <td><input type="text" class="form-control" maxlength="150" required placeholder="Ej: Corte, Torneado, Control"></td>
        <td><input type="number" class="form-control" step="0.0001" min="0" value="0" required></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarProceso(this)">×</button></td>
    `;
    tbody.appendChild(tr);
    reindexarProcesos();
}

function eliminarProceso(btn) {
    const tbody = document.querySelector('#tablaProcesos tbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        alert('La receta debe tener al menos un proceso.');
        return;
    }
    btn.closest('tr').remove();
    reindexarProcesos();
}

function reindexarProcesos() {
    document.querySelectorAll('#tablaProcesos tbody tr').forEach(function (tr, i) {
        tr.querySelector('.num-proceso').textContent = i + 1;
        const nombre = tr.querySelector('input[type="text"]');
        const horas = tr.querySelector('input[type="number"]');
        if (nombre) nombre.name = 'procesos[' + i + '][nombre]';
        if (horas) horas.name = 'procesos[' + i + '][horas_por_unidad]';
    });
}
</script>
