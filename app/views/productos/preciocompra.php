<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-tag"></i> Costos y Precios - <?= htmlspecialchars($producto['nombre']) ?></h3>
    <div>
        <a href="<?= BASE_URL ?>/productos" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <!-- Panel izquierdo: Formulario de costos -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Ingresar Costos</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-cart"></i> Precio de Compra
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="precio_compra"
                                   value="<?= $costos['precio_compra'] ?? 0 ?>"
                                   step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Precio unitario que paga al proveedor</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-building"></i> Costo Fijo
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="costo_fijo"
                                   value="<?= $costos['costo_fijo'] ?? 0 ?>"
                                   step="0.01" min="0">
                        </div>
                        <small class="text-muted">Transporte, almacenaje, etc. (monto fijo por unidad)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-percent"></i> Costo Variable (%)
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="costo_variable_pct"
                                   value="<?= $costos['costo_variable_pct'] ?? 0 ?>"
                                   step="0.01" min="0" max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">% sobre el precio de compra (ej: 10 = 10%)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-graph-up-arrow"></i> Margen de Ganancia (%)
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="margen_ganancia_pct"
                                   value="<?= $costos['margen_ganancia_pct'] ?? 0 ?>"
                                   step="0.01" min="0" max="500">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">% sobre el costo total (ej: 50 = 50%)</small>
                    </div>

                    <hr>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-save"></i> Guardar Costos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel derecho: Desglose visual del precio -->
    <div class="col-lg-7">
        <?php if ($calculo): ?>
        <!-- Resumen del precio final -->
        <div class="card mb-4 border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Precio de Venta Sugerido</h6>
                <h1 class="text-success mb-0">
                    $ <?= number_format($calculo['precio_sugerido'], 2, ',', '.') ?>
                </h1>
                <?php if ($calculo['precio_venta_actual'] > 0): ?>
                <small class="text-muted">
                    Precio actual: $ <?= number_format($calculo['precio_venta_actual'], 2, ',', '.') ?>
                </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Desglose visual paso a paso -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-layers"></i> Composicion del Precio</h5>
            </div>
            <div class="card-body">
                <!-- Paso 1: Precio de Compra -->
                <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #e3f2fd;">
                    <div class="me-3">
                        <span class="badge bg-primary rounded-pill fs-6">1</span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-primary">Precio de Compra</div>
                        <small class="text-muted">Lo que paga al proveedor</small>
                    </div>
                    <div class="fs-4 fw-bold text-primary">
                        $ <?= number_format($calculo['precio_compra'], 2, ',', '.') ?>
                    </div>
                </div>

                <!-- Paso 2: Costo Variable -->
                <?php if ($calculo['costo_variable'] > 0): ?>
                <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #fff3e0;">
                    <div class="me-3">
                        <span class="badge bg-warning rounded-pill fs-6">2</span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-warning">Costo Variable</div>
                        <small class="text-muted"><?= number_format($costos['costo_variable_pct'], 1) ?>% sobre precio de compra</small>
                    </div>
                    <div class="fs-4 fw-bold text-warning">
                        + $ <?= number_format($calculo['costo_variable'], 2, ',', '.') ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Paso 3: Costo Fijo -->
                <?php if ($calculo['costo_fijo'] > 0): ?>
                <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #fce4ec;">
                    <div class="me-3">
                        <span class="badge bg-danger rounded-pill fs-6">3</span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-danger">Costo Fijo</div>
                        <small class="text-muted">Transporte, almacenaje, etc.</small>
                    </div>
                    <div class="fs-4 fw-bold text-danger">
                        + $ <?= number_format($calculo['costo_fijo'], 2, ',', '.') ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Linea separadora: Costo Total -->
                <hr class="my-4">

                <!-- Paso 4: Costo Total -->
                <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #f3e5f5;">
                    <div class="me-3">
                        <span class="badge bg-info rounded-pill fs-6">4</span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-info">Costo Total (inversion)</div>
                        <small class="text-muted">Compra + Costos</small>
                    </div>
                    <div class="fs-4 fw-bold text-info">
                        $ <?= number_format($calculo['costo_total'], 2, ',', '.') ?>
                    </div>
                </div>

                <!-- Paso 5: Margen de Ganancia -->
                <?php if ($calculo['margen_ganancia_pct'] > 0): ?>
                <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: #e8f5e9;">
                    <div class="me-3">
                        <span class="badge bg-success rounded-pill fs-6">5</span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-success">Margen de Ganancia</div>
                        <small class="text-muted"><?= number_format($calculo['margen_ganancia_pct'], 1) ?>% sobre costo total</small>
                    </div>
                    <div class="fs-4 fw-bold text-success">
                        + $ <?= number_format($calculo['ganancia_neta'], 2, ',', '.') ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resultado final -->
                <div class="d-flex align-items-center p-4 rounded" style="background: #e8f5e9; border: 2px solid #4caf50;">
                    <div class="me-3">
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-success fs-5">Precio de Venta Sugerido</div>
                        <small class="text-muted">
                            Margen real: <?= number_format($calculo['margen_real_pct'], 1) ?>% sobre precio de venta
                        </small>
                    </div>
                    <div class="fs-3 fw-bold text-success">
                        $ <?= number_format($calculo['precio_sugerido'], 2, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra visual de composicion -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Composicion Visual del Precio</h6>
            </div>
            <div class="card-body">
                <?php
                $total = max($calculo['precio_sugerido'], 0.01);
                $pctCompra = ($calculo['precio_compra'] / $total) * 100;
                $pctCostoVar = ($calculo['costo_variable'] / $total) * 100;
                $pctCostoFijo = ($calculo['costo_fijo'] / $total) * 100;
                $pctGanancia = ($calculo['ganancia_neta'] / $total) * 100;
                ?>
                <div class="progress mb-3" style="height: 40px;">
                    <div class="progress-bar bg-primary" style="width: <?= $pctCompra ?>%"
                         title="Compra: $<?= number_format($calculo['precio_compra'], 2) ?>">
                        Compra
                    </div>
                    <?php if ($calculo['costo_variable'] > 0): ?>
                    <div class="progress-bar bg-warning" style="width: <?= $pctCostoVar ?>%"
                         title="Costo Variable: $<?= number_format($calculo['costo_variable'], 2) ?>">
                        Var
                    </div>
                    <?php endif; ?>
                    <?php if ($calculo['costo_fijo'] > 0): ?>
                    <div class="progress-bar bg-danger" style="width: <?= $pctCostoFijo ?>%"
                         title="Costo Fijo: $<?= number_format($calculo['costo_fijo'], 2) ?>">
                        Fijo
                    </div>
                    <?php endif; ?>
                    <div class="progress-bar bg-success" style="width: <?= $pctGanancia ?>%"
                         title="Ganancia: $<?= number_format($calculo['ganancia_neta'], 2) ?>">
                        Ganancia
                    </div>
                </div>

                <!-- Leyenda -->
                <div class="row text-center">
                    <div class="col">
                        <small>
                            <span class="badge bg-primary">&nbsp;</span> Compra
                            <strong>$<?= number_format($calculo['precio_compra'], 2) ?></strong>
                            (<?= number_format($pctCompra, 1) ?>%)
                        </small>
                    </div>
                    <?php if ($calculo['costo_variable'] > 0): ?>
                    <div class="col">
                        <small>
                            <span class="badge bg-warning">&nbsp;</span> Variable
                            <strong>$<?= number_format($calculo['costo_variable'], 2) ?></strong>
                            (<?= number_format($pctCostoVar, 1) ?>%)
                        </small>
                    </div>
                    <?php endif; ?>
                    <?php if ($calculo['costo_fijo'] > 0): ?>
                    <div class="col">
                        <small>
                            <span class="badge bg-danger">&nbsp;</span> Fijo
                            <strong>$<?= number_format($calculo['costo_fijo'], 2) ?></strong>
                            (<?= number_format($pctCostoFijo, 1) ?>%)
                        </small>
                    </div>
                    <?php endif; ?>
                    <div class="col">
                        <small>
                            <span class="badge bg-success">&nbsp;</span> Ganancia
                            <strong>$<?= number_format($calculo['ganancia_neta'], 2) ?></strong>
                            (<?= number_format($pctGanancia, 1) ?>%)
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Sin costos configurados -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Sin costos configurados</h5>
                <p class="text-muted">
                    Ingrese los costos de compra en el formulario de la izquierda para ver
                    el desglose visual del precio de venta.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
