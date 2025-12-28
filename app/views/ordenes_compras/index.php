<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= $title ?></h3>
    <a href="<?= BASE_URL ?>/ordenescompra/create"
       class="btn btn-primary">
        Nueva Orden de Compra
    </a>
</div>

<table class="table table-striped table-hover">
<thead class="table-dark">
<tr>
    <th>#</th>
    <th>Proveedor</th>
    <th>Fecha</th>
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
    <td>
        <?php if ($oc['estado'] === 'PENDIENTE'): ?>
            <a href="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $oc['id'] ?>"
               class="btn btn-sm btn-success">
               Aprobar
            </a>
        <?php endif; ?>

        <?php if ($oc['estado'] === 'APROBADA'): ?>
            <a href="<?= BASE_URL ?>/ingresosmercaderia/create/<?= $oc['id'] ?>"
               class="btn btn-sm btn-primary">
               Ingresar mercadería
            </a>
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

        <a href="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $oc['id'] ?>"
           class="btn btn-sm btn-success">
           Aprobar
        </a>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>