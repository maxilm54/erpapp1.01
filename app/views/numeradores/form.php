<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-list-ol"></i> <?= $numerador ? 'Editar Numerador' : 'Nuevo Numerador' ?></h3>
    <a href="<?= BASE_URL ?>/numeradores" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card col-md-8 mx-auto">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="mb-3">
                <label class="form-label">Tipo *</label>
                <input type="text" class="form-control" name="tipo" required
                       value="<?= htmlspecialchars($numerador['tipo'] ?? '') ?>"
                       placeholder="Ej: REMITO, FACTURA, NOTA_PEDIDO"
                       pattern="[A-Z0-9_]+" maxlength="30">
                <small class="text-muted">Solo letras mayusculas, numeros y guiones bajos. Ej: REMITO, FACTURA_V2</small>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ultimo Numero *</label>
                    <input type="number" class="form-control" name="ultimo_numero" required
                           value="<?= (int)($numerador['ultimo_numero'] ?? 0) ?>"
                           min="0">
                    <small class="text-muted">El proximo numero generado sera este + incremento</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Incremento *</label>
                    <input type="number" class="form-control" name="incremento" required
                           value="<?= (int)($numerador['incremento'] ?? 1) ?>"
                           min="1">
                    <small class="text-muted">Cuanto se suma al numero actual</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Prefijo</label>
                    <input type="text" class="form-control" name="prefijo"
                           value="<?= htmlspecialchars($numerador['prefijo'] ?? '') ?>"
                           placeholder="Ej: REM-"
                           maxlength="10">
                    <small class="text-muted">Opcional. Ej: REM-, OC-, NP-</small>
                </div>
            </div>

            <?php if ($numerador): ?>
            <div class="alert alert-info">
                <strong>Proximo numero:</strong>
                <?php
                $proximo = (int)$numerador['ultimo_numero'] + (int)$numerador['incremento'];
                $prefijo = $numerador['prefijo'] ?? '';
                echo htmlspecialchars($prefijo) . str_pad($proximo, max(6, strlen((string)$proximo)), '0', STR_PAD_LEFT);
                ?>
            </div>
            <?php endif; ?>

            <hr>
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/numeradores" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> <?= $numerador ? 'Actualizar' : 'Crear Numerador' ?>
                </button>
            </div>
        </form>
    </div>
</div>
