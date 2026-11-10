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

<?php $adjModulo = 'NOTA_PEDIDO'; $adjDocId = (int)$np['id'];
      require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

<?php $cpModulo = 'NOTA_PEDIDO'; $cpDocId = (int)$np['id'];
      $cpEditable = true;
      $cpEditAction = BASE_URL . '/notaspedido/guardar-campos/' . (int)$np['id'];
      $cpQuitarBase = BASE_URL . '/notaspedido/quitar-valor';
      require BASE_PATH . '/app/views/campos_personalizados/panel.php'; ?>

<div class="table-scroll mt-3">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th>Acciones</th>
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
                <td>
                    <?= htmlspecialchars($d['nombre']) ?>
                    <?php if (empty($d['producto_id'])): ?>
                        <span class="badge bg-info">Libre</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format($d['cantidad'], 3) ?></td>
                <td><?= number_format($d['precio'], 2) ?></td>
                <td><?= number_format($sub, 2) ?></td>
                <td>
                    <?php if (empty($d['producto_id'])): ?>
                        <form method="POST"
                              action="<?= BASE_URL ?>/notaspedido/agregar-producto/<?= $np['id'] ?>/<?= $d['id'] ?>"
                              class="d-inline"
                              onsubmit="return confirm('¿Agregar este producto a la base de productos? Después podrá editarlo en Productos.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Agregar a Productos
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/productos/update/<?= $d['producto_id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Editar producto
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-end">Total</th>
                <th>$<?= number_format($total, 2) ?></th>
            </tr>
        </tfoot>
    </table>
</div>

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
<button class="btn btn-primary"
        onclick="confirmarRemito(<?= $np['id'] ?>)">
        Remitir
    </button>

<a href="<?= BASE_URL ?>/notaspedido/pdf/<?= $np['id'] ?>" target="_blank"
   class="btn btn-outline-danger">
   <i class="bi bi-file-pdf"></i> Descargar PDF
</a>
<a href="<?= BASE_URL ?>/notaspedido/regenerar-pdf/<?= $np['id'] ?>"
   class="btn btn-outline-warning"
   onclick="return confirm('¿Regenerar el PDF de la nota de pedido? Se sobreescribirá el actual.')">
   <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
</a>
<a href="<?= BASE_URL ?>/notaspedido/reenviar/<?= $np['id'] ?>"
   class="btn btn-outline-primary"
   onclick="return confirm('¿Enviar nota de pedido por email al cliente?')">
   <i class="bi bi-envelope"></i> Enviar por Email
</a>

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