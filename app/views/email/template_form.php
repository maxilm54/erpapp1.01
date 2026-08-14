<h4>
    <i class="bi bi-pencil-square"></i>
    <?= $template ? 'Editar Template' : 'Nuevo Template' ?>
</h4>
<hr>

<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/email/save-template">
            <?php if ($template): ?>
                <input type="hidden" name="id" value="<?= $template['id'] ?>">
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de Email *</label>
                    <select name="tipo" class="form-select" required>
                        <?php foreach ($tipos as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($template['tipo'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Asunto (Subject) *</label>
                    <input type="text" name="asunto" class="form-control" 
                           value="<?= htmlspecialchars($template['asunto'] ?? '') ?>" required
                           placeholder="Ej: Remito de Salida N° {{numero}}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Cuerpo HTML del Email *</label>
                <textarea name="cuerpo_html" class="form-control" rows="20" required
                          style="font-family:monospace; font-size:13px;"
                          placeholder="HTML del email..."><?= htmlspecialchars($template['cuerpo_html'] ?? '') ?></textarea>
            </div>

            <div class="card mb-3 bg-light">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle"></i> Variables disponibles</h6>
                    <p class="small text-muted mb-1">Use dobles llaves para reemplazar variables: <code>{{variable}}</code></p>
                    <div class="row small">
                        <div class="col-md-4">
                            <strong>Comunes:</strong>
                            <ul class="text-muted">
                                <li><code>{{logo}}</code> - Logo de la empresa</li>
                                <li><code>{{empresa_nombre}}</code></li>
                                <li><code>{{empresa_email}}</code></li>
                                <li><code>{{empresa_cuit}}</code></li>
                                <li><code>{{empresa_direccion}}</code></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>Remito:</strong>
                            <ul class="text-muted">
                                <li><code>{{cliente_nombre}}</code></li>
                                <li><code>{{numero}}</code></li>
                                <li><code>{{fecha}}</code></li>
                                <li><code>{{total}}</code></li>
                                <li><code>{{>detalle_tabla}}</code> (HTML directo)</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>Pago:</strong>
                            <ul class="text-muted">
                                <li><code>{{cliente_nombre}}</code></li>
                                <li><code>{{pago_id}}</code></li>
                                <li><code>{{monto}}</code></li>
                                <li><code>{{medio_pago}}</code></li>
                                <li><code>{{#observaciones}}...{{/observaciones}}</code> (condicional)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Guardar Template
                </button>
                <a href="<?= BASE_URL ?>/email/templates" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
