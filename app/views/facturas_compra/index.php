<?php $facturas = $facturas ?? []; $filtros = $filtros ?? []; $estados = $estados ?? []; $proveedores = $proveedores ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3><i class="bi bi-receipt"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/facturascompra/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Registrar Factura
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/facturascompra">
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
                <div class="col-md-3">
                    <label class="form-label small mb-1">Proveedor</label>
                    <select name="proveedor_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (int)($filtros['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['razon_social']) ?>
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
                <div class="col-md-2">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" name="texto" class="form-control form-control-sm" placeholder="Nº o proveedor" value="<?= htmlspecialchars($filtros['texto'] ?? '') ?>">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($facturas)): ?>
    <div class="alert alert-info">No hay facturas registradas con este filtro.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Proveedor</th>
                <th>Factura</th>
                <th>Fecha</th>
                <th>OC</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pagado</th>
                <th class="text-end">Saldo</th>
                <th>Estado</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($facturas as $fc): ?>
            <?php
                $esAnulada = ($fc['estado'] ?? '') === 'ANULADA';
                $badge = $esAnulada ? 'bg-secondary' : 'bg-success';
            ?>
            <tr <?= $esAnulada ? 'class="text-muted"' : '' ?>>
                <td><?= (int)$fc['id'] ?></td>
                <td><?= htmlspecialchars($fc['proveedor_nombre'] ?? 'N/D') ?></td>
                <td>
                    <?= htmlspecialchars(($fc['tipo_fiscal'] ?? '') !== '' ? $fc['tipo_fiscal'] . ' ' : '') ?>
                    <?= htmlspecialchars($fc['numero']) ?>
                </td>
                <td><?= date('d/m/Y', strtotime($fc['fecha_factura'])) ?></td>
                <td>
                    <?php if (!empty($fc['orden_compra_id'])): ?>
                        <a href="<?= BASE_URL ?>/ordenescompra/show/<?= (int)$fc['orden_compra_id'] ?>">OC #<?= (int)$fc['orden_compra_id'] ?></a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= number_format((float)$fc['monto_total'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float)$fc['pagado_comprometido'], 2, ',', '.') ?></td>
                <td class="text-end fw-bold <?= (float)$fc['saldo'] > 0 && !$esAnulada ? 'text-danger' : '' ?>">
                    <?= $esAnulada ? '-' : number_format((float)$fc['saldo'], 2, ',', '.') ?>
                </td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars(FacturaCompra::ESTADOS[$fc['estado']] ?? $fc['estado']) ?></span></td>
                <td class="text-center">
                    <a href="<?= BASE_URL ?>/facturascompra/show/<?= (int)$fc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
