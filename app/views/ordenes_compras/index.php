<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= $title ?></h3>
    <a href="<?= BASE_URL ?>/ordenescompra/create"
       class="btn btn-primary">
        Nueva Orden de Compra
    </a>
</div>
<div class="table-scroll mt-3">
    <table class="table table-striped table-hover">
    <thead class="table-dark">
    <tr>
        <th>#</th>
        <th>Proveedor</th>
        <th>Fecha</th>
        <th>Entrega</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $oc): ?>
    <tr>
        <td><?= $oc['id'] ?></td>
        <td><?= htmlspecialchars($oc['razon_social']) ?></td>
        <td><?= date('d/m/Y', strtotime($oc['created_at'])) ?></td>
        <td><?= !empty($oc['fecha_entrega']) ? date('d/m/Y', strtotime($oc['fecha_entrega'])) : '&mdash;' ?></td>
        <td>
            <?php if ($oc['estado'] === 'PENDIENTE'): ?>
                <span class="badge bg-secondary">PENDIENTE</span>
            <?php endif; ?>

            <?php if (($oc['estado'] !== 'PENDIENTE' && $oc['estado'] !== 'RECIBIDA'&& $oc['estado'] !== 'ANULADA') || $oc['estado'] === 'PARCIAL'): ?>
                <a href="<?= BASE_URL ?>/ingresosmercaderia/create/<?= $oc['id'] ?>"
                class="btn btn-sm btn-primary">
                Ingresar mercadería
                </a>
            <?php endif; ?>

            <?php if (($oc['estado'] === 'RECIBIDA')): ?>
                <span class="badge bg-success">COMPLETA</span>
            <?php endif; ?>
            <?php if (($oc['estado'] === 'ANULADA')): ?>
                <span class="badge bg-danger">ANULADA</span>
            <?php endif; ?>
        </td>
        <td>
        <a href="<?= BASE_URL ?>/ordenescompra/show/<?= $oc['id'] ?>"
        class="btn btn-sm btn-info">
        Ver
        </a>

        <?php if ($oc['estado'] === 'PENDIENTE'): ?>
            <a href="<?= BASE_URL ?>/ordenescompra/edit/<?= $oc['id'] ?>"
            class="btn btn-sm btn-warning">
            Editar
            </a>

            <form method="POST" action="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $oc['id'] ?>" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <button type="submit" class="btn btn-sm btn-success"
                        onclick="return confirm('¿Aprobar esta orden?');">
                Aprobar
                </button>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/ordenescompra/anular/<?= $oc['id'] ?>" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('¿Anular esta orden?');">
                Anular
                </button>
            </form>
        <?php endif; ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
</div>