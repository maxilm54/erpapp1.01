<?php
$condiciones = [
    'NUEVO'          => ['NUEVO', 'success'],
    'BUEN_ESTADO'    => ['Buen Estado', 'primary'],
    'ESTADO_REGULAR' => ['Estado Regular', 'info'],
    'DANADO'         => ['Dañado', 'warning'],
    'INSERVIBLE'     => ['Inservible', 'danger'],
];
$estados = [
    'PENDIENTE' => ['PENDIENTE', 'warning text-dark'],
    'PROCESADA' => ['PROCESADA', 'success'],
    'ANULADA'   => ['ANULADA', 'danger'],
];
$metodos = [
    'EFECTIVO'       => ['Efectivo', 'success'],
    'TRANSFERENCIA'  => ['Transferencia', 'info'],
    'NOTA_CREDITO'   => ['Nota de Crédito', 'primary'],
];
?>

<h3><i class="bi bi-arrow-return-left"></i> Devolución #<?= $devolucion['numero'] ?></h3>

<div class="mb-3">
    <?php
    $estadoInfo = $estados[$devolucion['estado']] ?? [$devolucion['estado'], 'secondary'];
    ?>
    <span class="badge bg-<?= $estadoInfo[1] ?> fs-6"><?= $estadoInfo[0] ?></span>

    <?php if ($devolucion['estado'] !== 'ANULADA'): ?>
        <a href="<?= BASE_URL ?>/devoluciones/anular/<?= $devolucion['id'] ?>"
           class="btn btn-outline-danger btn-sm ms-2"
           onclick="return confirm('¿Está seguro de anular esta devolución?')">
            <i class="bi bi-x-circle"></i> Anular
        </a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-person"></i> Datos del Cliente</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:130px"><strong>Razón Social:</strong></td>
                        <td><?= htmlspecialchars($devolucion['nombre_cliente']) ?></td>
                    </tr>
                    <?php if (!empty($devolucion['cuit'])): ?>
                    <tr>
                        <td class="text-muted"><strong>CUIT:</strong></td>
                        <td><?= htmlspecialchars($devolucion['cuit']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($devolucion['cliente_direccion'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Dirección:</strong></td>
                        <td><?= htmlspecialchars($devolucion['cliente_direccion']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($devolucion['cliente_email'])): ?>
                    <tr>
                        <td class="text-muted"><strong>Email:</strong></td>
                        <td><?= htmlspecialchars($devolucion['cliente_email']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Datos de la Devolución</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:130px"><strong>Número:</strong></td>
                        <td>DEV-<?= str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Fecha:</strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($devolucion['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Remito:</strong></td>
                        <td>
                            <?php if ($devolucion['remito_id']): ?>
                                <a href="<?= BASE_URL ?>/remitossalida/show/<?= $devolucion['remito_id'] ?>">#<?= $devolucion['remito_numero'] ?? $devolucion['remito_id'] ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted"><strong>Usuario:</strong></td>
                        <td><?= htmlspecialchars($devolucion['nombre_usuario']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($devolucion['motivo'])): ?>
<div class="alert alert-info">
    <strong><i class="bi bi-chat-left-text"></i> Motivo:</strong><br>
    <?= nl2br(htmlspecialchars($devolucion['motivo'])) ?>
</div>
<?php endif; ?>

<?php if (!empty($devolucion['motivo_anulacion'])): ?>
<div class="alert alert-danger">
    <strong><i class="bi bi-x-circle"></i> Motivo de anulación:</strong><br>
    <?= nl2br(htmlspecialchars($devolucion['motivo_anulacion'])) ?>
</div>
<?php endif; ?>

<!-- Detalle de productos devueltos -->
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-box"></i> Productos Devueltos</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th width="40">#</th>
                    <th>Producto</th>
                    <th class="text-center">Condición</th>
                    <th class="text-end">Cant. Devuelta</th>
                    <th class="text-end">Reingresa</th>
                    <th class="text-end">Descarta</th>
                    <th class="text-end">Precio U.</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalGeneral = 0;
            $i = 1;
            foreach ($devolucion['detalle'] as $item):
                $precio = (float)$item['precio_unitario'];
                $cant = (float)$item['cantidad_total'];
                $subtotal = $precio * $cant;
                $totalGeneral += $subtotal;
                $condInfo = $condiciones[$item['condicion']] ?? [$item['condicion'], 'secondary'];
            ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                    <td><span class="badge bg-<?= $condInfo[1] ?>"><?= $condInfo[0] ?></span></td>
                    <td class="text-end"><?= number_format($cant, 2) ?></td>
                    <td class="text-end"><?= number_format((float)$item['cantidad_reingresa'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)$item['cantidad_descarta'], 2) ?></td>
                    <td class="text-end">$ <?= number_format($precio, 2, ',', '.') ?></td>
                    <td class="text-end fw-bold">$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="7" class="text-end">TOTAL:</td>
                    <td class="text-end">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Reembolsos -->
<?php if (!empty($devolucion['reembolsos'])): ?>
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-cash-stack"></i> Reembolsos</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Método</th>
                    <th class="text-end">Monto</th>
                    <th>Caja / Banco</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devolucion['reembolsos'] as $r):
                $metInfo = $metodos[$r['metodo']] ?? [$r['metodo'], 'secondary'];
            ?>
                <tr>
                    <td><span class="badge bg-<?= $metInfo[1] ?>"><?= $metInfo[0] ?></span></td>
                    <td class="text-end fw-bold">$ <?= number_format((float)$r['monto'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($r['caja_nombre'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['observaciones'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Acciones -->
<div class="d-flex gap-2 mt-3">
    <a href="<?= BASE_URL ?>/devoluciones" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <?php if (!empty($devolucion['pdf_path'])): ?>
        <a href="<?= BASE_URL ?>/devoluciones/pdf/<?= $devolucion['id'] ?>" target="_blank"
           class="btn btn-outline-danger">
            <i class="bi bi-file-pdf"></i> Descargar PDF
        </a>
        <a href="<?= BASE_URL ?>/devoluciones/regenerar-pdf/<?= $devolucion['id'] ?>"
           class="btn btn-outline-warning"
           onclick="return confirm('¿Regenerar el PDF? Se sobreescribirá el actual.')">
            <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
        </a>
        <a href="<?= BASE_URL ?>/devoluciones/reenviar/<?= $devolucion['id'] ?>"
           class="btn btn-outline-primary"
           onclick="return confirm('¿Reenviar devolución por email al cliente?')">
            <i class="bi bi-envelope"></i> Reenviar por Email
        </a>
    <?php endif; ?>
</div>
