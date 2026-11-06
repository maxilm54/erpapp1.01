<?php
/**
 * Widget de alertas de cheques. Se incluye en Cobros y Ventas No Cobradas.
 * Espera: $cheques_resumen (array) y opcionalmente $cheques_por_vencer.
 */
$chequesResumen = $cheques_resumen ?? null;
$chequesPorVencer = $cheques_por_vencer ?? [];

if (is_array($chequesResumen)):
    $enCarteraCant = (int)($chequesResumen['en_cartera_cant'] ?? 0);
    $enCarteraMonto = (float)($chequesResumen['en_cartera_monto'] ?? 0);
    $depositadosCant = (int)($chequesResumen['depositados_cant'] ?? 0);
    $depositadosMonto = (float)($chequesResumen['depositados_monto'] ?? 0);
    $pendientesCant = $enCarteraCant + $depositadosCant;
    $pendientesMonto = $enCarteraMonto + $depositadosMonto;
    $vencidosCant = (int)($chequesResumen['vencidos_cant'] ?? 0);
    $vencidosMonto = (float)($chequesResumen['vencidos_monto'] ?? 0);
    $porVencerCant = (int)($chequesResumen['por_vencer_cant'] ?? 0);
    $porVencerMonto = (float)($chequesResumen['por_vencer_monto'] ?? 0);
?>
<?php if ($pendientesCant > 0 || $vencidosCant > 0 || $porVencerCant > 0): ?>
<div class="alert <?= $vencidosCant > 0 ? 'alert-danger' : ($porVencerCant > 0 ? 'alert-warning' : 'alert-info') ?> d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <strong><i class="bi bi-calendar-check"></i> Cheques pendientes de acreditación:</strong>
        <?= $pendientesCant ?> por $ <?= number_format($pendientesMonto, 2, ',', '.') ?>
        <span class="text-muted small">(en cartera <?= $enCarteraCant ?> / depositados <?= $depositadosCant ?>)</span>

        <?php if ($vencidosCant > 0): ?>
            <span class="badge bg-danger ms-2">
                <?= $vencidosCant ?> vencido<?= $vencidosCant === 1 ? '' : 's' ?> ($ <?= number_format($vencidosMonto, 2, ',', '.') ?>)
            </span>
        <?php endif; ?>

        <?php if ($porVencerCant > 0): ?>
            <span class="badge bg-warning text-dark ms-1">
                <?= $porVencerCant ?> por vencer en 30 días ($ <?= number_format($porVencerMonto, 2, ',', '.') ?>)
            </span>
        <?php endif; ?>

        <?php if (!empty($chequesPorVencer)): ?>
            <div class="small mt-1">
                <?php foreach (array_slice($chequesPorVencer, 0, 3) as $ch): ?>
                    <?php $dias = (int)($ch['dias_restantes'] ?? 0); ?>
                    <span class="me-2">
                        <i class="bi bi-upc-scan"></i>
                        <?= htmlspecialchars($ch['banco'] ?? '') ?> <?= htmlspecialchars($ch['numero'] ?? '') ?>
                        — vence <?= date('d/m/Y', strtotime($ch['fecha_vencimiento'])) ?>
                        <?php if ($dias < 0): ?>
                            <span class="text-danger fw-bold">(<?= abs($dias) ?> días vencido)</span>
                        <?php elseif ($dias === 0): ?>
                            <span class="text-danger fw-bold">(vence hoy)</span>
                        <?php else: ?>
                            <span class="text-muted">(en <?= $dias ?> días)</span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($vencidosCant > 0): ?>
            <a href="<?= BASE_URL ?>/cheques?vencimiento=vencidos" class="btn btn-sm btn-danger">
                <i class="bi bi-exclamation-triangle"></i> Ver vencidos
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/cheques" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-wallet2"></i> Cartera de cheques
        </a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
