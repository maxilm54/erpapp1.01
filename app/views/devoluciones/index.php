<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-arrow-return-left"></i> Devoluciones</h3>
    <a href="<?= BASE_URL ?>/devoluciones/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nueva Devolución
    </a>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover mt-3">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Remito</th>
                <th>Estado</th>
                <th>Usuario</th>
                <th width="120"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($devoluciones)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">
                    No hay devoluciones registradas
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($devoluciones as $d): ?>
            <tr>
                <td><?= $d['numero'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                <td><?= htmlspecialchars($d['nombre_cliente']) ?></td>
                <td>
                    <?php if ($d['remito_id']): ?>
                        <a href="<?= BASE_URL ?>/remitossalida/show/<?= $d['remito_id'] ?>">#<?= $d['remito_numero'] ?? $d['remito_id'] ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $badgeClass = match($d['estado']) {
                        'PENDIENTE' => 'bg-warning text-dark',
                        'PROCESADA' => 'bg-success',
                        'ANULADA'   => 'bg-danger',
                        default     => 'bg-secondary',
                    };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= $d['estado'] ?></span>
                </td>
                <td><?= htmlspecialchars($d['nombre_usuario']) ?></td>
                <td class="text-center">
                    <a href="<?= BASE_URL ?>/devoluciones/show/<?= $d['id'] ?>"
                       class="btn btn-sm btn-primary">
                        Ver
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
