<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-gear"></i> <?= htmlspecialchars($title) ?></h3>
</div>

<!-- Nomenclador por cliente -->
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-tags"></i> Nomenclador por cliente</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/configuraciones/guardar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <input type="hidden" name="guardar" value="usar_nomenclador">

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="usar_nomenclador"
                       name="usar_nomenclador" value="1" <?= $nomenclador ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="usar_nomenclador">
                    Usar nomenclador por cliente
                </label>
            </div>
            <p class="text-muted mb-0 small">
                Con esta opcion activa, cada cliente puede tener un <strong>nomenclador</strong> (sufijo de codigo)
                definido en su ficha (ABM Clientes). Los productos creados para ese cliente se guardan con el
                SKU <code>BASE-nomenclador</code> (ej: <code>ABC-001-PIST</code>), y al agregar un producto
                existente a un presupuesto o nota de pedido se usa la variante con el sufijo del cliente,
                para identificar sus etiquetas/precios sin mezclarlos con otros clientes.
            </p>

            <div class="mt-3">
                <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
                <a href="<?= BASE_URL ?>/home" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>

<!-- Adjuntos en documentos -->
<div class="card mb-3">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="bi bi-paperclip"></i> Adjuntos en documentos de venta y producción</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/configuraciones/guardar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <input type="hidden" name="guardar" value="usar_adjuntos">

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="usar_adjuntos"
                       name="usar_adjuntos" value="1" <?= $usarAdjuntos ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="usar_adjuntos">
                    Permitir adjuntar archivos en Presupuestos, Notas de Pedido, Remitos y Producción
                </label>
            </div>
            <p class="text-muted mb-0 small">
                Con esta opcion activa, la vista y el formulario de cada documento muestran un panel de
                <strong>Adjuntos</strong> para subir archivos (PDF, imágenes, planillas, etc, hasta 10 MB c/u).
                Al aprobar un presupuesto que tiene adjuntos, se genera automáticamente la Nota de Pedido y
                estos archivos viajan con ella (y se pueden agregar más desde la NP).
            </p>

            <div class="mt-3">
                <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
                <a href="<?= BASE_URL ?>/home" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>

<!-- Adjuntos en Ordenes de Compra -->
<div class="card mb-3">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="bi bi-paperclip"></i> Adjuntos (comprobantes) en Órdenes de Compra</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/configuraciones/guardar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <input type="hidden" name="guardar" value="usar_adjuntos_compra">

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="usar_adjuntos_compra"
                       name="usar_adjuntos_compra" value="1" <?= $usarAdjuntosCompra ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="usar_adjuntos_compra">
                    Permitir adjuntar comprobantes en Órdenes de Compra
                </label>
            </div>
            <p class="text-muted mb-0 small">
                Activa el panel de <strong>Adjuntos</strong> en las órdenes de compra (al crear y en la vista del
                documento) para adjuntar comprobantes, presupuestos del proveedor, etc. <strong>No es
                obligatorio</strong>: si no se adjunta nada, el flujo sigue exactamente igual.
            </p>

            <div class="mt-3">
                <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
                <a href="<?= BASE_URL ?>/home" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>
