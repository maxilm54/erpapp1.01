<?php
$cheques = $cheques ?? [];
$resumen = $resumen ?? [];
$filtros = $filtros ?? [];
$estados = $estados ?? [];

$badgeEstado = function (string $estado): string {
    return [
        'EN_CARTERA' => 'bg-primary',
        'DEPOSITADO' => 'bg-info text-dark',
        'COBRADO'    => 'bg-success',
        'ENDOSADO'   => 'bg-dark',
        'RECHAZADO'  => 'bg-danger',
        'ANULADO'    => 'bg-secondary',
    ][$estado] ?? 'bg-secondary';
};
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><i class="bi bi-wallet2"></i> Cartera de Cheques</h3>
        <small class="text-muted">
            Cheques de terceros recibidos en cobranzas. La cartera es un activo:
            no suma a caja/banco hasta que se acredita o se endosa.
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/cobros" class="btn btn-outline-secondary">
            <i class="bi bi-cash-stack"></i> Cobros
        </a>
        <a href="<?= BASE_URL ?>/cobros/create" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> Nuevo Cobro
        </a>
    </div>
</div>

<!-- Resumen: los tres estados financieros del cheque -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-primary">
            <div class="card-body">
                <small class="text-muted d-block">En cartera (activo)</small>
                <h4 class="mb-0"><?= (int)($resumen['en_cartera_cant'] ?? 0) ?></h4>
                <span class="text-primary fw-bold">$ <?= number_format((float)($resumen['en_cartera_monto'] ?? 0), 2, ',', '.') ?></span>
                <small class="text-muted d-block mt-1">No suma a caja/banco</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-info">
            <div class="card-body">
                <small class="text-muted d-block">Depositados en tránsito</small>
                <h4 class="mb-0"><?= (int)($resumen['depositados_cant'] ?? 0) ?></h4>
                <span class="text-info fw-bold">$ <?= number_format((float)($resumen['depositados_monto'] ?? 0), 2, ',', '.') ?></span>
                <small class="text-muted d-block mt-1">Todavía no acreditados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-dark">
            <div class="card-body">
                <small class="text-muted d-block">Endosados a proveedores</small>
                <h4 class="mb-0"><?= (int)($resumen['endosados_cant'] ?? 0) ?></h4>
                <span class="text-dark fw-bold">$ <?= number_format((float)($resumen['endosados_monto'] ?? 0), 2, ',', '.') ?></span>
                <small class="text-muted d-block mt-1">Cancelan deuda, sin banco</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-success">
            <div class="card-body">
                <small class="text-muted d-block">Cobrados este mes</small>
                <h4 class="mb-0"><?= (int)($resumen['cobrados_mes_cant'] ?? 0) ?></h4>
                <span class="text-success fw-bold">$ <?= number_format((float)($resumen['cobrados_mes_monto'] ?? 0), 2, ',', '.') ?></span>
                <small class="text-muted d-block mt-1">Ya suman a disponibilidad</small>
            </div>
        </div>
    </div>
</div>

<?php if ((int)($resumen['por_vencer_cant'] ?? 0) > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-clock-history fs-4"></i>
    <div>
        Hay <strong><?= (int)$resumen['por_vencer_cant'] ?></strong> cheque(s) en cartera/depositados por
        <strong>$ <?= number_format((float)$resumen['por_vencer_monto'], 2, ',', '.') ?></strong>
        que vencen en los próximos 30 días.
    </div>
</div>
<?php endif; ?>

<?php if ((int)($resumen['vencidos_cant'] ?? 0) > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        Hay <strong><?= (int)$resumen['vencidos_cant'] ?></strong> cheque(s) vencido(s) por
        <strong>$ <?= number_format((float)$resumen['vencidos_monto'], 2, ',', '.') ?></strong>.
        Revisá si ya fueron acreditados o si hay que rechazarlos.
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/cheques">
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
                    <label class="form-label small mb-1">Vencimiento</label>
                    <select name="vencimiento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="vencidos" <?= ($filtros['vencimiento'] ?? '') === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                        <option value="hoy" <?= ($filtros['vencimiento'] ?? '') === 'hoy' ? 'selected' : '' ?>>Vencen hoy</option>
                        <option value="proximos" <?= ($filtros['vencimiento'] ?? '') === 'proximos' ? 'selected' : '' ?>>Próximos 30 días</option>
                        <option value="mes" <?= ($filtros['vencimiento'] ?? '') === 'mes' ? 'selected' : '' ?>>Este mes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Texto</label>
                    <input type="text" name="texto" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filtros['texto'] ?? '') ?>"
                           placeholder="Nº, banco o cliente">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Vence desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Vence hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="<?= BASE_URL ?>/cheques" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Cheques</h5>
        <span class="badge bg-dark"><?= count($cheques) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($cheques)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2 mb-0">No hay cheques para los filtros elegidos.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Vencimiento</th>
                        <th>Cliente</th>
                        <th>Banco / Nº</th>
                        <th>Tipo</th>
                        <th class="text-end">Monto</th>
                        <th>Estado</th>
                        <th>Cobro</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cheques as $ch):
                    $dias = (int)($ch['dias_restantes'] ?? 0);
                    $pendiente = in_array(($ch['estado'] ?? ''), ['EN_CARTERA', 'DEPOSITADO'], true);
                    $claseFila = '';
                    if ($pendiente && $dias < 0) {
                        $claseFila = 'table-danger';
                    } elseif ($pendiente && $dias <= 7) {
                        $claseFila = 'table-warning';
                    }
                ?>
                    <tr class="<?= $claseFila ?>">
                        <td>
                            <div><?= date('d/m/Y', strtotime($ch['fecha_vencimiento'])) ?></div>
                            <?php if ($pendiente): ?>
                                <?php if ($dias < 0): ?>
                                    <span class="badge bg-danger"><?= abs($dias) ?> días vencido</span>
                                <?php elseif ($dias === 0): ?>
                                    <span class="badge bg-danger">Vence hoy</span>
                                <?php elseif ($dias <= 7): ?>
                                    <span class="badge bg-warning text-dark">En <?= $dias ?> días</span>
                                <?php else: ?>
                                    <small class="text-muted">En <?= $dias ?> días</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ch['nombre_cliente'] ?? 'N/D') ?>
                            <?php if (!empty($ch['proveedor_nombre'])): ?>
                                <small class="text-muted d-block">Endosado a: <?= htmlspecialchars($ch['proveedor_nombre']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($ch['banco'] ?? '') ?></div>
                            <small class="text-muted">Nº <?= htmlspecialchars($ch['numero'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= ($ch['tipo'] ?? 'FISICO') === 'ECHEQ' ? 'E-cheq' : 'Físico' ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold">$ <?= number_format((float)$ch['monto'], 2, ',', '.') ?></td>
                        <td>
                            <span class="badge <?= $badgeEstado($ch['estado'] ?? '') ?>">
                                <?= htmlspecialchars($estados[$ch['estado'] ?? ''] ?? $ch['estado'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($ch['pago_id'])): ?>
                                <a href="<?= BASE_URL ?>/cobros/show/<?= (int)$ch['pago_id'] ?>" class="text-decoration-none">
                                    #<?= (int)$ch['pago_id'] ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?= BASE_URL ?>/cheques/show/<?= (int)$ch['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (($ch['estado'] ?? '') !== 'ANULADO'): ?>
                            <a href="<?= BASE_URL ?>/cheques/edit/<?= (int)$ch['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar datos">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
