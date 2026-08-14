<h4><i class="bi bi-clock-history"></i> Historial de Emails Enviados</h4>
<hr>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= BASE_URL ?>/email/historial" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($filtros['tipo'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="ENVIADO" <?= ($filtros['estado'] ?? '') === 'ENVIADO' ? 'selected' : '' ?>>Enviado</option>
                    <option value="ERROR" <?= ($filtros['estado'] ?? '') === 'ERROR' ? 'selected' : '' ?>>Error</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm" 
                       value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>" placeholder="Email o asunto...">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" 
                       value="<?= $filtros['fecha_desde'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" 
                       value="<?= $filtros['fecha_hasta'] ?? '' ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Destinatario</th>
                    <th>Asunto</th>
                    <th class="text-center">Estado</th>
                    <th>Usuario</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emails as $e): ?>
                <tr class="<?= $e['estado'] === 'ERROR' ? 'table-danger' : '' ?>">
                    <td class="small"><?= date('d/m/Y H:i', strtotime($e['enviado_at'])) ?></td>
                    <td>
                        <?php
                        $badgeClass = match($e['tipo'] ?? '') {
                            'REMITO' => 'bg-primary',
                            'PAGO' => 'bg-success',
                            'PRESUPUESTO' => 'bg-info',
                            'NOTA_PEDIDO' => 'bg-warning text-dark',
                            'FACTURA' => 'bg-danger',
                            'ORDEN_COMPRA' => 'bg-secondary',
                            default => 'bg-dark'
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $e['tipo'] ?></span>
                    </td>
                    <td class="small"><?= htmlspecialchars($e['email_destino']) ?></td>
                    <td class="small"><?= htmlspecialchars($e['asunto']) ?></td>
                    <td class="text-center">
                        <?php if ($e['estado'] === 'ENVIADO'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Enviado</span>
                        <?php else: ?>
                            <span class="badge bg-danger" title="<?= htmlspecialchars($e['error'] ?? '') ?>">
                                <i class="bi bi-x-lg"></i> Error
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($e['usuario_nombre'] ?? '-') ?></td>
                    <td class="small text-danger">
                        <?= $e['error'] ? htmlspecialchars(substr($e['error'], 0, 80)) . (strlen($e['error']) > 80 ? '...' : '') : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($emails)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No hay emails registrados</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
