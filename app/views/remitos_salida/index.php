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
                <th>Entrega</th>
                <th width="200"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($remitos)): ?>
            <tr>
                <td colspan="8" class="text-center text-muted">
                    No hay remitos registrados
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($remitos as $r): ?>
            <?php $entregado = !empty($r['entregado']); ?>
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
                <td class="text-nowrap">
                    <?php if ($entregado): ?>
                        <span class="badge bg-success" title="Entrega confirmada el <?= $r['entregado_at'] ? date('d/m/Y H:i', strtotime($r['entregado_at'])) : '-' ?>">ENTREGADO</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">PENDIENTE</span>
                    <?php endif; ?>
                </td>
                <td class="text-center text-nowrap">
                    <a href="<?= BASE_URL ?>/remitossalida/show/<?= $r['id'] ?>"
                    class="btn btn-sm btn-primary">
                        Ver
                    </a>
                    <?php if (!$entregado): ?>
                        <form method="post" action="<?= BASE_URL ?>/remitossalida/confirmarEntrega/<?= (int)$r['id'] ?>"
                              class="d-inline js-entrega" data-accion="confirmar" data-remito="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Confirmar entrega">
                                <i class="bi bi-check2-circle"></i> Entrega
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= BASE_URL ?>/remitossalida/revertirEntrega/<?= (int)$r['id'] ?>"
                              class="d-inline js-entrega" data-accion="revertir" data-remito="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revertir la entrega confirmada">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.js-entrega').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var revertir = form.dataset.accion === 'revertir';
        Swal.fire({
            icon: revertir ? 'warning' : 'question',
            title: revertir ? '¿Revertir la entrega?' : '¿Confirmar entrega?',
            text: revertir
                ? 'El remito #' + form.dataset.remito + ' volverá a quedar pendiente de entrega.'
                : 'Se marcará el remito #' + form.dataset.remito + ' como entregado.',
            showCancelButton: true,
            confirmButtonColor: revertir ? '#d33' : '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: revertir ? 'Sí, revertir entrega' : 'Sí, confirmar entrega',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
