<h1>Remitos de Salida</h1>
<div class="table-scroll mt-3">
    <table class="table table-striped table-hover mt-3">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Nota de Pedido</th>
                <th>Cliente</th>
                <th>Usuario</th>
                <th width="120"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($remitos)): ?>
            <tr>
                <td colspan="6" class="text-center text-muted">
                    No hay remitos registrados
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($remitos as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td>#<?= $r['nota_pedido_id'] ?></td>
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