<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-bank"></i> Creditos Bancarios</h3>
    <a href="<?= BASE_URL ?>/creditos/create" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> Nuevo Credito
    </a>
</div>

<div class="table-responsive">
<table class="table table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Entidad</th>
            <th>Monto Original</th>
            <th>Saldo</th>
            <th>Cuotas</th>
            <th>Cuota Mensual</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($creditos)): ?>
        <tr><td colspan="8" class="text-center text-muted">No hay creditos registrados.</td></tr>
        <?php else: ?>
        <?php foreach ($creditos as $c): ?>
        <tr>
            <td><?= $c['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars($c['entidad']) ?></strong>
                <br><small class="text-muted"><?= htmlspecialchars($c['caja_nombre'] ?? '') ?></small>
            </td>
            <td>$ <?= number_format($c['monto_original'], 2, ',', '.') ?></td>
            <td>$ <?= number_format($c['saldo_actual'], 2, ',', '.') ?></td>
            <td><?= $c['cuotas_pagadas'] ?? 0 ?> / <?= $c['cantidad_cuotas'] ?></td>
            <td>$ <?= number_format($c['monto_cuota'], 2, ',', '.') ?></td>
            <td>
                <?php if ($c['estado'] === 'ACTIVO'): ?>
                    <span class="badge bg-success">Activo</span>
                <?php elseif ($c['estado'] === 'PAGADO'): ?>
                    <span class="badge bg-primary">Pagado</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Cancelado</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/creditos/show/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
