<?php $cobros = $cobros ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-cash-stack"></i> Cobros</h3>
    <a href="<?= BASE_URL ?>/cobros/create" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> Nuevo Cobro
    </a>
</div>

<?php if (empty($cobros)): ?>
    <div class="alert alert-info">No hay cobros registrados.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Remito</th>
                <th class="text-end">Monto</th>
                <th>Medio Pago</th>
                <th>Caja/Banco</th>
                <th>Estado</th>
                <th width="100"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cobros as $c):
            $anulado = !empty($c['anulado']);
        ?>
            <tr class="<?= $anulado ? 'table-secondary' : '' ?>">
                <td><?= $c['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                <td><?= htmlspecialchars($c['nombre_cliente'] ?? 'N/D') ?></td>
                <td>
                    <?php if (!empty($c['remito_id'])): ?>
                        <a href="<?= BASE_URL ?>/remito-show/<?= $c['remito_id'] ?>" class="text-decoration-none">
                            #<?= $c['remito_id'] ?>
                        </a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-end fw-bold <?= $anulado ? 'text-decoration-line-through' : '' ?>">
                    $ <?= number_format((float)$c['monto'], 2, ',', '.') ?>
                </td>
                <td><?= htmlspecialchars($c['medio_pago'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['caja_nombre'] ?? '-') ?></td>
                <td>
                    <?php if ($anulado): ?>
                        <span class="badge bg-danger">ANULADO</span>
                    <?php else: ?>
                        <span class="badge bg-success">ACTIVO</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/cobros/show/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
