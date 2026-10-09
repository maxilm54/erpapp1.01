<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Cuenta Corriente de Clientes</h1>
    <div>
        <a href="<?= BASE_URL ?>/ctacte/cliente" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-people"></i> Desglose por Cliente
        </a>
        <a href="<?= BASE_URL ?>/ctacte/pago" class="btn btn-success btn-sm">
            <i class="bi bi-cash"></i> Registrar Pago
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Cliente</label>
                <select name="cliente_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($filtros['cliente_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="DEBITO" <?= ($filtros['tipo'] ?? '') === 'DEBITO' ? 'selected' : '' ?>>Débito (Venta)</option>
                    <option value="CREDITO" <?= ($filtros['tipo'] ?? '') === 'CREDITO' ? 'selected' : '' ?>>Crédito (Pago/Devolución)</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Origen</label>
                <select name="origen" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($origenes as $o): ?>
                    <option value="<?= htmlspecialchars($o) ?>" <?= ($filtros['origen'] ?? '') === $o ? 'selected' : '' ?>>
                        <?= htmlspecialchars($o) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Cliente, origen, referencia..." value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/ctacte" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Resumen del conjunto filtrado -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total Débitos (Compras)</div>
                <div class="fs-5 fw-bold text-danger">$ <?= number_format((float)$resumen['total_debito'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total Créditos (Pagos/Devoluciones)</div>
                <div class="fs-5 fw-bold text-success">$ <?= number_format((float)$resumen['total_credito'], 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Saldo</div>
                <div class="fs-5 fw-bold text-primary">$ <?= number_format((float)$resumen['saldo'], 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="table-scroll mb-2">
    <table class="table table-bordered table-striped table-sm mb-0">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Referencia</th>
                <th>Débito</th>
                <th>Crédito</th>
                <th>Saldo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimientos)): ?>
            <tr>
                <td colspan="9" class="text-center text-muted">No hay movimientos que coincidan con los filtros</td>
            </tr>
            <?php else: foreach ($movimientos as $m): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/ctacte/cliente/<?= (int)$m['cliente_id'] ?><?= $m['cliente_id'] == 9999 && !empty($m['cliente_nombre']) ? '?nombre=' . urlencode($m['cliente_nombre']) : '' ?>">
                        <?= htmlspecialchars($m['nombre_cliente'] ?? 'N/D') ?>
                    </a>
                </td>
                <td>
                    <span class="badge bg-<?= $m['tipo'] == 'DEBITO' ? 'danger' : 'success' ?>">
                        <?= $m['tipo'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($m['origen']) ?></td>
                <td>#<?= (int)$m['referencia_id'] ?></td>
                <td><?= $m['tipo'] == 'DEBITO' ? number_format((float)$m['monto'], 2) : '' ?></td>
                <td><?= $m['tipo'] == 'CREDITO' ? number_format((float)$m['monto'], 2) : '' ?></td>
                <td><strong><?= number_format((float)$m['saldo'], 2) ?></strong></td>
                <td>
                    <a href="<?= BASE_URL ?>/ctacte/show/<?= (int)$m['id'] ?>"
                    class="btn btn-outline-primary btn-sm">Ver</a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center">
    <span class="text-muted small">
        Mostrando <?= count($movimientos) ?> de <?= number_format($totalRows) ?> movimientos
        — Página <?= $page ?> de <?= $totalPages ?>
    </span>
    <?php $baseUrl = BASE_URL . '/ctacte'; require BASE_PATH . '/app/views/layout/pagination.php'; ?>
</div>
