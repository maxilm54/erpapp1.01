<?php if ($modo === 'lista'): ?>

<!-- ==================== MODO A: DESGLOSE POR CLIENTE ==================== -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Desglose por Cliente</h1>
    <a href="<?= BASE_URL ?>/ctacte" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list-ul"></i> Todos los Movimientos
    </a>
</div>

<!-- Búsqueda de cliente -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Buscar cliente</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre o CUIT..." value="<?= htmlspecialchars($q ?? '') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
                <a href="<?= BASE_URL ?>/ctacte/cliente" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="table-scroll mb-2">
    <table class="table table-bordered table-striped table-sm mb-0">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>CUIT</th>
                <th>Débitos (Compras)</th>
                <th>Créditos (Pagos/Devol.)</th>
                <th>Saldo</th>
                <th>Última Actividad</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($resumenClientes)): ?>
            <tr>
                <td colspan="7" class="text-center text-muted">No hay clientes con movimientos<?= !empty($q) ? ' que coincidan con la búsqueda' : '' ?></td>
            </tr>
            <?php else: foreach ($resumenClientes as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre_cliente']) ?></td>
                <td><?= htmlspecialchars($r['cuit']) ?></td>
                <td class="text-danger"><?= number_format((float)$r['total_debito'], 2) ?></td>
                <td class="text-success"><?= number_format((float)$r['total_credito'], 2) ?></td>
                <td>
                    <span class="badge bg-<?= (float)$r['saldo'] > 0 ? 'danger' : ((float)$r['saldo'] < 0 ? 'success' : 'secondary') ?>">
                        $ <?= number_format((float)$r['saldo'], 2) ?>
                    </span>
                </td>
                <td><?= $r['ultima_actividad'] ? date('d/m/Y', strtotime($r['ultima_actividad'])) : '-' ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/ctacte/cliente/<?= (int)$r['cliente_id'] ?><?= $r['cliente_id'] == 9999 && !empty($r['cliente_nombre']) ? '?nombre=' . urlencode($r['cliente_nombre']) : '' ?>"
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver movimientos
                    </a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<span class="text-muted small"><?= count($resumenClientes) ?> clientes con movimientos</span>

<?php else: ?>

<!-- ==================== MODO B: EXTRACTO DE UN CLIENTE ==================== -->
<?php
    $nombreCliente = $clienteData['razon_social'] ?? ($clienteNombre ?: 'Cliente Ocasional');
    $cuitCliente   = $clienteData['cuit'] ?? '';
    $titleParam    = $clienteId === 9999 && !empty($clienteNombre) ? '&nombre=' . urlencode($clienteNombre) : '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">
        Extracto: <?= htmlspecialchars($nombreCliente) ?>
        <?php if (!empty($cuitCliente)): ?>
        <small class="text-muted fs-6">(CUIT: <?= htmlspecialchars($cuitCliente) ?>)</small>
        <?php endif; ?>
    </h1>
    <a href="<?= BASE_URL ?>/ctacte/cliente" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver al desglose
    </a>
</div>

<!-- Resumen del cliente -->
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
                <div class="small text-muted">Saldo Actual</div>
                <div class="fs-5 fw-bold <?= (float)$resumen['saldo'] > 0 ? 'text-danger' : 'text-success' ?>">
                    $ <?= number_format((float)$resumen['saldo'], 2) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros del extracto -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
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
                <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Origen, referencia..." value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <?php if ($clienteId === 9999 && !empty($clienteNombre)): ?>
                <input type="hidden" name="nombre" value="<?= htmlspecialchars($clienteNombre) ?>">
                <?php endif; ?>
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/ctacte/cliente/<?= $clienteId ?><?= $titleParam ?>" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="table-scroll mb-2">
    <table class="table table-bordered table-striped table-sm mb-0">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Referencia</th>
                <th>Débito</th>
                <th>Crédito</th>
                <th>Saldo</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimientos)): ?>
            <tr>
                <td colspan="8" class="text-center text-muted">No hay movimientos que coincidan con los filtros</td>
            </tr>
            <?php else: foreach ($movimientos as $m): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                <td>
                    <span class="badge bg-<?= $m['tipo'] == 'DEBITO' ? 'danger' : 'success' ?>">
                        <?= $m['tipo'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($m['origen']) ?></td>
                <td>#<?= (int)$m['referencia_id'] ?></td>
                <td class="text-danger"><?= $m['tipo'] == 'DEBITO' ? number_format((float)$m['monto'], 2) : '' ?></td>
                <td class="text-success"><?= $m['tipo'] == 'CREDITO' ? number_format((float)$m['monto'], 2) : '' ?></td>
                <td><strong><?= number_format((float)$m['saldo'], 2) ?></strong></td>
                <td class="small"><?= htmlspecialchars($m['observaciones'] ?? '') ?></td>
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
    <?php $baseUrl = BASE_URL . '/ctacte/cliente/' . $clienteId; require BASE_PATH . '/app/views/layout/pagination.php'; ?>
</div>

<?php endif; ?>
