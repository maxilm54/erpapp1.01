<h1>Nota de Pedido #<?= $np['id'] ?></h1>

<p><strong>Cliente:</strong> <?= $np['razon_social'] ?></p>
<p><strong>Estado:</strong> <?= $np['estado'] ?></p>
<?php if ($np['estado'] === 'ANULADA'): ?>
    <div class="alert alert-danger">
        <strong>Nota de Pedido ANULADA</strong><br>
        <strong>Motivo:</strong> <?= nl2br(htmlspecialchars($np['motivo_anulacion'])) ?><br>
        <small class="text-muted">
            Anulada el <?= date('d/m/Y H:i', strtotime($np['anulado_at'])) ?>
        </small>
    </div>
<?php endif; ?>
<?php if (!empty($np['presupuesto_id'])): ?>
    <div class="mb-3">
        <strong>Presupuesto asociado:</strong>
        <a href="<?= BASE_URL ?>/presupuestos/show/<?= $np['presupuesto_id'] ?>"
           class="btn btn-outline-primary btn-sm">
            Ver Presupuesto #<?= $np['presupuesto_id'] ?>
            (<?= date('d/m/Y', strtotime($np['presupuesto_fecha'])) ?>)
        </a>
    </div>
<?php else: ?>
    <div class="mb-3 text-muted">
        <strong>Presupuesto:</strong> Sin presupuesto asociado
    </div>
<?php endif; ?>

<?php if ($np['observaciones']): ?>
<p><strong>Observaciones:</strong> <?= nl2br($np['observaciones']) ?></p>
<?php endif ?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Precio</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $total = 0;
        foreach ($np['detalle'] as $d): 
            $sub = $d['cantidad'] * $d['precio'];
            $total += $sub;
        ?>
        <tr>
            <td><?= $d['nombre'] ?></td>
            <td><?= number_format($d['cantidad'], 3) ?></td>
            <td><?= number_format($d['precio'], 2) ?></td>
            <td><?= number_format($sub, 2) ?></td>
        </tr>
        <?php endforeach ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="text-end">Total</th>
            <th>$<?= number_format($total, 2) ?></th>
        </tr>
    </tfoot>
</table>

<?php if ($np['estado'] === 'BORRADOR'): ?>
    <button class="btn btn-success"
        onclick="confirmarAprobacionNP(<?= $np['id'] ?>)">
        Aprobar
    </button>
    <button class="btn btn-danger"
        onclick="confirmarAnulacionNP(<?= $np['id'] ?>)">
        Anular
    </button>
<?php endif ?>

<a href="<?= BASE_URL ?>/notaspedido" class="btn btn-secondary">Volver</a>
<script>
function confirmarAnulacionNP(id) {
    Swal.fire({
        title: '¿Anular Nota de Pedido?',
        text: 'La nota quedará anulada y no podrá utilizarse',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= BASE_URL ?>/notaspedido/anular/' + id;
        }
    });
}
</script>
<script>
function confirmarAprobacionNP(id) {
    Swal.fire({
        title: '¿Aprobar Nota de Pedido?',
        text: 'Una vez aprobada podrá remitirse',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= BASE_URL ?>/notaspedido/approve/' + id;
        }
    });
}
</script>