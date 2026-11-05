<?php
$cheque = $cheque ?? [];
$tipos = $tipos ?? [];
$cajas = $cajas ?? [];
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>/cheques/show/<?= (int)$cheque['id'] ?>" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Volver al detalle del cheque
    </a>
</div>

<h3><i class="bi bi-pencil-square"></i> Editar cheque</h3>
<p class="text-muted">
    <?= htmlspecialchars($cheque['nombre_cliente'] ?? 'Cliente') ?> —
    Cobro #<?= (int)($cheque['pago_id'] ?? 0) ?>
</p>

<form method="POST" action="<?= BASE_URL ?>/cheques/update/<?= (int)$cheque['id'] ?>" class="mt-3">
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-wallet2"></i> Datos del cheque</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Tipo</label>
                    <select name="cheque_tipo" class="form-select">
                        <?php foreach ($tipos as $clave => $etiqueta): ?>
                            <option value="<?= $clave ?>" <?= ($cheque['tipo'] ?? 'FISICO') === $clave ? 'selected' : '' ?>>
                                <?= htmlspecialchars($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Número *</label>
                    <input type="text" name="cheque_numero" class="form-control" required maxlength="50"
                           value="<?= htmlspecialchars($cheque['numero'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Banco *</label>
                    <input type="text" name="cheque_banco" class="form-control" required maxlength="100"
                           value="<?= htmlspecialchars($cheque['banco'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Sucursal</label>
                    <input type="text" name="cheque_sucursal" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($cheque['sucursal'] ?? '') ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Titular</label>
                    <input type="text" name="cheque_titular" class="form-control" maxlength="150"
                           value="<?= htmlspecialchars($cheque['titular'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">CUIT</label>
                    <input type="text" name="cheque_cuit" class="form-control" maxlength="20"
                           value="<?= htmlspecialchars($cheque['cuit'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Monto *</label>
                    <input type="number" step="0.01" min="0.01" name="cheque_monto" class="form-control" required
                           value="<?= htmlspecialchars(number_format((float)($cheque['monto'] ?? 0), 2, '.', '')) ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Fecha de emisión</label>
                    <input type="date" name="cheque_fecha_emision" class="form-control"
                           value="<?= htmlspecialchars($cheque['fecha_emision'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Fecha de vencimiento *</label>
                    <input type="date" name="cheque_fecha_vencimiento" class="form-control" required
                           value="<?= htmlspecialchars($cheque['fecha_vencimiento'] ?? '') ?>">
                    <div class="form-text">Si la cambiás, se vuelve a avisar por email cuando corresponda.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Caja / Banco destino</label>
                    <select name="caja_banco_id" class="form-select">
                        <option value="">Sin asignar</option>
                        <?php foreach ($cajas as $cb): ?>
                            <option value="<?= (int)$cb['id'] ?>" <?= (int)($cheque['caja_banco_id'] ?? 0) === (int)$cb['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cb['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Observaciones</label>
                    <textarea name="cheque_observaciones" class="form-control" rows="2"><?= htmlspecialchars($cheque['observaciones'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Guardar cambios
        </button>
        <a href="<?= BASE_URL ?>/cheques/show/<?= (int)$cheque['id'] ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
