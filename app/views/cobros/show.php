<?php
$cobro = $cobro ?? [];
$anulado = !empty($cobro['anulado']);
$cobroExito = $_SESSION['cobro_exito'] ?? null;
unset($_SESSION['cobro_exito']);
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
                    <a href="<?= BASE_URL ?>/remitossalida/show/<?= $cobro['remito_id'] ?>" class="text-decoration-none">
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
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-file-pdf"></i> Comprobante de Pago</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($cobro['pdf_path']) && file_exists($cobro['pdf_path'])): ?>
            <a href="<?= BASE_URL ?>/cobros/pdf/<?= $cobro['id'] ?>" target="_blank" class="btn btn-outline-danger">
                <i class="bi bi-file-pdf"></i> Ver / Descargar PDF
            </a>
            <a href="<?= BASE_URL ?>/cobros/regenerar-pdf/<?= $cobro['id'] ?>" class="btn btn-outline-warning"
               onclick="return confirm('¿Regenerar el comprobante PDF? Se sobreescribirá el actual.')">
                <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/cobros/regenerar-pdf/<?= $cobro['id'] ?>" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-plus"></i> Generar PDF
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Acciones -->
<div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/cobros" class="btn btn-secondary">Volver</a>
    <?php if (!$anulado): ?>
        <a href="<?= BASE_URL ?>/cobros/reenviar/<?= $cobro['id'] ?>" class="btn btn-outline-primary">
            <i class="bi bi-envelope"></i> Enviar por Email
        </a>
        <form method="POST" action="<?= BASE_URL ?>/cobros/anular/<?= $cobro['id'] ?>" class="d-inline"
              onsubmit="return confirm('¿Está seguro de anular este cobro? Se revertirá el movimiento en ctacte y caja.')">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-x-circle"></i> Anular Cobro
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($cobroExito): ?>
<!-- Modal de éxito después del cobro -->
<div class="modal fade" id="modalExitoCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h4 class="text-success">Cobro Registrado</h4>
                <p class="mb-1">Cobro #<?= $cobroExito['id'] ?> registrado exitosamente.</p>
                <p class="mb-0"><strong>$ <?= number_format((float)$cobroExito['monto'], 2, ',', '.') ?></strong> — <?= htmlspecialchars($cobroExito['cliente']) ?></p>

                <hr>

                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php if (!empty($cobro['pdf_path']) && file_exists($cobro['pdf_path'])): ?>
                    <a href="<?= BASE_URL ?>/cobros/pdf/<?= $cobroExito['id'] ?>" target="_blank" class="btn btn-outline-danger">
                        <i class="bi bi-file-pdf"></i> Ver PDF
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/cobros/reenviar/<?= $cobroExito['id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-envelope"></i> Enviar por Email
                    </a>
                    <a href="<?= BASE_URL ?>/cobros/ventas-no-cobradas" class="btn btn-warning">
                        <i class="bi bi-arrow-left"></i> Seguir Cobrando
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalExitoCobro'));
    modal.show();
});
</script>
<?php endif; ?>
