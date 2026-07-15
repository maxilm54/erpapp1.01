<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-truck"></i> Remitos de Salida</h3>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/remitossalida/create-manual" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Remito Manual
        </a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover mt-3">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>NP</th>
                <th>Cliente</th>
                <th>Usuario</th>
                <th width="120"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($remitos)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">
                    No hay remitos registrados
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($remitos as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td>
                    <span class="badge <?= ($r['tipo'] ?? 'NP') === 'MANUAL' ? 'bg-info' : 'bg-primary' ?>">
                        <?= ($r['tipo'] ?? 'NP') === 'MANUAL' ? 'MANUAL' : 'NP' ?>
                    </span>
                </td>
                <td>
                    <?php if ($r['nota_pedido_id']): ?>
                        <a href="<?= BASE_URL ?>/notaspedido/show/<?= $r['nota_pedido_id'] ?>">#<?= $r['nota_pedido_id'] ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['cliente']) ?></td>
                <td><?= htmlspecialchars($r['usuario']) ?></td>
                <td class="text-center">
                    <a href="<?= BASE_URL ?>/remitossalida/show/<?= $r['id'] ?>"
                    class="btn btn-sm btn-primary">
                        Ver
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
