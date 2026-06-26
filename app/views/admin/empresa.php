<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-building"></i> <?= htmlspecialchars($tenant['nombre']) ?></h3>
    <div>
        <a href="<?= BASE_URL ?>/admin/empresa-users" class="btn btn-info">
            <i class="bi bi-people"></i> Usuarios
        </a>
        <a href="<?= BASE_URL ?>/home" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Datos de la Empresa</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th style="width: 180px;">ID:</th>
                        <td><?= $tenant['id'] ?></td>
                    </tr>
                    <tr>
                        <th>Nombre:</th>
                        <td><?= htmlspecialchars($tenant['nombre']) ?></td>
                    </tr>
                    <tr>
                        <th>Base de Datos:</th>
                        <td><code><?= htmlspecialchars($tenant['dbname']) ?></code></td>
                    </tr>
                    <tr>
                        <th>Host:</th>
                        <td><?= htmlspecialchars($tenant['host']) ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php if ($tenant['activo']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Creado:</th>
                        <td><?= $tenant['created_at'] ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Usuarios (<?= count($tenantUsers) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tenantUsers)): ?>
                    <p class="text-muted">No hay usuarios en esta empresa.</p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenantUsers as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php if (strtoupper($u['rol']) === 'ADMIN'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif (strtoupper($u['rol']) === 'USUARIO'): ?>
                                    <span class="badge bg-primary">Operario</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($u['rol']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/admin/empresa-create-user" class="btn btn-sm btn-outline-success mt-2">
                    <i class="bi bi-person-plus"></i> Nuevo Usuario
                </a>
            </div>
        </div>
    </div>
</div>
