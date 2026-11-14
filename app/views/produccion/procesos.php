<?php
$cantidadPedida = (float)$orden['cantidad'];
$totalEnProceso = 0.0;
$totalHoras = 0.0;
foreach ($procesos as $proceso) {
    $enProceso = (float)$proceso['cantidad_en_proceso'];
    $totalEnProceso += $enProceso;
    $totalHoras += $enProceso * (float)$proceso['horas_por_unidad'];
}
$pendiente = max(0, $cantidadPedida - $totalFinalizado - $totalEnProceso);
$cerrada = in_array($orden['estado'], ['FINALIZADA', 'CANCELADA'], true);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3"></i> Procesos de Producción #<?= (int)$orden['id'] ?></h3>
        <small class="text-muted"><?= htmlspecialchars($orden['producto']) ?> — Pedido: <?= number_format($cantidadPedida, 3, ',', '.') ?> piezas</small>
    </div>
    <div>
        <span class="badge bg-<?= $orden['estado'] === 'FINALIZADA' ? 'success' : ($orden['estado'] === 'CANCELADA' ? 'danger' : ($orden['estado'] === 'EN_PRODUCCION' ? 'primary' : 'warning')) ?> fs-6">
            <?= htmlspecialchars($orden['estado']) ?>
        </span>
        <a href="<?= BASE_URL ?>/ordenproduccion/show/<?= (int)$orden['id'] ?>" class="btn btn-secondary btn-sm ms-2">Volver</a>
    </div>
</div>

<div class="row text-center mb-3">
    <div class="col-md-3">
        <div class="card p-2">
            <small class="text-muted">Finalizadas</small>
            <strong class="fs-5 text-success"><?= number_format($totalFinalizado, 3, ',', '.') ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-2">
            <small class="text-muted">En procesos</small>
            <strong class="fs-5 text-primary"><?= number_format($totalEnProceso, 3, ',', '.') ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-2">
            <small class="text-muted">Pendientes de cargar</small>
            <strong class="fs-5 text-muted"><?= number_format($pendiente, 3, ',', '.') ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-2">
            <small class="text-muted">Horas estimadas en curso</small>
            <strong class="fs-5"><?= number_format($totalHoras, 2, ',', '.') ?> h</strong>
        </div>
    </div>
</div>

<?php if ($cerrada): ?>
    <div class="alert alert-info">
        La orden está <?= strtolower(htmlspecialchars($orden['estado'])) ?>. El tablero queda solo para consulta.
    </div>
<?php elseif ($orden['estado'] === 'PENDIENTE'): ?>
    <div class="alert alert-warning py-2">
        Las piezas están en el primer proceso. Al mover o finalizar la primera tanda, la OP pasa a <strong>EN_PRODUCCIÓN</strong>.
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th width="50">#</th>
                <th>Proceso</th>
                <th class="text-end">Horas por pieza</th>
                <th class="text-end">Piezas en proceso</th>
                <th class="text-end">Horas estimadas</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($procesos)): ?>
                <tr><td colspan="6" class="text-center text-muted">La OP no tiene procesos cargados.</td></tr>
            <?php endif; ?>
            <?php foreach ($procesos as $i => $proceso): ?>
                <?php
                    $esUltimo = ($i === count($procesos) - 1);
                    $enProceso = (float)$proceso['cantidad_en_proceso'];
                    $horasProceso = $enProceso * (float)$proceso['horas_por_unidad'];
                    $siguiente = $esUltimo ? null : $procesos[$i + 1];
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($proceso['nombre']) ?></strong>
                        <?php if ($esUltimo): ?>
                            <span class="badge bg-dark">Último</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= number_format((float)$proceso['horas_por_unidad'], 4, ',', '.') ?> h</td>
                    <td class="text-end"><strong><?= number_format($enProceso, 3, ',', '.') ?></strong></td>
                    <td class="text-end"><?= number_format($horasProceso, 2, ',', '.') ?> h</td>
                    <td>
                        <?php if (!$cerrada && $enProceso > 0.0005): ?>
                            <form method="POST"
                                  action="<?= BASE_URL ?>/ordenproduccion/<?= $esUltimo ? 'finalizar-proceso' : 'mover-proceso' ?>/<?= (int)$orden['id'] ?>"
                                  class="row g-2 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <input type="hidden" name="proceso_id" value="<?= (int)$proceso['id'] ?>">
                                <div class="col-auto">
                                    <input type="number" name="cantidad" class="form-control form-control-sm"
                                           style="width: 110px;" step="0.001" min="0.001"
                                           max="<?= number_format($enProceso, 3, '.', '') ?>"
                                           value="<?= number_format($enProceso, 3, '.', '') ?>" required>
                                </div>
                                <div class="col-auto">
                                    <input type="text" name="observaciones" class="form-control form-control-sm"
                                           style="width: 150px;" placeholder="Observaciones (opcional)">
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm <?= $esUltimo ? 'btn-success' : 'btn-primary' ?>">
                                        <?= $esUltimo ? 'Finalizar piezas' : 'Mover a ' . htmlspecialchars($siguiente['nombre']) ?>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h5 class="mt-4"><i class="bi bi-clock-history"></i> Historial de movimientos</h5>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead class="table-light">
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="text-end">Cantidad</th>
                <th>Usuario</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimientos)): ?>
                <tr><td colspan="7" class="text-center text-muted">Todavía no hay movimientos de procesos.</td></tr>
            <?php else: ?>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?= htmlspecialchars($mov['created_at'] ?? '') ?></td>
                        <td>
                            <?php if (($mov['tipo'] ?? '') === 'FINALIZAR'): ?>
                                <span class="badge bg-success">FINALIZAR</span>
                            <?php else: ?>
                                <span class="badge bg-primary">MOVER</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($mov['origen_nombre'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($mov['destino_nombre'] ?? 'Producto terminado') ?></td>
                        <td class="text-end"><?= number_format((float)$mov['cantidad'], 3, ',', '.') ?></td>
                        <td><?= htmlspecialchars($mov['usuario'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($mov['observaciones'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
