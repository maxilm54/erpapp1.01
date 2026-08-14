<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-bank"></i> Nuevo Credito Bancario</h3>
    <a href="<?= BASE_URL ?>/creditos" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card col-md-8 mx-auto">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

            <div class="mb-3">
                <label class="form-label">Entidad Financiera *</label>
                <input type="text" class="form-control" name="entidad" required
                       placeholder="Ej: Banco Galicia, Banco Nacion...">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Monto Original *</label>
                    <input type="number" class="form-control" name="monto_original" required
                           step="0.01" min="0.01" placeholder="1000000.00">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Moneda</label>
                    <select class="form-select" name="moneda">
                        <option value="ARS">Pesos (ARS)</option>
                        <option value="USD">Dolares (USD)</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tasa Interes Anual (%)</label>
                    <input type="number" class="form-control" name="tasa_interes"
                           step="0.01" min="0" value="0" placeholder="0 para sin interes">
                    <small class="text-muted">0 = credito sin interes</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Cantidad de Cuotas *</label>
                    <input type="number" class="form-control" name="cantidad_cuotas" required
                           min="1" value="12">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <option value="FIJO">Tasa Fija</option>
                        <option value="VARIABLE">Tasa Variable</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha Desembolso *</label>
                    <input type="date" class="form-control" name="fecha_desembolso"
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta Destino (Banco/Caja) *</label>
                    <select class="form-select" name="caja_banco_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($cajas as $caja): ?>
                        <option value="<?= $caja['id'] ?>">
                            <?= htmlspecialchars($caja['nombre']) ?>
                            (<?= number_format($caja['saldo_actual'], 2, ',', '.') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Cuenta donde se recibira el desembolso</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" rows="3"
                          placeholder="Detalles adicionales del credito..."></textarea>
            </div>

            <hr>
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/creditos" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> Registrar Credito
                </button>
            </div>
        </form>
    </div>
</div>
