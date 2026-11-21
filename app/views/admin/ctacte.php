<?php
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
$estadoBadge = function (string $estado): string {
    return match ($estado) {
        'SALDADO' => '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Saldado</span>',
        'PARCIAL' => '<span class="badge bg-info text-dark"><i class="bi bi-hourglass-split"></i> Parcial</span>',
        default   => '<span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pendiente</span>',
    };
};
$hoy = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($title) ?></h3>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/ctacte-pago" class="btn btn-success btn-sm">
            <i class="bi bi-cash-stack"></i> Registrar pago
        </a>
        <a href="<?= BASE_URL ?>/admin/ctacte-create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo registro
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    <a href="<?= BASE_URL ?>/admin/migrations" class="alert-link">Ir a Migraciones</a>
</div>
<?php endif; ?>

<!-- Resumen -->
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card border-primary h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Facturado</div>
                <div class="fs-5 fw-bold text-primary">$<?= $fmt($resumen['facturado']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Cobrado</div>
                <div class="fs-5 fw-bold text-success">$<?= $fmt($resumen['cobrado']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Saldo pendiente (<?= (int)$resumen['pendientes'] ?> registro/s)</div>
                <div class="fs-5 fw-bold text-warning">$<?= $fmt($resumen['saldo']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Vencido sin cobrar</div>
                <div class="fs-5 fw-bold text-danger">$<?= $fmt($resumen['vencido']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Generar mes -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="POST" action="<?= BASE_URL ?>/admin/ctacte-generar"
              class="row g-2 align-items-end"
              onsubmit="return confirm('¿Generar los registros del mes para todas las empresas activas? Los que ya existen no se duplican.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="col-auto">
                <label class="form-label mb-0 small">Generar periodo</label>
                <input type="month" name="periodo" class="form-control form-control-sm" value="<?= htmlspecialchars($mesActual) ?>" required>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar-plus"></i> Generar registros del mes
                </button>
            </div>
            <div class="col">
                <div class="form-text mb-0">
                    Cada empresa recibe un registro con fecha de alta el dia 1 y vencimiento el dia 10.
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/admin/ctacte" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Empresa</label>
                <select name="tenant_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (string)($filtros['tenant_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['PENDIENTE' => 'Pendiente', 'PARCIAL' => 'Parcial', 'SALDADO' => 'Saldado'] as $val => $txt): ?>
                    <option value="<?= $val ?>" <?= ($filtros['estado'] ?? '') === $val ? 'selected' : '' ?>><?= $txt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Periodo</label>
                <input type="month" name="periodo" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['periodo'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Empresa u observacion..."
                       value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="vencidos" value="1" id="chkVencidos"
                           <?= !empty($filtros['vencidos']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="chkVencidos">Solo vencidos</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/admin/ctacte" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Registros -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Registros (<?= count($registros) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($registros)): ?>
            <p class="text-muted p-3 mb-0">No hay registros para este filtro.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Periodo</th>
                    <th>Empresa</th>
                    <th>Alta</th>
                    <th>Vencimiento</th>
                    <th>Conceptos</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th>Estado</th>
                    <th>Obs.</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r):
                $saldo = (float)$r['saldo_pendiente'];
                $vencido = $saldo > 0 && $r['fecha_vencimiento'] < $hoy;
                $partes = explode('-', $r['periodo']);
                $periodoTxt = ($partes[1] ?? '') . '/' . ($partes[0] ?? '');
                $total = (float)$r['sistema'] + (float)$r['soporte'] + (float)$r['desarrollo'];
            ?>
                <tr class="<?= $saldo <= 0 ? 'table-light' : '' ?>">
                    <td class="fw-semibold"><?= htmlspecialchars($periodoTxt) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/ctacte?tenant_id=<?= (int)$r['tenant_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($r['tenant_nombre']) ?>
                        </a>
                        <div class="small text-muted"><code><?= htmlspecialchars($r['tenant_dbname']) ?></code></div>
                    </td>
                    <td><?= date('d/m/Y', strtotime($r['fecha_alta'])) ?></td>
                    <td>
                        <?= date('d/m/Y', strtotime($r['fecha_vencimiento'])) ?>
                        <?php if ($vencido): ?>
                            <div><span class="badge bg-danger">Vencido</span></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <div>Sistema: <span class="fw-semibold">$<?= $fmt($r['sistema']) ?></span></div>
                        <div>Soporte: <span class="fw-semibold">$<?= $fmt($r['soporte']) ?></span></div>
                        <div>Desarrollo: <span class="fw-semibold">$<?= $fmt($r['desarrollo']) ?></span></div>
                    </td>
                    <td class="text-end fw-semibold">$<?= $fmt($total) ?></td>
                    <td class="text-end text-success">$<?= $fmt($r['pago'] ?? 0) ?></td>
                    <td class="text-end fw-bold <?= $saldo > 0 ? 'text-warning' : 'text-muted' ?>">$<?= $fmt($saldo) ?></td>
                    <td><?= $estadoBadge((string)$r['estado']) ?></td>
                    <td class="small" style="max-width:180px">
                        <?= $r['obs'] !== null && $r['obs'] !== '' ? nl2br(htmlspecialchars($r['obs'])) : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= BASE_URL ?>/admin/ctacte-edit/<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= BASE_URL ?>/admin/ctacte-delete/<?= (int)$r['id'] ?>" style="display:inline"
                              onsubmit="return confirm('¿Eliminar este registro de cuenta corriente?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"
                                    <?= (float)($r['pago'] ?? 0) > 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagos registrados (incluye los anulados, que no se borran) -->
<div class="card">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-receipt"></i> Pagos registrados</h5>
        <span class="small">
            <?= count(array_filter($pagos, fn($p) => (int)($p['activo'] ?? 1) === 1)) ?> activo(s)
            &middot;
            <?= count(array_filter($pagos, fn($p) => (int)($p['activo'] ?? 1) === 0)) ?> anulado(s)
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pagos)): ?>
            <p class="text-muted p-3 mb-0">Todavia no hay pagos registrados.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th class="text-end">Monto</th>
                    <th>Observacion</th>
                    <th>Estado</th>
                    <th>Anulado</th>
                    <th>Registrado por</th>
                    <th>Cargado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pagos as $p):
                $activo = (int)($p['activo'] ?? 1) === 1;
            ?>
                <tr class="<?= $activo ? '' : 'table-secondary' ?>">
                    <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                    <td><?= htmlspecialchars($p['tenant_nombre']) ?></td>
                    <td class="text-end fw-semibold <?= $activo ? 'text-success' : 'text-muted text-decoration-line-through' ?>">
                        $<?= $fmt($p['monto']) ?>
                    </td>
                    <td class="small"><?= $p['obs'] !== null && $p['obs'] !== '' ? nl2br(htmlspecialchars($p['obs'])) : '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <?php if ($activo): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Anulado</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if (!$activo && !empty($p['anulado_at'])): ?>
                            <div class="fw-semibold"><?= date('d/m/Y H:i', strtotime($p['anulado_at'])) ?></div>
                            <div>por <?= htmlspecialchars($p['anulado_por_nombre'] ?? '-') ?></div>
                            <?php if (!empty($p['motivo_anulacion'])): ?>
                                <div class="fst-italic text-muted"><?= nl2br(htmlspecialchars($p['motivo_anulacion'])) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($p['usuario_nombre'] ?? '-') ?></td>
                    <td class="small text-muted"><?= htmlspecialchars(substr((string)$p['created_at'], 0, 16)) ?></td>
                    <td class="text-end">
                        <?php if ($activo): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Anular pago"
                                    data-bs-toggle="modal" data-bs-target="#modalAnularPago"
                                    data-pago-id="<?= (int)$p['id'] ?>"
                                    data-pago-info="N° <?= (int)$p['id'] ?> — <?= htmlspecialchars($p['tenant_nombre']) ?> — $<?= $fmt($p['monto']) ?> (<?= date('d/m/Y', strtotime($p['fecha'])) ?>)">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        <?php else: ?>
                            <span class="text-muted small">Anulado</span>
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

<?php if (!empty($pagos)): ?>
<!-- Modal para anular un pago pidiendo el motivo -->
<div class="modal fade" id="modalAnularPago" tabindex="-1" aria-labelledby="modalAnularPagoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formAnularPago" action="#">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalAnularPagoLabel"><i class="bi bi-x-circle"></i> Anular pago</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Pago: <strong id="infoAnularPago">-</strong></p>
                    <div class="alert alert-warning small py-2">
                        El pago <strong>no se elimina</strong>: queda en el historial como
                        <strong>Anulado</strong> y el monto vuelve al saldo de los registros.
                    </div>
                    <label class="form-label">Motivo de la anulacion *</label>
                    <textarea name="motivo" id="motivoAnulacion" class="form-control" rows="3" maxlength="500" required
                              placeholder="Ej: pago duplicado, cheque rechazado, error de carga..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger"><i class="bi bi-x-circle"></i> Anular pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalAnularPago');
    if (!modal) return;

    const form = document.getElementById('formAnularPago');
    const info = document.getElementById('infoAnularPago');
    const motivo = document.getElementById('motivoAnulacion');

    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        form.action = <?= json_encode(BASE_URL) ?> + '/admin/ctacte-pago-anular/' + btn.dataset.pagoId;
        info.textContent = btn.dataset.pagoInfo || '';
        motivo.value = '';
    });

    modal.addEventListener('shown.bs.modal', function () {
        motivo.focus();
    });
})();
</script>
<?php endif; ?>
