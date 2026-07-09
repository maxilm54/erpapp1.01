<?php
$badgeClasses = [
    'BORRADOR' => 'bg-secondary',
    'APROBADO' => 'bg-primary',
    'PAGADO'   => 'bg-success',
    'ANULADO'  => 'bg-danger',
];
$estadoClass = $badgeClasses[$gasto['estado']] ?? 'bg-secondary';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/gastos" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver a Gastos
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-wallet2"></i> Gasto #<?= $gasto['id'] ?></h3>
    <span class="badge <?= $estadoClass ?> fs-6"><?= $gasto['estado'] ?></span>
</div>

<div class="row">
    <!-- Datos principales -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Datos del Gasto</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Fecha:</strong><br>
                        <?= date('d/m/Y', strtotime($gasto['fecha'])) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Categoría:</strong><br>
                        <span class="badge bg-info text-dark"><?= $gasto['categoria'] ?></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Medio de Pago:</strong><br>
                        <?= str_replace('_', ' ', $gasto['medio_pago']) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Descripción:</strong><br>
                        <?= htmlspecialchars($gasto['descripcion']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>N° Comprobante:</strong><br>
                        <?= htmlspecialchars($gasto['comprobante'] ?? '-') ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Monto Total:</strong><br>
                        <span class="fs-4 fw-bold text-success">$ <?= number_format($gasto['monto_total'], 2, ',', '.') ?></span>
                    </div>
                </div>

                <?php if ($gasto['orden_compra_id']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Orden de Compra:</strong><br>
                        <a href="<?= BASE_URL ?>/ordenescompra/show/<?= $gasto['orden_compra_id'] ?>">
                            OC #<?= $gasto['oc_numero'] ?>
                        </a>
                        <span class="badge bg-secondary ms-1"><?= $gasto['oc_estado'] ?></span>
                    </div>
                    <?php if (!empty($gasto['proveedor_nombre'])): ?>
                    <div class="col-md-6">
                        <strong>Proveedor:</strong><br>
                        <?= htmlspecialchars($gasto['proveedor_nombre']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($gasto['observaciones'])): ?>
                <hr>
                <div>
                    <strong>Observaciones:</strong><br>
                    <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($gasto['observaciones'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-md-4">
        <!-- Auditoría -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Auditoría</h6>
            </div>
            <div class="card-body">
                <small>
                    <strong>Registrado por:</strong><br>
                    <?= htmlspecialchars($gasto['usuario_nombre']) ?>
                    <br><br>
                    <strong>Creado:</strong><br>
                    <?= $gasto['created_at'] ?>
                    <br><br>
                    <strong>Última actualización:</strong><br>
                    <?= $gasto['updated_at'] ?? '-' ?>
                </small>
            </div>
        </div>

        <!-- Acciones según estado -->
        <?php if ($gasto['estado'] !== 'ANULADO'): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Acciones</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <?php if ($gasto['estado'] === 'BORRADOR'): ?>
                    <a href="<?= BASE_URL ?>/gastos/edit/<?= $gasto['id'] ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Editar
                    </a>
                    <a href="<?= BASE_URL ?>/gastos/aprobar/<?= $gasto['id'] ?>" class="btn btn-primary"
                       onclick="return confirm('¿Aprobar este gasto?')">
                        <i class="bi bi-check-circle"></i> Aprobar
                    </a>
                <?php endif; ?>

                <?php if ($gasto['estado'] === 'APROBADO'): ?>
                    <a href="<?= BASE_URL ?>/gastos/pagar/<?= $gasto['id'] ?>" class="btn btn-success"
                       onclick="return confirm('¿Marcar como pagado?')">
                        <i class="bi bi-cash"></i> Marcar como Pagado
                    </a>
                <?php endif; ?>

                <?php if (in_array($gasto['estado'], ['BORRADOR', 'APROBADO'])): ?>
                    <a href="<?= BASE_URL ?>/gastos/anular/<?= $gasto['id'] ?>" class="btn btn-outline-danger"
                       onclick="return confirm('¿Anular este gasto? Esta acción no se puede deshacer.')">
                        <i class="bi bi-x-circle"></i> Anular
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
