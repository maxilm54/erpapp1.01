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

<!-- Campos personalizados por modulo -->
<div class="card mb-3">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-card-list"></i> Campos personalizados por módulo</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Definí campos adicionales (ej: <em>Número de compra</em>, <em>Número de contrato</em>, <em>Pedido por</em>)
            y aparecerán siempre en el formulario de cada módulo. Los valores cargados en un
            <strong>Presupuesto</strong> viajan automáticamente a la <strong>Nota de Pedido</strong> generada desde
            ese presupuesto, aunque la NP no tenga el campo configurado.
        </p>

        <div class="row g-3">
            <?php foreach (Campopersonalizado::MODULOS as $cpModuloClave): ?>
                <?php $cpListaModulo = $camposPorModulo[$cpModuloClave] ?? []; ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header py-2 bg-secondary-subtle">
                            <strong><i class="bi bi-folder2-open"></i> <?= htmlspecialchars(Campopersonalizado::MODULOS_LABELS[$cpModuloClave]) ?></strong>
                            <span class="badge bg-secondary ms-1"><?= count($cpListaModulo) ?></span>
                        </div>
                        <div class="card-body py-2">
                            <?php if (empty($cpListaModulo)): ?>
                                <p class="text-muted small mb-2">Sin campos configurados.</p>
                            <?php else: ?>
                                <table class="table table-sm mb-2 align-middle">
                                    <tbody>
                                    <?php foreach ($cpListaModulo as $cpCampo): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($cpCampo['nombre']) ?>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($cpCampo['tipo']) ?></span>
                                            </td>
                                            <td class="text-end" style="width:90px">
                                                <!-- Editar inline (sin JS: details/summary) -->
                                                <details class="d-inline-block">
                                                    <summary class="btn btn-sm btn-outline-secondary py-0 px-1" title="Editar"><i class="bi bi-pencil"></i></summary>
                                                    <form method="POST" action="<?= BASE_URL ?>/configuraciones/campo-editar/<?= (int)$cpCampo['id'] ?>"
                                                          class="border rounded p-2 mt-1 bg-light" style="min-width:230px; text-align:left;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                                        <input type="text" name="cp_nombre" class="form-control form-control-sm mb-1"
                                                               value="<?= htmlspecialchars($cpCampo['nombre']) ?>" maxlength="150" required>
                                                        <select name="cp_tipo" class="form-select form-select-sm mb-1">
                                                            <?php foreach (Campopersonalizado::TIPOS as $cpTipoOpt): ?>
                                                                <option value="<?= $cpTipoOpt ?>" <?= $cpCampo['tipo'] === $cpTipoOpt ? 'selected' : '' ?>><?= ucfirst($cpTipoOpt) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Guardar</button>
                                                    </form>
                                                </details>
                                                <form method="POST" action="<?= BASE_URL ?>/configuraciones/campo-eliminar/<?= (int)$cpCampo['id'] ?>"
                                                      style="display:inline"
                                                      onsubmit="return confirm('¿Eliminar el campo \'<?= htmlspecialchars($cpCampo['nombre']) ?>\' de los formularios? Los valores ya guardados siguen visibles en los documentos.')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <form method="POST" action="<?= BASE_URL ?>/configuraciones/campo-agregar" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <input type="hidden" name="cp_modulo" value="<?= htmlspecialchars($cpModuloClave) ?>">
                                <input type="text" name="cp_nombre" class="form-control form-control-sm" placeholder="Nombre del campo (ej: Número de compra)" maxlength="150" required>
                                <select name="cp_tipo" class="form-select form-select-sm" style="width:110px">
                                    <?php foreach (Campopersonalizado::TIPOS as $cpTipoOpt): ?>
                                        <option value="<?= $cpTipoOpt ?>"><?= ucfirst($cpTipoOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-plus-lg"></i> Agregar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
