<h1>Notas de Pedido</h1>

<a href="<?= BASE_URL ?>/notaspedido/create" class="btn btn-success mb-3">Nueva NP</a>
<div class="table-scroll mt-3">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Entrega</th>
                <th>Productos / Cantidad</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notas as $n): ?>
            <?php
                $itemsCount = (int)($n['items_count'] ?? 0);
                $totalPedida = (float)($n['total_pedida'] ?? 0);
                $totalRemitida = (float)($n['total_remitida'] ?? 0);
                $remitidoCompleto = ($n['remitido'] ?? '') === 'RemitidoCompleto';
                $puedeRemitir = $n['estado'] === 'APROBADA' && !$remitidoCompleto;
                $badgeEstado = $n['estado'] === 'APROBADA'
                    ? 'primary'
                    : ($n['estado'] === 'ANULADA' ? 'danger' : 'secondary');
            ?>
            <tr>
                <td><?= (int)$n['id'] ?></td>
                <td><?= htmlspecialchars($n['razon_social']) ?></td>
                <td>
                    <span class="badge bg-<?= $badgeEstado ?>"><?= htmlspecialchars($n['estado']) ?></span>
                    <?php if (!empty($n['produccion_pendiente'])): ?>
                        <span class="badge bg-warning text-dark">Producción</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($n['created_at'])) ?></td>
                <td><?= !empty($n['fecha_entrega']) ? date('d/m/Y', strtotime($n['fecha_entrega'])) : '&mdash;' ?></td>
                <td>
                    <?php if ($itemsCount === 1): ?>
                        <strong><?= htmlspecialchars($n['item_descripcion'] ?? '—') ?></strong><br>
                    <?php elseif ($itemsCount === 0): ?>
                        <span class="text-muted small">Sin items</span><br>
                    <?php endif; ?>

                    <span class="small <?= $remitidoCompleto ? 'text-success fw-bold' : '' ?>">
                        Pedida: <?= number_format($totalPedida, 3, ',', '.') ?>
                        &mdash;
                        Remitida: <?= number_format($totalRemitida, 3, ',', '.') ?>
                    </span>

                    <?php if ($remitidoCompleto): ?>
                        <span class="text-success fw-bold ms-1">Remitido Completo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$n['id'] ?>" class="btn btn-sm btn-primary">Ver NP</a>
                    <?php if ($puedeRemitir): ?>
                        <button class="btn btn-primary btn-sm"
                            onclick="confirmarRemito(<?= (int)$n['id'] ?>)">
                            Remitir
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<script>
function confirmarRemito(id) {
    Swal.fire({
        title: 'Generar Remito',
        text: 'Se descontará stock de los productos',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= BASE_URL ?>/remitossalida/create/' + id;
        }
    });
}
</script>