<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-file-earmark-text"></i> Comprobantes</h4>
    <a href="<?= BASE_URL ?>/sdcomp/create" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Comprobante
    </a>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="VENTA" <?= ($filtros['tipo'] ?? '') === 'VENTA' ? 'selected' : '' ?>>Salida</option>
                    <option value="COMPRA" <?= ($filtros['tipo'] ?? '') === 'COMPRA' ? 'selected' : '' ?>>Entrada</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="PENDIENTE" <?= ($filtros['estado'] ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="PARCIAL" <?= ($filtros['estado'] ?? '') === 'PARCIAL' ? 'selected' : '' ?>>Parcial</option>
                    <option value="COBRADO" <?= ($filtros['estado'] ?? '') === 'COBRADO' ? 'selected' : '' ?>>Cobrado / Pagado</option>
                    <option value="ANULADO" <?= ($filtros['estado'] ?? '') === 'ANULADO' ? 'selected' : '' ?>>Anulado</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= $filtros['fecha_desde'] ?? '' ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= $filtros['fecha_hasta'] ?? '' ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Cliente/Proveedor..." value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/sdcomp" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="table-responsive">
    <table class="table table-sm table-striped table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Cliente / Proveedor</th>
                <th>CUIT</th>
                <th>Descripcion</th>
                <th>Monto Total</th>
                <th>Saldo Pend.</th>
                <th>Estado</th>
                <th>Pagos</th>
                <th>Usuario</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimientos)): ?>
            <tr>
                <td colspan="12" class="text-center text-muted">No hay comprobantes registrados</td>
            </tr>
            <?php else: ?>
            <?php foreach ($movimientos as $m):
                $esVenta = $m['tipo'] === 'VENTA';
            ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                <td>
                    <span class="badge <?= $esVenta ? 'bg-info' : 'bg-warning text-dark' ?>">
                        <?= $esVenta ? 'Salida' : 'Entrada' ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($m['razon_social'] ?? '-') ?></td>
                <td><?= htmlspecialchars($m['cuit'] ?? '-') ?></td>
                <td><?= htmlspecialchars(substr($m['descripcion'] ?? '', 0, 40)) ?></td>
                <td class="text-end">$ <?= number_format($m['monto_total'], 2, ',', '.') ?></td>
                <td class="text-end">$ <?= number_format($m['saldo_pendiente'], 2, ',', '.') ?></td>
                <td>
                    <?php
                    $estadoLabel = $m['estado'];
                    if ($m['estado'] === 'COBRADO') $estadoLabel = $esVenta ? 'COBRADO' : 'PAGADO';

                    $estados = [
                        'PENDIENTE' => 'bg-danger',
                        'PARCIAL'   => 'bg-warning text-dark',
                        'COBRADO'   => 'bg-success',
                        'ANULADO'   => 'bg-secondary'
                    ];
                    $badge = $estados[$m['estado']] ?? 'bg-secondary';
                    ?>
                    <span class="badge <?= $badge ?>"><?= $estadoLabel ?></span>
                </td>
                <td class="text-center"><?= $m['cantidad_pagos'] ?></td>
                <td><?= htmlspecialchars($m['usuario_nombre']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/sdcomp/show/<?= $m['id'] ?>" class="btn btn-outline-primary btn-sm" title="Ver">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
