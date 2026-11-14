<h5>Producto: <?= htmlspecialchars($receta['producto']) ?></h5>
<p><strong>Procedimiento / observaciones:</strong></p>
<p><?= nl2br(htmlspecialchars($receta['proceso_fabrica'] ?? '')) ?></p>

<?php $procesos = $receta['procesos'] ?? []; ?>
<h6 class="mt-3">Procesos de fabricación</h6>
<div class="table-scroll">
    <table class="table table-sm table-bordered">
        <thead class="table-light">
            <tr>
                <th width="40">#</th>
                <th>Proceso</th>
                <th class="text-end">Horas por pieza</th>
                <th class="text-end">Costo fijo</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($procesos)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sin procesos cargados.</td></tr>
            <?php else: ?>
                <?php foreach ($procesos as $i => $proceso): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($proceso['nombre']) ?></td>
                    <td class="text-end"><?= number_format((float)$proceso['horas_por_unidad'], 4, ',', '.') ?> h</td>
                    <td class="text-end">$ <?= number_format((float)($proceso['costo'] ?? 0), 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="3" class="text-end"><strong>Total procesos:</strong></td>
                <td class="text-end">
                    <strong>$ <?php
                        $costoProcesosModal = 0;
                        foreach ($procesos as $p) { $costoProcesosModal += (float)($p['costo'] ?? 0); }
                        echo number_format($costoProcesosModal, 2, ',', '.');
                    ?></strong>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="table-scroll mt-3">
    <table class="table table-sm table-bordered mt-3">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receta['detalle'] as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= number_format($d['cantidad'], 3) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php if (!empty($rec_productos)): ?>
<h6 class="mt-3">Productos componentes</h6>
<div class="table-scroll">
    <table class="table table-sm table-bordered">
        <thead class="table-light">
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rec_productos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= number_format((float)$p['cantidad'], 3, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>