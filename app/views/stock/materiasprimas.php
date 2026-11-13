<h1>Stock de Materias Primas</h1>

<form method="GET" action="<?= BASE_URL ?>/stock/materiasprimas" class="row mb-3">
    <div class="col-md-4">
        <select name="deposito_id" class="form-select" onchange="this.form.submit()">
            <option value="">Todos los depósitos</option>
            <?php foreach ($depositos as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($deposito_id == $d['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nombre']) ?><?= !empty($d['es_sobrante']) ? ' (restos)' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php $esSobrante = !empty($deposito['es_sobrante']); ?>
<?php if ($esSobrante): ?>
<div class="alert alert-info py-2">
    En <strong>MP sobrante</strong> se muestran solo los metros/kilogramos de restos. No se calcula equivalencia en barras o rollos porque un resto no representa una presentación completa.
</div>
<?php endif; ?>

<div class="alert alert-light border py-2 small">
    La columna <strong>Equiv. stock total</strong> es solo una referencia: muestra cuántas presentaciones completas
    equivalen al stock total y cuánto queda. <strong>No es el sobrante de una OP.</strong>
    El sobrante real de una producción se ve en la columna <strong>Sobrante</strong> y en el depósito <strong>MP sobrante</strong>.
</div>

<div class="table-scroll mt-3">
    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Unidad</th>
                <th class="text-end">Stock Disponible</th>
                <th class="text-end">Stock Reservado</th>
                <th class="text-end">Total</th>
                <th class="text-end">Sobrante</th>
                <th>Equiv. stock total <small class="text-muted">(ref.)</small></th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stock as $mp):
                $stock_val=(float)$mp['stock'];
                $reserva=(float)($mp['stockreserva']??0);
                $total=$stock_val + $reserva;
                $sobrante=(float)($mp['stock_sobrante'] ?? 0);
                $badgeClass = match($mp['estado'] ?? 'normal') {
                    'critico' => 'bg-danger',
                    'bajo' => 'bg-warning text-dark',
                    'alto' => 'bg-primary',
                    default => 'bg-success'
                };
                $equivale = '';
                if (!$esSobrante && !empty($mp['presentacion']) && $stock_val > 0) {
                    $equivale = MateriaPrima::textoDesglosePresentacion($stock_val, (float)$mp['presentacion'], (string)($mp['unidad_medida'] ?? ''));
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($mp['nombre']) ?></td>
                <td><?= htmlspecialchars($mp['unidad_medida']) ?></td>
                <td class="text-end"><?= number_format($stock_val, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($reserva, 1,',', '.') ?></td>
                <td class="text-end"><?= number_format($total,1,',', '.') ?></td>
                <td class="text-end">
                    <?php if ($sobrante > 0): ?>
                        <span class="badge bg-info text-dark"><?= number_format($sobrante, 3, ',', '.') ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($equivale !== ''): ?>
                        <span title="Equivalencia del stock total según la presentación; no representa unidades físicas contadas ni el sobrante de una OP">
                            <?= htmlspecialchars($equivale) ?> <small class="text-muted">(equiv. total)</small>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge <?= $badgeClass ?>"><?= strtoupper($mp['estado'] ?? 'NORMAL') ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<hr>
<a href="<?= BASE_URL ?>/ajustesstock" class="btn btn-primary">Volver</a>