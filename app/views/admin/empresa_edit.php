<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-building"></i> Editar Empresa</h3>
    <a href="<?= BASE_URL ?>/admin/empresa" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Datos de la Empresa</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/admin/empresa-update" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Empresa</label>
                        <input type="text" class="form-control" id="nombre"
                               value="<?= htmlspecialchars($tenant['nombre']) ?>" disabled>
                        <small class="text-muted">El nombre no se puede editar desde aquí.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cuit" class="form-label">CUIT</label>
                            <input type="text" class="form-control" id="cuit" name="cuit"
                                   value="<?= htmlspecialchars($empresaConfig['cuit'] ?? '') ?>"
                                   placeholder="20-12345678-9" maxlength="13">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="responsable" class="form-label">Responsable</label>
                            <input type="text" class="form-control" id="responsable" name="responsable"
                                   value="<?= htmlspecialchars($empresaConfig['responsable'] ?? '') ?>"
                                   placeholder="Nombre del responsable">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($empresaConfig['email'] ?? '') ?>"
                                   placeholder="empresa@ejemplo.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono"
                                   value="<?= htmlspecialchars($empresaConfig['telefono'] ?? '') ?>"
                                   placeholder="011-1234-5678">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion"
                               value="<?= htmlspecialchars($empresaConfig['direccion'] ?? '') ?>"
                               placeholder="Calle 123, Ciudad, Provincia">
                    </div>

                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo de la Empresa</label>
                        <input type="file" class="form-control" id="logo" name="logo"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <?php if (!empty($empresaConfig['logo'])): ?>
                            <small class="text-muted">Actual: <?= htmlspecialchars($empresaConfig['logo']) ?></small>
                        <?php endif; ?>
                        <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Tamaño recomendado: 200x200px</small>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información</h5>
            </div>
            <div class="card-body">
                <p><strong>Base de Datos:</strong> <code><?= htmlspecialchars($tenant['dbname']) ?></code></p>
                <p><strong>Estado:</strong>
                    <?php if ($tenant['activo']): ?>
                        <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                    <?php endif; ?>
                </p>
                <p><strong>Creado:</strong> <?= $tenant['created_at'] ?></p>

                <?php if (!empty($empresaConfig['logo'])): ?>
                    <hr>
                    <p><strong>Logo Actual:</strong></p>
                    <img src="<?= empresaUploadUrl('img_config') ?>/<?= htmlspecialchars($empresaConfig['logo']) ?>"
                         alt="Logo" class="img-fluid rounded" style="max-height: 150px;">
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
