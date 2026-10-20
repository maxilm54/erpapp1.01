<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-calendar-x"></i> Lotes - Vencimientos</h3>
    <form method="GET" action="<?= BASE_URL ?>/lotes" class="d-flex gap-2">
        <label class="form-label mb-0 align-self-center">Próximos a vencer (días):</label>
        <input type="number" name="dias" class="form-control form-control-sm" style="width:100px" value="<?= (int)$dias ?>" min="1" max="365">
        <button class="btn btn-sm btn-primary">Filtrar</button>
    </form>
</div>

<?php if (!empty($vencidos)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <strong>¡Hay <?= count($vencidos) ?> lote(s) vencidos con stock!</strong> Revisar y gestionar descarte o devolución.
</div>
<?php endif; ?>
<?php if (!empty($proximos)): ?>
<div class="alert alert-warning">
    <i class="bi bi-clock"></i> <strong><?= count($proximos) ?> lote(s)</strong> vencen dentro de los próximos <?= (int)$dias ?> días.
</div>
<?php endif; ?>

<!-- LOTES VENCIDOS -->
<h5 class="mt-4">Lotes Vencidos (con stock)</h5>
<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Tipo</th>
                <th>Producto / M.Prima</th>
                <th>Código Lote</th>
                <th>N° Serie</th>
                <th class="text-end">Stock</th>
                <th>Vencimiento</th>
                <th>Días Vencido</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vencidos)): ?>
            <tr><td colspan="8" class="text-center text-success">No hay lotes vencidos con stock. ✓</td></tr>
            <?php else: ?>
            <?php foreach ($vencidos as $l):
                $diasVencido = (int)((strtotime('now') - strtotime($l['fecha_vencimiento'])) / 86400);
                $esProducto = !empty($l['producto_id']);
                $nombre = $esProducto ? $l['producto_nombre'] : $l['mp_nombre'];
                $link = $esProducto ? BASE_URL . '/productos/show/' . $l['producto_id'] : BASE_URL . '/materiasprimas/show/' . $l['materia_prima_id'];
            ?>
            <tr class="table-danger">
                <td>
                    <span class="badge bg-<?= $esProducto ? 'info' : 'warning' ?>"><?= $esProducto ? 'Producto' : 'M.Prima' ?></span>
                </td>
                <td><a href="<?= $link ?>"><?= htmlspecialchars($nombre ?? '(sin nombre)') ?></a></td>
                <td><?= htmlspecialchars($l['codigo_lote']) ?></td>
                <td><?= htmlspecialchars($l['numero_serie'] ?? '') ?></td>
                <td class="text-end"><?= number_format($l['stock_actual'], 3, ',', '.') ?></td>
                <td><?= date('d/m/Y', strtotime($l['fecha_vencimiento'])) ?></td>
                <td><span class="badge bg-danger"><?= $diasVencido ?> días</span></td>
                <td><a href="<?= $link ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-eye"></i> Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- LOTES PRÓXIMOS A VENCER -->
<h5 class="mt-4">Lotes Próximos a Vencer (<?= (int)$dias ?> días)</h5>
<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Tipo</th>
                <th>Producto / M.Prima</th>
                <th>Código Lote</th>
                <th>N° Serie</th>
                <th class="text-end">Stock</th>
                <th>Vencimiento</th>
                <th>Faltan</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($proximos)): ?>
            <tr><td colspan="8" class="text-center text-success">No hay lotes próximos a vencer. ✓</td></tr>
            <?php else: ?>
            <?php foreach ($proximos as $l):
                $faltan = (int)((strtotime($l['fecha_vencimiento']) - strtotime('now')) / 86400) + 1;
                $esProducto = !empty($l['producto_id']);
                $nombre = $esProducto ? $l['producto_nombre'] : $l['mp_nombre'];
                $link = $esProducto ? BASE_URL . '/productos/show/' . $l['producto_id'] : BASE_URL . '/materiasprimas/show/' . $l['materia_prima_id'];
            ?>
            <tr class="table-warning">
                <td>
                    <span class="badge bg-<?= $esProducto ? 'info' : 'warning' ?>"><?= $esProducto ? 'Producto' : 'M.Prima' ?></span>
                </td>
                <td><a href="<?= $link ?>"><?= htmlspecialchars($nombre ?? '(sin nombre)') ?></a></td>
                <td><?= htmlspecialchars($l['codigo_lote']) ?></td>
                <td><?= htmlspecialchars($l['numero_serie'] ?? '') ?></td>
                <td class="text-end"><?= number_format($l['stock_actual'], 3, ',', '.') ?></td>
                <td><?= date('d/m/Y', strtotime($l['fecha_vencimiento'])) ?></td>
                <td><span class="badge bg-warning text-dark"><?= $faltan ?> días</span></td>
                <td><a href="<?= $link ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-eye"></i> Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>