<div class="d-flex justify-content-between mb-3">
    <h3>Empresas (Tenants)</h3>
    <a href="<?= BASE_URL ?>/admin/create" class="btn btn-primary">
        Nueva Empresa
    </a>
</div>

<div class="table-responsive mt-3">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Base de Datos</th>
            <th>Host</th>
            <th>Estado</th>
            <th>Creado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tenants as $t): ?>
        <tr>
            <td><?= $t['id'] ?></td>
            <td><?= htmlspecialchars($t['nombre']) ?></td>
            <td><code><?= htmlspecialchars($t['dbname']) ?></code></td>
            <td><?= htmlspecialchars($t['host']) ?></td>
            <td>
                <?php if ($t['activo']): ?>
                    <span class="badge bg-success">Activo</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Inactivo</span>
                <?php endif; ?>
            </td>
            <td><?= $t['created_at'] ?></td>
            <td>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= BASE_URL ?>/admin/show/<?= $t['id'] ?>">
                   <i class="bi bi-eye"></i> Ver
                </a>
                <a class="btn btn-sm btn-info"
                   href="<?= BASE_URL ?>/admin/users/<?= $t['id'] ?>">
                   <i class="bi bi-people"></i>
                </a>
                <a class="btn btn-sm btn-warning"
                   href="<?= BASE_URL ?>/admin/edit/<?= $t['id'] ?>">
                   <i class="bi bi-pencil"></i>
                </a>
                <a class="btn btn-sm btn-danger"
                   href="<?= BASE_URL ?>/admin/delete/<?= $t['id'] ?>"
                   onclick="return confirm('¿Eliminar este tenant? Se eliminarán también las asociaciones de usuarios.')">
                   <i class="bi bi-trash"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tenants)): ?>
        <tr>
            <td colspan="7" class="text-center text-muted">
                No hay empresas (tenants) registradas.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
