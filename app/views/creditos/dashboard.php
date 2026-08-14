<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-speedometer2"></i> Dashboard Creditos</h3>
    <div>
        <a href="<?= BASE_URL ?>/creditos/create" class="btn btn-success">
            <i class="bi bi-plus-lg"></i> Nuevo Credito
        </a>
        <a href="<?= BASE_URL ?>/creditos" class="btn btn-secondary">
            <i class="bi bi-list"></i> Ver Todos
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6><i class="bi bi-bank"></i> Total Creditos</h6>
                <h2><?= $creditos['total_creditos'] ?? 0 ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6><i class="bi bi-check-circle"></i> Activos</h6>
                <h2><?= $creditos['creditos_activos'] ?? 0 ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6><i class="bi bi-cash-stack"></i> Saldo Pendiente</h6>
                <h5>$ <?= number_format($creditos['saldo_total'] ?? 0, 2, ',', '.') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6><i class="bi bi-calendar"></i> Prox. Cuotas (Pend.)</h6>
                <h5>$ <?= number_format($cuotas['cuotas_pendientes_monto'] ?? 0, 2, ',', '.') ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Proximas Cuotas a Pagar</h5>
    </div>
    <div class="card-body">
        <?php if (empty($proximas)): ?>
            <p class="text-muted">No hay cuotas pendientes.</p>
        <?php else: ?>
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Credito</th>
                    <th>Entidad</th>
                    <th>Cuota #</th>
                    <th>Monto</th>
                    <th>Vencimiento</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proximas as $p): ?>
                <tr>
                    <td>#<?= $p['credito_id'] ?></td>
                    <td><?= htmlspecialchars($p['entidad']) ?></td>
                    <td><?= $p['numero_cuota'] ?></td>
                    <td>$ <?= number_format($p['monto'], 2, ',', '.') ?></td>
                    <td>
                        <?php
                        $venc = new DateTime($p['fecha_vencimiento']);
                        $hoy = new DateTime();
                        $dias = $hoy->diff($venc)->days;
                        $clase = '';
                        if ($venc < $hoy) $clase = 'text-danger fw-bold';
                        elseif ($dias <= 7) $clase = 'text-warning fw-bold';
                        ?>
                        <span class="<?= $clase ?>"><?= date('d/m/Y', strtotime($p['fecha_vencimiento'])) ?></span>
                        <?php if ($venc < $hoy): ?>
                            <small class="text-danger">(Vencida)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/creditos/show/<?= $p['credito_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
