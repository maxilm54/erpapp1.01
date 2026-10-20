<?php
$isEdit = !empty($movimiento);
$accion = $isEdit
    ? BASE_URL . '/cuentacorrienteempresa/edit/' . (int)$movimiento['id']
    : BASE_URL . '/cuentacorrienteempresa/create';
?>

<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-journal-plus"></i> <?= $isEdit ? 'Editar Movimiento' : 'Nuevo Movimiento' ?></h3>
    <a href="<?= BASE_URL ?>/cuentacorrienteempresa" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card p-4 shadow col-md-8 mx-auto">
    <form method="POST" action="<?= $accion ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="tipo" class="form-label">Tipo *</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <option value="">Seleccione...</option>
                    <option value="INGRESO" <?= ($movimiento['tipo'] ?? '') === 'INGRESO' ? 'selected' : '' ?>>Ingreso (entra dinero)</option>
                    <option value="GASTO" <?= ($movimiento['tipo'] ?? '') === 'GASTO' ? 'selected' : '' ?>>Gasto (sale dinero)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="monto" class="form-label">Monto *</label>
                <input type="number" step="0.01" min="0.01" class="form-control" id="monto" name="monto" required
                       value="<?= htmlspecialchars((string)($movimiento['monto'] ?? '')) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción *</label>
            <input type="text" class="form-control" id="descripcion" name="descripcion" required maxlength="255"
                   placeholder="Ej: Compra deMP a proveedor X"
                   value="<?= htmlspecialchars($movimiento['descripcion'] ?? '') ?>">
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="fecha" class="form-label">Fecha *</label>
                <input type="date" class="form-control" id="fecha" name="fecha" required
                       value="<?= htmlspecialchars(substr((string)($movimiento['fecha'] ?? date('Y-m-d')), 0, 10)) ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label for="categoria_id" class="form-label">Categoría</label>
                <select name="categoria_id" id="categoria_id" class="form-select">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (string)($movimiento['categoria_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="proveedor_id" class="form-label">Proveedor</label>
                <select name="proveedor_id" id="proveedor_id" class="form-select">
                    <option value="">Sin proveedor</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (string)($movimiento['proveedor_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['razon_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Con proveedor, el saldo se computa como deuda con ese proveedor.</small>
            </div>
        </div>

        <?php if (!$isEdit): ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="referencia_tipo" class="form-label">Tipo de referencia</label>
                <input type="text" class="form-control" id="referencia_tipo" name="referencia_tipo" maxlength="50"
                       placeholder="Ej: ORDEN_COMPRA">
            </div>
            <div class="col-md-6 mb-3">
                <label for="referencia_id" class="form-label">N° de referencia</label>
                <input type="number" class="form-control" id="referencia_id" name="referencia_id">
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end">
            <a href="<?= BASE_URL ?>/cuentacorrienteempresa" class="btn btn-secondary me-2">Cancelar</a>
            <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
        </div>
    </form>
</div>
