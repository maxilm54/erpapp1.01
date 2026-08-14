<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-people"></i> Todos los Usuarios</h3>
    <a href="<?= BASE_URL ?>/admin/create-user" class="btn btn-success">
        <i class="bi bi-person-plus"></i> Nuevo Usuario
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted">No hay usuarios registrados.</p>
        <?php else: ?>
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Empresas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if (strtoupper($u['rol']) === 'SUPERADMIN'): ?>
                            <span class="badge bg-danger">SuperAdmin</span>
                        <?php elseif (strtoupper($u['rol']) === 'ADMIN'): ?>
                            <span class="badge bg-warning text-dark">Admin</span>
                        <?php elseif (strtoupper($u['rol']) === 'USUARIO'): ?>
                            <span class="badge bg-primary">Operario</span>
                        <?php elseif (strtoupper($u['rol']) === 'GERENTE_FINANCIERO'): ?>
                            <span class="badge bg-info">Gerente</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($u['rol']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $userModel = new User();
                        $tenants = $userModel->getTenantsForUser($u['id']);
                        if (empty($tenants)):
                        ?>
                            <span class="text-muted">Sin asignar</span>
                        <?php else: ?>
                            <?php foreach ($tenants as $t): ?>
                                <span class="badge bg-outline-primary"><?= htmlspecialchars($t['nombre']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/admin/edit-user/<?= $u['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/delete-user/<?= $u['id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Eliminar este usuario?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
