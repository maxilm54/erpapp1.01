<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/cajas" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cajas
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-bank"></i> <?= htmlspecialchars($title) ?></h3>
    <span class="badge <?= $caja['tipo'] === 'CAJA' ? 'bg-success' : 'bg-primary' ?> fs-5">
        Saldo: $ <?= number_format($caja['saldo_actual'], 2, ',', '.') ?>
    </span>
</div>

<!-- Info de la caja -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Tipo:</strong> <?= $caja['tipo'] ?></div>
            <div class="col-md-3"><strong>Banco:</strong> <?= htmlspecialchars($caja['banco'] ?? '-') ?></div>
            <div class="col-md-3"><strong>N° Cuenta:</strong> <code><?= htmlspecialchars($caja['numero_cuenta'] ?? '-') ?></code></div>
            <div class="col-md-3"><strong>CBU:</strong> <code><?= htmlspecialchars($caja['cbu'] ?? '-') ?></code></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/caja-detalle/<?= $caja['id'] ?>" class="row g-2 align-items-end">
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
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/contabilidad/caja-detalle/<?= $caja['id'] ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Movimientos -->
<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th class="text-end">Monto</th>
            <th class="text-end">Saldo Anterior</th>
            <th class="text-end">Saldo Posterior</th>
            <th>Registrado por</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($movimientos as $m): ?>
        <tr>
            <td><?= $m['id'] ?></td>
            <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
            <td>
                <span class="badge <?= $m['tipo'] === 'INGRESO' ? 'bg-success' : ($m['tipo'] === 'EGRESO' ? 'bg-danger' : 'bg-info') ?>">
                    <?= $m['tipo'] ?>
                </span>
            </td>
            <td><?= htmlspecialchars($m['descripcion'] ?? '-') ?></td>
            <td class="text-end fw-bold <?= $m['tipo'] === 'INGRESO' ? 'text-success' : 'text-danger' ?>">
                <?= $m['tipo'] === 'INGRESO' ? '+' : '-' ?> $ <?= number_format($m['monto'], 2, ',', '.') ?>
            </td>
            <td class="text-end">$ <?= number_format($m['saldo_anterior'], 2, ',', '.') ?></td>
            <td class="text-end">$ <?= number_format($m['saldo_posterior'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($m['usuario_nombre']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movimientos)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                No hay movimientos registrados.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
