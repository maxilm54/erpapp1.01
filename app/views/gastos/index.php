<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-wallet2"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/gastos/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Gasto
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/gastos" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Categoría</label>
                <select name="categoria" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach (['PROVEEDORES','SUELDOS','SERVICIOS','ALQUILER','IMPUESTOS','OTROS'] as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($filters['categoria'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= $cat ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['BORRADOR','APROBADO','PAGADO','ANULADO'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                            <?= $est ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['fecha_desde'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['fecha_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm"
                       placeholder="Descripción o comprobante"
                       value="<?= htmlspecialchars($filters['buscar'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>/gastos" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Badges de estado -->
<div class="mb-3">
    <?php
    $badgeClasses = [
        'BORRADOR' => 'bg-secondary',
        'APROBADO' => 'bg-primary',
        'PAGADO'   => 'bg-success',
        'ANULADO'  => 'bg-danger',
    ];
    foreach ($badgeClasses as $est => $cls):
        $cnt = $estadosCount[$est] ?? 0;
    ?>
        <span class="badge <?= $cls ?> me-1"><?= $estado ?>: <?= $cnt ?></span>
    <?php endforeach; ?>
</div>

<!-- Tabla de gastos -->
<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Categoría</th>
            <th>Descripción</th>
            <th>Monto</th>
            <th>Medio Pago</th>
            <th>Estado</th>
            <th>OC</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($gastos as $g): ?>
        <tr>
            <td><?= $g['id'] ?></td>
            <td><?= date('d/m/Y', strtotime($g['fecha'])) ?></td>
            <td><span class="badge bg-info text-dark"><?= $g['categoria'] ?></span></td>
            <td><?= htmlspecialchars(mb_substr($g['descripcion'], 0, 50)) ?></td>
            <td class="text-end fw-bold">$ <?= number_format($g['monto_total'], 2, ',', '.') ?></td>
            <td><?= $g['medio_pago'] ?></td>
            <td>
                <?php
                $cls = $badgeClasses[$g['estado']] ?? 'bg-secondary';
                ?>
                <span class="badge <?= $cls ?>"><?= $g['estado'] ?></span>
            </td>
            <td>
                <?php if ($g['oc_numero']): ?>
                    <a href="<?= BASE_URL ?>/ordenescompra/show/<?= $g['orden_compra_id'] ?>">OC #<?= $g['oc_numero'] ?></a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/gastos/show/<?= $g['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($gastos)): ?>
        <tr>
            <td colspan="9" class="text-center text-muted py-4">
                No se encontraron gastos con los filtros aplicados.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
