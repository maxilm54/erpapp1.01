<?php $ordenes = $ordenes ?? []; $filtros = $filtros ?? []; $estados = $estados ?? []; $resumen = $resumen ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/ordenespago/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nueva Orden de Pago
    </a>
</div>

<?php if (!empty($resumen)): ?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-secondary">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">En borrador</div>
                <div class="fs-5 fw-bold"><?= (int)$resumen['borrador_cant'] ?> por $ <?= number_format((float)$resumen['borrador_monto'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Aprobadas por pagar</div>
                <div class="fs-5 fw-bold"><?= (int)$resumen['aprobadas_cant'] ?> por $ <?= number_format((float)$resumen['aprobadas_monto'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Pagadas este mes</div>
                <div class="fs-5 fw-bold"><?= (int)$resumen['pagadas_mes_cant'] ?> por $ <?= number_format((float)$resumen['pagadas_mes_monto'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/ordenespago">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $clave => $etiqueta): ?>
                            <option value="<?= $clave ?>" <?= ($filtros['estado'] ?? '') === $clave ? 'selected' : '' ?>>
                                <?= htmlspecialchars($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="texto" class="form-control form-control-sm" placeholder="Proveedor o factura" value="<?= htmlspecialchars($filtros['texto'] ?? '') ?>">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($ordenes)): ?>
    <div class="alert alert-info">No hay órdenes de pago con este filtro.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Número</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Fecha</th>
                <th class="text-end">Monto</th>
                <th>Medio de pago</th>
                <th>Caja/Banco</th>
                <th>Estado</th>
                <th class="text-center">Acción</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ordenes as $op): ?>
            <?php
                $badges = ['BORRADOR' => 'bg-secondary', 'APROBADA' => 'bg-warning text-dark', 'PAGADA' => 'bg-success', 'ANULADA' => 'bg-danger'];
                $badgeOp = $badges[$op['estado']] ?? 'bg-secondary';
            ?>
            <tr <?= ($op['estado'] ?? '') === 'ANULADA' ? 'class="text-muted"' : '' ?>>
                <td><strong>OP-<?= str_pad((string)$op['numero'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                <td><?= htmlspecialchars($op['proveedor_nombre'] ?? 'N/D') ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/facturascompra/show/<?= (int)$op['factura_compra_id'] ?>">
                        <?= htmlspecialchars(($op['factura_tipo'] ?? '') !== '' ? $op['factura_tipo'] . ' ' : '') ?><?= htmlspecialchars($op['factura_numero'] ?? '') ?>
                    </a>
                </td>
                <td><?= date('d/m/Y', strtotime($op['fecha'])) ?></td>
                <td class="text-end"><?= number_format((float)$op['monto'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($op['medio_pago']) ?></td>
                <td><?= htmlspecialchars($op['caja_nombre'] ?? '-') ?></td>
                <td><span class="badge <?= $badgeOp ?>"><?= htmlspecialchars(OrdenPago::ESTADOS[$op['estado']] ?? $op['estado']) ?></span></td>
                <td class="text-center">
                    <a href="<?= BASE_URL ?>/ordenespago/show/<?= (int)$op['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
