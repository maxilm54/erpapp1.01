<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-building"></i> Empresas</h6>
                <h2 class="mb-0"><?= $totalTenants ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-people"></i> Usuarios</h6>
                <h2 class="mb-0"><?= $totalUsers ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-database"></i> Migraciones</h6>
                <a href="<?= BASE_URL ?>/admin/migrations" class="text-dark">
                    <h2 class="mb-0"><i class="bi bi-arrow-right-circle"></i></h2>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Ultimas Empresas</h5>
                <a href="<?= BASE_URL ?>/admin/tenants" class="btn btn-sm btn-light">Ver Todas</a>
            </div>
            <div class="card-body">
                <?php if (empty($tenants)): ?>
                    <p class="text-muted">No hay empresas registradas.</p>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($tenants, 0, 5) as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['nombre']) ?></td>
                            <td>
                                <?php if ($t['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/show/<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Acciones Rapidas</h5>
            </div>
            <div class="card-body">
                <a href="<?= BASE_URL ?>/admin/create" class="btn btn-outline-primary me-2 mb-2">
                    <i class="bi bi-plus-lg"></i> Nueva Empresa
                </a>
                <a href="<?= BASE_URL ?>/admin/all-users" class="btn btn-outline-success me-2 mb-2">
                    <i class="bi bi-people"></i> Gestionar Usuarios
                </a>
                <a href="<?= BASE_URL ?>/admin/migrations" class="btn btn-outline-warning me-2 mb-2">
                    <i class="bi bi-database"></i> Migraciones
                </a>
            </div>
        </div>
    </div>
</div>
