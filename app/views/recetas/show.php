<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-book"></i> Receta #<?= $receta['id'] ?></h3>
    <div>
        <a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <a href="<?= BASE_URL ?>/recetas/edit/<?= $receta['id'] ?>" class="btn btn-warning" onclick="return confirm('Editar Receta?')">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?= BASE_URL ?>/recetas/delete/<?= $receta['id'] ?>" class="btn btn-danger" onclick="return confirm('Eliminar Receta?')">
            <i class="bi bi-trash"></i> Eliminar
        </a>
    </div>
</div>

<div class="row">
    <!-- Columna izquierda: Detalle de la receta con costos -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-check"></i> Producto: <?= htmlspecialchars($receta['producto']) ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Materia Prima</th>
                                <th class="text-end">Cantidad</th>
                                <th>Unidad</th>
                                <th class="text-end">Precio Compra</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rec_det as $d): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($d['nombre']) ?></strong>
                                </td>
                                <td class="text-end"><?= number_format($d['cantidad'], 3, ',', '.') ?></td>
                                <td><?= htmlspecialchars($d['unidad_medida'] ?? '-') ?></td>
                                <td class="text-end">
                                    <?php if ($d['precio_compra'] !== null): ?>
                                        $ <?= number_format($d['precio_compra'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin precio</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($d['subtotal'] !== null): ?>
                                        <strong>$ <?= number_format($d['subtotal'], 2, ',', '.') ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Costo Total Receta:</strong></td>
                                <td class="text-end">
                                    <strong class="fs-5">$ <?= number_format($costo_total_receta, 2, ',', '.') ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Procedimiento -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clipboard-text"></i> Procedimiento / Observaciones</h6>
            </div>
            <div class="card-body">
                <?= nl2br(htmlspecialchars($receta['proceso_fabrica'] ?? 'Sin procedimiento especificado')) ?>
            </div>
        </div>
    </div>

    <!-- Columna derecha: Comparativa de costos del producto -->
    <div class="col-lg-4">
        <!-- Costo de la receta vs precio de venta -->
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-calculator"></i> Analisis de Costos</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Costo Materiales (Receta)</small>
                    <div class="fs-4 fw-bold text-primary">$ <?= number_format($costo_total_receta, 2, ',', '.') ?></div>
                </div>

                <div class="mb-3">
                    <small class="text-muted">Precio Venta Producto</small>
                    <div class="fs-4 fw-bold text-success">
                        $ <?= number_format((float)($receta['precio_venta'] ?? 0), 2, ',', '.') ?>
                    </div>
                </div>

                <?php
                $precioVenta = (float)($receta['precio_venta'] ?? 0);
                $gananciaSimple = $precioVenta - $costo_total_receta;
                $margenSimple = $precioVenta > 0 ? ($gananciaSimple / $precioVenta * 100) : 0;
                ?>
                <hr>
                <div class="mb-3">
                    <small class="text-muted">Ganancia (Venta - Materiales)</small>
                    <div class="fs-5 fw-bold <?= $gananciaSimple >= 0 ? 'text-success' : 'text-danger' ?>">
                        $ <?= number_format($gananciaSimple, 2, ',', '.') ?>
                        <small>(<?= number_format($margenSimple, 1) ?>%)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Costos completos del producto (si existen) -->
        <?php if ($calculo_producto): ?>
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Costos Completos Producto</h6>
                <a href="<?= BASE_URL ?>/productos/preciocompra/<?= $receta['producto_id'] ?>"
                   class="btn btn-sm btn-light" title="Editar costos">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col">
                        <small class="text-muted d-block">Compra</small>
                        <strong>$ <?= number_format($calculo_producto['precio_compra'], 2) ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Costo Fijo</small>
                        <strong>$ <?= number_format($calculo_producto['costo_fijo'], 2) ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Variable</small>
                        <strong>$ <?= number_format($calculo_producto['costo_variable'], 2) ?></strong>
                    </div>
                </div>

                <div class="alert alert-success text-center mb-3">
                    <small>Precio Sugerido</small>
                    <div class="fs-4 fw-bold">$ <?= number_format($calculo_producto['precio_sugerido'], 2) ?></div>
                </div>

                <div class="row text-center">
                    <div class="col">
                        <small class="text-muted d-block">Costo Total</small>
                        <strong>$ <?= number_format($calculo_producto['costo_total'], 2) ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Ganancia Neta</small>
                        <strong class="text-success">$ <?= number_format($calculo_producto['ganancia_neta'], 2) ?></strong>
                    </div>
                    <div class="col">
                        <small class="text-muted d-block">Margen Real</small>
                        <strong class="text-success"><?= number_format($calculo_producto['margen_real_pct'], 1) ?>%</strong>
                    </div>
                </div>

                <hr>
                <div class="text-center">
                    <a href="<?= BASE_URL ?>/productos/preciocompra/<?= $receta['producto_id'] ?>"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-pencil-square"></i> Editar Costos del Producto
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Sin costos configurados -->
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Sin Costos Configurados</h6>
            </div>
            <div class="card-body text-center">
                <p class="text-muted mb-3">
                    Este producto no tiene costos configurados.<br>
                    Configure precio de compra, costos fijos/variables y margen de ganancia.
                </p>
                <a href="<?= BASE_URL ?>/productos/preciocompra/<?= $receta['producto_id'] ?>"
                   class="btn btn-outline-success">
                    <i class="bi bi-plus-circle"></i> Configurar Costos
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
