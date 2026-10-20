<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Cuenta Corriente de la Empresa</h1>
    <div>
        <a href="<?= BASE_URL ?>/cuentacorrienteempresa/create" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Movimiento
        </a>
    </div>
</div>

<!-- Resumen del conjunto filtrado -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Ingresos (entra dinero)</div>
                <div class="fs-5 fw-bold text-success">$ <?= number_format((float)$balance['ingresos'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Gastos (sale dinero)</div>
                <div class="fs-5 fw-bold text-danger">$ <?= number_format((float)$balance['gastos'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Saldo</div>
                <div class="fs-5 fw-bold <?= (float)$balance['balance'] < 0 ? 'text-danger' : 'text-primary' ?>">
                    $ <?= number_format((float)$balance['balance'], 2, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/cuentacorrienteempresa" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="INGRESO" <?= ($filtros['tipo'] ?? '') === 'INGRESO' ? 'selected' : '' ?>>Ingreso</option>
                    <option value="GASTO" <?= ($filtros['tipo'] ?? '') === 'GASTO' ? 'selected' : '' ?>>Gasto</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Categoría</label>
                <select name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (string)($filtros['categoria_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Proveedor</label>
                <select name="proveedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (string)($filtros['proveedor_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['razon_social']) ?>
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
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Descripción..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/cuentacorrienteempresa" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Deuda por proveedor -->
<?php if (!empty($saldos)): ?>
<div class="card mb-3">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="bi bi-people"></i> Deuda por proveedor</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($saldos as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['razon_social']) ?></td>
                    <td class="text-end fw-bold <?= (float)$s['saldo'] > 0 ? 'text-danger' : 'text-success' ?>">
                        $ <?= number_format((float)$s['saldo'], 2, ',', '.') ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/cuentacorrienteempresa?proveedor_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Ver movimientos
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Movimientos -->
<div class="table-scroll mb-2">
    <table class="table table-bordered table-striped table-sm mb-0">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Categoría</th>
                <th>Proveedor</th>
                <th class="text-end">Monto</th>
                <th>Usuario</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($movements)): ?>
            <tr><td colspan="8" class="text-center text-muted">No hay movimientos para este filtro.</td></tr>
        <?php else: foreach ($movements as $m): ?>
            <tr>
                <td><?= htmlspecialchars(substr((string)$m['fecha'], 0, 10)) ?></td>
                <td>
                    <span class="badge <?= $m['tipo'] === 'INGRESO' ? 'bg-success' : 'bg-danger' ?>">
                        <?= $m['tipo'] === 'INGRESO' ? 'Ingreso' : 'Gasto' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($m['descripcion']) ?></td>
                <td><?= htmlspecialchars($m['categoria_nombre'] ?? '-') ?></td>
                <td><?= htmlspecialchars($m['proveedor_nombre'] ?? '-') ?></td>
                <td class="text-end fw-bold">$ <?= number_format((float)$m['monto'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($m['usuario_nombre'] ?? '-') ?></td>
                <td class="text-end">
                    <a href="<?= BASE_URL ?>/cuentacorrienteempresa/edit/<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/cuentacorrienteempresa/delete/<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('¿Anular este movimiento?')">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php $baseUrl = BASE_URL . '/cuentacorrienteempresa'; require BASE_PATH . '/app/views/layout/pagination.php'; ?>
