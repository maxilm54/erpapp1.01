<?php
$tieneNpAsociada = !empty($presupuesto['nota_pedido_id']);
$fechaVencimiento = !empty($presupuesto['vencimiento'])
    ? date('d/m/Y', strtotime($presupuesto['vencimiento']))
    : '';
$vencido = !empty($presupuesto['vencimiento']) && $presupuesto['vencimiento'] < date('Y-m-d');
$aprobadoVencido = !empty($presupuesto['aprobado_at'])
    && !empty($presupuesto['vencimiento'])
    && date('Y-m-d', strtotime($presupuesto['aprobado_at'])) > $presupuesto['vencimiento'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Presupuesto #<?= $presupuesto['id'] ?></h3>

        <div>
            <?php if ($presupuesto['estado'] === 'BORRADOR'): ?>
                <a href="<?= BASE_URL ?>/presupuestos/edit/<?= $presupuesto['id'] ?>"
                   class="btn btn-warning">Editar</a>

                <form id="formAprobarPresupuesto" method="POST"
                      action="<?= BASE_URL ?>/presupuestos/aprobar/<?= $presupuesto['id'] ?>"
                      class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirmarAprobacionPresupuesto(<?= $vencido ? 'true' : 'false' ?>, '<?= htmlspecialchars($fechaVencimiento, ENT_QUOTES) ?>')">
                        Aprobar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($presupuesto['estado'] !== 'ANULADO' && !$tieneNpAsociada): ?>
                <form method="POST"
                      action="<?= BASE_URL ?>/presupuestos/anular/<?= $presupuesto['id'] ?>"
                      class="d-inline"
                      onsubmit="return confirm('¿Anular el presupuesto? Queda el registro y esta acción no se puede revertir.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                    <button class="btn btn-danger">Anular</button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/presupuestos" class="btn btn-secondary">Volver</a>
            <?php if ($tieneNpAsociada): ?>
                <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$presupuesto['nota_pedido_id'] ?>"
                   class="btn btn-outline-secondary">
                    Ver NP #<?= (int)$presupuesto['nota_pedido_id'] ?>
                </a>
                <?php if ($presupuesto['estado'] !== 'ANULADO'): ?>
                    <span class="text-muted small ms-2">
                        Para anular el presupuesto, primero anulá la NP.
                        Si la NP está remitida por completo, generá una devolución.
                    </span>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/presupuestos/pdf/<?= $presupuesto['id'] ?>" target="_blank"
               class="btn btn-outline-danger">
               <i class="bi bi-file-pdf"></i> Descargar PDF
            </a>
            <a href="<?= BASE_URL ?>/presupuestos/regenerar-pdf/<?= $presupuesto['id'] ?>"
               class="btn btn-outline-warning"
               onclick="return confirm('¿Regenerar el PDF del presupuesto? Se sobreescribirá el actual.')">
               <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
            </a>
            <a href="<?= BASE_URL ?>/presupuestos/reenviar/<?= $presupuesto['id'] ?>"
               class="btn btn-outline-primary"
               onclick="return confirm('¿Enviar presupuesto por email al cliente?')">
               <i class="bi bi-envelope"></i> Enviar por Email
            </a>
        </div>
    </div>

    <div class="mb-3">
        <strong>Cliente:</strong> <?= htmlspecialchars($presupuesto['razon_social']) ?><br>
        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($presupuesto['created_at'])) ?><br>
        <?php if (!empty($presupuesto['fecha_entrega'])): ?>
        <strong>Entrega comprometida:</strong> <?= date('d/m/Y', strtotime($presupuesto['fecha_entrega'])) ?><br>
        <?php endif; ?>
        <strong>Estado:</strong>
        <?php
        $badgeEstado = $presupuesto['estado'] === 'APROBADO'
            ? 'success'
            : ($presupuesto['estado'] === 'ANULADO' ? 'danger' : 'secondary');
        ?>
        <span class="badge bg-<?= $badgeEstado ?>">
            <?= $presupuesto['estado'] ?>
        </span>

        <br>
        <strong>Vencimiento:</strong>
        <?php if (!empty($presupuesto['vencimiento'])): ?>
            <?= $fechaVencimiento ?>
            <?php if (!empty($presupuesto['aprobado_at'])): ?>
                <?php if ($aprobadoVencido): ?>
                    <span class="badge bg-danger">Aprobado vencido</span>
                <?php else: ?>
                    <span class="badge bg-success">Aprobado en término</span>
                <?php endif; ?>
            <?php elseif ($presupuesto['estado'] === 'BORRADOR' && $vencido): ?>
                <span class="badge bg-danger">Vencido</span>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-muted">Sin vencimiento</span>
        <?php endif; ?>

        <?php if ($presupuesto['estado'] === 'APROBADO' || !empty($presupuesto['aprobado_at'])): ?>
            <br>
            <strong>Aprobado:</strong>
            <?php if (!empty($presupuesto['aprobado_at'])): ?>
                <?= date('d/m/Y H:i', strtotime($presupuesto['aprobado_at'])) ?>
            <?php else: ?>
                <span class="text-muted">sin fecha registrada</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($presupuesto['estado'] === 'BORRADOR'): ?>
            <form method="POST"
                  action="<?= BASE_URL ?>/presupuestos/actualizar-vencimiento/<?= (int)$presupuesto['id'] ?>"
                  class="d-flex align-items-end gap-2 mt-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                <div>
                    <label class="form-label small mb-0">Cambiar vencimiento</label>
                    <input type="date" name="vencimiento" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($presupuesto['vencimiento'] ?? '') ?>">
                </div>
                <button class="btn btn-sm btn-outline-primary">Guardar vencimiento</button>
            </form>
        <?php endif; ?>

        <?php if ($presupuesto['estado'] === 'ANULADO'): ?>
            <div class="alert alert-danger mt-3 mb-0">
                <strong>Presupuesto anulado.</strong>
                Queda el registro, pero no se puede aprobar ni modificar.
            </div>
        <?php endif; ?>
    </div>

    <?php $adjModulo = 'PRESUPUESTO'; $adjDocId = (int)$presupuesto['id'];
          require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

    <?php $cpModulo = 'PRESUPUESTO'; $cpDocId = (int)$presupuesto['id'];
          require BASE_PATH . '/app/views/campos_personalizados/panel.php'; ?>

    <div class="table-scroll mt-3 table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php $total = 0; ?>
            <?php foreach ($presupuesto['detalle'] as $d): 
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
                    <td>$<?= number_format($d['precio'], 2) ?></td>
                    <td>$<?= number_format($sub, 2) ?></td>
                    <td>
                        <?php if (empty($d['producto_id'])): ?>
                            <?php if (!empty($usarRecetasLibres)): ?>
                                <?php if (!empty($d['receta_id']) && !empty($recetasLibres[(int)$d['id']])): ?>
                                    <a href="<?= BASE_URL ?>/recetas/show/<?= (int)$d['receta_id'] ?>"
                                       class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-book"></i> Ver receta
                                    </a>
                                    <?php if ($presupuesto['estado'] === 'BORRADOR'): ?>
                                        <a href="<?= BASE_URL ?>/recetas/edit/<?= (int)$d['receta_id'] ?>"
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i> Editar receta
                                        </a>
                                    <?php endif; ?>
                                <?php elseif (empty($d['receta_id']) && $presupuesto['estado'] === 'BORRADOR'): ?>
                                    <a href="<?= BASE_URL ?>/recetas/create?presupuesto_detalle_id=<?= (int)$d['id'] ?>&amp;volver=presupuesto-<?= (int)$presupuesto['id'] ?>"
                                       class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-plus-circle"></i> Crear receta
                                    </a>
                                <?php elseif (!empty($d['receta_id'])): ?>
                                    <span class="badge bg-secondary" title="La receta está inactiva o no disponible">Receta inactiva</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($presupuesto['estado'] !== 'ANULADO'): ?>
                                <form method="POST"
                                      action="<?= BASE_URL ?>/presupuestos/agregar-producto/<?= $presupuesto['id'] ?>/<?= $d['id'] ?>"
                                      class="d-inline"
                                      onsubmit="return confirm('¿Agregar este producto a la base de productos? Después podrá editarlo en Productos.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plus-circle"></i> Agregar a Productos
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">Sin producto asociado</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/productos/update/<?= $d['producto_id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Editar producto
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Total</th>
                    <th>$<?= number_format($total, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if (!empty($usarRecetasLibres) && !empty($recetasLibres)): ?>
        <?php foreach ($presupuesto['detalle'] as $linea): ?>
            <?php if (empty($linea['producto_id']) && !empty($recetasLibres[(int)$linea['id']])): ?>
                <?php
                $resumen = $recetasLibres[(int)$linea['id']];
                $titulo = 'Receta del ítem libre';
                $precioLinea = (float)$linea['precio'];
                $volverUrl = null;
                require BASE_PATH . '/app/views/recetas/_resumen_card.php';
                ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function confirmarAprobacionPresupuesto(vencido, fechaVencimiento) {
    const form = document.getElementById('formAprobarPresupuesto');
    if (!form) return true;

    if (!vencido) {
        return true;
    }

    Swal.fire({
        title: 'Presupuesto vencido',
        html: 'Este presupuesto venció el <strong>' + fechaVencimiento + '</strong>.<br>¿Querés aprobarlo igual?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, aprobar vencido',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });

    return false;
}
</script>