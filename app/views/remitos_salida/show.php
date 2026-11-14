<?php
$esManual = empty($remito['idNpRem']);
?>

<h3><i class="bi bi-truck"></i> Remito de Salida #<?= $remito['NumRem'] ?></h3>

<div class="mb-3">
    <?php if ($esManual): ?>
        <span class="badge bg-info">REMITO MANUAL</span>
    <?php else: ?>
        <span class="badge bg-primary">REMITO CON NP</span>
        <a href="<?= BASE_URL ?>/notaspedido/show/<?= $remito['idNpRem'] ?>" class="btn btn-sm btn-secondary ms-2">
            <i class="bi bi-file-text"></i> Ver NP #<?= $remito['idNpRem'] ?>
        </a>
    <?php endif; ?>
    <?php if (empty($remito['entregado'])): ?>
        <form method="post" action="<?= BASE_URL ?>/remitossalida/confirmarEntrega/<?= (int)$remito['NumRem'] ?>"
              class="d-inline ms-2 js-entrega" data-accion="confirmar" data-remito="<?= (int)$remito['NumRem'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="volver" value="show">
            <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-check2-circle"></i> Confirmar entrega
            </button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/remitossalida/revertirEntrega/<?= (int)$remito['NumRem'] ?>"
              class="d-inline ms-2 js-entrega" data-accion="revertir" data-remito="<?= (int)$remito['NumRem'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="volver" value="show">
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Revertir la entrega confirmada">
                <i class="bi bi-arrow-counterclockwise"></i> Revertir entrega
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Columna izquierda: Datos del cliente -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-person"></i> Datos del Cliente</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:130px"><strong>Razón Social:</strong></td>
                        <td><?= htmlspecialchars($remito['RazonSocial']) ?></td>
                    </tr>
                    <?php if (!empty($remito['cuit'])): ?>
                    <tr>
                        <td class="text-muted"><strong>CUIT:</strong></td>
                        <td><?= htmlspecialchars($remito['cuit']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($remito['direccion'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Dirección:</strong></td>
                        <td><?= htmlspecialchars($remito['direccion']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($remito['localidad'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Localidad:</strong></td>
                        <td><?= htmlspecialchars($remito['localidad']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($remito['email'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Email:</strong></td>
                        <td><a href="mailto:<?= htmlspecialchars($remito['email']) ?>"><?= htmlspecialchars($remito['email']) ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($remito['telefono'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Teléfono:</strong></td>
                        <td><?= htmlspecialchars($remito['telefono']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Columna derecha: Datos del remito -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Datos del Remito</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:130px"><strong>Número:</strong></td>
                        <td>#<?= $remito['NumRem'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Fecha:</strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($remito['fecha'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Usuario:</strong></td>
                        <td><?= htmlspecialchars($remito['UserRem']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Entrega:</strong></td>
                        <td>
                            <?php if (!empty($remito['entregado'])): ?>
                                <span class="badge bg-success">ENTREGADO</span>
                                <small class="text-muted">
                                    <?= $remito['entregado_at'] ? date('d/m/Y H:i', strtotime($remito['entregado_at'])) : '' ?>
                                    <?= !empty($remito['EntregadoPor']) ? ' · ' . htmlspecialchars($remito['EntregadoPor']) : '' ?>
                                </small>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">PENDIENTE DE ENTREGA</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($remito['obsRemRem'])): ?>
<div class="alert alert-info">
    <strong><i class="bi bi-chat-left-text"></i> Observaciones:</strong><br>
    <?= nl2br(htmlspecialchars($remito['obsRemRem'])) ?>
</div>
<?php endif; ?>

<?php $adjModulo = 'REMITO_SALIDA'; $adjDocId = (int)$remito['NumRem'];
      require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

<?php $cpModulo = 'REMITO_SALIDA'; $cpDocId = (int)$remito['NumRem'];
      require BASE_PATH . '/app/views/campos_personalizados/panel.php'; ?>

<!-- Detalle de items -->
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-box"></i> Detalle de Mercadería</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th width="40">#</th>
                    <th>Producto / Concepto</th>
                    <th class="text-end">Precio U.</th>
                    <th width="120">Cantidad</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalGeneral = 0;
            $i = 1;
            foreach ($remito['detalle'] as $item):
                $precio = (float)($item['precioUnitario'] ?? 0);
                $cant = (float)$item['CantRem'];
                $subtotal = $precio * $cant;
                $totalGeneral += $subtotal;
            ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($item['ProdRem']) ?></td>
                    <td class="text-end">$ <?= number_format($precio, 2, ',', '.') ?></td>
                    <td><?= number_format($cant, 2) ?></td>
                    <td class="text-end fw-bold">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="4" class="text-end">TOTAL:</td>
                    <td class="text-end">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Acciones -->
<div class="d-flex gap-2 mt-3">
    <a href="<?= BASE_URL ?>/remitossalida" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <a href="<?= BASE_URL ?>/remitossalida/pdf/<?= $remito['NumRem'] ?>" target="_blank"
       class="btn btn-outline-danger">
       <i class="bi bi-file-pdf"></i> Descargar PDF
    </a>
    <a href="<?= BASE_URL ?>/remitossalida/regenerar-pdf/<?= $remito['NumRem'] ?>"
       class="btn btn-outline-warning"
       onclick="return confirm('¿Regenerar el PDF del remito? Se sobreescribirá el actual.')">
       <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
    </a>
    <a href="<?= BASE_URL ?>/remitossalida/reenviar/<?= $remito['NumRem'] ?>"
       class="btn btn-outline-primary"
       onclick="return confirm('¿Reenviar remito por email al cliente?')">
       <i class="bi bi-envelope"></i> Reenviar por Email
    </a>
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
