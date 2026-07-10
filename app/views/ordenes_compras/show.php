<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Orden de Compra #<?= $orden['id'] ?></h3>

    <span class="badge bg-<?= 
        $orden['estado'] === 'PENDIENTE' ? 'warning' :
        ($orden['estado'] === 'APROBADA' ? 'secondary' : ($orden['estado'] === 'ANULADA' ? 'danger' : 'success'))
    ?>">
        <?= $orden['estado'] ?>
    </span>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p><strong>Proveedor:</strong> <?= htmlspecialchars($orden['razon_social']) ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($orden['created_at'])) ?></p>
        <?php if (!empty($orden['observaciones'])): ?>
            <p><strong>Observaciones:</strong></p>
            <p class="mb-0"><?= nl2br(htmlspecialchars($orden['observaciones'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<h5>Detalle</h5>
<div class="table-scroll mt-3">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Tipo</th>
                <th>Nombre</th>
                <th>Pedida</th>
                <th>Recibida</th>
                <th>Faltante</th>
                <th>Unidad</th>
                <th>Precio Unitario</th>
                <th>Subtotal</th>
                <th>Moneda</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orden['detalle'] as $d): ?>
        <?php 
            $tipoLabel = ($d['tipo'] ?? 'materia_prima') === 'producto' ? 'Producto' : 'Materia Prima';
            $tipoClass = ($d['tipo'] ?? 'materia_prima') === 'producto' ? 'info' : 'warning';
        ?>
        <tr>
            <td><span class="badge bg-<?= $tipoClass ?>"><?= $tipoLabel ?></span></td>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><?= number_format($d['pedida'], 3,',','.') ?></td>
            <td><?= number_format($d['recibida'], 3,',','.') ?></td>
            <td><?= number_format($d['faltante'], 3,',','.') ?></td>
            <td><?= htmlspecialchars($d['nombre_medida'] ?? $d['id_unidadmedida'] ?? '') ?></td>
            <td><?= number_format($d['precio_unitario'], 3,',','.') ?></td>
            <?php
                $Subtotal = $d['recibida'] * $d['precio_unitario']; 
                $total+=$Subtotal;
            ?>
            <td><?= number_format($Subtotal, 3,',','.') ?></td>
            <td><?= htmlspecialchars($d['moneda']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="7" class="text-end">Total:</th>
            <th><?= number_format($total, 3,',','.') ?></th>
            <th></th>
        </tr>
        </tbody>
    </table>
</div>
<div class="mt-4 d-flex gap-2">
    <a href="<?= BASE_URL ?>/ordenescompra"
       class="btn btn-secondary">
       Volver
    </a>

    <?php if ($orden['estado'] === 'PENDIENTE'): ?>
        <a href="<?= BASE_URL ?>/ordenescompra/edit/<?= $orden['id'] ?>"
           class="btn btn-warning">
           Editar
        </a>

        <a href="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $orden['id'] ?>"
           class="btn btn-success"
           onclick="return confirm('¿Aprobar esta orden?');">
           Aprobar
        </a>
        <a href="<?= BASE_URL ?>/ordenescompra/anular/<?= $orden['id'] ?>"
           class="btn btn-danger"
           onclick="return confirm('¿Anular esta orden?');">
           Anular
        </a>
    <?php endif; ?>
</div>