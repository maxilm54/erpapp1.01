<div class="d-flex justify-content-between mb-3">
    <h3>Gestión de Usuarios</h3>
    <a href="<?= BASE_URL ?>/users/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Usuario
    </a>
</div>

<div class="table-responsive mt-3">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Espacio(s)</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
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
            <td>
                <?php if (!empty($u['tenants'])): ?>
                    <?php foreach ($u['tenants'] as $t): ?>
                        <span class="badge bg-success"><?= htmlspecialchars($t['nombre']) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted">Sin asignar</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($u['activo']): ?>
                    <span class="badge bg-success">Activo</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Inactivo</span>
                <?php endif; ?>
            </td>
            <td>
                <a class="btn btn-sm btn-warning"
                   href="<?= BASE_URL ?>/users/edit/<?= $u['id'] ?>">
                   <i class="bi bi-pencil"></i>
                </a>
                <?php if ($u['activo']): ?>
                    <a class="btn btn-sm btn-outline-danger"
                       href="<?= BASE_URL ?>/users/delete/<?= $u['id'] ?>"
                       onclick="return confirm('¿Desactivar este usuario?')">
                       <i class="bi bi-person-dash"></i>
                    </a>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-success"
                       href="<?= BASE_URL ?>/users/activate/<?= $u['id'] ?>">
                       <i class="bi bi-person-check"></i>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr>
            <td colspan="6" class="text-center text-muted">No hay usuarios registrados.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
