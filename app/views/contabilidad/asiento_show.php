<?php
$tipoLabels = [
    'OPERACION' => 'bg-primary',
    'APERTURA'  => 'bg-info',
    'CIERRE'    => 'bg-dark',
    'AJUSTE'    => 'bg-warning text-dark',
];
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/contabilidad/asientos" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver al Libro Diario
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-journal-text"></i> Asiento #<?= $asiento['numero'] ?></h3>
    <div>
        <span class="badge <?= $tipoLabels[$asiento['tipo']] ?? 'bg-secondary' ?> fs-6"><?= $asiento['tipo'] ?></span>
        <?php if ($asiento['tipo'] !== 'AJUSTE'): ?>
        <a href="<?= BASE_URL ?>/contabilidad/asiento-anular/<?= $asiento['id'] ?>"
           class="btn btn-outline-danger btn-sm ms-2"
           onclick="return confirm('¿Anular este asiento? Se generará un asiento inverso.')">
            <i class="bi bi-x-circle"></i> Anular
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($asiento['fecha'])) ?></div>
                    <div class="col-md-4"><strong>Registrado por:</strong> <?= htmlspecialchars($asiento['usuario_nombre']) ?></div>
                    <div class="col-md-4"><strong>Creado:</strong> <?= $asiento['created_at'] ?></div>
                </div>
                <div class="mb-3">
                    <strong>Descripción:</strong><br>
                    <?= htmlspecialchars($asiento['descripcion']) ?>
                </div>
                <?php if (!empty($asiento['observaciones'])): ?>
                <div class="mb-3">
                    <strong>Observaciones:</strong><br>
                    <em class="text-muted"><?= htmlspecialchars($asiento['observaciones']) ?></em>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detalle (debe/haber) -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">Detalle del Asiento</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cuenta</th>
                            <th class="text-end">Debe</th>
                            <th class="text-end">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asiento['detalle'] as $d): ?>
                        <tr>
                            <td>
                                <code><?= htmlspecialchars($d['codigo']) ?></code>
                                <?= htmlspecialchars($d['cuenta_nombre']) ?>
                            </td>
                            <td class="text-end"><?= $d['debe'] > 0 ? '$ ' . number_format($d['debe'], 2, ',', '.') : '-' ?></td>
                            <td class="text-end"><?= $d['haber'] > 0 ? '$ ' . number_format($d['haber'], 2, ',', '.') : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <th>TOTALES</th>
                            <th class="text-end">$ <?= number_format($asiento['total_debe'], 2, ',', '.') ?></th>
                            <th class="text-end">$ <?= number_format($asiento['total_haber'], 2, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card <?= $asiento['balanceado'] ? 'bg-success' : 'bg-danger' ?> text-white">
            <div class="card-body text-center">
                <h5><?= $asiento['balanceado'] ? 'Asiento Balanceado' : 'Asiento DESBALANCEADO' ?></h5>
                <?php if (!$asiento['balanceado']): ?>
                <p>Diferencia: $ <?= number_format(abs($asiento['total_debe'] - $asiento['total_haber']), 2, ',', '.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
