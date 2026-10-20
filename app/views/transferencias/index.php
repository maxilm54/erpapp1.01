<h3><?= $title ?></h3>

<a href="<?= BASE_URL ?>/transferencias/create" class="btn btn-primary mb-3">Nueva Transferencia</a>

<div class="table-scroll">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Depósito Origen</th>
                <th>Depósito Destino</th>
                <th>Estado</th>
                <th>Usuario</th>
                <th>Fecha</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transferencias)): ?>
            <tr><td colspan="7" class="text-center">No hay transferencias registradas</td></tr>
            <?php else: ?>
            <?php foreach ($transferencias as $t): ?>
            <tr>
                <td><?= $t['numero'] ?></td>
                <td><?= htmlspecialchars($t['deposito_origen']) ?></td>
                <td><?= htmlspecialchars($t['deposito_destino']) ?></td>
                <td>
                    <?php if ($t['estado'] === 'EN_TRANSITO'): ?>
                        <span class="badge bg-warning text-dark">EN TRÁNSITO</span>
                    <?php elseif ($t['estado'] === 'COMPLETADA'): ?>
                        <span class="badge bg-success">COMPLETADA</span>
                    <?php else: ?>
                        <span class="badge bg-danger">ANULADA</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($t['usuario']) ?></td>
                <td><?= $t['created_at'] ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/transferencias/show/<?= $t['id'] ?>" class="btn btn-sm btn-info">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>