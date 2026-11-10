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
        <?php if (!empty($orden['fecha_entrega'])): ?>
            <p><strong>Entrega comprometida:</strong> <?= date('d/m/Y', strtotime($orden['fecha_entrega'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($orden['aprobada_at'])): ?>
            <p class="mb-1"><strong>Aprobada:</strong> <?= date('d/m/Y H:i', strtotime($orden['aprobada_at'])) ?>
                por <?= htmlspecialchars($orden['aprobado_por_nombre'] ?? 'N/D') ?></p>
        <?php endif; ?>
        <?php if (!empty($orden['observaciones'])): ?>
            <p><strong>Observaciones:</strong></p>
            <p class="mb-0"><?= nl2br(htmlspecialchars($orden['observaciones'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php $adjModulo = 'ORDEN_COMPRA'; $adjDocId = (int)$orden['id'];
      require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

<?php $cpModulo = 'ORDEN_COMPRA'; $cpDocId = (int)$orden['id'];
      require BASE_PATH . '/app/views/campos_personalizados/panel.php'; ?>

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
        <?php
            $total = 0; // inicializacion: sin esto el total queda indefinido si no hay detalle
            $totalesPorMoneda = [];
        ?>
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
                // Subtotal de lo PEDIDO (independiente de lo recibido): siempre visible
                $Subtotal = (float)$d['pedida'] * (float)$d['precio_unitario'];
                $moneda = (string)($d['moneda'] ?? '');
                $totalesPorMoneda[$moneda] = ($totalesPorMoneda[$moneda] ?? 0) + $Subtotal;
            ?>
            <td class="fw-semibold"><?= number_format($Subtotal, 2,',','.') ?></td>
            <td><?= htmlspecialchars($moneda) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="7" class="text-end">Total:</th>
            <?php if (count($totalesPorMoneda) > 1): ?>
                <th colspan="2">
                    <?php foreach ($totalesPorMoneda as $moneda => $tot): ?>
                        <div><?= htmlspecialchars($moneda !== '' ? $moneda : '$') ?> <?= number_format($tot, 2,',','.') ?></div>
                    <?php endforeach; ?>
                </th>
            <?php else: ?>
                <?php $total = reset($totalesPorMoneda) ?: 0; ?>
                <th class="fw-bold"><?= number_format($total, 2,',','.') ?></th>
            <?php endif; ?>
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

        <form method="POST" action="<?= BASE_URL ?>/ordenescompra/aprobar/<?= $orden['id'] ?>" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('¿Aprobar esta orden?');">
                Aprobar
            </button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/ordenescompra/anular/<?= $orden['id'] ?>" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('¿Anular esta orden?');">
                Anular
            </button>
        </form>
    <?php endif; ?>

    <?php if (in_array($orden['estado'], ['APROBADA', 'PARCIAL', 'RECIBIDA'], true)): ?>
        <?php
            // Facturado activo sobre esta OC (por la tabla de relacion)
            $facturadoOc = 0.0;
            foreach ($facturas as $fc) {
                if (($fc['estado'] ?? '') === 'REGISTRADA') {
                    $facturadoOc += (float)($fc['monto_asignado'] ?? $fc['monto_total'] ?? 0);
                }
            }
            $totalOcMostrar = 0.0;
            foreach ($orden['detalle'] as $d) {
                $totalOcMostrar += (float)$d['pedida'] * (float)$d['precio_unitario'];
            }
            $ocCubierta = $totalOcMostrar > 0 && $facturadoOc >= $totalOcMostrar - 0.02;
        ?>
        <?php if (!$ocCubierta): ?>
            <a href="<?= BASE_URL ?>/facturascompra/create?oc=<?= $orden['id'] ?>"
               class="btn btn-outline-primary">
               <i class="bi bi-receipt"></i> Registrar factura
            </a>
        <?php else: ?>
            <span class="badge bg-success align-self-center" title="La OC ya está cubierta por facturas registradas">
                <i class="bi bi-check2-circle"></i> Facturada
            </span>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Facturas de compra de esta OC -->
<?php if (!empty($facturas)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-receipt"></i> Facturas de compra</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Saldo</th>
                        <th>Estado</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($facturas as $fc): ?>
                    <?php
                        $badgeFc = ($fc['estado'] ?? '') === 'ANULADA' ? 'bg-secondary' : 'bg-success';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(($fc['tipo_fiscal'] ?? '') !== '' ? $fc['tipo_fiscal'] . ' ' : '') ?><?= htmlspecialchars($fc['numero']) ?></td>
                        <td><?= date('d/m/Y', strtotime($fc['fecha_factura'])) ?></td>
                        <td class="text-end">$ <?= number_format((float)$fc['monto_total'], 2, ',', '.') ?></td>
                        <td class="text-end">$ <?= number_format((float)$fc['saldo'], 2, ',', '.') ?></td>
                        <td><span class="badge <?= $badgeFc ?>"><?= htmlspecialchars(FacturaCompra::ESTADOS[$fc['estado']] ?? $fc['estado']) ?></span></td>
                        <td class="text-center">
                            <a href="<?= BASE_URL ?>/facturascompra/show/<?= (int)$fc['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>