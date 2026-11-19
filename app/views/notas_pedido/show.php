<?php
$productosNpAprobacion = [];
$lineasLibresNpAprobacion = 0;
foreach (($np['detalle'] ?? []) as $dNp) {
    if (empty($dNp['producto_id'])) {
        $lineasLibresNpAprobacion++;
    } else {
        $productosNpAprobacion[(int)$dNp['producto_id']] = true;
    }
}
$puedeProduccionNp = count($productosNpAprobacion) === 1 && $lineasLibresNpAprobacion === 0;
?>
<h1>Nota de Pedido #<?= $np['id'] ?></h1>

<p><strong>Cliente:</strong> <?= $np['razon_social'] ?></p>
<p>
    <strong>Estado:</strong> <?= $np['estado'] ?>
    <?php if (!empty($np['produccion_pendiente'])): ?>
        <span class="badge bg-warning text-dark ms-1">Pendiente de producir</span>
    <?php endif; ?>
    <?php if (($np['remitido'] ?? '') === 'RemitidoCompleto'): ?>
        <span class="badge bg-success ms-1">Remitido Completo</span>
    <?php endif; ?>
</p>
<?php if (!empty($np['fecha_entrega'])): ?>
    <p><strong>Entrega comprometida:</strong> <?= date('d/m/Y', strtotime($np['fecha_entrega'])) ?></p>
<?php endif; ?>
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

<?php if (!empty($npProduccion['configActiva'])): ?>
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning-subtle">
            <strong><i class="bi bi-diagram-3"></i> Producción desde receta</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($npProduccion['orden'])): ?>
                <div class="alert alert-success mb-0">
                    Esta Nota de Pedido ya generó la
                    <a href="<?= BASE_URL ?>/ordenproduccion/show/<?= (int)$npProduccion['orden']['id'] ?>">
                        Orden de Producción #<?= (int)$npProduccion['orden']['id'] ?>
                    </a>
                    <span class="badge bg-success"><?= htmlspecialchars($npProduccion['orden']['estado']) ?></span>
                </div>
            <?php elseif (($np['remitido'] ?? '') === 'RemitidoCompleto'): ?>
                <p class="text-muted mb-0 small">
                    La Nota de Pedido ya fue remitida por completo. No hace falta generar producción.
                </p>
            <?php elseif ($np['estado'] !== 'APROBADA'): ?>
                <p class="text-muted mb-0 small">
                    Aprobá la Nota de Pedido para poder generarla como orden de producción desde la receta de su producto.
                </p>
            <?php elseif (!empty($npProduccion['evaluacion']['elegible'])): ?>
                <p class="mb-2 small">
                    <strong>Producto:</strong> <?= htmlspecialchars($npProduccion['evaluacion']['producto']) ?> &mdash;
                    <strong>Cantidad:</strong> <?= number_format((float)$npProduccion['evaluacion']['cantidad'], 3, ',', '.') ?> &mdash;
                    <strong>Receta:</strong> <?= htmlspecialchars($npProduccion['evaluacion']['receta']['nombre']) ?>
                </p>
                <form method="POST"
                      action="<?= BASE_URL ?>/notaspedido/generar-produccion/<?= (int)$np['id'] ?>"
                      onsubmit="return confirm('¿Generar la orden de producción desde la receta del producto? Se reservarán las materias primas necesarias.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                    <button class="btn btn-warning">
                        <i class="bi bi-gear"></i> Generar orden de producción
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <strong>No se puede generar la producción:</strong>
                    <?= htmlspecialchars($npProduccion['evaluacion']['motivo'] ?? 'No hay datos suficientes para evaluar la receta.') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($depositoReservadoNp)): ?>
    <?php
    $puedeReservar = $np['estado'] === 'APROBADA'
        && ($np['remitido'] ?? '') !== 'RemitidoCompleto';
    $hayReservasVigentes = false;
    foreach ($reservasNp as $r) {
        if ($r['estado'] === 'RESERVADO' && (float)$r['disponible'] > 0.000001) {
            $hayReservasVigentes = true;
            break;
        }
    }

    $lineasReserva = [];
    foreach ($detalleReserva as $d) {
        if (empty($d['producto_id']) || (float)$d['pendiente'] <= 0) {
            continue;
        }
        $pid = (int)$d['producto_id'];
        if (!isset($lineasReserva[$pid])) {
            $lineasReserva[$pid] = [
                'producto_id' => $pid,
                'nombre'      => $d['nombre'],
                'pendiente'   => 0.0,
            ];
        }
        $lineasReserva[$pid]['pendiente'] += (float)$d['pendiente'];
    }
    ?>
    <div class="card mb-3 border-warning">
        <div class="card-header bg-warning-subtle">
            <strong><i class="bi bi-bookmark-check"></i> Reservas de productos</strong>
            <span class="badge bg-warning text-dark ms-2"><?= htmlspecialchars($depositoReservadoNp['nombre']) ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($reservasNp)): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Consumido</th>
                                <th>Disponible</th>
                                <th>Origen</th>
                                <th>Depósito origen</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservasNp as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['producto'] ?? ('Producto #' . $r['producto_id'])) ?></td>
                                    <td><?= number_format((float)$r['cantidad'], 3, ',', '.') ?></td>
                                    <td><?= number_format((float)$r['cantidad_consumida'], 3, ',', '.') ?></td>
                                    <td><?= number_format((float)$r['disponible'], 3, ',', '.') ?></td>
                                    <td>
                                        <?= $r['origen'] === 'PRODUCCION' ? 'Producción' : 'Manual' ?>
                                        <?php if (!empty($r['orden_produccion_id'])): ?>
                                            <a href="<?= BASE_URL ?>/ordenproduccion/show/<?= (int)$r['orden_produccion_id'] ?>"
                                               class="small">OP #<?= (int)$r['orden_produccion_id'] ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['deposito_origen_nombre'] ?? '—') ?></td>
                                    <td>
                                        <?php
                                        $claseEstado = $r['estado'] === 'RESERVADO'
                                            ? 'warning text-dark'
                                            : ($r['estado'] === 'CONSUMIDO' ? 'success' : 'secondary');
                                        ?>
                                        <span class="badge bg-<?= $claseEstado ?>"><?= htmlspecialchars($r['estado']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hayReservasVigentes): ?>
                    <form method="POST"
                          action="<?= BASE_URL ?>/notaspedido/liberar-reservas/<?= (int)$np['id'] ?>"
                          class="mb-3"
                          onsubmit="return confirm('¿Liberar las reservas vigentes y devolver el stock a su depósito de origen?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                        <button class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-arrow-counterclockwise"></i> Liberar reservas
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted small mb-3">Esta Nota de Pedido todavía no tiene productos reservados.</p>
            <?php endif; ?>

            <?php if ($puedeReservar): ?>
                <?php if (empty($lineasReserva) || empty($depositosReserva)): ?>
                    <p class="text-muted small mb-0">
                        No hay líneas de producto pendientes o depósitos disponibles para reservar.
                    </p>
                <?php else: ?>
                    <hr>
                    <h6 class="mb-2"><i class="bi bi-plus-circle"></i> Reservar productos para esta NP</h6>
                    <?php if (!empty($npProduccion['orden'])): ?>
                        <p class="text-muted small">
                            Esta NP tiene la OP #<?= (int)$npProduccion['orden']['id'] ?>: la producción se reserva
                            automáticamente. Acá podés reservar stock adicional que ya esté en otro depósito.
                        </p>
                    <?php endif; ?>
                    <form method="POST" action="<?= BASE_URL ?>/notaspedido/reservar-productos/<?= (int)$np['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

                        <div class="mb-2">
                            <label class="form-label small mb-1">Depósito de origen</label>
                            <select name="deposito_origen_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar depósito...</option>
                                <?php foreach ($depositosReserva as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Pendiente</th>
                                        <th>Ya reservado</th>
                                        <th>Disponible para reservar</th>
                                        <th style="width:140px">Reservar ahora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lineasReserva as $l): ?>
                                        <?php
                                        $reservado = (float)($reservasResumen[$l['producto_id']]['disponible'] ?? 0);
                                        $aReservar = max(0, (float)$l['pendiente'] - $reservado);
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($l['nombre']) ?></td>
                                            <td><?= number_format((float)$l['pendiente'], 3, ',', '.') ?></td>
                                            <td><?= number_format($reservado, 3, ',', '.') ?></td>
                                            <td><?= number_format($aReservar, 3, ',', '.') ?></td>
                                            <td>
                                                <?php if ($aReservar > 0.000001): ?>
                                                    <input type="number"
                                                           step="0.001"
                                                           min="0"
                                                           max="<?= $aReservar ?>"
                                                           name="items[<?= (int)$l['producto_id'] ?>]"
                                                           class="form-control form-control-sm"
                                                           value="0">
                                                <?php else: ?>
                                                    <span class="badge bg-success">Cubierto</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-warning btn-sm">
                            <i class="bi bi-bookmark-plus"></i> Reservar productos
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif (!empty($npProduccion['orden'])): ?>
                <p class="text-muted small mb-0">
                    Esta NP tiene una OP asignada: la producción se reserva automáticamente al finalizarla.
                </p>
            <?php elseif ($np['estado'] !== 'APROBADA'): ?>
                <p class="text-muted small mb-0">
                    Aprobá la Nota de Pedido para poder reservar productos.
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($np['estado'] === 'BORRADOR'): ?>
    <form id="formAprobarNp" method="POST"
          action="<?= BASE_URL ?>/notaspedido/approve/<?= (int)$np['id'] ?>"
          class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <input type="hidden" name="flujo" id="flujoAprobacionNp" value="normal">
        <button type="button" class="btn btn-success"
                onclick="confirmarAprobacionNP(<?= (int)$np['id'] ?>)">
            Aprobar
        </button>
    </form>
<?php endif ?>

<?php if ($np['estado'] !== 'ANULADA' && ($np['remitido'] ?? '') !== 'RemitidoCompleto'): ?>
    <button class="btn btn-danger"
        onclick="confirmarAnulacionNP(<?= (int)$np['id'] ?>)">
        Anular
    </button>
<?php endif ?>

<?php if ($np['estado'] === 'APROBADA' && ($np['remitido'] ?? '') !== 'RemitidoCompleto'): ?>
    <button class="btn btn-primary"
            onclick="confirmarRemito(<?= (int)$np['id'] ?>)">
        Remitir
    </button>
<?php endif ?>

<?php if (($np['remitido'] ?? '') === 'RemitidoCompleto' && $np['estado'] !== 'ANULADA'): ?>
    <div class="alert alert-info mt-3">
        Esta Nota de Pedido está remitida por completo y <strong>no se puede anular</strong>.
        Para revertir la entrega, generá una devolución.
        <a href="<?= BASE_URL ?>/devoluciones/create" class="alert-link">Ir a Devoluciones</a>
    </div>
<?php endif ?>

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
const puedeProduccionNp = <?= $puedeProduccionNp ? 'true' : 'false' ?>;

function confirmarAprobacionNP(id) {
    const opcionesAprobacion = {
        normal: 'Camino normal: aprobar para remitir'
    };
    if (puedeProduccionNp) {
        opcionesAprobacion.produccion = 'Procesar mediante receta en producción';
    }

    Swal.fire({
        title: '¿Aprobar Nota de Pedido?',
        text: 'Elegí cómo va a continuar esta NP',
        input: 'select',
        inputOptions: opcionesAprobacion,
        inputPlaceholder: 'Elegí una opción...',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Aprobar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'Tenés que elegir cómo continúa la NP';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('flujoAprobacionNp').value = result.value;
            document.getElementById('formAprobarNp').submit();
        }
    });
}
</script>