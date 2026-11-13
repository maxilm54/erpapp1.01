<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= htmlspecialchars($materiaprima['nombre']) ?></h3>
    <a href="<?= BASE_URL ?>/materiasprimas" class="btn btn-secondary btn-sm">Volver</a>
</div>

<?php if (!empty($vencidos)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <strong>¡Lotes vencidos con stock!</strong> Hay <?= count($vencidos) ?> lote(s) vencido(s) que aún tienen stock.
</div>
<?php endif; ?>
<?php if (!empty($proximos)): ?>
<div class="alert alert-warning">
    <i class="bi bi-clock"></i> <strong>Próximos a vencer</strong> <?= count($proximos) ?> lote(s) vencen dentro de los próximos 30 días.
</div>
<?php endif; ?>

<?php
$tipoGestion = $materiaprima['tipo_gestion'] ?? 'UNIDAD';
$badgesTipo = [
    'UNIDAD'     => 'bg-secondary',
    'LINEAL'     => 'bg-info text-dark',
    'SUPERFICIE' => 'bg-warning text-dark',
    'GRANEL'     => 'bg-success',
];
$claseTipo = $badgesTipo[$tipoGestion] ?? 'bg-secondary';

$camposFicha = ['parametro_texto_1', 'parametro_numerico_1', 'parametro_numerico_2', 'presentacion', 'obs'];
$tieneFicha = false;
foreach ($camposFicha as $campo) {
    if (isset($materiaprima[$campo]) && trim((string)$materiaprima[$campo]) !== '') {
        $tieneFicha = true;
        break;
    }
}
$fmtNum = static function ($valor, int $decimales) {
    if ($valor === null || $valor === '') {
        return '—';
    }
    $texto = number_format((float)$valor, $decimales, ',', '.');
    return rtrim(rtrim($texto, '0'), ',');
};
?>

<div class="card p-3 mb-3">
    <div class="row">
        <div class="col-md-3"><strong>SKU:</strong> <?= htmlspecialchars($materiaprima['sku'] ?? '') ?></div>
        <div class="col-md-3"><strong>Unidad Medida:</strong> <?= htmlspecialchars($materiaprima['nombre_unidad'] ?? '') ?></div>
        <div class="col-md-3">
            <strong>Stock Total:</strong> <?= number_format($stock, 3, ',', '.') ?> <?= htmlspecialchars($materiaprima['nombre_unidad'] ?? '') ?>
            <?php if (!empty($materiaprima['presentacion']) && $stock > 0): ?>
                <br><small class="text-muted" title="Equivalencia según la presentación; no son unidades físicas contadas">
                    <?= htmlspecialchars(MateriaPrima::textoDesglosePresentacion((float)$stock, (float)$materiaprima['presentacion'], (string)($materiaprima['nombre_unidad'] ?? ''))) ?> (equiv.)
                </small>
            <?php endif; ?>
        </div>
        <div class="col-md-3"><strong>Categoría:</strong> <?= htmlspecialchars($materiaprima['categoria_nombre'] ?? '') ?></div>
    </div>
    <div class="row mt-2">
        <div class="col-md-4">
            <strong>Tipo de gestión:</strong>
            <span class="badge <?= $claseTipo ?> fs-6"><?= htmlspecialchars($tiposGestion[$tipoGestion] ?? $tipoGestion) ?></span>
        </div>
        <div class="col-md-4">
            <strong>Unidad de compra:</strong>
            <?php if (!empty($materiaprima['id_unidadcompra']) && (int)$materiaprima['id_unidadcompra'] !== (int)$materiaprima['id_unidadmedida']): ?>
                <?= htmlspecialchars($materiaprima['nombre_unidad_compra'] ?? '') ?>
                <br><small class="text-muted">
                    1 <?= htmlspecialchars($materiaprima['nombre_unidad_compra'] ?? '') ?> =
                    <?= $fmtNum($materiaprima['factor_conversion'] ?? null, 6) ?>
                    <?= htmlspecialchars($materiaprima['nombre_unidad'] ?? '') ?>
                    · 1 <?= htmlspecialchars($materiaprima['nombre_unidad'] ?? '') ?> =
                    <?= $fmtNum(1 / max((float)($materiaprima['factor_conversion'] ?? 1), 0.000001), 6) ?>
                    <?= htmlspecialchars($materiaprima['nombre_unidad_compra'] ?? '') ?>
                </small>
            <?php else: ?>
                <span class="text-muted">Igual a la unidad de medida</span>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <?php if (!empty($materiaprima['usa_serie'])): ?>
                <span class="badge bg-danger fs-6"><i class="bi bi-upc-scan"></i> Controla N° de Serie</span>
            <?php else: ?>
                <span class="badge bg-secondary fs-6">Sin control de serie</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-2">Ficha técnica</h5>
        <a href="<?= BASE_URL ?>/materiasprimas/update/<?= (int)$materiaprima['id'] ?>" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-pencil-square"></i> Editar
        </a>
    </div>
    <?php if (!$tieneFicha): ?>
        <p class="text-muted mb-0">Sin especificaciones cargadas.</p>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4 mb-2">
                <strong>Parámetro texto 1:</strong><br>
                <?= htmlspecialchars($materiaprima['parametro_texto_1'] ?? '—') ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Parámetro numérico 1:</strong><br>
                <?= $fmtNum($materiaprima['parametro_numerico_1'] ?? null, 4) ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Parámetro numérico 2:</strong><br>
                <?= $fmtNum($materiaprima['parametro_numerico_2'] ?? null, 4) ?>
            </div>
            <div class="col-md-4 mb-2">
                <strong>Presentación:</strong><br>
                <?= $fmtNum($materiaprima['presentacion'] ?? null, 2) ?>
                <?= htmlspecialchars($materiaprima['nombre_unidad'] ?? '') ?>
                <?php if (!empty($materiaprima['presentacion'])): ?>
                    <small class="text-muted">por presentación</small>
                <?php endif; ?>
            </div>
            <div class="col-md-8 mb-2">
                <strong>Observaciones:</strong><br>
                <?= htmlspecialchars($materiaprima['obs'] ?? '—') ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<h5>Lotes / Series</h5>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>Código Lote</th>
            <th>N° Serie</th>
            <th class="text-end">Stock</th>
            <th>Vencimiento</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($lotes)): ?>
        <tr><td colspan="5" class="text-center text-muted">No hay lotes registrados para esta materia prima</td></tr>
        <?php else: ?>
        <?php foreach ($lotes as $l): ?>
            <?php
                $proximo = $l['fecha_vencimiento'] && $l['fecha_vencimiento'] >= date('Y-m-d') && $l['fecha_vencimiento'] <= date('Y-m-d', strtotime('+30 days'));
            ?>
            <tr class="<?= $l['fecha_vencimiento'] && $l['fecha_vencimiento'] < date('Y-m-d') ? 'table-danger' : ($proximo ? 'table-warning' : '') ?>">
                <td><?= htmlspecialchars($l['codigo_lote']) ?></td>
                <td><?= htmlspecialchars($l['numero_serie'] ?? '') ?></td>
                <td class="text-end"><?= number_format($l['stock_actual'] ?? 0, 3, ',', '.') ?></td>
                <td><?= $l['fecha_vencimiento'] ? date('d/m/Y', strtotime($l['fecha_vencimiento'])) : '—' ?></td>
                <td>
                    <?php if ($l['fecha_vencimiento'] && $l['fecha_vencimiento'] < date('Y-m-d')): ?>
                        <span class="badge bg-danger">VENCIDO</span>
                    <?php elseif ($proximo): ?>
                        <span class="badge bg-warning text-dark">PRÓXIMO</span>
                    <?php else: ?>
                        <span class="badge bg-success">VIGENTE</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($materiaprima['usa_serie'])): ?>
<h5 class="mt-4">Unidades con N° de Serie (<?= count($series) ?>)</h5>
<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>N° Serie</th>
            <th>Lote</th>
            <th>Depósito</th>
            <th>Estado</th>
            <th>Ingreso</th>
            <th>Remito</th>
            <th>Registrado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($series)): ?>
        <tr><td colspan="7" class="text-center text-muted">No hay unidades con serie registradas para esta materia prima</td></tr>
        <?php else: ?>
        <?php foreach ($series as $s): ?>
            <?php
                $estados = [
                    'DISPONIBLE'    => ['bg-success', 'DISPONIBLE'],
                    'REMITIDO'      => ['bg-primary', 'REMITIDO'],
                    'EN_DEVOLUCION' => ['bg-warning text-dark', 'EN DEVOLUCIÓN'],
                    'BAJA'          => ['bg-danger', 'BAJA'],
                ];
                $est = $estados[$s['estado']] ?? ['bg-secondary', $s['estado']];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['numero_serie']) ?></strong></td>
                <td><?= htmlspecialchars($s['codigo_lote'] ?? '—') ?></td>
                <td><?= htmlspecialchars($s['deposito_nombre'] ?? '—') ?></td>
                <td><span class="badge <?= $est[0] ?>"><?= $est[1] ?></span></td>
                <td><?= $s['ingreso_id'] ? '#' . $s['ingreso_id'] : '—' ?></td>
                <td><?= $s['remito_salida_id'] ? '#' . $s['remito_salida_id'] : '—' ?></td>
                <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<p class="text-muted small">Creado: <?= $materiaprima['created_at'] ?? '' ?></p>