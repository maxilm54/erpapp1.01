<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-people"></i> Usuarios de: <?= htmlspecialchars($tenant['nombre']) ?></h3>
    <div>
        <a href="<?= BASE_URL ?>/admin/empresa-create-user" class="btn btn-success">
            <i class="bi bi-person-plus"></i> Nuevo Usuario
        </a>
        <a href="<?= BASE_URL ?>/admin/empresa" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($tenantUsers)): ?>
            <p class="text-muted">No hay usuarios en esta empresa.</p>
        <?php else: ?>
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenantUsers as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
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
                    <td>
                        <?php if ($u['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/admin/empresa-edit-user/<?= $u['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                        <a href="<?= BASE_URL ?>/admin/empresa-remove-user/<?= $u['id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('¿Remover este usuario de la empresa?')">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
