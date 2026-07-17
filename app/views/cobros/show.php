<?php
$cobro = $cobro ?? [];
$anulado = !empty($cobro['anulado']);
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/cobros" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Cobros
    </a>
</div>

<h3><i class="bi bi-cash-coin"></i> Cobro #<?= $cobro['id'] ?></h3>

<?php if ($anulado): ?>
    <span class="badge bg-danger mb-2 fs-6">ANULADO</span>
<?php else: ?>
    <span class="badge bg-success mb-2 fs-6">ACTIVO</span>
<?php endif; ?>

<!-- Datos del cobro -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Datos del Cobro</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Fecha:</strong><br>
                <?= date('d/m/Y H:i', strtotime($cobro['created_at'])) ?>
            </div>
            <div class="col-md-3">
                <strong>Cliente:</strong><br>
                <?= htmlspecialchars($cobro['nombre_cliente'] ?? 'N/D') ?>
            </div>
            <div class="col-md-3">
                <strong>Monto:</strong><br>
                <span class="fs-4 fw-bold <?= $anulado ? 'text-decoration-line-through text-muted' : 'text-success' ?>">
                    $ <?= number_format((float)$cobro['monto'], 2, ',', '.') ?>
                </span>
            </div>
            <div class="col-md-3">
                <strong>Medio de Pago:</strong><br>
                <?= htmlspecialchars($cobro['medio_pago'] ?? '-') ?>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3">
                <strong>Caja / Banco:</strong><br>
                <?= htmlspecialchars($cobro['caja_nombre'] ?? 'Sin asignar') ?>
            </div>
            <div class="col-md-3">
                <strong>Remito:</strong><br>
                <?php if (!empty($cobro['remito_id'])): ?>
                    <a href="<?= BASE_URL ?>/remito-show/<?= $cobro['remito_id'] ?>" class="text-decoration-none">
                        #<?= $cobro['remito_id'] ?> <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <strong>Usuario:</strong><br>
                <?= htmlspecialchars($cobro['usuario_id'] ?? '-') ?>
            </div>
            <div class="col-md-3">
                <strong>Observaciones:</strong><br>
                <?= nl2br(htmlspecialchars($cobro['observaciones'] ?? '-')) ?>
            </div>
        </div>
    </div>
</div>

<!-- Comprobante PDF -->
<?php if (!empty($cobro['pdf_path']) && file_exists(BASE_PATH . '/' . $cobro['pdf_path'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-file-pdf"></i> Comprobante</h5>
    </div>
    <div class="card-body">
        <a href="<?= BASE_URL . '/' . $cobro['pdf_path'] ?>" target="_blank" class="btn btn-outline-danger">
            <i class="bi bi-file-pdf"></i> Descargar Comprobante PDF
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Acciones -->
<div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/cobros" class="btn btn-secondary">Volver</a>
    <?php if (!$anulado): ?>
        <form method="POST" action="<?= BASE_URL ?>/cobros/anular/<?= $cobro['id'] ?>" class="d-inline"
              onsubmit="return confirm('¿Está seguro de anular este cobro? Se revertirá el movimiento en ctacte y caja.')">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-x-circle"></i> Anular Cobro
            </button>
        </form>
    <?php endif; ?>
</div>
